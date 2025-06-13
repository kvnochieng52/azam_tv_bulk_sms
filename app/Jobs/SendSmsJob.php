<?php

namespace App\Jobs;

use App\Models\ContactList;
use App\Models\Contact;
use App\Models\Queue;
use App\Models\Text;
use App\Models\TextStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Bus\Batchable;

class SendSmsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hour timeout for large files

    protected $text;

    public function __construct(Text $text)
    {
        $this->text = $text;
    }

    public function handle()
    {
        try {
            Log::info("Starting SMS Campaign ID: {$this->text->id} - Contact Type: {$this->text->contact_type}");

            // Update status to processing
            $this->text->status_id = TextStatus::PROCESSING;
            $this->text->save();

            // Process based on contact type
            switch ($this->text->contact_type) {
                case 'manual':
                    $contacts = $this->processManualContacts();
                    $this->sendSmsToContacts($contacts);
                    break;

                case 'list':
                    $contacts = $this->processSavedContacts();
                    $this->sendSmsToContacts($contacts);
                    break;

                case 'csv':
                    // For CSV files, process and send in chunks to avoid memory issues
                    $this->processCsvContacts();
                    break;

                default:
                    Log::error("Unknown contact type: {$this->text->contact_type}");
                    $this->text->status_id = TextStatus::ERROR;
                    $this->text->save();
            }

            Log::info("Completed SMS Campaign ID: {$this->text->id}");
        } catch (\Exception $e) {
            Log::error("Error in SMS Job: " . $e->getMessage());

            // Update status to error
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();

            // Rethrow exception if this job should be retried
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Process contacts from manual entry
     */
    private function processManualContacts()
    {
        return array_map(function ($contact) {
            return ['phone' => $this->cleanPhoneNumber(trim($contact)), 'message' => $this->text->message];
        }, explode(',', $this->text->recepient_contacts));
    }

    /**
     * Process contacts from saved contact lists
     */
    private function processSavedContacts()
    {
        try {
            $contactIds = json_decode($this->text->contact_list, true) ?? [];
            Log::info("Processing Saved Contacts: " . json_encode($contactIds));

            // Improve efficiency by fetching contacts in chunks
            $batchSize = 1000;
            $contacts = [];

            // Get the contact groups selected by the user
            $contactGroups = Contact::whereIn('id', $contactIds)
                ->where('is_active', 1) // Only get active contact groups
                ->get();

            Log::info('Found Contact Groups: ' . $contactGroups->count());

            foreach ($contactGroups as $group) {
                // Process contact lists in chunks to avoid memory issues
                $group->contactLists()
                    //->where('is_active', 1)
                    ->select('telephone')
                    ->chunk($batchSize, function ($phoneNumbers) use (&$contacts) {
                        foreach ($phoneNumbers as $record) {
                            if (!empty($record->telephone)) {
                                $contacts[] = ['phone' => $this->cleanPhoneNumber($record->telephone), 'message' => $this->text->message];
                            }
                        }

                        // Free up memory
                        gc_collect_cycles();
                    });
            }

            // Update contacts_count
            //$this->text->contacts_count = count($contacts);
            $this->text->save();

            return $contacts;
        } catch (\Exception $e) {
            Log::error("Error processing contact lists: " . $e->getMessage());

            // Update text status to error
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();

            return [];
        }
    }

    /**
     * Process contacts from CSV file
     */
    private function processCsvContacts()
    {
        // Get the proper path to the CSV file using Storage
        $csvPath = storage_path('app/public/' . $this->text->csv_file_path);

        // Log file location for debugging
        Log::info("Processing CSV file at: {$csvPath}");

        if (!file_exists($csvPath)) {
            Log::error("CSV file not found: {$csvPath}");

            // Update text status to error
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();

            return [];
        }

        // Define valid column names for phone numbers
        $validPhoneColumns = [
            'contact',
            'contacts',
            'telephone',
            'mobile',
            'phone number',
            'phone',
            'mobile number'
        ];

        $contacts = [];
        $batchSize = 1000; // Process in batches of 1000 to avoid memory issues
        $processedRows = 0;
        $totalRows = 0;

        try {
            // First pass to count total rows (this is efficient and doesn't load everything to memory)
            if (($countHandle = fopen($csvPath, 'r')) !== false) {
                while (fgetcsv($countHandle) !== false) {
                    $totalRows++;
                }
                fclose($countHandle);
            }

            // Subtract 1 for the header row
            $totalRows = max(0, $totalRows - 1);
            Log::info("Total rows in CSV: {$totalRows}");

            // Update text with total count
            //  $this->text->contacts_count = $totalRows;
            $this->text->save();

            // Now process in batches
            if (($handle = fopen($csvPath, 'r')) !== false) {
                $headers = fgetcsv($handle);
                if (!$headers) {
                    Log::error("Invalid CSV file. No headers found.");
                    fclose($handle);
                    return [];
                }

                $headerMap = array_map('strtolower', array_map('trim', $headers));
                $phoneColumnIndex = null;

                foreach ($headerMap as $index => $header) {
                    if (in_array($header, $validPhoneColumns)) {
                        $phoneColumnIndex = $index;
                        break;
                    }
                }

                if ($phoneColumnIndex === null) {
                    Log::error("No valid contact column found in the CSV.");
                    fclose($handle);

                    // Update text status to error
                    $this->text->status_id = TextStatus::ERROR;
                    $this->text->save();

                    return [];
                }

                Log::info("Found phone column at index {$phoneColumnIndex}: {$headers[$phoneColumnIndex]}");

                $currentBatch = [];
                while (($row = fgetcsv($handle)) !== false) {
                    // Make sure row has enough columns
                    if (count($row) <= $phoneColumnIndex) {
                        Log::warning("Skipping row - not enough columns");
                        continue;
                    }

                    // Create a data map for placeholders, handling potential column count mismatch
                    $contactData = [];
                    foreach ($headers as $index => $header) {
                        $contactData[$header] = $row[$index] ?? '';
                    }

                    $phone = trim($row[$phoneColumnIndex] ?? '');

                    // Validate and clean phone number
                    if (!empty($phone)) {
                        $cleanedPhone = $this->cleanPhoneNumber($phone);

                        // Only use numbers that are valid after cleaning
                        if (!empty($cleanedPhone)) {
                            $message = $this->replacePlaceholders($this->text->message, $contactData);
                            $currentBatch[] = ['phone' => $cleanedPhone, 'message' => $message];

                            // When we reach batch size, process this batch and start a new one
                            if (count($currentBatch) >= $batchSize) {
                                $contacts = array_merge($contacts, $currentBatch);
                                $processedRows += count($currentBatch);

                                // Send this batch immediately to avoid memory issues
                                $this->sendSmsToContacts($currentBatch);

                                // Update progress
                                $progress = min(99, floor(($processedRows / $totalRows) * 100));
                                Log::info("CSV Processing Progress: {$progress}% ({$processedRows}/{$totalRows})");

                                // Reset batch
                                $currentBatch = [];

                                // Free up memory
                                gc_collect_cycles();
                            }
                        }
                    }
                }

                // Process any remaining contacts in the last batch
                if (!empty($currentBatch)) {
                    $contacts = array_merge($contacts, $currentBatch);
                    $this->sendSmsToContacts($currentBatch);
                }

                fclose($handle);

                // Log final count
                Log::info("Total valid contacts processed: " . count($contacts));
            }
        } catch (\Exception $e) {
            Log::error("Error processing CSV: " . $e->getMessage());

            // Update text status to error
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
        }

        return $contacts;
    }

    /**
     * Clean and format a phone number for Kenyan SMS sending
     * - If starting with 0, remove 0 and add 254 prefix (0713295853 -> 254713295853)
     * - If just the number, add 254 prefix (713295853 -> 254713295853)
     * - If already has 254 prefix, leave as is
     * - Remove any special characters (spaces, +, etc.)
     *
     * @param string $phoneNumber The phone number to clean
     * @return string The cleaned and formatted phone number
     */
    private function cleanPhoneNumber($phoneNumber)
    {
        // Remove any non-digit characters (spaces, dashes, plus signs, etc.)
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Skip empty numbers
        if (empty($cleaned)) {
            return '';
        }

        // If already starts with 254, keep it as is
        if (strpos($cleaned, '254') === 0) {
            return $cleaned;
        }

        // If starts with 0, remove it first
        if (strpos($cleaned, '0') === 0) {
            $cleaned = substr($cleaned, 1);
        }

        // Add 254 prefix
        return '254' . $cleaned;
    }

    /**
     * Replace placeholders in message with actual contact data
     */
    private function replacePlaceholders($message, $contactData)
    {
        foreach ($contactData as $key => $value) {
            $placeholder = '{' . trim($key) . '}';
            $message = str_replace($placeholder, $value, $message);
        }
        return $message;
    }

    /**
     * Send SMS to contacts
     */
    private function sendSmsToContacts(array $contacts)
    {
        if (empty($contacts)) {
            Log::warning("No contacts to send to for text ID: {$this->text->id}");
            return;
        }



        $username  = config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');
        $senderId = config('services.africastalking.sender_id');



        try {
            $AT = new AfricasTalking($username, $apiKey,);
            $sms = $AT->sms();

            // Update status to sending
            $this->text->status_id = TextStatus::SENDING;
            $this->text->save();

            // Bulk insert queue records for efficiency
            $queueRecords = [];
            $successCount = 0;
            $failCount = 0;

            // Process in smaller batches to avoid API limits
            $smsBatchSize = 100;
            $contactBatches = array_chunk($contacts, $smsBatchSize);

            foreach ($contactBatches as $batchIndex => $contactBatch) {
                // Prepare recipients in bulk format
                $recipients = [];
                foreach ($contactBatch as $contact) {
                    $recipients[] = $contact['phone'];
                }

                // Some recipients might have the same message, some might have personalized messages
                // Group by message for efficiency
                $messageGroups = [];
                foreach ($contactBatch as $contact) {
                    $messageGroups[$contact['message']][] = $contact['phone'];
                }

                foreach ($messageGroups as $message => $phones) {
                    try {
                        // Send the message to this group
                        $response = $sms->send([
                            'to'      => implode(',', $phones),
                            'message' => $message,
                            'from'    => $senderId,
                        ]);


                        log::info("RESPONSE DATA", $response);
                        Log::info("Batch {$batchIndex} - Sent to " . count($phones) . " recipients");


                        $responseArray = json_decode(json_encode($response), true);

                        // Track successful sends

                        if (
                            // isset($responseArray['data']) &&
                            // isset($responseArray['data']['SMSMessageData']) &&
                            isset($responseArray['data']['SMSMessageData']['Recipients'])
                        ) {

                            $recipients = $responseArray['data']['SMSMessageData']['Recipients'];



                            // log::info("REX DATA", $recipients);

                            foreach ($recipients as $recipient) {

                                // log::info("HAVE ENETERD HERE: ", $recipient);

                                $status = $recipient['status'] === 'Success' ? TextStatus::SENT : TextStatus::FAILED;

                                if ($status === TextStatus::SENT) {
                                    $successCount++;
                                } else {
                                    $failCount++;
                                }

                                // Prepare queue record
                                $queueRecords[] = [
                                    'text_id' => $this->text->id,
                                    'message' => $message,
                                    'recipient' => $recipient['number'],
                                    'status' => $status,
                                    'response' => json_encode($recipient),
                                    'created_by' => $this->text->created_by,
                                    'updated_by' => $this->text->updated_by,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];

                                // Bulk insert when we have enough records
                                // if (count($queueRecords) >= 1000) {
                                Queue::insert($queueRecords);
                                $queueRecords = [];
                                // }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to send SMS batch: " . $e->getMessage());
                        $failCount += count($phones);

                        // Add failed records
                        foreach ($phones as $phone) {
                            $queueRecords[] = [
                                'text_id' => $this->text->id,
                                'message' => $message,
                                'recipient' => $phone,
                                'status' => TextStatus::FAILED,
                                'response' => json_encode(['error' => $e->getMessage()]),
                                'created_by' => $this->text->created_by,
                                'updated_by' => $this->text->updated_by,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }

                // Sleep briefly to avoid hitting API rate limits
                if (count($contactBatches) > 10) {
                    usleep(200000); // 200ms
                }
            }

            // Insert any remaining queue records
            if (!empty($queueRecords)) {
                Queue::insert($queueRecords);
            }

            // Update text status based on results
            if ($failCount === 0 && $successCount > 0) {
                $this->text->status_id = TextStatus::SENT;
            } elseif ($successCount === 0) {
                $this->text->status_id = TextStatus::FAILED;
            } else {
                $this->text->status_id = TextStatus::PARTIAL;
            }

            $this->text->save();

            Log::info("SMS Campaign completed - Success: {$successCount}, Failed: {$failCount}");
        } catch (\Exception $e) {
            Log::error("Failed to initialize SMS sending: " . $e->getMessage());

            // Update text status to error
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
        }
    }
}
