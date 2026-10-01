<?php

namespace App\Mail;

use App\Mail\Concerns\WaitsForInternet;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountSetupLink extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, WaitsForInternet;

    public function __construct(
        public User $user,
        public string $link,
        public int $days,
    ) {}

    public function build()
    {
        $subject = $this->user->isPendingInvite()
            ? "You're invited to Lawa't Kape"
            : "Set a new password for Lawa't Kape";

        return $this->subject($subject)->text('emails.account-setup-link');
    }
}
