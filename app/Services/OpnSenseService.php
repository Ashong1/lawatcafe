<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpnSenseService
{
    protected $baseUrl;
    protected $apiKey;
    protected $apiSecret;
    protected $zone;

    public function __construct()
    {
        $this->baseUrl = config('services.opnsense.url');
        $this->apiKey = config('services.opnsense.key');
        $this->apiSecret = config('services.opnsense.secret');
        $this->zone = config('services.opnsense.zone', 0);
    }

    /**
     * Authorize a device on the OPNsense Captive Portal.
     *
     * @param string $ip The client IP address.
     * @param string $username A identifier for the session (e.g. Voucher code).
     * @return bool
     */
    public function authorizeDevice($ip, $voucherCode = 'guest')
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning("OPNsense: API credentials not configured.");
            return false;
        }

        try {
            // Use zone from session if available (captured from redirect), otherwise use config default
            $zone = session('zone', $this->zone);
            
            // Following the 'Direct Post' approach via the session/connect API
            // This endpoint is used to manually authorize an IP session in a specific zone.
            $url = "{$this->baseUrl}/api/captiveportal/session/connect/{$zone}/";
            
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withoutVerifying() 
                ->post($url, [
                    'user' => 'laravel_guest', // The dedicated user created in OPNsense
                    'password' => 'Laravel123',
                    'ip' => $ip,
                ]);

            $data = $response->json();

            if ($response->successful()) {
                // If we get a sessionId or a successful state, the device is authorized
                if (isset($data['sessionId']) || (isset($data['clientState']) && in_array($data['clientState'], ['AUTHORIZED', 'CONNECTED', 'ALREADY_AUTHORIZED']))) {
                    Log::info("OPNsense: Successfully authorized IP {$ip} via session/connect. Session: " . ($data['sessionId'] ?? 'N/A'));
                    return true;
                }
            }

            Log::error("OPNsense: Failed to authorize IP {$ip} via session/connect", [
                'status' => $response->status(),
                'response' => $data
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error("OPNsense: Exception during authorization: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the list of active sessions from OPNsense.
     *
     * @return array
     */
    public function listSessions()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        try {
            $zone = session('zone', $this->zone);
            $url = "{$this->baseUrl}/api/captiveportal/session/list/{$zone}/";
            
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withoutVerifying()
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        }
    }

    /**
     * Terminate an active session on OPNsense.
     *
     * @param string $sessionId
     * @return bool
     */
    public function disconnectDevice($sessionId)
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return false;
        }

        try {
            $zone = session('zone', $this->zone);
            $url = "{$this->baseUrl}/api/captiveportal/session/disconnect/{$zone}/";
            
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withoutVerifying()
                ->post($url, [
                    'sessionId' => $sessionId
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception disconnecting session {$sessionId}: " . $e->getMessage());
            return false;
        }
    }
}
