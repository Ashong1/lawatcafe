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

        // Simplified: Defaulting to Gmail IMAP settings as requested
        $host = Setting::get('imap_host', 'imap.gmail.com');
        $port = Setting::get('imap_port', '993');
        $encryption = Setting::get('imap_encryption', 'ssl');
        $username = Setting::get('imap_username');
        $password = Setting::get('imap_password');

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
            // We search for UNSEEN messages
            $messages = $folder->query()->unseen()->get();

            $this->info("Found " . $messages->count() . " unseen messages.");

            foreach ($messages as $message) {
                $subject = $message->getSubject();
                $body = $message->getTextBody() ?: $message->getHTMLBody(true);
                $from = $message->getFrom()[0]->mail;

                $this->info("Processing email from: $from - Subject: $subject");

                // GCash Parsing Logic
                // Pattern for Reference Number (Usually 9-13 digits)
                // Pattern for Amount (e.g. PHP 100.00 or 100.00)
                
                $refNumber = null;
                $amount = null;

                // Match Reference Number: "Ref. No. 1234..." or "Ref No: 1234..."
                if (preg_match('/Ref\.?\s*No\.?[:\s]*(\d+)/i', $body, $matches)) {
                    $refNumber = $matches[1];
                }

                // Match Amount: "Amount: PHP 100.00" or "Sent PHP 100.00"
                if (preg_match('/PHP\s*([\d,]+\.\d{2})/i', $body, $matches)) {
                    $amount = str_replace(',', '', $matches[1]);
                } elseif (preg_match('/Amount[:\s]*([\d,]+\.\d{2})/i', $body, $matches)) {
                    $amount = str_replace(',', '', $matches[1]);
                }

                if ($refNumber && $amount) {
                    $this->info("Extracted: Ref #$refNumber, Amount: $amount");

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
                } else {
                    $this->warn("Could not extract payment details from this email.");
                    // Optional: mark as seen anyway or leave unread to manual review
                    // $message->setFlag('Seen'); 
                }
            }

            $client->disconnect();
            $this->info("IMAP Scan completed.");

        } catch (\Exception $e) {
            $this->error("IMAP Error: " . $e->getMessage());
        }
    }
}
