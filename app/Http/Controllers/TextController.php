<?php

namespace App\Http\Controllers;

use App\Models\Text;
use App\Models\Contact;
use App\Models\ContactList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\TextStatus;
use App\Models\Queue;
use League\Csv\Writer;

class TextController extends Controller
{
    /**
     * Export SMS data to CSV
     */
    public function exportCsv(Request $request)
    {
        $search = $request->input('search', '');

        $query = Text::with([
            'status:id,text_status_name,color_code',
            'creator:id,name,email',
            'updater:id,name,email',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('text_title', 'like', "%{$search}%")
                    ->orWhereHas('status', function ($sq) use ($search) {
                        $sq->where('text_status_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('creator', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $texts = $query->orderBy('created_at', 'desc')->get();

        // Create CSV
        $csv = Writer::createFromString('');

        // Add CSV headers
        $csv->insertOne([
            'Title',
            'Message Content',
            'Status',
            'Contact Type',
            'Contacts Count',
            'Created By',
            'Created At',
            'Updated By',
            'Updated At',
            'Scheduled',
            'Schedule Date',
            'Sender ID',
            'Priority',
            'Send Speed'
        ]);

        // Add data rows
        foreach ($texts as $text) {
            // Determine contact type
            $contactType = 'Unknown';
            if ($text->contact_type === 'manual') {
                $contactType = 'Manual Entry';
            } elseif ($text->contact_type === 'csv') {
                $contactType = 'CSV Upload';
            } elseif ($text->contact_type === 'list') {
                $contactType = 'Contact List';
            }

            $csv->insertOne([
                $text->text_title,
                $text->message,
                $text->status ? $text->status->text_status_name : 'Unknown',
                $contactType,
                $text->contacts_count ?? 0,
                $text->creator ? $text->creator->name : 'Unknown',
                $text->created_at ? $text->created_at->format('Y-m-d H:i:s') : '',
                $text->updater ? $text->updater->name : 'N/A',
                $text->updated_at ? $text->updated_at->format('Y-m-d H:i:s') : '',
                $text->scheduled ? 'Yes' : 'No',
                $text->scheduled && $text->schedule_date ? $text->schedule_date : '',
                $text->sender_id ?? 'Default',
                $text->priority ?? 'Normal',
                $text->send_speed ?? 'Normal'
            ]);
        }

        // Set response headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sms-records-' . date('Y-m-d') . '.csv"',
        ];

        // Return the CSV as a download
        return response($csv->toString(), 200, $headers);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $query = Text::with([
            'status:id,text_status_name,color_code', // Only load needed fields
            'creator:id,name,email',                // Only load needed fields
            'updater:id,name,email',                // Only load needed fields
            // Additional fields for contact viewing
            // 'contactList:id,name'                   // For contact list information
        ])
            ->select('texts.*') // Ensure we select all text fields including contact_type, recipient_contacts, contact_list_id and csv_file_path
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('text_title', 'like', "%{$search}%")
                    ->orWhereHas('status', function ($sq) use ($search) {
                        $sq->where('text_status_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('creator', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $texts = $query->paginate(10)->withQueryString();
        // dd($texts->toArray());


        return Inertia::render('SMS/Index', [
            'texts' => $texts,
            'filters' => ['search' => $search]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get contact groups for dropdown
        $contacts = Contact::where('is_active', 1)
            ->withCount(['contactLists' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->get();

        return Inertia::render('SMS/Create', [
            'contacts' => $contacts
        ]);
    }

    /**
     * Preview SMS details before sending
     */
    public function preview(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'text_title' => 'required|string|max:255',
            'contact_type' => 'required|string|in:manual,csv,list',
            'message' => 'required|string|max:300',
            'recepient_contacts' => 'required_if:contact_type,manual|nullable|string',
            'contact_list' => 'required_if:contact_type,list|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $contacts = [];
        $contactsCount = 0;
        $previewData = [
            'text_title' => $request->text_title,
            'message' => $request->message,
            'contacts_count' => 0,
            'scheduled' => $request->scheduled ? 1 : 0,
            'schedule_date' => $request->schedule_date,
        ];

        // Process recipient contacts based on contact type
        switch ($request->contact_type) {
            case 'manual':
                $contacts = array_filter(explode(',', $request->recepient_contacts));
                $contactsCount = count($contacts);
                $previewData['contacts'] = $contacts;
                break;

            case 'csv':
                // Process CSV file
                if ($request->csv_file_path) {
                    // Use existing uploaded file
                    $csvFilePath = $request->csv_file_path;
                    $filename = basename($csvFilePath);
                    $fullPath = storage_path('app/public/' . $csvFilePath);

                    // Read the CSV file
                    $csvData = array_map('str_getcsv', file($fullPath));

                    // Get header row
                    $headerRow = array_shift($csvData);
                    $columnNames = array_map('trim', $headerRow);

                    // Create lowercase version for case-insensitive matching
                    $headerMap = array_map('strtolower', $columnNames);

                    // Log headers for debugging
                    Log::info('CSV Headers Found: ' . json_encode($columnNames));

                    // Find phone/mobile column for contact counting
                    $phoneColumnIndex = array_search('phone', $headerMap);
                    if ($phoneColumnIndex === false) {
                        $phoneColumnIndex = array_search('mobile', $headerMap);
                    }
                    if ($phoneColumnIndex === false) {
                        $phoneColumnIndex = array_search('telephone', $headerMap);
                    }
                    if ($phoneColumnIndex === false) {
                        $phoneColumnIndex = array_search('contact', $headerMap);
                    }

                    // Extract contacts from first column if no phone column found
                    if ($phoneColumnIndex === false && !empty($csvData)) {
                        $phoneColumnIndex = 0;
                    }

                    // Extract contacts from the phone column
                    if ($phoneColumnIndex !== false) {
                        foreach ($csvData as $row) {
                            if (isset($row[$phoneColumnIndex]) && !empty($row[$phoneColumnIndex])) {
                                $contacts[] = $row[$phoneColumnIndex];
                            }
                        }
                    }

                    $contactsCount = count($contacts);

                    // Add CSV info to preview data
                    $previewData['contacts'] = $contacts;
                    $previewData['csv_file_columns'] = json_encode($columnNames);
                    $previewData['csv_file_name'] = $filename;
                    $previewData['csv_file_path'] = $csvFilePath;

                    // Now handle personalization
                    $personalized_message = $request->message;
                    $original_message = $request->message;

                    // Check if message contains placeholders like {column_name}
                    if (!empty($csvData) && preg_match_all('/\{([^\}]+)\}/', $personalized_message, $matches)) {
                        Log::info('Placeholders found in message: ' . json_encode($matches[1]));

                        // Get the first row of data to use for personalization example
                        $firstRow = $csvData[0];

                        // Create a direct mapping from column name to value
                        $dataMap = [];
                        foreach ($columnNames as $index => $colName) {
                            if (isset($firstRow[$index])) {
                                $dataMap[strtolower($colName)] = $firstRow[$index];
                            }
                        }

                        Log::info('Data map for replacements: ' . json_encode($dataMap));

                        // Replace all placeholders
                        foreach ($matches[1] as $placeholder) {
                            $placeholderKey = strtolower(trim($placeholder));
                            Log::info("Looking for placeholder '{$placeholderKey}' in data map");

                            if (isset($dataMap[$placeholderKey])) {
                                $value = $dataMap[$placeholderKey];
                                Log::info("Found value for {$placeholderKey}: {$value}");

                                // Replace the placeholder
                                $personalized_message = str_replace(
                                    '{' . $placeholder . '}',
                                    $value,
                                    $personalized_message
                                );
                            } else {
                                Log::warning("Placeholder {$placeholder} not found in data");
                            }
                        }

                        // Log the result
                        Log::info('Original message: ' . $original_message);
                        Log::info('Personalized message: ' . $personalized_message);

                        // Add to preview data
                        $previewData['personalizedMessage'] = $personalized_message;
                        $previewData['originalMessage'] = $original_message;
                        $previewData['customizationInfo'] = "Message shown is personalized using the first row of your CSV data. Each recipient will receive their own customized message.";
                    } else {
                        $previewData['personalizedMessage'] = $original_message;
                    }
                }
                break;

            case 'list':
                // Get contacts from selected contact groups
                $contactIds = $request->contact_list;

                if (!empty($contactIds)) {
                    // Log the contact IDs for debugging
                    Log::info('Selected Contact IDs: ' . json_encode($contactIds));

                    // Get the contact groups selected by the user
                    $contactGroups = Contact::whereIn('id', $contactIds)
                        ->where('is_active', 1) // Only get active contact groups
                        ->get();

                    Log::info('Found Contact Groups: ' . $contactGroups->count());

                    $contacts = [];
                    $groupNames = [];

                    // For each contact group, get all the active contacts within it
                    foreach ($contactGroups as $group) {
                        // Add group name to the list
                        $groupNames[] = $group->name;

                        // Get all active contacts in this group
                        $phoneNumbers = $group->contactLists()
                            ->where('is_active', 1)
                            ->pluck('telephone')
                            ->toArray();

                        Log::info('Group: ' . $group->name . ' has ' . count($phoneNumbers) . ' contacts');

                        // Merge with the main list
                        $contacts = array_merge($contacts, $phoneNumbers);
                    }

                    // Remove duplicates and count
                    $contacts = array_unique($contacts);
                    $contactsCount = count($contacts);

                    // Add to preview data
                    $previewData['contacts'] = $contacts;
                    $previewData['contact_list'] = json_encode($contactIds);
                    $previewData['contact_groups'] = $groupNames;
                    $previewData['personalizedMessage'] = $request->message; // Original message
                } else {
                    // No contact groups selected
                    $contacts = [];
                    $contactsCount = 0;
                    $previewData['contacts'] = [];
                }
                break;
        }

        // Count valid and invalid contacts for better preview
        $validContacts = 0;
        $invalidContacts = 0;
        $totalContacts = count($contacts);

        // Validate each phone number using more flexible regex pattern
        // Allow international formats, different lengths, and common delimiters
        $phoneRegex = '/^(?:\+?(?:[0-9]|\(\d+\))[ -]?)?(?:\d{3,4}[ -]?)?\d{3}[ -]?\d{3,4}$/';

        foreach ($contacts as $contact) {
            $contact = trim($contact);
            if (preg_match($phoneRegex, $contact)) {
                $validContacts++;
            } else {
                $invalidContacts++;
            }
        }

        // Add counts to preview data
        $previewData['validContacts'] = $validContacts;
        $previewData['invalidContacts'] = $invalidContacts;
        $previewData['totalContacts'] = $totalContacts;
        $previewData['messageTotalChars'] = strlen($request->message);

        return response()->json([
            'status' => 'success',
            'preview' => $previewData
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation would go here similar to the preview method
        $validator = Validator::make($request->all(), [
            'text_title' => 'required|string|max:255',
            'contact_type' => 'required|string|in:manual,csv,list',
            'message' => 'required|string|max:300',
            'recepient_contacts' => 'required_if:contact_type,manual|nullable|string',
            'contact_list' => 'required_if:contact_type,list|nullable',
            'scheduled' => 'nullable|boolean',
            'schedule_date' => 'required_if:scheduled,1|nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Create new text record
        $text = new Text();
        $text->text_title = $request->text_title;
        $text->message = $request->message;
        $text->contact_type = $request->contact_type;
        $text->scheduled = $request->scheduled ? 1 : 0;
        $text->schedule_date = $request->schedule_date;
        $text->updated_by = Auth::id();
        $text->created_by = Auth::id();
        $text->status_id =  $request->scheduled ? TextStatus::SCHEDULED : TextStatus::PROCESSING; // Pending
        $text->created_at = now();
        $text->updated_at = now();


        // Process contacts based on contact type
        switch ($request->contact_type) {
            case 'manual':
                $contacts = array_filter(explode(',', $request->recepient_contacts));
                $text->contacts_count = count($contacts);
                $text->recepient_contacts = $request->recepient_contacts;
                break;

            case 'csv':
                $text->csv_file_path = $request->csv_file_path;
                $text->csv_file_name = basename($request->csv_file_path);
                $text->csv_file_columns = $request->csv_file_columns;
                $text->csv_phone_column = $request->csv_phone_column;

                // Count contacts from CSV
                if ($text->csv_file_path) {
                    // Use the contacts_count from preview if available
                    if ($request->has('contacts_count') && is_numeric($request->contacts_count)) {
                        $text->contacts_count = $request->contacts_count;
                    } else {
                        // If not available, count them again like in the preview method
                        $csvFilePath = $text->csv_file_path;
                        $fullPath = storage_path('app/public/' . $csvFilePath);
                        
                        if (file_exists($fullPath)) {
                            // Read the CSV file
                            $csvData = array_map('str_getcsv', file($fullPath));
                            
                            // Get header row
                            $headerRow = array_shift($csvData);
                            $columnNames = array_map('trim', $headerRow);
                            
                            // Create lowercase version for case-insensitive matching
                            $headerMap = array_map('strtolower', $columnNames);
                            
                            // Find phone/mobile column for contact counting
                            $phoneColumnIndex = array_search('phone', $headerMap);
                            if ($phoneColumnIndex === false) {
                                $phoneColumnIndex = array_search('mobile', $headerMap);
                            }
                            if ($phoneColumnIndex === false) {
                                $phoneColumnIndex = array_search('telephone', $headerMap);
                            }
                            if ($phoneColumnIndex === false) {
                                $phoneColumnIndex = array_search('contact', $headerMap);
                            }
                            
                            // Extract contacts from first column if no phone column found
                            if ($phoneColumnIndex === false && !empty($csvData)) {
                                $phoneColumnIndex = 0;
                            }
                            
                            // Extract contacts from the phone column
                            $contacts = [];
                            if ($phoneColumnIndex !== false) {
                                foreach ($csvData as $row) {
                                    if (isset($row[$phoneColumnIndex]) && !empty($row[$phoneColumnIndex])) {
                                        $contacts[] = $row[$phoneColumnIndex];
                                    }
                                }
                            }
                            
                            $text->contacts_count = count($contacts);
                            
                            // Log for debugging
                            Log::info('CSV Contacts counted during store: ' . $text->contacts_count);
                        } else {
                            // Log error if file not found
                            Log::error('CSV file not found at: ' . $fullPath);
                            $text->contacts_count = 0;
                        }
                    }
                }
                break;

            case 'list':
                $contactIds = $request->contact_list;
                $text->contact_list = json_encode($contactIds);

                if (!empty($contactIds)) {
                    $contactGroups = Contact::whereIn('id', $contactIds)->get();
                    $contacts = [];

                    foreach ($contactGroups as $group) {
                        $phoneNumbers = $group->contactLists()
                            ->where('is_active', 1)
                            ->pluck('telephone')
                            ->toArray();
                        $contacts = array_merge($contacts, $phoneNumbers);
                    }

                    $contacts = array_unique($contacts);
                    $text->contacts_count = count($contacts);
                }
                break;
        }

        $text->save();

        // Process the text message based on scheduled status
        if (!$text->scheduled) {
            // Queue messages for immediate sending
            // $this->queueMessages($text);
        }

        return redirect()->route('sms.index')->with('success', 'SMS created successfully and ' . ($text->scheduled ? 'scheduled for later.' : 'queued for sending.'));
    }

    /**
     * Queue messages for sending
     */
    private function queueMessages(Text $text)
    {
        // Implementation for queueing messages to be sent
        // This would handle the actual SMS sending logic or queuing for background processing

        // For now, just mark the text as processing
        $text->status_id = 2; // Processing
        $text->save();

        // In a real implementation, you would:
        // 1. Parse the recipient list
        // 2. Create queue records for each recipient
        // 3. Trigger a background job to process these queues
    }

    /**
     * Display the specified resource.
     */
    public function show(Text $text)
    {
        return Inertia::render('SMS/Show', [
            'text' => $text->load('status')
        ]);
    }

    /**
     * Download the CSV file for an SMS
     */
    public function downloadCsv(Request $request, $filename)
    {
        // Security check to prevent directory traversal
        $filename = basename($filename);
        $csvPath = 'csv_uploads/' . $filename;
        
        // Check if file exists
        if (!Storage::disk('public')->exists($csvPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }
        
        // Get full path to the file
        $fullPath = storage_path('app/public/' . $csvPath);
        
        // Return the file as a download
        return response()->download($fullPath, $filename);
    }
    
    /**
     * Display SMS logs
     */
    public function logs()
    {
        $texts = Text::with('status')->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('SMS/Logs', [
            'texts' => $texts
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Text $text)
    {
        $contactLists = ContactList::where('is_active', 1)->get();

        return Inertia::render('SMS/Edit', [
            'text' => $text,
            'contactLists' => $contactLists
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Text $text)
    {
        // Only allow updating texts that haven't been processed yet
        if ($text->status_id > 1) {
            return redirect()->back()->with('error', 'Cannot update SMS that has already been processed.');
        }

        // Validate the request (similar to store)
        $validator = Validator::make($request->all(), [
            'text_title' => 'required|string|max:255',
            'message' => 'required|string|max:300',
            'scheduled' => 'nullable|boolean',
            'schedule_date' => 'required_if:scheduled,1|nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Update text properties
        $text->text_title = $request->text_title;
        $text->message = $request->message;
        $text->scheduled = $request->scheduled ? 1 : 0;
        $text->schedule_date = $request->schedule_date;
        $text->updated_by = Auth::id();
        $text->save();

        return redirect()->route('sms.index')->with('success', 'SMS updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Text $text)
    {
        // Only allow deleting texts that haven't been sent
        if ($text->status_id > 2) {
            return redirect()->back()->with('error', 'Cannot delete SMS that has already been sent.');
        }

        $text->delete();

        return redirect()->route('sms.index')->with('success', 'SMS deleted successfully.');
    }
}
