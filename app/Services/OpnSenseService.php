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
        // Prioritize database setting, fallback to config
        $this->zone = \App\Models\Setting::get('opnsense_zone', config('services.opnsense.zone', 0));
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
            
            Log::info("OPNsense Request URL: " . $url);
            Log::info("OPNsense API Key Length: " . strlen($this->apiKey));
            
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withoutVerifying() 
                ->post($url, [
                    'user' => config('services.opnsense.guest_user'), 
                    'password' => config('services.opnsense.guest_pass'),
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
     * Get the ARP table from OPNsense.
     *
     * @return array
     */
    public function getArpTable()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        try {
            $url = "{$this->baseUrl}/api/diagnostics/interface/getArp";
            
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withoutVerifying()
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception fetching ARP table: " . $e->getMessage());
            return [];
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
                $data = $response->json();
                $rows = $data['rows'] ?? $data['sessions'] ?? $data;
                
                if (!is_array($rows)) return [];

                return array_map(function($session) {
                    // Normalize byte keys
                    if (isset($session['bytes_in']) && !isset($session['bytes_received'])) {
                        $session['bytes_received'] = $session['bytes_in'];
                    }
                    if (isset($session['bytes_out']) && !isset($session['bytes_sent'])) {
                        $session['bytes_sent'] = $session['bytes_out'];
                    }
                    return $session;
                }, $rows);
            }

            return [];
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception fetching sessions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get the gateway status from OPNsense.
     */
    public function getGatewayStatus()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        try {
            $url = "{$this->baseUrl}/api/diagnostics/gateway/status";
            
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withoutVerifying()
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception fetching gateway status: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get interface statistics from OPNsense.
     */
    public function getInterfaceStats()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        try {
            $url = "{$this->baseUrl}/api/diagnostics/interface/getInterfaceStatistics";
            
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withoutVerifying()
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $stats = $data['statistics'] ?? [];
                
                $normalized = [];
                foreach ($stats as $key => $values) {
                    // Extract the human name (WAN, LAN) or the interface name (vtnet0)
                    $name = 'unknown';
                    
                    // Improved regex to handle nested brackets [[WAN]] -> wan
                    if (preg_match('/\[+([a-zA-Z0-9]+)\]+/', $key, $matches)) {
                        $name = strtolower($matches[1]);
                    } elseif (preg_match('/\(([^\)]+)\)/', $key, $matches)) {
                        $name = strtolower($matches[1]);
                    }

                    $inBytes = (int) ($values['received-bytes'] ?? 0);
                    $outBytes = (int) ($values['sent-bytes'] ?? 0);

                    // We want the aggregate entry for each interface.
                    // Usually, this is the one with the highest traffic count.
                    if (!isset($normalized[$name]) || ($inBytes + $outBytes) > ($normalized[$name]['inbytes'] + $normalized[$name]['outbytes'])) {
                        $normalized[$name] = [
                            'inbytes' => $inBytes,
                            'outbytes' => $outBytes,
                            'inpackets' => (int) ($values['received-packets'] ?? 0),
                            'outpackets' => (int) ($values['sent-packets'] ?? 0),
                            'errors' => (int) (($values['received-errors'] ?? 0) + ($values['send-errors'] ?? 0)),
                        ];
                    }
                }
                return $normalized;
            }

            return [];
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception fetching interface stats: " . $e->getMessage());
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
            Log::warning("OPNsense Disconnect: API credentials missing.");
            return false;
        }

        try {
            $zone = session('zone', $this->zone);
            $url = "{$this->baseUrl}/api/captiveportal/session/disconnect/{$zone}/";
            
            // We send both camelCase and lowercase to be safe across OPNsense versions
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withoutVerifying()
                ->post($url, [
                    'sessionId' => $sessionId,
                    'sessionid' => $sessionId 
                ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info("OPNsense: Disconnect request sent for session {$sessionId}. Response: " . json_encode($data));
                
                // If the session was deleted or returned successfully
                return true;
            }

            Log::error("OPNsense: Failed to disconnect session {$sessionId}", [
                'status' => $response->status(),
                'response' => $data
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception disconnecting session {$sessionId}: " . $e->getMessage());
            return false;
        }
    }
}
