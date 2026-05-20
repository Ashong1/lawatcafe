<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;
use App\Models\EwalletPayment;
use Webklex\PHPIMAP\ClientManager;
use Carbon\Carbon;

class ScanImapReceipts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:scan-receipts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan Gmail IMAP for e-wallet payment receipt emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting IMAP Scan...");

        // Fetch all needed settings at once to avoid multiple DB hits
        $configKeys = ['imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password'];
        $settings = [];
        foreach ($configKeys as $key) {
            $settings[$key] = Setting::get($key);
        }

        $host = $settings['imap_host'] ?? 'imap.gmail.com';
        $port = $settings['imap_port'] ?? '993';
        $encryption = $settings['imap_encryption'] ?? 'ssl';
        $username = $settings['imap_username'];
        $password = $settings['imap_password'];

        if (!$username || !$password) {
            $this->error("IMAP credentials (username/password) not configured in settings.");
            return;
        }

        $cm = new ClientManager();
        $client = $cm->make([
            'host'          => $host,
            'port'          => $port,
            'encryption'    => $encryption,
            'validate_cert' => true,
            'username'      => $username,
            'password'      => $password,
            'protocol'      => 'imap'
        ]);

        try {
            $client->connect();
            $this->info("Connected to IMAP server.");

            $folder = $client->getFolder('INBOX');
            
            // Search for unread emails from GCash or similar
            $messages = $folder->query()->unseen()->get();

            $this->info("Found " . $messages->count() . " unseen messages.");

            $ai = new \App\Services\AIService();

            foreach ($messages as $message) {
                try {
                    $subject = $message->getSubject();
                    $body = $message->getTextBody() ?: $message->getHTMLBody(true);
                    $from = $message->getFrom()[0]->mail;

                    $this->info("Processing email from: $from - Subject: $subject");

                    $refNumber = null;
                    $amount = null;

                    // Match Reference Number
                    if (preg_match('/Ref\.?\s*No\.?[:\s]*(\d+)/i', $body, $matches)) {
                        $refNumber = $matches[1];
                    }

                    // Match Amount
                    if (preg_match('/PHP\s*([\d,]+\.\d{2})/i', $body, $matches)) {
                        $amount = str_replace(',', '', $matches[1]);
                    } elseif (preg_match('/Amount[:\s]*([\d,]+\.\d{2})/i', $body, $matches)) {
                        $amount = str_replace(',', '', $matches[1]);
                    }

                    if (!$refNumber || !$amount) {
                        $this->info("Regex failed, falling back to AI extraction...");
                        $aiResult = $ai->extractPaymentDetails($body);
                        
                        if ($aiResult) {
                            $refNumber = $aiResult['reference_number'];
                            $amount = $aiResult['amount'];
                            $this->info("Extracted (AI): Ref #$refNumber, Amount: $amount");
                        }
                    } else {
                        $this->info("Extracted (Regex): Ref #$refNumber, Amount: $amount");
                    }

                    if ($refNumber && $amount) {
                        \Illuminate\Support\Facades\DB::transaction(function() use ($refNumber, $amount, $from, $message) {
                            EwalletPayment::updateOrCreate(
                                ['reference_number' => $refNumber],
                                [
                                    'amount' => $amount,
                                    'sender_details' => $from,
                                    'email_date' => $message->getDate()[0] ?? now(),
                                    'is_used' => false
                                ]
                            );
                            // Mark as seen so we don't process it again
                            $message->setFlag('Seen');
                        });
                    } else {
                        $this->warn("Could not extract payment details. Leaving unread.");
                    }
                } catch (\Exception $e) {
                    $this->error("Error processing message: " . $e->getMessage());
                }
            }

            $client->disconnect();
            $this->info("IMAP Scan completed.");

        } catch (\Exception $e) {
            $this->error("IMAP Error: " . $e->getMessage());
        }
    }
}
