<?php

namespace App\Http\Controllers;

use App\Services\SystemPowerService;
use Illuminate\Http\Request;

class SystemPowerController extends Controller
{
    public function __construct(private SystemPowerService $power) {}

    public function index()
    {
        return view('admin.system-power', [
            'serverReady' => $this->power->serverControlReady(),
            'appDown' => app()->isDownForMaintenance(),
        ]);
    }

    public function act(Request $request, string $action)
    {
        $rules = ['password' => ['required', 'current_password']];
        // Turning the server off can't be undone from here, so it takes a typed word as well.
        if ($action === 'shutdown-server') {
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
}
