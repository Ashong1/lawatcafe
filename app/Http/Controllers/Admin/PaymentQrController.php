<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EwalletPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentQrController extends Controller
{
    public function update(Request $request, string $wallet, EwalletPaymentService $wallets)
    {
        abort_unless(array_key_exists($wallet, EwalletPaymentService::WALLETS), 404);
        $label = EwalletPaymentService::WALLETS[$wallet];
        $hasQr = collect($wallets->all())->firstWhere('key', $wallet)['qr'] !== null;

        $request->validate([
            'qr' => [Rule::requiredIf(! $hasQr), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'account_name' => 'nullable|string|max:80',
        ], [
            'qr.required' => "Choose the picture of your {$label} QR code.",
            'qr.image' => 'That file is not a picture.',
            'qr.max' => 'That picture is over 2 MB.',
            'qr.uploaded' => 'That picture is over 2 MB.',
        ]);

        $wallets->save($wallet, $request->file('qr'), $request->input('account_name'));

        return back()->with('success', "{$label} is ready. The register now offers it as a way to pay.");
    }

    public function destroy(string $wallet, EwalletPaymentService $wallets)
    {
        abort_unless(array_key_exists($wallet, EwalletPaymentService::WALLETS), 404);

        $wallets->remove($wallet);

        return back()->with('success', EwalletPaymentService::WALLETS[$wallet].' is off. The register no longer offers it.');
    }
}
