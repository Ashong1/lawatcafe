<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ProxmoxService;
use App\Services\SystemPowerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SystemPowerController extends Controller
{
    public function __construct(private SystemPowerService $power) {}

    public function act(Request $request, string $action)
    {
        // The whole server is the system administrator's; the owner can restart the web app.
        abort_if((str_ends_with($action, '-server') || $action === 'shutdown-host') && ! $request->user()->isSuperAdmin(), 403);

        $rules = ['password' => ['required', 'current_password']];
        // Turning the server off can't be undone from here, so it takes a typed word as well.
        if (in_array($action, ['shutdown-server', 'shutdown-host'], true)) {
            $rules['confirm'] = ['required', 'in:SHUT DOWN'];
        }
        $request->validate($rules, [
            'password.current_password' => 'That password is wrong, so nothing was done.',
            'confirm.in' => 'Type SHUT DOWN in capitals to turn the server off.',
        ]);

        if (in_array($action, ['restart-server', 'shutdown-server'], true) && ! $this->power->serverControlReady()) {
            return back()->with('error', 'The server needs a one-time setup before it can be restarted or shut down from here.');
        }

        $user = $request->user();

        if ($action === 'shutdown-host') {
            $proxmox = app(ProxmoxService::class);
            if (! $proxmox->configured()) {
                return back()->with('error', 'Connect to Proxmox first (Security page), then try again.');
            }
            try {
                $proxmox->shutDown($user);
            } catch (\Throwable $e) {
                return back()->with('error', 'Proxmox did not accept the shutdown: '.$this->reason($e));
            }

            return back()->with('success', 'The whole machine is shutting down. Wi-Fi and internet go off; turn it back on with its power button.');
        }

        return match ($action) {
            'restart-app' => tap(back()->with('success', 'The web app is restarting. Pages may pause for a few seconds.'), fn () => $this->power->restartApp($user)),
            'shutdown-app' => redirect('/'.$this->power->shutDownApp($user)),
            'restart-server' => tap(back()->with('success', 'The server is restarting. Everything will be back in about a minute; refresh then.'), fn () => $this->power->restartServer($user)),
            'shutdown-server' => tap(back()->with('success', 'The server is shutting down. Start it again from Proxmox.'), fn () => $this->power->shutDownServer($user)),
        };
    }

    public function start(Request $request)
    {
        $this->power->startApp($request->user());

        return back()->with('success', 'The web app is back on for everyone.');
    }

    public function saveProxmox(Request $request, ProxmoxService $proxmox)
    {
        $data = $request->validate([
            'url' => ['required', 'url:https', 'max:255'],
            'node' => ['required', 'regex:/^[A-Za-z0-9.-]+$/', 'max:64'],
            'token_id' => ['required', 'regex:/^[^\s=]+@[^\s=]+![^\s=]+$/', 'max:128'],
            'token' => ['nullable', 'string', 'max:128'],
            'certificate' => ['nullable', 'string', 'max:10000', 'regex:/-----BEGIN CERTIFICATE-----/'],
        ], ['token_id.regex' => 'The token ID looks like root@pam!lawatkape.', 'certificate.regex' => 'Paste the whole certificate, from -----BEGIN CERTIFICATE----- to the end.']);

        Setting::set('proxmox_url', rtrim($data['url'], '/'));
        Setting::set('proxmox_node', $data['node']);
        Setting::set('proxmox_token_id', $data['token_id']);
        if (filled($data['token'] ?? null)) {
            Setting::set('proxmox_token', Crypt::encryptString(trim($data['token'])));
        }
        if (filled($data['certificate'] ?? null)) {
            file_put_contents($proxmox->caFile(), trim($data['certificate'])."\n");
        }

        return back()->with('success', 'Proxmox connection saved. Tap Test connection to check it.');
    }

    public function testProxmox(ProxmoxService $proxmox)
    {
        if (! $proxmox->configured()) {
            return back()->with('error', 'Fill in the Proxmox connection first.');
        }
        try {
            return back()->with('success', $proxmox->check());
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reach Proxmox: '.$this->reason($e));
        }
    }

    private function reason(\Throwable $e): string
    {
        $status = $e instanceof \Illuminate\Http\Client\RequestException ? $e->response->status() : null;

        return match (true) {
            $status === 401 => 'the token ID or secret is wrong.',
            $status === 403 => 'the token is missing a permission (it needs Sys.Audit and Sys.PowerMgmt).',
            str_contains($e->getMessage(), 'SSL') || str_contains($e->getMessage(), 'certificate') => 'its certificate could not be checked. Paste Proxmox\'s root certificate (pve-root-ca.pem).',
            default => 'the address or machine name is wrong, or it is off.',
        };
    }
}
