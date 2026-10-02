<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The shop's own receive-money QR codes. The customer scans one with their
 * GCash, Maya or bank app and pays the shop directly; the cashier checks the
 * customer's "sent" screen and types its reference number. Nothing here talks
 * to a payment provider: there is no merchant account to confirm against.
 */
class EwalletPaymentService
{
    /** key => what sales.payment_method stores. */
    public const WALLETS = [
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'qrph' => 'QR Ph',
    ];

    public const HELP = [
        'gcash' => 'GCash app → QR → Receive money (or "My QR"). Save it, then upload it here.',
        'maya' => 'Maya app → Receive money → "My QR". Save it, then upload it here.',
        'qrph' => 'Your bank\'s or e-wallet\'s QR Ph / InstaPay QR. Any GCash, Maya or bank app can pay it.',
    ];

    private const DIR = 'payment-qr';

    /** Every wallet with its setup, for the settings page. */
    public function all(): array
    {
        return collect(self::WALLETS)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'help' => self::HELP[$key],
            'qr' => ($path = Setting::get("payment_qr_{$key}")) ? '/storage/'.$path : null,
            'account_name' => Setting::get("payment_qr_{$key}_name", ''),
        ])->values()->all();
    }

    /** Wallets with a QR uploaded: the register's payment choices besides cash. */
    public function enabled(): array
    {
        return array_values(array_filter($this->all(), fn ($w) => $w['qr'] !== null));
    }

    /** Values accepted for sales.payment_method right now. */
    public function acceptedMethods(): array
    {
        return array_merge(['Cash'], array_column($this->enabled(), 'label'));
    }

    public function save(string $key, ?UploadedFile $qr, ?string $accountName): void
    {
        if ($qr) {
            $old = Setting::get("payment_qr_{$key}");
            Setting::set("payment_qr_{$key}", $qr->storeAs(self::DIR, $key.'-'.Str::random(8).'.'.$qr->guessExtension(), 'public'));
            $this->deleteFile($old);
        }
        Setting::set("payment_qr_{$key}_name", trim((string) $accountName));
    }

    public function remove(string $key): void
    {
        $this->deleteFile(Setting::get("payment_qr_{$key}"));
        Setting::set("payment_qr_{$key}", null);
    }

    private function deleteFile(?string $path): void
    {
        if ($path && str_starts_with($path, self::DIR.'/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
