<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    // 1. View all accounts
    public function index()
    {
        $query = User::where('role', '!=', 'super_admin');

        // A plain admin manages staff only — admin-level accounts (and the
        // super_admin row above) are super_admin's exclusive responsibility.
        if (! auth()->user()->isSuperAdmin()) {
            $query->where('role', 'staff');
        }

        $users = $query->latest()->get();
        $assignableRoles = $this->assignableRoles();

        return view('accounts.index', compact('users', 'assignableRoles'));
    }

    // 2. Create a new account
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(array_keys($this->assignableRoles()))],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('accounts.index')->with('success', 'Account created successfully!');
    }

    // 3. Update an existing account
    public function update(Request $request, User $account)
    {
        $this->authorizeTarget($account);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($account->id)],
            'password' => 'nullable|string|min:8', // Password is optional when editing
            'role' => ['required', Rule::in(array_keys($this->assignableRoles()))],
        ]);

        $account->name = $request->name;
        $account->email = $request->email;
        $account->role = $request->role;

        // Only change the password if they typed a new one
        if ($request->filled('password')) {
            $account->password = Hash::make($request->password);
        }

        $account->save();

        return redirect()->route('accounts.index')->with('success', 'Account updated successfully!');
    }

    // 4. Delete an account
    public function destroy(User $account)
    {
        // Optional: Prevent the admin from deleting themselves!
        if (auth()->id() === $account->id) {
            return redirect()->route('accounts.index')->with('error', 'You cannot delete your own account!');
        }

        $this->authorizeTarget($account);

        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Account deleted successfully!');
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
