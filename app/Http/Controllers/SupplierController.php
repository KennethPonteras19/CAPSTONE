<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SUPPLIER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = Supplier::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('contact_person', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $suppliers = $query
            ->latest()
            ->get();

        return view('supplier', compact('suppliers'));
    }


    /*
    |--------------------------------------------------------------------------
    | STORE / ADD SUPPLIER
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);

        Supplier::create([
            'name' => $request->name,
            'contact_person' => $request->contact_person,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'is_active' => true,
        ]);

        return redirect()
            ->route('suppliers')
            ->with('success', 'Supplier added successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE / EDIT SUPPLIER
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);

        $supplier = Supplier::findOrFail($id);

        $supplier->update([
            'name' => $request->name,
            'contact_person' => $request->contact_person,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
        ]);

        return redirect()
            ->route('suppliers')
            ->with('success', 'Supplier updated successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVATE / DEACTIVATE
    |--------------------------------------------------------------------------
    */

    public function toggleStatus($id)
    {
        $supplier = Supplier::findOrFail($id);

        $supplier->update([
            'is_active' => !$supplier->is_active,
        ]);

        $message = $supplier->is_active
            ? 'Supplier activated successfully.'
            : 'Supplier deactivated successfully.';

        return redirect()
            ->route('suppliers')
            ->with('success', $message);
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE SUPPLIER
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);

        $supplier->delete();

        return redirect()
            ->route('suppliers')
            ->with('success', 'Supplier deleted successfully.');
    }
}
