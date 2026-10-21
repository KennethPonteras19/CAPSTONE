<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Storage;

class StoreSettingController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'store_name' => 'required|string|max:255',
            'brand_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $settings = StoreSetting::first();

        if (!$settings) {
            $settings = new StoreSetting();
        }

        $settings->store_name = $request->store_name;
        $settings->brand_name = $request->brand_name;

        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('logo')) {

    if (
        $settings->logo &&
        Storage::disk('public')->exists($settings->logo)
    ) {
        Storage::disk('public')->delete($settings->logo);
    }

    $settings->logo = $request->file('logo')->store(
        'store-logos',
        'public'
    );
}

        $settings->save();

        return back()->with(
            'success',
            'Store information updated successfully.'
        );
    }
}