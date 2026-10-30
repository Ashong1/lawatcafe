<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * The Proxmox machine this app's container runs on (owner, Oct 30: "shut down
 * the proxmox itself"). The container can't power off its host, so this asks
 * the Proxmox API with a token the super admin enters on the Security page.
 * The token needs only Sys.Audit (Test) and Sys.PowerMgmt (Shut down).
 */
class ProxmoxService
{
    /** Proxmox's own root certificate (/etc/pve/pve-root-ca.pem), so its self-signed HTTPS can still be checked. */
    public function caFile(): string
    {
        return storage_path('app/proxmox-ca.pem');
    }

    public function configured(): bool
    {
        return $this->url() && $this->tokenId() && $this->secret();
    }

    /** The saved connection, else the PVE_* values in .env. */
    public function url(): ?string
    {
        $host = config('services.proxmox.host');

        return Setting::get('proxmox_url') ?: ($host ? (str_contains($host, '://') ? $host : "https://{$host}:8006") : null);
    }

    public function tokenId(): ?string
    {
        $env = config('services.proxmox');

        return Setting::get('proxmox_token_id') ?: (! empty($env['user']) && ! empty($env['token_id']) ? $env['user'].'!'.$env['token_id'] : null);
    }

    private function secret(): ?string
    {
        $saved = Setting::get('proxmox_token');

        return $saved ? Crypt::decryptString($saved) : config('services.proxmox.token_secret');
    }

    /** The saved node name, else the machine's own (a single-node Proxmox lists just itself). */
    public function node(): string
    {
        return Setting::get('proxmox_node') ?: Cache::remember('proxmox_node', 86400, fn () => (string) $this->request()->get('/api2/json/nodes')->throw()->json('data.0.node'));
    }

    /** Reads the machine's status; never changes anything. Returns a plain sentence. */
    public function check(): string
    {
        $data = $this->request()->get($this->statusPath())->throw()->json('data', []);

        return 'Connected to '.$this->node().(isset($data['pveversion']) ? ' ('.$data['pveversion'].')' : '').
            (isset($data['uptime']) ? ', up for '.round($data['uptime'] / 3600, 1).' hours.' : '.');
    }

    public function shutDown(User $by): void
    {
        ActivityLog::record('host_shutting_down', $by, $this->node());
        $this->request()->asForm()->post($this->statusPath(), ['command' => 'shutdown'])->throw();
    }

    private function statusPath(): string
    {
        return '/api2/json/nodes/'.rawurlencode($this->node()).'/status';
    }

    private function request(): PendingRequest
    {
        $url = (string) $this->url();
        $options = [];
        if (is_file($this->caFile())) {
            $options['verify'] = $this->caFile();
            // Proxmox's certificate names the node, not every address it has (pve02 here
            // lists 192.168.0.3, not .250), so reach the IP under the node's name.
            $parts = parse_url($url);
            $name = Setting::get('proxmox_node');
            if ($name && filter_var($parts['host'] ?? '', FILTER_VALIDATE_IP)) {
                $port = $parts['port'] ?? 8006;
                $options['curl'] = [CURLOPT_RESOLVE => ["{$name}:{$port}:{$parts['host']}"]];
                $url = "https://{$name}:{$port}";
            }
        }

        return Http::baseUrl($url)
            ->withHeaders(['Authorization' => 'PVEAPIToken='.$this->tokenId().'='.$this->secret()])
            ->withOptions($options)
            ->timeout(10);
    }
}
