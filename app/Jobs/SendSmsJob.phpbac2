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

class SendSmsJobBac3 implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 3600;
    protected $text;

    public function __construct(Text $text)
    {
        $this->text = $text;
    }

    public function handle()
    {
        // Check if this job has already been processed
        if (
            $this->text->status_id === TextStatus::SENT ||
            $this->text->status_id === TextStatus::PARTIAL
        ) {
            Log::info("SMS Campaign ID: {$this->text->id} already completed. Skipping.");
            return;
        }

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

            // Only update status to error if this is the last attempt
            if ($this->attempts() >= $this->tries) {
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
            }

            throw $e;
        }
    }

    private function processManualContacts()
    {
        return array_map(function ($contact) {
            return ['phone' => $this->cleanPhoneNumber(trim($contact)), 'message' => $this->text->message];
        }, explode(',', $this->text->recepient_contacts));
    }

    private function processSavedContacts()
    {
        try {
            $contactIds = json_decode($this->text->contact_list, true) ?? [];
            Log::info("Processing Saved Contacts: " . json_encode($contactIds));

            $batchSize = 1000;
            $contacts = [];

            $contactGroups = Contact::whereIn('id', $contactIds)
                ->where('is_active', 1)
                ->get();

            Log::info('Found Contact Groups: ' . $contactGroups->count());

            foreach ($contactGroups as $group) {
                $group->contactLists()
                    ->select('telephone')
                    ->chunk($batchSize, function ($phoneNumbers) use (&$contacts) {
                        foreach ($phoneNumbers as $record) {
                            if (!empty($record->telephone)) {
                                $phone = $this->cleanPhoneNumber($record->telephone);
                                if ($phone) {
                                    $contacts[] = ['phone' => $phone, 'message' => $this->text->message];
                                }
                            }
                        }
                        gc_collect_cycles();
                    });
            }

            $this->text->save();
            return $contacts;
        } catch (\Exception $e) {
            Log::error("Error processing contact lists: " . $e->getMessage());
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
            return [];
        }
    }

    private function processCsvContacts()
    {
        $csvPath = storage_path('app/public/' . $this->text->csv_file_path);
        Log::info("Processing CSV file at: {$csvPath}");

        if (!file_exists($csvPath)) {
            Log::error("CSV file not found: {$csvPath}");
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
            return [];
        }

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
        $batchSize = 1000;
        $processedRows = 0;
        $skippedRows = 0;

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

            $headers = str_getcsv($lines[0]);
            if (!$headers) {
                Log::error("Invalid CSV file. No headers found.");
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
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
                Log::error("No valid contact column found. Available headers: " . implode(', ', $headers));
                $this->text->status_id = TextStatus::ERROR;
                $this->text->save();
                return [];
            }

            Log::info("Found phone column at index {$phoneColumnIndex}: {$headers[$phoneColumnIndex]}");

            $currentBatch = [];

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

                $message = $this->replacePlaceholders($this->text->message, $contactData);

                $currentBatch[] = [
                    'phone' => $cleanedPhone,
                    'message' => $message,
                    'row_number' => $i + 1,
                    'original_phone' => $phone
                ];
                $processedRows++;

                if (count($currentBatch) >= $batchSize) {
                    $this->sendSmsToContacts($currentBatch);
                    $currentBatch = [];
                    gc_collect_cycles();
                }
            }

            if (!empty($currentBatch)) {
                $this->sendSmsToContacts($currentBatch);
            }

            Log::info("=== CSV Processing Complete ===");
            Log::info("Total lines in file: " . count($lines));
            Log::info("Successfully processed: {$processedRows}");
            Log::info("Skipped rows: {$skippedRows}");
        } catch (\Exception $e) {
            Log::error("Error processing CSV: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
        }

        return $contacts;
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

    private function sendSmsToContacts(array $contacts)
    {
        if (empty($contacts)) {
            Log::warning("No contacts to send to for text ID: {$this->text->id}");
            return;
        }

        // Deduplicate contacts before processing
        $uniqueContacts = [];
        $seenPhones = [];

        $uniqueContacts[] = $contacts;

        // foreach ($contacts as $contact) {
        //     $phone = $contact['phone'];

        //     $uniqueContacts[] = $contact;
        //     // if (!isset($seenPhones[$phone])) {
        //     //     $seenPhones[$phone] = true;

        //     // }
        // }

        if (count($uniqueContacts) < count($contacts)) {
            Log::info("Removed " . (count($contacts) - count($uniqueContacts)) . " duplicate phone numbers");
        }

        $contacts = $uniqueContacts;

        DB::beginTransaction();

        try {
            //$username = config('services.africastalking.username');
            $username = 'tsggfegfgfevbevb';
            //$apiKey = config('services.africastalking.api_key');
            $apiKey = 'fegfgfevbevb';
            $senderId = config('services.africastalking.sender_id');

            $AT = new AfricasTalking($username, $apiKey);
            $sms = $AT->sms();

            $this->text->status_id = TextStatus::SENDING;
            $this->text->save();

            $queueRecords = [];
            $successCount = 0;
            $failCount = 0;

            $smsBatchSize = 100;
            $contactBatches = array_chunk($contacts, $smsBatchSize);

            foreach ($contactBatches as $batchIndex => $contactBatch) {
                // Filter out already processed numbers
                // $phones = array_column($contactBatch, 'phone');
                // $existing = Queue::where('text_id', $this->text->id)
                // ->whereIn('recipient', $phones)
                // ->pluck('recipient')
                // ->toArray();

                $existing = [];

                $filteredBatch = array_filter($contactBatch, function ($contact) use ($existing) {
                    return !in_array($contact['phone'], $existing);
                });

                if (count($filteredBatch) < count($contactBatch)) {
                    Log::info("Skipping " . count($contactBatch) - count($filteredBatch) . " already processed numbers");
                }

                if (empty($filteredBatch)) {
                    continue;
                }

                $messageGroups = [];
                foreach ($filteredBatch as $contact) {
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

                        if (isset($responseArray['data']['SMSMessageData']['Recipients'])) {
                            foreach ($responseArray['data']['SMSMessageData']['Recipients'] as $recipient) {
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
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to send SMS batch: " . $e->getMessage());
                        $failCount += count($phones);

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

                // Insert records in batches
                if (!empty($queueRecords)) {
                    Queue::insert($queueRecords);
                    $queueRecords = [];
                }

                if (count($contactBatches) > 10) {
                    usleep(200000);
                }
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
            DB::commit();

            Log::info("SMS Campaign completed - Success: {$successCount}, Failed: {$failCount}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to send SMS: " . $e->getMessage());
            $this->text->status_id = TextStatus::ERROR;
            $this->text->save();
            throw $e;
        }
    }
}
