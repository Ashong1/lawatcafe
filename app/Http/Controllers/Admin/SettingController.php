<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Display Store Preferences.
     */
    public function store()
    {
        $settings = [
            'payment_qr_code' => Setting::get('payment_qr_code', ''),
            'low_stock_threshold' => Setting::get('low_stock_threshold', '500'),
            'store_open_time' => Setting::get('store_open_time', '08:00'),
            'store_close_time' => Setting::get('store_close_time', '22:00'),
            'receipt_header' => Setting::get('receipt_header', 'Thank you for visiting Lawa\'t Kape!'),
        ];

        return view('admin.settings.store', compact('settings'));
    }

    /**
     * Display API Integrations (Gmail IMAP).
     */
    public function integrations()
    {
        $settings = [
            'imap_username' => Setting::get('imap_username', ''),
            'imap_password' => Setting::get('imap_password', ''),
            'active_ai_model' => Setting::get('active_ai_model', 'gemini-1.5-pro'),
            'ai_api_key' => Setting::get('ai_api_key', ''),
        ];

        return view('admin.settings.integrations', compact('settings'));
    }

    /**
     * Display Network Configuration.
     */
    public function network()
    {
        $settings = [
            'opnsense_zone' => Setting::get('opnsense_zone', '0'),
            'network_ignored_ips' => Setting::get('network_ignored_ips', '192.168.2.251,192.168.2.100,192.168.2.5,192.168.2.4'),
            'network_vip_ips' => Setting::get('network_vip_ips', '192.168.2.100,192.168.2.5,192.168.2.4,192.168.2.99'),
            'network_infrastructure_ips' => Setting::get('network_infrastructure_ips', '192.168.254.254,192.168.254.108,192.168.2.117,192.168.2.250,192.168.2.99,192.168.2.100,192.168.2.5,192.168.2.4'),
        ];

        return view('admin.settings.network', compact('settings'));
    }

    /**
     * Update specified settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'imap_username' => 'nullable|email',
            'imap_password' => 'nullable|string',
            'active_ai_model' => 'nullable|string',
            'ai_api_key' => 'nullable|string',
            'payment_qr_code' => 'nullable|image|max:2048',
            'voucher_durations' => 'nullable|json',
            'network_ignored_ips' => 'nullable|string',
            'network_vip_ips' => 'nullable|string',
            'network_infrastructure_ips' => 'nullable|string',
            'opnsense_zone' => 'nullable|string',
            'low_stock_threshold' => 'nullable|numeric',
            'free_wifi_min_amount' => 'nullable|numeric|min:0',
            'free_wifi_duration' => 'nullable|numeric|min:1',
            'store_open_time' => 'nullable|string',
            'store_close_time' => 'nullable|string',
            'receipt_header' => 'nullable|string|max:255',
        ]);

        foreach ($validated as $key => $value) {
            if ($key !== 'payment_qr_code') {
                Setting::set($key, $value);
            }
        }

        if ($request->hasFile('payment_qr_code')) {
            $oldQr = Setting::get('payment_qr_code');
            if ($oldQr) {
                Storage::disk('public')->delete($oldQr);
            }

            $path = $request->file('payment_qr_code')->store('qrcodes', 'public');
            Setting::set('payment_qr_code', $path);
        }

        // Clear dashboard cache
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats');

        return redirect()->back()->with('success', 'Configuration updated successfully.');
    }
}
