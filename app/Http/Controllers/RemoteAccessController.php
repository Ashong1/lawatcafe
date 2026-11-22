<?php

namespace App\Http\Controllers;

use App\Services\RemoteAccessService;
use Illuminate\Http\Request;

class RemoteAccessController extends Controller
{
    public function on(Request $request, RemoteAccessService $remote)
    {
        if ($error = $remote->turnOn($request->user())) {
            return back()->with('error', $error);
        }

        return back()->with('success', "Remote access is on: {$remote->url()}. It stays on until you turn it off.");
    }

    public function off(Request $request, RemoteAccessService $remote)
    {
        $remote->turnOff($request->user());

        return back()->with('success', 'Remote access is off. The app is only reachable inside the shop.');
    }

    /** Super admin only (see routes): let staff accounts in through the remote link, or stop them. */
    public function staff(Request $request, RemoteAccessService $remote)
    {
        $allowed = $request->boolean('allowed');
        $remote->setStaffAllowed($allowed, $request->user());

        return back()->with('success', $allowed
            ? 'Staff can now sign in through the remote link.'
            : 'Staff can no longer sign in through the remote link. Anyone signed in there is signed out on their next click.');
    }
}
