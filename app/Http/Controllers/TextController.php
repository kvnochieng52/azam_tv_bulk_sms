<?php

namespace App\Http\Controllers;

use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Support\Facades\File;

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
use App\Jobs\SendSmsJob;

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
        ])
            ->select('texts.*') // Ensure we select all text fields
            ->where('texts.status_id', '!=', TextStatus::SCHEDULED) // Exclude scheduled texts
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
            // Dispatch job with 5 second delay for immediate sending
            \App\Jobs\SendSmsJob::dispatch($text)->delay(now()->addSeconds(5));

            // Redirect to SMS index for direct messages
            // return redirect()->route('sms.index')
            //     ->with('success', 'SMS created successfully and queued for sending.');

            return redirect()->route('sms.index');
            // ->with('success', 'SMS created successfully and queued for sending.');
        } else {
            // For scheduled SMS, schedule the job to run at the specified date and time
            $scheduleDate = new \DateTime($text->schedule_date);
            $delay = $scheduleDate->getTimestamp() - now()->timestamp;

            // Only schedule if the date is in the future
            if ($delay > 0) {
                \App\Jobs\SendSmsJob::dispatch($text)->delay(now()->addSeconds($delay));
            } else {
                // If scheduled date is in the past, send immediately with a 5 second delay
                \App\Jobs\SendSmsJob::dispatch($text)->delay(now()->addSeconds(5));
            }

            // Redirect to scheduled SMS page for scheduled messages
            return redirect()->route('sms.scheduled');
            //  ->with('success', 'SMS scheduled successfully for ' . $text->schedule_date . '.');
        }
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
        // Load the status and creator relationships
        return Inertia::render('SMS/Show', [
            'text' => $text->load(['status', 'creator'])
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
     * Display scheduled SMS messages
     */
    public function scheduled(Request $request)
    {
        $search = $request->input('search', '');
        $query = Text::with([
            'status:id,text_status_name,color_code',
            'creator:id,name,email',
            'updater:id,name,email',
        ])
            ->where('status_id', TextStatus::SCHEDULED) // Only get scheduled texts
            ->orderBy('created_at', 'desc'); // Order by schedule date

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

        return Inertia::render('SMS/Scheduled', [
            'texts' => $texts,
            'filters' => ['search' => $search]
        ]);
    }

    /**
     * Get the progress of SMS sending for a specific text or all texts
     */
    public function getProgress(Request $request)
    {
        $textIds = $request->input('text_ids', []);

        // First get the progress counts
        $countQuery = DB::table('texts')
            ->select('texts.id', 'texts.contacts_count')
            ->selectRaw('COUNT(queues.id) as processed_count')
            ->leftJoin('queues', 'texts.id', '=', 'queues.text_id')
            ->groupBy('texts.id', 'texts.contacts_count');

        // If specific text IDs were provided, filter by them
        if (!empty($textIds)) {
            $countQuery->whereIn('texts.id', $textIds);
        }

        $progressCounts = $countQuery->get();

        // Then get the current status information
        $statusQuery = DB::table('texts')
            ->select('texts.id', 'texts.status_id', 'text_statuses.text_status_name', 'text_statuses.color_code')
            ->join('text_statuses', 'texts.status_id', '=', 'text_statuses.id');

        if (!empty($textIds)) {
            $statusQuery->whereIn('texts.id', $textIds);
        }

        $statuses = $statusQuery->get()->keyBy('id');

        // Combine progress and status information
        $progress = $progressCounts->mapWithKeys(function ($item) use ($statuses) {
            $totalCount = max(1, $item->contacts_count); // Avoid division by zero
            $processedCount = $item->processed_count;
            $percentage = min(100, round(($processedCount / $totalCount) * 100));

            // Get status information
            $status = $statuses[$item->id] ?? null;

            return [$item->id => [
                'processed' => $processedCount,
                'total' => $totalCount,
                'percentage' => $percentage,
                'status_id' => $status ? $status->status_id : null,
                'status_name' => $status ? $status->text_status_name : null,
                'color_code' => $status ? $status->color_code : null
            ]];
        });

        return response()->json($progress);
    }

    /**
     * Get detailed SMS statistics from the queues table
     */
    public function getSmsStatistics(Request $request)
    {
        $textId = $request->input('text_id');

        if (!$textId) {
            return response()->json(['error' => 'Text ID is required'], 400);
        }

        // Get basic SMS information
        $text = DB::table('texts')
            ->select('id', 'contacts_count')
            ->where('id', $textId)
            ->first();

        if (!$text) {
            return response()->json(['error' => 'SMS not found'], 404);
        }

        // Count messages by status ID from texts table
        $text = DB::table('texts')
            ->select('id', 'contacts_count', 'status_id')
            ->where('id', $textId)
            ->first();

        // Count processed messages from queues table
        $processedCount = DB::table('queues')
            ->where('text_id', $textId)
            ->count();

        // Define status codes
        $PROCESSING = 2; // In Queue
        $SENT = 3;       // Delivered/Sent
        $FAILED = 4;     // Failed
        $ERROR = 7;      // Error

        // Determine counts based on text status and processed count
        // $total = $text->contacts_count;

        // if ($text->status_id == $SENT) {
        //     // If status is SENT, all messages are delivered
        //     $delivered = $total;
        //     $queued = 0;
        //     $failed = 0;
        // } else if ($text->status_id == $FAILED || $text->status_id == $ERROR) {
        //     // If status is FAILED or ERROR, all are failed
        //     $delivered = 0;
        //     $queued = 0;
        //     $failed = $total;
        // } else {
        //     // For processing or other statuses, use processed count to estimate
        //     $delivered = min($processedCount, $total);
        //     $queued = max(0, $total - $processedCount);
        //     $failed = 0; // We don't know failures without detailed queue status
        // }

        // // If status is specifically FAILED or ERROR, count as failed
        // if ($text->status_id == $FAILED || $text->status_id == $ERROR) {
        //     $failed = $total;
        //     $delivered = 0;
        // }

        // If requested, include individual contact statuses


        $total = $processedCount;
        $contactStatuses = [];
        if ($request->input('include_contacts', false)) {
            $contactStatuses = DB::table('queues')
                ->select('recipient', 'status')
                ->where('text_id', $textId)
                ->get()
                ->keyBy('recipient')
                ->toArray();
        }


        $delivered = DB::table('queues')
            ->where('text_id', $textId)
            ->where('status', $SENT)
            ->count();

        $queued = DB::table('queues')
            ->where('text_id', $textId)
            ->where('status', $PROCESSING)
            ->count();

        $failed = DB::table('queues')
            ->where('text_id', $textId)
            ->where('status', $FAILED)
            ->count();

        return response()->json([
            'text_id' => $textId,
            // 'total' => $text->contacts_count,
            'tota' => $total,
            'delivered' => $delivered,
            'queued' => $queued,
            'failed' => $failed,
            'contacts' => $contactStatuses
        ]);
    }

    /**
     * Export detailed SMS data for a specific text message
     * Format: telephone|message|date/time|status
     */
    public function exportSmsDetail(Text $text)
    {
        // Status code constants for reference
        $PROCESSING = 1;
        $IN_QUEUE = 2;
        $SENT = 3;
        $FAILED = 4;
        $ERROR = 7;

        // Get SMS details
        $smsData = [];

        // Get creator information by joining with users table
        $creator = DB::table('users')
            ->select('name')
            ->where('id', $text->created_by)
            ->first();

        // Add header information
        $headerInfo = [
            'SMS Title' => $text->text_title,
            'Created By' => $creator ? $creator->name : 'System',
            'Created At' => $text->created_at->format('Y-m-d H:i:s'),
            'Status' => optional($text->status)->text_status_name ?? 'Unknown',
            'Total Contacts' => $text->contacts_count,
        ];

        // Constants for queue status codes
        $SENT = 3;      // SMS sent/delivered
        $IN_QUEUE = 2;  // SMS in queue/processing
        $FAILED = 4;    // SMS failed (part of undelivered)
        $ERROR = 7;     // SMS error (part of undelivered)

        // Get all queue records for this text with status information and creator information
        $queueItems = DB::table('queues')
            ->select('queues.*', 'text_statuses.text_status_name', 'users.name as created_by_name')
            ->leftJoin('text_statuses', 'queues.status', '=', 'text_statuses.id')
            ->leftJoin('users', 'queues.created_by', '=', 'users.id')
            ->where('queues.text_id', $text->id)
            ->get();

        // Also get raw text_statuses for other status handling
        $textStatuses = DB::table('text_statuses')->get()->keyBy('id');

        // Only process entries that exist in the queues table
        // Skip adding any entries for contacts not in the queue
        foreach ($queueItems as $item) {
            // Get the status name from the joined text_statuses table or fall back to a default
            $statusName = $item->text_status_name ?? 'Unknown';

            // Use the message from the queue item, not from the text
            $message = $item->message ?? $text->message;

            $smsData[] = [
                'telephone' => $item->recipient,
                'message' => $message,
                'date/time' => date('Y-m-d H:i:s', strtotime($item->created_at)),
                'status' => $statusName,
                'response' => $item->response ?? 'Unknown',
                'created_by' => $item->created_by_name ?? 'System'

            ];
        }

        // No longer adding entries for contacts not in the queue
        // This ensures we only export actual entries from the queues table

        // Generate CSV file
        $csvFileName = "sms_detail_{$text->id}_" . date('Y-m-d_H-i-s') . ".csv";
        $csvPath = storage_path('app/public/exports/' . $csvFileName);

        // Ensure the directory exists
        if (!File::exists(storage_path('app/public/exports'))) {
            File::makeDirectory(storage_path('app/public/exports'), 0755, true);
        }

        // Create CSV file
        $file = fopen($csvPath, 'w');

        // Add header information as comments
        foreach ($headerInfo as $key => $value) {
            fputcsv($file, ["# {$key}: {$value}"]);
        }

        // Add a blank line
        fputcsv($file, []);

        // Add column headers
        fputcsv($file, ['telephone', 'message', 'date/time', 'status', 'created_by', 'response']);

        // Add data rows
        foreach ($smsData as $row) {
            fputcsv($file, [
                $row['telephone'],
                $row['message'],
                $row['date/time'],
                $row['status'],
                $row['created_by'],
                $row['response']

            ]);
        }

        fclose($file);

        // Save export record if Export model exists
        if (class_exists('\App\Models\Export')) {
            \App\Models\Export::create([
                'file_name' => $csvFileName,
                'file_path' => 'exports/' . $csvFileName,
                'exported_by' => auth()->id(),
                'export_type' => 'sms_detail'
            ]);
        }

        // Return the file as a download
        return response()->download($csvPath, $csvFileName, [
            'Content-Type' => 'text/csv',
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
        try {
            // Store text information before deletion
            $id = $text->id;
            $wasScheduled = $text->scheduled;
            $title = $text->text_title;

            // Only allow deleting texts that haven't been sent
            if ($text->status_id == TextStatus::SENT || $text->status_id == TextStatus::SENDING || $text->status_id == TextStatus::PROCESSING) {
                return response()->json(['success' => false, 'message' => 'Cannot delete SMS that has already been sent.'], 422);
            }

            // Delete any related records in the queues table first
            \DB::table('queues')->where('text_id', $id)->delete();

            // Delete the text record
            $deleted = $text->delete();

            if (!$deleted) {
                throw new \Exception('Failed to delete SMS record');
            }

            // For API requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "SMS '{$title}' deleted successfully."
                ]);
            }

            // For web requests
            if ($wasScheduled) {
                return redirect()->route('sms.scheduled')
                    ->with('success', "Scheduled SMS '{$title}' deleted successfully.");
            } else {
                return redirect()->route('sms.index')
                    ->with('success', "SMS '{$title}' deleted successfully.");
            }
        } catch (\Exception $e) {
            // For API requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error deleting SMS: ' . $e->getMessage()
                ], 500);
            }

            // For web requests
            return redirect()->back()->with('error', 'Error deleting SMS: ' . $e->getMessage());
        }
    }
}
