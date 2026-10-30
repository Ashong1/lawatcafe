<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
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
        return Setting::get('proxmox_url') && Setting::get('proxmox_node') && Setting::get('proxmox_token_id') && Setting::get('proxmox_token');
    }

    /** Reads the machine's status; never changes anything. Returns a plain sentence. */
    public function check(): string
    {
        $data = $this->request()->get($this->statusPath())->throw()->json('data', []);

        return 'Connected to '.Setting::get('proxmox_node').(isset($data['pveversion']) ? ' ('.$data['pveversion'].')' : '').
            (isset($data['uptime']) ? ', up for '.round($data['uptime'] / 3600, 1).' hours.' : '.');
    }

    public function shutDown(User $by): void
    {
        ActivityLog::record('host_shutting_down', $by, Setting::get('proxmox_node'));
        $this->request()->asForm()->post($this->statusPath(), ['command' => 'shutdown'])->throw();
    }

    private function statusPath(): string
    {
        return '/api2/json/nodes/'.rawurlencode((string) Setting::get('proxmox_node')).'/status';
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl((string) Setting::get('proxmox_url'))
            ->withHeaders(['Authorization' => 'PVEAPIToken='.Setting::get('proxmox_token_id').'='.Crypt::decryptString((string) Setting::get('proxmox_token'))])
            ->timeout(10);

        return is_file($this->caFile()) ? $request->withOptions(['verify' => $this->caFile()]) : $request;
    }
}
