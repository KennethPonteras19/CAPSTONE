<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\StoreSetting;

class SettingsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW SETTINGS PAGE
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $storeSettings = StoreSetting::first();

        return view(
            'settings',
            compact('storeSettings')
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE STORE INFORMATION
    |--------------------------------------------------------------------------
    */

    public function updateStore(Request $request)
    {
        $request->validate([
            'store_name' => 'required|string|max:255',
            'brand_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);


        /*
        |--------------------------------------------------------------------------
        | GET EXISTING SETTINGS
        |--------------------------------------------------------------------------
        */

        $storeSettings = StoreSetting::first();


        /*
        |--------------------------------------------------------------------------
        | CREATE SETTINGS IF NONE EXISTS
        |--------------------------------------------------------------------------
        */

        if (!$storeSettings) {
            $storeSettings = new StoreSetting();
        }


        /*
        |--------------------------------------------------------------------------
        | STORE NAME
        |--------------------------------------------------------------------------
        */

        $storeSettings->store_name =
            $request->store_name;


        /*
        |--------------------------------------------------------------------------
        | BRAND NAME
        |--------------------------------------------------------------------------
        */

        $storeSettings->brand_name =
            $request->brand_name;


        /*
        |--------------------------------------------------------------------------
        | LOGO UPLOAD
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('logo')) {

            /*
            | Delete old uploaded logo
            */

            if (
                $storeSettings->logo &&
                Storage::disk('public')->exists(
                    $storeSettings->logo
                )
            ) {

                Storage::disk('public')->delete(
                    $storeSettings->logo
                );
            }


            /*
            | Save new logo
            */

            $storeSettings->logo =
                $request->file('logo')->store(
                    'store-logos',
                    'public'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE
        |--------------------------------------------------------------------------
        */

        $storeSettings->save();


        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

        return back()->with(
            'success',
            'Store information updated successfully.'
        );
    }
}