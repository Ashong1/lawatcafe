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
}
