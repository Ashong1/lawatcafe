<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Emails the same choose-your-password link staff get when invited.
     * The reply is the same whether or not the account exists.
     */
    public function store(Request $request, AccountInviteService $invites): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'max:255'],
        ]);

        $login = trim($request->string('email'));
        $user = User::where(str_contains($login, '@') ? 'email' : 'username', Str::lower($login))->first();

        if ($user && ! $user->isDeactivated()) {
            $invites->sendLink($user, $request->getSchemeAndHttpHost());
        }

        return back()->with('status', 'If that account exists, a link to choose a new password is on its way to its email.');
    }
}
