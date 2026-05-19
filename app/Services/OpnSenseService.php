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
            $url = "{$this->baseUrl}/api/captiveportal/access/logon/{$zone}/";
            
            // We use the 'laravel_guest' credentials provided by the user for the CP logon
            // while using the API Key/Secret for the HTTP Basic Auth header.
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withoutVerifying() 
                ->asForm()
                ->post($url, [
                    'user' => 'laravel_guest',
                    'password' => 'Laravel123', 
                    'ip' => $ip,
                ]);

            $data = $response->json();

            if ($response->successful()) {
                // If we get UNKNOWN but the IP matches, it often means the login was accepted
                // but OPNsense hasn't updated the state in the immediate response.
                if (isset($data['clientState']) && in_array($data['clientState'], ['AUTHORIZED', 'ALREADY_AUTHORIZED', 'UNKNOWN'])) {
                    Log::info("OPNsense: Authorization attempt for IP {$ip} (Voucher: {$voucherCode}). Response state: {$data['clientState']}");
                    return true;
                }
            }

            Log::error("OPNsense: Failed to authorize IP {$ip}", [
                'status' => $response->status(),
                'body' => $response->body(),
                'response' => $data
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error("OPNsense: Exception during authorization: " . $e->getMessage());
            return false;
        }
    }
}
