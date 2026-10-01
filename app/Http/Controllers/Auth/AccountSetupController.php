<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Where an emailed invite or new-password link lands: the person chooses
 * their password and is signed in.
 */
class AccountSetupController extends Controller
{
    public function __construct(private AccountInviteService $invites) {}

    public function show(Request $request, User $user): View
    {
        return view('auth.account-setup', [
            'user' => $user,
            'state' => $this->state($request, $user),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        if ($this->state($request, $user) !== 'ok') {
            return redirect()->to($request->fullUrl());
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $this->invites->setPassword($user, $request->input('password'));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->isAdminOrAbove() ? '/dashboard' : '/staff-dashboard')
            ->with('success', "Welcome, {$user->name}! Your password is set. Next time, sign in with ".($user->username ? "username {$user->username}" : $user->email).'.');
    }

    /** ok | expired | used | signed_in_as_someone_else */
    private function state(Request $request, User $user): string
    {
        if (! $request->hasValidRelativeSignature() || $user->isDeactivated()) {
            return 'expired';
        }
        if (! $this->invites->linkIsCurrent($user, $request->query('v'))) {
            return 'used';
        }
        if (Auth::check() && Auth::id() !== $user->id) {
            return 'signed_in_as_someone_else';
        }

        return 'ok';
    }
}
