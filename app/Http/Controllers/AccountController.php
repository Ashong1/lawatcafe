<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    // 1. View all accounts
    public function index()
    {
        // Fetch all users (you can order them by newest first)
        $users = User::latest()->get();
        return view('accounts.index', compact('users'));
    }

    // 2. Create a new staff account
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,staff',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('accounts.index')->with('success', 'Staff account created successfully!');
    }

    // 3. Update an existing account
    public function update(Request $request, User $account)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($account->id)],
            'password' => 'nullable|string|min:8', // Password is optional when editing
            'role' => 'required|in:admin,staff',
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

        $account->delete();
        return redirect()->route('accounts.index')->with('success', 'Account deleted successfully!');
    }
}