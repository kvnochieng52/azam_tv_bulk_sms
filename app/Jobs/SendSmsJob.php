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
        $contacts = [];
        $phoneNumbers = array_map('trim', explode(',', $this->text->recepient_contacts));
        $seenCombinations = []; // Track phone+message combinations

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

    /**
     * Process contacts from saved contact lists
     */
    private function processSavedContacts()
    {
        try {
            $contactIds = json_decode($this->text->contact_list, true) ?? [];
            Log::info("Processing Saved Contacts: " . json_encode($contactIds));

            $contacts = [];
            $seenCombinations = []; // Track phone+message combinations across all contact lists
            $batchSize = 1000;

            // Get the contact groups selected by the user
            $contactGroups = Contact::whereIn('id', $contactIds)
                ->where('is_active', 1)
                ->get();

            Log::info('Found Contact Groups: ' . $contactGroups->count());

            foreach ($contactGroups as $group) {
                // Process contact lists in chunks to avoid memory issues
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
            $this->text->save();

            return $contacts;
        } catch (\Exception $e) {
            Log::error("Error processing contact lists: " . $e->getMessage());
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

        Log::info("Processing CSV file at: {$csvPath}");

        if (!file_exists($csvPath)) {
            Log::error("CSV file not found: {$csvPath}");
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
        $seenCombinations = []; // Track phone+message combinations in CSV
        $batchSize = 1000;
        $processedRows = 0;
        $skippedRows = 0;
        $duplicateRows = 0;
        $totalRows = 0;

        try {
            $csvContent = file_get_contents($csvPath);
            if ($csvContent === false) {
                Log::error("Could not read CSV file content");
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
                return [];
            }

            $lines = explode("\n", $csvContent);
            $totalRows = count($lines) - 1;

            // Remove empty lines at the end
            while (end($lines) === '' || end($lines) === null) {
                array_pop($lines);
                $totalRows--;
            }

            Log::info("Total data rows in CSV: {$totalRows}");

            if (empty($lines)) {
                Log::error("CSV file is empty");
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
                return [];
            }

            // Parse header
            $headers = str_getcsv($lines[0]);
            if (!$headers) {
                Log::error("Invalid CSV file. No headers found.");
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
                return [];
            }

            // Find phone column
            $headerMap = array_map('strtolower', array_map('trim', $headers));
            $phoneColumnIndex = null;

            foreach ($headerMap as $index => $header) {
                if (in_array($header, $validPhoneColumns)) {
                    $phoneColumnIndex = $index;
                    break;
                }
            }

            if ($phoneColumnIndex === null) {
                Log::error("No valid contact column found. Available headers: " . implode(', ', $headers));
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
                return [];
            }

            Log::info("Found phone column at index {$phoneColumnIndex}: {$headers[$phoneColumnIndex]}");

            $currentBatch = [];

            // Process each data row (skip header at index 0)
            for ($i = 1; $i < count($lines); $i++) {
                $lineContent = trim($lines[$i]);

                if (empty($lineContent)) {
                    Log::debug("Skipping empty line at index {$i}");
                    $skippedRows++;
                    continue;
                }

                $row = str_getcsv($lineContent);

                if (!$row || count($row) <= $phoneColumnIndex) {
                    Log::warning("Skipping row " . ($i + 1) . " - insufficient columns");
                    $skippedRows++;
                    continue;
                }

                // Create contact data map
                $contactData = [];
                foreach ($headers as $index => $header) {
                    $contactData[$header] = $row[$index] ?? '';
                }

                $phone = trim($row[$phoneColumnIndex] ?? '');

                if (empty($phone)) {
                    Log::warning("Skipping row " . ($i + 1) . " - empty phone number");
                    $skippedRows++;
                    continue;
                }

                $cleanedPhone = $this->cleanPhoneNumber($phone);

                if (empty($cleanedPhone)) {
                    Log::warning("Skipping row " . ($i + 1) . " - invalid phone number: '{$phone}'");
                    $skippedRows++;
                    continue;
                }

                $seenCombinations[] = $cleanedPhone;
                $message = $this->replacePlaceholders($this->text->message, $contactData);

                // Check for duplicates within CSV (phone + message combination)
                $combination = $cleanedPhone . '|' . $message;
                if (in_array($combination, $seenCombinations)) {
                    Log::warning("Skipping row " . ($i + 1) . " - duplicate phone+message combination: '{$cleanedPhone}' with message hash: " . substr(md5($message), 0, 8));
                    $duplicateRows++;
                    continue;
                }

                $seenCombinations[] = $combination;

                $currentBatch[] = [
                    'phone' => $cleanedPhone,
                    'message' => $message,
                    'row_number' => $i + 1,
                    'original_phone' => $phone
                ];
                $processedRows++;

                // Process in batches to avoid memory issues
                if (count($currentBatch) >= $batchSize) {
                    Log::info("Sending batch of " . count($currentBatch) . " contacts");
                    $this->sendSmsToContacts($currentBatch);

                    $progress = min(99, floor(($processedRows / $totalRows) * 100));
                    Log::info("CSV Processing Progress: {$progress}% ({$processedRows}/{$totalRows}) - Skipped: {$skippedRows} - Duplicates: {$duplicateRows}");

                    $currentBatch = [];
                    gc_collect_cycles();
                }
            }

            // Process remaining contacts
            if (!empty($currentBatch)) {
                Log::info("Sending final batch of " . count($currentBatch) . " contacts");
                $this->sendSmsToContacts($currentBatch);
            }

            // Final statistics
            Log::info("=== CSV Processing Complete ===");
            Log::info("Total lines in file: " . count($lines));
            Log::info("Data rows available: {$totalRows}");
            Log::info("Successfully processed: {$processedRows}");
            Log::info("Skipped rows: {$skippedRows}");
            Log::info("Duplicate rows: {$duplicateRows}");
        } catch (\Exception $e) {
            Log::error("Error processing CSV: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
        }

        return [];
    }

    /**
     * Clean and format a phone number for Kenyan SMS sending
     */
    private function cleanPhoneNumber($phoneNumber)
    {
        // Remove any non-digit characters
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

    private function replacePlaceholders($message, $contactData)
    {
        foreach ($contactData as $key => $value) {
            $placeholder = '{' . trim($key) . '}';
            $message = str_replace($placeholder, $value, $message);
        }
        return $message;
    }

    /**
     * Send SMS to contacts with improved duplicate prevention
     */
    private function sendSmsToContacts(array $contacts)
    {
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

            // Update status to sending
            $this->text->status_id = TextStatus::SENDING;
            $this->text->save();

            $successCount = 0;
            $failCount = 0;
            $duplicateCount = 0;
            $smsBatchSize = 100;

            // First, remove duplicates from the current batch and check against database
            $uniqueContacts = $this->filterUniqueContacts($contacts, $duplicateCount);

            if (empty($uniqueContacts)) {
                Log::info("All contacts were duplicates or already processed");
                return;
            }

            $contactBatches = array_chunk($uniqueContacts, $smsBatchSize);

            foreach ($contactBatches as $batchIndex => $contactBatch) {
                // Use database transaction for each batch
                DB::transaction(function () use ($contactBatch, $batchIndex, $sms, $senderId, &$successCount, &$failCount) {

                    // Group by message for efficiency
                    $messageGroups = [];
                    foreach ($contactBatch as $contact) {
                        $messageGroups[$contact['message']][] = $contact['phone'];
                    }

                    foreach ($messageGroups as $message => $phones) {
                        $queueRecords = [];

                        try {
                            // Send the message to this group
                            $response = $sms->send([
                                'to'      => implode(',', $phones),
                                'message' => $message,
                                'from'    => $senderId,
                            ]);

                            Log::info("Batch {$batchIndex} - API call sent to " . count($phones) . " recipients");
                            $responseArray = json_decode(json_encode($response), true);

                            // Process API response
                            if (isset($responseArray['data']['SMSMessageData']['Recipients'])) {
                                $recipients = $responseArray['data']['SMSMessageData']['Recipients'];

                                foreach ($recipients as $recipient) {
                                    $status = $recipient['status'] === 'Success' ? TextStatus::SENT : TextStatus::FAILED;

                                    if ($status === TextStatus::SENT) {
                                        $successCount++;
                                    } else {
                                        $failCount++;
                                    }

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
                                }
                            } else {
                                // If API response format is unexpected, mark all as failed
                                Log::warning("Unexpected API response format for batch {$batchIndex}");
                                foreach ($phones as $phone) {
                                    $failCount++;
                                    $queueRecords[] = [
                                        'text_id' => $this->text->id,
                                        'message' => $message,
                                        'recipient' => $phone,
                                        'status' => TextStatus::FAILED,
                                        'response' => json_encode(['error' => 'Unexpected API response format']),
                                        'created_by' => $this->text->created_by,
                                        'updated_by' => $this->text->updated_by,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ];
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error("Failed to send SMS batch {$batchIndex}: " . $e->getMessage());

                            // Mark all phones in this group as failed
                            foreach ($phones as $phone) {
                                $failCount++;
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

                        // Insert all queue records at once using bulk insert
                        if (!empty($queueRecords)) {
                            try {
                                Queue::insert($queueRecords);
                                Log::info("Successfully logged " . count($queueRecords) . " queue records for batch {$batchIndex}");
                            } catch (\Exception $e) {
                                Log::error("Failed to insert queue records for batch {$batchIndex}: " . $e->getMessage());

                                // Fallback: insert one by one with duplicate checking
                                foreach ($queueRecords as $record) {
                                    try {
                                        Queue::updateOrCreate(
                                            [
                                                'text_id' => $record['text_id'],
                                                'recipient' => $record['recipient'],
                                                'message' => $record['message']
                                            ],
                                            $record
                                        );
                                    } catch (\Exception $individualError) {
                                        Log::error("Failed to insert individual queue record: " . $individualError->getMessage());
                                    }
                                }
                            }
                        }
                    }

                    Log::info("Batch {$batchIndex} completed successfully within transaction");
                }, 5); // 5 retry attempts for deadlock resolution

                // Rate limiting between batches
                if (count($contactBatches) > 10) {
                    usleep(400000); // 400ms
                }
            }

            // Final status update in transaction
            DB::transaction(function () use ($successCount, $failCount, $duplicateCount) {
                // Refresh the model to get latest status
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

            Log::info("SMS Campaign completed - Success: {$successCount}, Failed: {$failCount}, Duplicates Skipped: {$duplicateCount}");
        } catch (\Exception $e) {
            Log::error("Failed to initialize SMS sending: " . $e->getMessage());

            DB::transaction(function () {
                $this->text->refresh();
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
            });
        }
    }

    /**
     * Filter unique contacts and remove duplicates based on phone+message combination
     */
    private function filterUniqueContacts(array $contacts, &$duplicateCount)
    {
        $uniqueContacts = [];
        $seenInCurrentBatch = [];
        $duplicateCount = 0;

        // First, get all phone+message combinations for this text campaign that have already been processed
        $alreadyProcessedCombinations = Queue::where('text_id', $this->text->id)
            ->get(['recipient', 'message'])
            ->map(function ($item) {
                return $item->recipient . '|' . $item->message;
            })
            ->toArray();

        Log::info("Found " . count($alreadyProcessedCombinations) . " already processed phone+message combinations for text ID: {$this->text->id}");

        foreach ($contacts as $contact) {
            $phone = $contact['phone'];
            $message = $contact['message'];
            $combination = $phone . '|' . $message;

            // Skip if already processed in database
            if (in_array($combination, $alreadyProcessedCombinations)) {
                Log::debug("Skipping already processed combination: {$phone} with message hash: " . substr(md5($message), 0, 8));
                $duplicateCount++;
                continue;
            }

            // Skip if already seen in current batch
            if (in_array($combination, $seenInCurrentBatch)) {
                Log::debug("Skipping duplicate in current batch: {$phone} with message hash: " . substr(md5($message), 0, 8));
                $duplicateCount++;
                continue;
            }

            $seenInCurrentBatch[] = $combination;
            $uniqueContacts[] = $contact;
        }

        Log::info("Filtered " . count($uniqueContacts) . " unique contacts from " . count($contacts) . " total contacts. Duplicates: {$duplicateCount}");

        return $uniqueContacts;
    }
}
