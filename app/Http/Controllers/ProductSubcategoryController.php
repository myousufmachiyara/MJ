<?php

namespace App\Http\Controllers;

use App\Models\ProductSubcategory;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductSubcategoryController extends Controller
{
    public function index()
    {
        $subcategories = ProductSubcategory::with('category')->get();
        $categories = ProductCategory::all(); // for dropdown
        return view('products.subcategories', compact('subcategories', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:product_categories,id',
            'name'        => 'required|string|max:255|unique:product_subcategories,name',
            // FIX (subcategory update/delete "not working" — see the
            // migration 2026_09_29_144400_scope_product_subcategories_code_
            // unique_to_category.php for the full background): `code` only
            // needs to be unique WITHIN its own Category now, not across
            // the whole table — this app intentionally reuses the same
            // Subcategory Code (e.g. "DWN") under several different
            // Categories. Also excludes soft-deleted rows, so a code that
            // belonged to a since-deleted subcategory in the same category
            // can be reused instead of being blocked forever.
            'code'        => [
                'required', 'string', 'max:255',
                Rule::unique('product_subcategories', 'code')
                    ->where(fn ($query) => $query->where('category_id', $request->category_id))
                    ->whereNull('deleted_at'),
            ],
        ]);

        ProductSubcategory::create($request->only('category_id', 'name', 'code', 'description', 'status'));

        return redirect()->route('product_subcategories.index')->with('success', 'Subcategory created successfully.');
    }

    public function update(Request $request, $id)
    {
        $productSubcategory = ProductSubcategory::findOrFail($id);

        $request->validate([
            'category_id' => 'required|exists:product_categories,id',
            'name'        => 'required|string|max:255|unique:product_subcategories,name,' . $id,
            // FIX: see store() above — same category-scoped, soft-delete-
            // aware uniqueness check, ignoring this row's own id.
            'code'        => [
                'required', 'string', 'max:255',
                Rule::unique('product_subcategories', 'code')
                    ->where(fn ($query) => $query->where('category_id', $request->category_id))
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
        ]);

        $productSubcategory->update($request->only('category_id', 'name', 'code', 'description', 'status'));

        return redirect()
            ->route('product_subcategories.index')
            ->with('success', 'Subcategory updated successfully.');
    }

    public function destroy(ProductSubcategory $productSubcategory)
    {
        $productSubcategory->delete();
        return redirect()->route('product_subcategories.index')->with('success', 'Subcategory deleted successfully.');
    }
}
