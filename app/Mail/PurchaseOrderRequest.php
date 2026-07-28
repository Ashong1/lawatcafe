<?php

namespace App\Mail;

use App\Models\PurchaseOrderDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrderDraft $draft)
    {
    }

    public function build()
    {
        return $this->subject("Purchase Order — {$this->draft->ingredient->name}")
            ->text('emails.purchase-order-request');
    }
}
