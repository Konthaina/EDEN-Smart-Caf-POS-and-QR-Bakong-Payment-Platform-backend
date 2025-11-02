<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    // GET /api/settings
    public function index()
    {
        $settings = Setting::whereIn('key', [
            'shop_name',
            'shop_logo',
            'tax_rate',
            'exchange_rate_usd_khr',
            'bakong_machine_id', // <-- include machine id
        ])->pluck('value', 'key');

        // Build logo URL (full asset URL or null)
        $logoPath = $settings['shop_logo'] ?? null;
        $logoUrl  = $logoPath ? asset('storage/' . ltrim($logoPath, '/')) : null;

        return response()->json([
            'shop_name'             => $settings['shop_name'] ?? 'My Cafe',
            'shop_logo'             => $logoUrl, // full URL for frontend
            'tax_rate'              => isset($settings['tax_rate']) ? floatval($settings['tax_rate']) : 0,
            'exchange_rate_usd_khr' => isset($settings['exchange_rate_usd_khr']) ? floatval($settings['exchange_rate_usd_khr']) : 4100,
            'bakong_machine_id'     => $settings['bakong_machine_id'] ?? env('BAKONG_MERCHANT_ID'), // fallback to env if not set
        ]);
    }

    // POST /api/settings
    public function update(Request $request)
    {
        $validated = $request->validate([
            'shop_name'             => 'nullable|string|max:255',
            'shop_logo'             => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'tax_rate'              => 'nullable|numeric|min:0',
            'exchange_rate_usd_khr' => 'nullable|numeric|min:0',
            'bakong_machine_id'     => 'nullable|string|max:255', // <-- validate machine id
        ]);

        // Handle Shop Logo Upload
        if ($request->hasFile('shop_logo')) {
            $file = $request->file('shop_logo');

            // Remove old logo if exists in storage/app/public
            $oldSetting = Setting::where('key', 'shop_logo')->first();
            if ($oldSetting && $oldSetting->value && Storage::disk('public')->exists($oldSetting->value)) {
                Storage::disk('public')->delete($oldSetting->value);
            }

            // Save new logo in storage/app/public/logos (DB stores relative path like "logos/xxx.png")
            $path = $file->store('logos', 'public');

            Setting::updateOrCreate(
                ['key' => 'shop_logo'],
                ['value' => $path]
            );
        }

        // Handle numeric & text settings that should NOT be saved when empty
        foreach (['shop_name', 'tax_rate', 'exchange_rate_usd_khr'] as $key) {
            if ($request->filled($key)) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $request->input($key)]
                );
            }
        }

        // Handle bakong_machine_id separately so you CAN clear it (empty string is allowed)
        if ($request->has('bakong_machine_id')) {
            Setting::updateOrCreate(
                ['key' => 'bakong_machine_id'],
                ['value' => (string) $request->input('bakong_machine_id')] // store '' if user clears it
            );
        }

        return response()->json(['success' => true]);
    }
}
