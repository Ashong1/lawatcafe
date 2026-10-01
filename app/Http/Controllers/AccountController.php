<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountInviteService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Roles the current viewer may assign, keyed by value => label.
     * super_admin is never in this list for anyone — it's a single fixed
     * account, not creatable/assignable through this UI at all.
     */
    private function assignableRoles(): array
    {
        $roles = ['staff' => 'Staff / Barista'];

        if (auth()->user()->isSuperAdmin()) {
            $roles['admin'] = 'System Administrator';
        }

        return $roles;
    }

    public function __construct(private AccountInviteService $invites) {}

    public function index(Request $request)
    {
        $query = User::where('role', '!=', 'super_admin');

        // A plain admin manages staff only — admin-level accounts (and the
        // super_admin row above) are super_admin's exclusive responsibility.
        if (! auth()->user()->isSuperAdmin()) {
            $query->where('role', 'staff');
        }

        $removed = (clone $query)->whereNotNull('deactivated_at')->orderByDesc('deactivated_at')->get();
        $users = $query->whereNull('deactivated_at')->latest()->get();
        $assignableRoles = $this->assignableRoles();

        // For "Copy invite link" when the email can't get through (no internet).
        // Only for accounts nobody has signed in to yet, so the owner can't use
        // it to take over an account someone already uses.
        $inviteLinks = $users->filter->isPendingInvite()
            ->mapWithKeys(fn (User $u) => [$u->id => $this->invites->link($u, $request->getSchemeAndHttpHost())]);

        return view('accounts.index', compact('users', 'removed', 'assignableRoles', 'inviteLinks'));
    }

    public function store(Request $request)
    {
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
            'email' => 'required|string|email|max:255|unique:users',
            'role' => ['required', Rule::in(array_keys($this->assignableRoles()))],
        ], [
            'username.regex' => 'Use only small letters, numbers, dots, dashes or underscores — no spaces.',
            'username.unique' => 'Someone already has the username :input. Try adding a number or their last name.',
            'email.unique' => 'This email already has an account. If they were removed, use Restore under "Removed" below.',
        ]);

        $user = $this->invites->invite($data, $request->getSchemeAndHttpHost());

        return redirect()->route('accounts.index')
            ->with('success', "Invite sent to {$user->email}. {$user->name} chooses their own password from the email, then signs in as {$user->username}.");
    }

    public function update(Request $request, User $account)
    {
        $this->authorizeTarget($account);

        if ($request->filled('username')) {
            $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            // Optional: accounts from before usernames sign in by email until one is added.
            'username' => ['nullable', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users')->ignore($account->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($account->id)],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles()))],
        ], [
            'username.regex' => 'Use only small letters, numbers, dots, dashes or underscores — no spaces.',
        ]);

        $account->fill($data)->save();

        return redirect()->route('accounts.index')->with('success', "Saved {$account->name}'s details.");
    }

    /** Re-sends the invite, or for an active account, a link to choose a new password. */
    public function sendLink(Request $request, User $account)
    {
        $this->authorizeTarget($account);

        $this->invites->sendLink($account, $request->getSchemeAndHttpHost());

        return redirect()->route('accounts.index')->with('success', $account->isPendingInvite()
            ? "Invite sent again to {$account->email}."
            : "Sent {$account->name} a link to choose a new password ({$account->email}). Their current password works until they do.");
    }

    public function destroy(User $account)
    {
        if (auth()->id() === $account->id) {
            return redirect()->route('accounts.index')->with('error', 'You cannot delete your own account!');
        }

        $this->authorizeTarget($account);

        // Deleting would fail on their sales and shifts, and take their cash
        // movements, wastage and deliveries with it, so they are switched off.
        if ($account->hasWorkHistory()) {
            $account->forceFill(['deactivated_at' => now()])->save();

            return redirect()->route('accounts.index')->with('success', "Removed {$account->name}. They can no longer sign in; their past sales and shifts stay in the reports.");
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('success', "Removed {$account->name}. They can no longer sign in.");
    }

    public function restore(User $account)
    {
        $this->authorizeTarget($account);

        $account->forceFill(['deactivated_at' => null])->save();

        return redirect()->route('accounts.index')->with('success', "{$account->name} can sign in again with their old password.");
    }

    /**
     * A plain admin may only ever act on staff accounts. Reject direct access
     * to an admin/super_admin-role target (e.g. a crafted PUT/DELETE to a
     * known ID) even though those rows never appear in their list to begin with.
     */
    private function authorizeTarget(User $account): void
    {
        if (! auth()->user()->isSuperAdmin() && $account->role !== 'staff') {
            abort(403, 'Unauthorized access.');
        }
    }
}
