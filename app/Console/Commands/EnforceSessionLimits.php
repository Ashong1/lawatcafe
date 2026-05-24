<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use App\Services\OpnSenseService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnforceSessionLimits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'network:enforce-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan active OPNsense sessions and disconnect those with expired vouchers.';

    /**
     * Execute the console command.
     */
    public function handle(OpnSenseService $opnsense)
    {
        $this->info("Fetching active sessions from OPNsense...");
        $sessions = $opnsense->listSessions();
        
        if (empty($sessions)) {
            $this->warn("No active sessions found.");
            return;
        }

        $this->info("Scanning " . count($sessions) . " sessions for expiration...");

        foreach ($sessions as $session) {
            $ip = str_replace('/32', '', $session['ipAddress'] ?? '');
            $mac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $session['macAddress'] ?? ''));
            $sessionId = $session['sessionId'] ?? null;

            if (!$sessionId) continue;

            // Find matching used voucher
            // We search for used vouchers that haven't been 'purged' yet
            $voucher = Voucher::where('is_used', true)
                ->where(function($query) use ($ip, $mac) {
                    $query->where('ip_address', $ip);
                    if (!empty($mac)) {
                        $query->orWhere('mac_address', $mac);
                    }
                })
                ->orderBy('used_at', 'desc')
                ->first();

            if (!$voucher) {
                $this->line(" - Session {$ip}: No matching voucher found. Skipping.");
                continue;
            }

            // Calculate expiration
            $expirationTime = $voucher->used_at->addMinutes($voucher->duration_minutes);
            
            if (now()->greaterThan($expirationTime)) {
                $this->warn(" - Session {$ip}: EXPIRED (Allotted: {$voucher->duration_minutes}m). Disconnecting...");
                
                $disconnected = $opnsense->disconnectDevice($sessionId);
                
                if ($disconnected) {
                    $this->info("   [SUCCESS] Device kicked.");
                    Log::info("EnforceSessions: Disconnected {$ip} (Voucher: {$voucher->code}) due to expiration.");
                } else {
                    $this->error("   [FAILED] Could not kick device.");
                }
            } else {
                $remaining = now()->diffInMinutes($expirationTime, false);
                $this->line(" - Session {$ip}: Valid. {$remaining}m remaining.");
            }
        }

        $this->info("Session enforcement complete.");
    }
}
