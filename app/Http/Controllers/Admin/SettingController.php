<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'imap_username' => Setting::get('imap_username', ''),
            'imap_password' => Setting::get('imap_password', ''),
            'payment_qr_code' => Setting::get('payment_qr_code', ''),
            'voucher_durations' => Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'),
            'network_ignored_ips' => Setting::get('network_ignored_ips', '192.168.2.251,192.168.2.100,192.168.2.5,192.168.2.4'),
            'opnsense_zone' => Setting::get('opnsense_zone', '0'),
            'low_stock_threshold' => Setting::get('low_stock_threshold', '500'),
        ];

        return view('admin.settings.payment', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'imap_username' => 'required|email',
            'imap_password' => 'required|string',
            'payment_qr_code' => 'nullable|image|max:2048',
            'voucher_durations' => 'required|json',
            'network_ignored_ips' => 'required|string',
            'opnsense_zone' => 'required|string',
            'low_stock_threshold' => 'required|numeric',
        ]);

        Setting::set('imap_username', $request->imap_username);
        Setting::set('imap_password', $request->imap_password);
        Setting::set('voucher_durations', $request->voucher_durations);
        Setting::set('network_ignored_ips', $request->network_ignored_ips);
        Setting::set('opnsense_zone', $request->opnsense_zone);
        Setting::set('low_stock_threshold', $request->low_stock_threshold);

        // Clear dashboard cache
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats');

        if ($request->hasFile('payment_qr_code')) {
            // Delete old QR code if exists
            $oldQr = Setting::get('payment_qr_code');
            if ($oldQr) {
                Storage::disk('public')->delete($oldQr);
            }

            $path = $request->file('payment_qr_code')->store('qrcodes', 'public');
            Setting::set('payment_qr_code', $path);
        }

        return redirect()->back()->with('success', 'System settings updated successfully.');
    }
}
