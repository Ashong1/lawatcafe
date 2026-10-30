<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Who signed in, who failed, and who changed accounts or made backups: the
 * security trail the owner reads on Settings → Security. Rows are only
 * ever added; nothing in the app edits or deletes them.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'signed_in' => 'Signed in',
        'signed_out' => 'Signed out',
        'sign_in_failed' => 'Wrong username or password',
        'locked_out' => 'Locked out (too many tries)',
        'password_reset' => 'Reset their password',
        'account_added' => 'Added an account',
        'account_changed' => 'Changed an account',
        'account_removed' => 'Archived an account',
        'account_restored' => 'Restored an account',
        'backup_made' => 'Database backup made',
        'backup_failed' => 'Database backup failed',
        'backup_restored' => 'Restored a database backup',
        'app_restarted' => 'Restarted the web app',
        'app_shut_down' => 'Shut down the web app',
        'app_started' => 'Turned the web app back on',
        'server_restarting' => 'Restarted the server',
        'server_shutting_down' => 'Shut down the server',
        'host_shutting_down' => 'Shut down the whole Proxmox machine',
        'remote_access_on' => 'Turned on remote access',
        'remote_access_off' => 'Turned off remote access',
    ];

    /** Kinds of entry, so sign-ins, accounts, backups and system power can be read apart (evaluators, Oct 28). */
    public const CATEGORIES = [
        'Sign-ins' => ['signed_in', 'signed_out', 'sign_in_failed', 'locked_out', 'password_reset'],
        'Accounts' => ['account_added', 'account_changed', 'account_removed', 'account_restored'],
        'Backups' => ['backup_made', 'backup_failed', 'backup_restored'],
        'System' => ['app_restarted', 'app_shut_down', 'app_started', 'server_restarting', 'server_shutting_down', 'host_shutting_down', 'remote_access_on', 'remote_access_off'],
    ];

    protected $fillable = ['user_id', 'event', 'detail', 'ip_address', 'user_agent'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $event, ?User $user = null, ?string $detail = null): void
    {
        $request = app()->runningInConsole() ? null : request();

        static::create([
            'user_id' => $user?->id,
            'event' => $event,
            'detail' => $detail ? Str::limit($detail, 250) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250) : null,
        ]);
    }

    public function label(): string
    {
        return self::LABELS[$this->event] ?? Str::headline($this->event);
    }
}
