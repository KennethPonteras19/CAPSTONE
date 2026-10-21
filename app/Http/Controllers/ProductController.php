<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::all();

        return response()->view('productmanagement', compact('products'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:products,id',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'stock' => 'required|numeric|min:0',
            'stock_unit' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:'.Product::MAX_IMAGE_SIZE_KB,
        ]);

        $product = $request->filled('id') ? Product::findOrFail($data['id']) : new Product;
        $oldImage = $product->image_path;
        unset($data['id'], $data['image']);
        $data['status'] = $data['stock'] <= 0 ? 'Out of Stock' : ($data['stock'] <= 5 ? 'Low Stock' : 'Available');

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
            if (! $data['image_path']) {
                throw ValidationException::withMessages(['image' => 'The image could not be saved. Please try again.']);
            }
        }

        try {
            $product->fill($data)->save();
        } catch (\Throwable $exception) {
            if (isset($data['image_path'])) {
                Storage::disk('public')->delete($data['image_path']);
            }
            throw $exception;
        }

        if (isset($data['image_path']) && $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('product.management')->with(
            'success',
            'Product saved successfully.'
        );
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        return back()->with(
            'success',
            'Product deleted successfully.'
        );
    }
}
