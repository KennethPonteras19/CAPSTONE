<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use Illuminate\Http\Request;

class ConsumableController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | STORE / ADD CONSUMABLE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',

            'category' => 'nullable|string|max:255',

            'unit' => 'required|string|max:50',

            'stock' => 'required|numeric|min:0',

            'minimum_stock' => 'required|numeric|min:0',

            'price' => 'required|numeric|min:0',
        ]);

        Consumable::create([
            'name' => $validated['name'],

            'category' => $validated['category'] ?? null,

            'unit' => $validated['unit'],

            'stock' => $validated['stock'],

            'minimum_stock' => $validated['minimum_stock'],

            'price' => $validated['price'],

            'is_active' => true,
        ]);

        return redirect()
            ->route('inventory.management')
            ->with(
                'success',
                'Consumable added successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE / EDIT CONSUMABLE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',

            'category' => 'nullable|string|max:255',

            'unit' => 'required|string|max:50',

            'stock' => 'required|numeric|min:0',

            'minimum_stock' => 'required|numeric|min:0',

            'price' => 'required|numeric|min:0',
        ]);

        $consumable = Consumable::findOrFail($id);

        $consumable->update([
            'name' => $validated['name'],

            'category' => $validated['category'] ?? null,

            'unit' => $validated['unit'],

            'stock' => $validated['stock'],

            'minimum_stock' => $validated['minimum_stock'],

            'price' => $validated['price'],
        ]);

        return redirect()
            ->route('inventory.management')
            ->with(
                'success',
                'Consumable updated successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVATE / DEACTIVATE
    |--------------------------------------------------------------------------
    */

    public function toggleStatus($id)
    {
        $consumable = Consumable::findOrFail($id);

        $consumable->update([
            'is_active' => !$consumable->is_active,
        ]);

        $message = $consumable->is_active
            ? 'Consumable activated successfully.'
            : 'Consumable deactivated successfully.';

        return redirect()
            ->route('inventory.management')
            ->with(
                'success',
                $message
            );
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $consumable = Consumable::findOrFail($id);

        $consumable->delete();

        return redirect()
            ->route('inventory.management')
            ->with(
                'success',
                'Consumable deleted successfully.'
            );
    }
}