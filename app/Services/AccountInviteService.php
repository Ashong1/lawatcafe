<?php

namespace App\Services;

use App\Mail\AccountSetupLink;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * New accounts are created without a usable password; the person gets an
 * email link to choose their own, so the owner never knows it. The same link
 * sets a new password for an existing account.
 */
class AccountInviteService
{
    public const LINK_DAYS = 3;

    public function invite(array $data, string $baseUrl): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => $data['role'],
            // Random and never shown: nobody can sign in until the link is used.
            'password' => Str::random(64),
            'password_set_at' => null,
        ]);

        $this->sendLink($user, $baseUrl);

        return $user;
    }

    public function sendLink(User $user, string $baseUrl): string
    {
        $link = $this->link($user, $baseUrl);

        Mail::to($user->email)->send(new AccountSetupLink($user, $link, self::LINK_DAYS));

        return $link;
    }

    /**
     * The signature is relative so the link works on whichever address the
     * shop reaches the server by. $baseUrl is the address the owner was using
     * when they sent it, which is one the shop's phones can reach; APP_URL
     * goes through the proxy's HTTPS, which phones don't trust.
     */
    public function link(User $user, string $baseUrl): string
    {
        $path = URL::temporarySignedRoute('account.setup', now()->addDays(self::LINK_DAYS), [
            'user' => $user->id,
            'v' => $this->fingerprint($user),
        ], absolute: false);

        return rtrim($baseUrl, '/').$path;
    }

    /** False once the password the link was made for has been changed: each link works once. */
    public function linkIsCurrent(User $user, ?string $fingerprint): bool
    {
        return is_string($fingerprint) && hash_equals($this->fingerprint($user), $fingerprint);
    }

    public function setPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'password_set_at' => now(),
            // They proved the address by opening the link.
            'email_verified_at' => $user->email_verified_at ?? now(),
            'remember_token' => Str::random(60),
        ])->save();
    }

    private function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', $user->password, (string) config('app.key')), 0, 16);
    }
}
