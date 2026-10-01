<?php

namespace App\Mail;

use App\Models\Shift;
use App\Mail\Concerns\WaitsForInternet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShiftAuditResult extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, WaitsForInternet;

    public function __construct(
        public Shift $shift,
        public array $summary,
        public float $variance,
        public ?string $aiSummary,
    ) {}

    public function build()
    {
        return $this->subject('Shift Audit — Shortage of ₱'.number_format(abs($this->variance), 2))
            ->text('emails.shift-audit-result');
    }
}
