<?php

namespace App\Mail;

use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShiftAuditResult extends Mailable
{
    use Queueable, SerializesModels;

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
