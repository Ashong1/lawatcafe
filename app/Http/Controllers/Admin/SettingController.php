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
        ];

        return view('admin.settings.payment', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'imap_username' => 'required|email',
            'imap_password' => 'required|string',
            'payment_qr_code' => 'nullable|image|max:2048',
        ]);

        Setting::set('imap_username', $request->imap_username);
        Setting::set('imap_password', $request->imap_password);

        if ($request->hasFile('payment_qr_code')) {
            // Delete old QR code if exists
            $oldQr = Setting::get('payment_qr_code');
            if ($oldQr) {
                Storage::disk('public')->delete($oldQr);
            }

            $path = $request->file('payment_qr_code')->store('qrcodes', 'public');
            Setting::set('payment_qr_code', $path);
        }

        return redirect()->back()->with('success', 'Payment settings updated successfully.');
    }
}
