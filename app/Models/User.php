<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'password_set_at',
        'deactivated_at',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /** Exactly the developer/system account — the single fixed super_admin. */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /** Admin-tier or higher — the check ~20 call sites want when they mean "the same access admin has". */
    public function isAdminOrAbove(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    /**
     * The role as it should be shown to a human.
     *
     * Lives here rather than in each layout because the two layouts used to
     * hardcode it from their own filename — admin.blade.php said "Admin
     * Status:" for everyone it rendered, which meant the super_admin account
     * was labelled a plain admin. Anything displaying a role reads this, so
     * adding a role means editing one match arm.
     */
    public function roleLabel(): string
    {
        return match ($this->role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'staff' => 'Staff',
            // Deliberately not the raw column: an unrecognised role is a data
            // problem, and echoing it verbatim invites it to look official.
            default => 'Unknown Role',
        };
    }

    /** Invited but hasn't chosen a password yet, so can't sign in. */
    public function isPendingInvite(): bool
    {
        return $this->password_set_at === null;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Records that point at this account. Sales and shifts refuse a delete;
     * cash movements, wastage and deliveries would be deleted with it.
     */
    public function hasWorkHistory(): bool
    {
        foreach (['sales' => 'user_id', 'shifts' => 'user_id', 'shift_transactions' => 'user_id', 'wastages' => 'user_id',
            'ingredient_deliveries' => 'user_id', 'sale_void_requests' => 'requested_by'] as $table => $column) {
            if (DB::table($table)->where($column, $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
