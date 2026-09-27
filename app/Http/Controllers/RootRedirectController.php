<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * "/" on the guest hostname opens the portal; everywhere else, staff login.
 *
 * wifi.lawatkape.lab is the address guests are told to type into their real
 * browser to check their remaining time (the sign-in window cannot open the
 * browser for them — see portal/success), so it must not land them on the
 * staff sign-in.
 */
class RootRedirectController extends Controller
{
    public function __invoke(Request $request)
    {
        return str_starts_with($request->getHost(), 'wifi.')
            ? redirect()->route('portal.index')
            : redirect('/login');
    }
}
