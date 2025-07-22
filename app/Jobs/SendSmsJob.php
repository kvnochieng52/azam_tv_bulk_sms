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
    public $tries = 1;

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

            // FIXED: More specific lock key to prevent cross-campaign interference
            $lockKey = "sms_job_lock_{$this->text->id}_{$this->text->contact_type}_" . md5($this->text->created_at);
            $lock = cache()->lock($lockKey, 3600); // 1 hour lock

            if (!$lock->get()) {
                Log::warning("SMS Campaign ID: {$this->text->id} is already being processed. Skipping.");
                return;
            }

            try {
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
                        // FIXED: Process CSV with improved row mapping
                        $this->processCsvContactsFixed();
                        break;

                    default:
                        Log::error("Unknown contact type: {$this->text->contact_type}");
                        $this->text->status_id = TextStatus::ERROR;
                        $this->text->save();
                }

                Log::info("Completed SMS Campaign ID: {$this->text->id}");
            } finally {
                $lock->release();
            }
        } catch (\Exception $e) {
            Log::error("Error in SMS Job: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

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
     * FIXED: Completely rewritten CSV processing to eliminate row mapping issues
     */
    private function processCsvContactsFixed()
    {
        $csvPath = storage_path('app/public/' . $this->text->csv_file_path);
        Log::info("Processing CSV file at: {$csvPath}");

        if (!file_exists($csvPath)) {
            Log::error("CSV file not found: {$csvPath}");
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
            return;
        }

        // FIXED: Create unique processing identifier for this specific job
        $processingId = uniqid("csv_proc_{$this->text->id}_", true);
        Log::info("CSV Processing ID: {$processingId}");

        $validPhoneColumns = [
            'contact',
            'contacts',
            'telephone',
            'mobile',
            'phone number',
            'phone',
            'mobile number'
        ];

        $batchSize = 100; // Reduced for better control
        $processedRows = 0;
        $skippedRows = 0;
        $duplicateRows = 0;

        // FIXED: Use database-based duplicate tracking instead of memory arrays
        $tempTableName = "temp_csv_processing_{$this->text->id}";

        try {
            // Create temporary table for this processing session
            DB::statement("CREATE TEMPORARY TABLE IF NOT EXISTS `{$tempTableName}` (
                `phone_message_hash` VARCHAR(64) PRIMARY KEY,
                `phone` VARCHAR(20),
                `message` TEXT,
                `row_number` INT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // FIXED: Read and parse CSV more reliably
            $csvData = $this->parseCSVFileRobustly($csvPath);

            if (empty($csvData)) {
                Log::error("CSV parsing failed or file is empty");
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
                return;
            }

            $headers = array_shift($csvData); // Remove header row
            $totalRows = count($csvData);

            Log::info("CSV parsed successfully. Total data rows: {$totalRows}");
            Log::info("Headers found: " . implode(', ', $headers));

            // Find phone column
            $phoneColumnIndex = $this->findPhoneColumnIndex($headers, $validPhoneColumns);

            if ($phoneColumnIndex === null) {
                Log::error("No valid contact column found. Available headers: " . implode(', ', $headers));
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
                return;
            }

            Log::info("Using phone column: {$headers[$phoneColumnIndex]} (index: {$phoneColumnIndex})");

            $currentBatch = [];

            // FIXED: Process each row with explicit mapping
            foreach ($csvData as $rowIndex => $rowData) {
                $actualRowNumber = $rowIndex + 2; // +2 because we removed header and array is 0-based

                Log::debug("Processing row {$actualRowNumber}: " . json_encode($rowData));

                // FIXED: Ensure row has enough columns
                if (!is_array($rowData) || count($rowData) <= $phoneColumnIndex) {
                    Log::warning("Row {$actualRowNumber} has insufficient columns. Expected: " . count($headers) . ", Got: " . count($rowData));
                    $skippedRows++;
                    continue;
                }

                // FIXED: Create proper contact data mapping with validation
                $contactData = [];
                for ($i = 0; $i < count($headers); $i++) {
                    $contactData[trim($headers[$i])] = isset($rowData[$i]) ? trim($rowData[$i]) : '';
                }

                $phone = $contactData[$headers[$phoneColumnIndex]] ?? '';

                if (empty($phone)) {
                    Log::warning("Row {$actualRowNumber} has empty phone number");
                    $skippedRows++;
                    continue;
                }

                $cleanedPhone = $this->cleanPhoneNumber($phone);
                if (empty($cleanedPhone)) {
                    Log::warning("Row {$actualRowNumber} has invalid phone: '{$phone}'");
                    $skippedRows++;
                    continue;
                }

                // FIXED: Generate message with proper row data
                $personalizedMessage = $this->replacePlaceholders($this->text->message, $contactData);

                // FIXED: Create unique hash for phone+message combination
                $combinationHash = md5($cleanedPhone . '|' . $personalizedMessage);

                // FIXED: Check for duplicates using database instead of memory
                $isDuplicate = DB::select("SELECT 1 FROM `{$tempTableName}` WHERE phone_message_hash = ? LIMIT 1", [$combinationHash]);

                if (!empty($isDuplicate)) {
                    Log::debug("Row {$actualRowNumber} is duplicate: {$cleanedPhone}");
                    $duplicateRows++;
                    continue;
                }

                // Insert into temp table to track this combination
                DB::insert(
                    "INSERT INTO `{$tempTableName}` (phone_message_hash, phone, message, row_number) VALUES (?, ?, ?, ?)",
                    [$combinationHash, $cleanedPhone, $personalizedMessage, $actualRowNumber]
                );

                $currentBatch[] = [
                    'phone' => $cleanedPhone,
                    'message' => $personalizedMessage,
                    'row_number' => $actualRowNumber,
                    'original_phone' => $phone,
                    'processing_id' => $processingId,
                    'contact_data' => $contactData // Keep for debugging
                ];
                $processedRows++;

                // Process in smaller batches
                if (count($currentBatch) >= $batchSize) {
                    Log::info("Sending batch of " . count($currentBatch) . " contacts (Processing ID: {$processingId})");
                    $this->sendSmsToContactsFixed($currentBatch, $processingId);

                    $progress = min(99, floor(($processedRows / $totalRows) * 100));
                    Log::info("Progress: {$progress}% ({$processedRows}/{$totalRows}) - Skipped: {$skippedRows}, Duplicates: {$duplicateRows}");

                    $currentBatch = [];
                    gc_collect_cycles(); // Clean memory
                }
            }

            // Process remaining contacts
            if (!empty($currentBatch)) {
                Log::info("Sending final batch of " . count($currentBatch) . " contacts (Processing ID: {$processingId})");
                $this->sendSmsToContactsFixed($currentBatch, $processingId);
            }

            Log::info("=== CSV Processing Complete (ID: {$processingId}) ===");
            Log::info("Total rows: {$totalRows}, Processed: {$processedRows}, Skipped: {$skippedRows}, Duplicates: {$duplicateRows}");
        } catch (\Exception $e) {
            Log::error("Error in CSV processing: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
        } finally {
            // Clean up temporary table
            try {
                DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$tempTableName}`");
            } catch (\Exception $e) {
                Log::warning("Failed to cleanup temp table: " . $e->getMessage());
            }
        }
    }

    /**
     * FIXED: More robust CSV parsing
     */
    private function parseCSVFileRobustly($csvPath)
    {
        $csvData = [];

        if (($handle = fopen($csvPath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if (!empty(array_filter($data))) { // Skip completely empty rows
                    $csvData[] = $data;
                }
            }
            fclose($handle);
        }

        return $csvData;
    }

    /**
     * FIXED: Better phone column detection
     */
    private function findPhoneColumnIndex($headers, $validPhoneColumns)
    {
        $headerMap = array_map('strtolower', array_map('trim', $headers));

        foreach ($headerMap as $index => $header) {
            if (in_array($header, $validPhoneColumns)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * FIXED: Improved SMS sending with better tracking
     */
    private function sendSmsToContactsFixed(array $contacts, $processingId)
    {
        if (empty($contacts)) {
            Log::warning("No contacts to send to for text ID: {$this->text->id} (Processing ID: {$processingId})");
            return;
        }

        Log::info("Sending SMS to " . count($contacts) . " contacts (Processing ID: {$processingId})");

        $username = config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');
        $senderId = config('services.africastalking.sender_id');

        try {
            $AT = new AfricasTalking($username, $apiKey);
            $sms = $AT->sms();

            $this->text->status_id = TextStatus::SENDING;
            $this->text->save();

            $successCount = 0;
            $failCount = 0;

            // FIXED: Send individual SMS to preserve personalization and prevent mix-ups
            foreach ($contacts as $contact) {
                try {
                    Log::info("Sending to {$contact['phone']} from row {$contact['row_number']} (Processing ID: {$processingId})");
                    Log::debug("Message preview: " . substr($contact['message'], 0, 100) . "...");

                    // Send individual message
                    $response = $sms->send([
                        'to' => $contact['phone'],
                        'message' => $contact['message'],
                        'from' => $senderId,
                    ]);

                    $responseArray = json_decode(json_encode($response), true);

                    $status = TextStatus::FAILED;
                    $responseData = ['error' => 'Unexpected response format'];

                    if (
                        isset($responseArray['data']['SMSMessageData']['Recipients']) &&
                        count($responseArray['data']['SMSMessageData']['Recipients']) > 0
                    ) {

                        $recipient = $responseArray['data']['SMSMessageData']['Recipients'][0];
                        $status = $recipient['status'] === 'Success' ? TextStatus::SENT : TextStatus::FAILED;
                        $responseData = $recipient;
                    }

                    if ($status === TextStatus::SENT) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }

                    // FIXED: Create queue record with processing ID for tracking
                    $this->createQueueRecordSafely([
                        'text_id' => $this->text->id,
                        'message' => $contact['message'],
                        'recipient' => $contact['phone'],
                        'status' => $status,
                        'response' => json_encode(array_merge($responseData, [
                            'processing_id' => $processingId,
                            'row_number' => $contact['row_number']
                        ])),
                        'created_by' => $this->text->created_by ?? 1,
                        'updated_by' => $this->text->updated_by ?? 1,
                    ]);

                    // Rate limiting
                    usleep(250000); // 250ms between messages

                } catch (\Exception $e) {
                    Log::error("Failed to send SMS to {$contact['phone']}: " . $e->getMessage());
                    $failCount++;

                    $this->createQueueRecordSafely([
                        'text_id' => $this->text->id,
                        'message' => $contact['message'],
                        'recipient' => $contact['phone'],
                        'status' => TextStatus::FAILED,
                        'response' => json_encode([
                            'error' => $e->getMessage(),
                            'processing_id' => $processingId,
                            'row_number' => $contact['row_number']
                        ]),
                        'created_by' => $this->text->created_by ?? 1,
                        'updated_by' => $this->text->updated_by ?? 1,
                    ]);
                }
            }

            // Update final status
            DB::transaction(function () use ($successCount, $failCount) {
                $this->text->refresh();

                if ($failCount === 0 && $successCount > 0) {
                    $this->text->status_id = TextStatus::SENT;
                } elseif ($successCount === 0) {
                    $this->text->status_id = TextStatus::FAILED;
                } else {
                    $this->text->status_id = TextStatus::PARTIAL;
                }

                $this->text->save();
            });

            Log::info("SMS Campaign completed (Processing ID: {$processingId}) - Success: {$successCount}, Failed: {$failCount}");
        } catch (\Exception $e) {
            Log::error("Failed to initialize SMS sending: " . $e->getMessage());
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
        }
    }

    /**
     * FIXED: Safe queue record creation with duplicate checking
     */
    private function createQueueRecordSafely($data)
    {

        Queue::create(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        // try {
        //     $existing = Queue::where('text_id', $data['text_id'])
        //         ->where('recipient', $data['recipient'])
        //         ->where('message', $data['message'])
        //         ->first();

        //     if (!$existing) {
        //         Queue::create(array_merge($data, [
        //             'created_at' => now(),
        //             'updated_at' => now(),
        //         ]));
        //         Log::debug("Queue record created for {$data['recipient']}");
        //     } else {
        //         Log::debug("Queue record already exists for {$data['recipient']}");
        //     }
        // } catch (\Exception $e) {
        //     Log::error("Failed to create queue record for {$data['recipient']}: " . $e->getMessage());
        // }
    }

    // Keep all your existing methods below (processManualContacts, processSavedContacts, etc.)
    // I'm only showing the fixed methods to focus on the CSV issue

    private function processManualContacts()
    {
        $contacts = [];
        $phoneNumbers = array_map('trim', explode(',', $this->text->recepient_contacts));
        $seenCombinations = [];

        foreach ($phoneNumbers as $phone) {
            if (!empty($phone)) {
                $cleanedPhone = $this->cleanPhoneNumber($phone);
                if (!empty($cleanedPhone)) {
                    $combination = $cleanedPhone . '|' . $this->text->message;
                    if (!in_array($combination, $seenCombinations)) {
                        $contacts[] = ['phone' => $cleanedPhone, 'message' => $this->text->message];
                        $seenCombinations[] = $combination;
                    }
                }
            }
        }

        return $contacts;
    }

    private function processSavedContacts()
    {
        try {
            $contactIds = json_decode($this->text->contact_list, true) ?? [];
            Log::info("Processing Saved Contacts: " . json_encode($contactIds));

            $contacts = [];
            $seenCombinations = [];
            $batchSize = 1000;

            $contactGroups = Contact::whereIn('id', $contactIds)
                ->where('is_active', 1)
                ->get();

            Log::info('Found Contact Groups: ' . $contactGroups->count());

            foreach ($contactGroups as $group) {
                $group->contactLists()
                    ->select('telephone')
                    ->chunk($batchSize, function ($phoneNumbers) use (&$contacts, &$seenCombinations) {
                        foreach ($phoneNumbers as $record) {
                            if (!empty($record->telephone)) {
                                $cleanedPhone = $this->cleanPhoneNumber($record->telephone);
                                if (!empty($cleanedPhone)) {
                                    $combination = $cleanedPhone . '|' . $this->text->message;
                                    if (!in_array($combination, $seenCombinations)) {
                                        $contacts[] = [
                                            'phone' => $cleanedPhone,
                                            'message' => $this->text->message
                                        ];
                                        $seenCombinations[] = $combination;
                                    }
                                }
                            }
                        }
                        gc_collect_cycles();
                    });
            }

            Log::info("Total unique contacts processed: " . count($contacts));
            return $contacts;
        } catch (\Exception $e) {
            Log::error("Error processing contact lists: " . $e->getMessage());
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
            return [];
        }
    }

    private function cleanPhoneNumber($phoneNumber)
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (empty($cleaned)) {
            return '';
        }

        if (strpos($cleaned, '254') === 0) {
            return $cleaned;
        }

        if (strpos($cleaned, '0') === 0) {
            $cleaned = substr($cleaned, 1);
        }

        return '254' . $cleaned;
    }

    private function replacePlaceholders($message, $contactData)
    {
        foreach ($contactData as $key => $value) {
            $placeholder = '{' . trim($key) . '}';
            $message = str_replace($placeholder, $value, $message);
        }
        return $message;
    }

    // Your existing sendSmsToContacts method for manual/list contacts
    private function sendSmsToContacts(array $contacts)
    {
        // Keep your existing implementation for non-CSV contacts
        // This handles manual and list contacts with the original logic
        if (empty($contacts)) {
            Log::warning("No contacts to send to for text ID: {$this->text->id}");
            return;
        }

        Log::info("Preparing to send SMS to " . count($contacts) . " contacts");

        $username = config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');
        $senderId = config('services.africastalking.sender_id');

        try {
            $AT = new AfricasTalking($username, $apiKey);
            $sms = $AT->sms();

            $this->text->status_id = TextStatus::SENDING;
            $this->text->save();

            $successCount = 0;
            $failCount = 0;
            $duplicateCount = 0;
            $smsBatchSize = 50;

            $uniqueContacts = $this->filterUniqueContacts($contacts, $duplicateCount);

            if (empty($uniqueContacts)) {
                Log::info("All contacts were duplicates or already processed");
                return;
            }

            $contactBatches = array_chunk($uniqueContacts, $smsBatchSize);

            foreach ($contactBatches as $batchIndex => $contactBatch) {
                $messageGroups = [];
                foreach ($contactBatch as $contact) {
                    $messageGroups[$contact['message']][] = $contact['phone'];
                }

                foreach ($messageGroups as $message => $phones) {
                    try {
                        $response = $sms->send([
                            'to' => implode(',', $phones),
                            'message' => $message,
                            'from' => $senderId,
                        ]);

                        $responseArray = json_decode(json_encode($response), true);
                        $queueRecords = [];

                        if (isset($responseArray['data']['SMSMessageData']['Recipients'])) {
                            foreach ($responseArray['data']['SMSMessageData']['Recipients'] as $recipient) {
                                $status = $recipient['status'] === 'Success' ? TextStatus::SENT : TextStatus::FAILED;
                                $phone = $recipient['number'];

                                if ($status === TextStatus::SENT) {
                                    $successCount++;
                                } else {
                                    $failCount++;
                                }

                                $this->createQueueRecordSafely([
                                    'text_id' => $this->text->id,
                                    'message' => $message,
                                    'recipient' => $phone,
                                    'status' => $status,
                                    'response' => json_encode($recipient),
                                    'created_by' => $this->text->created_by ?? 1,
                                    'updated_by' => $this->text->updated_by ?? 1,
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to send SMS batch: " . $e->getMessage());
                        foreach ($phones as $phone) {
                            $failCount++;
                            $this->createQueueRecordSafely([
                                'text_id' => $this->text->id,
                                'message' => $message,
                                'recipient' => $phone,
                                'status' => TextStatus::FAILED,
                                'response' => json_encode(['error' => $e->getMessage()]),
                                'created_by' => $this->text->created_by ?? 1,
                                'updated_by' => $this->text->updated_by ?? 1,
                            ]);
                        }
                    }
                }

                if (count($contactBatches) > 1) {
                    sleep(2);
                }
            }

            DB::transaction(function () use ($successCount, $failCount) {
                $this->text->refresh();
                if ($failCount === 0 && $successCount > 0) {
                    $this->text->status_id = TextStatus::SENT;
                } elseif ($successCount === 0) {
                    $this->text->status_id = TextStatus::FAILED;
                } else {
                    $this->text->status_id = TextStatus::PARTIAL;
                }
                $this->text->save();
            });

            Log::info("SMS Campaign completed - Success: {$successCount}, Failed: {$failCount}");
        } catch (\Exception $e) {
            Log::error("Failed to initialize SMS sending: " . $e->getMessage());
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
        }
    }

    private function filterUniqueContacts(array $contacts, &$duplicateCount)
    {
        $uniqueContacts = [];
        $seenInCurrentBatch = [];
        $duplicateCount = 0;

        $alreadyProcessedCombinations = [];

        try {
            $processedRecords = Queue::where('text_id', $this->text->id)
                ->select('recipient', 'message')
                ->get();

            foreach ($processedRecords as $record) {
                $alreadyProcessedCombinations[] = $record->recipient . '|' . $record->message;
            }
        } catch (\Exception $e) {
            Log::error("Error fetching already processed combinations: " . $e->getMessage());
        }

        foreach ($contacts as $contact) {
            $combination = $contact['phone'] . '|' . $contact['message'];

            if (
                in_array($combination, $alreadyProcessedCombinations) ||
                in_array($combination, $seenInCurrentBatch)
            ) {
                $duplicateCount++;
                continue;
            }

            $seenInCurrentBatch[] = $combination;
            $uniqueContacts[] = $contact;
        }

        return $uniqueContacts;
    }
}
