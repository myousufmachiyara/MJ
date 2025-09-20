<?php

namespace App\Http\Controllers;

use App\Models\ProductSubcategory;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

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
            'code'        => 'required|string|max:255|unique:product_subcategories,code',
        ]);

        ProductSubcategory::create($request->only('category_id', 'name', 'code', 'description', 'status'));

        return redirect()->route('product_subcategories.index')->with('success', 'Subcategory created successfully.');
    }

    public function update(Request $request, ProductSubcategory $productSubcategory)
    {
        $request->validate([
            'category_id' => 'required|exists:product_categories,id',
            'name'        => 'required|string|max:255|unique:product_subcategories,name,' . $productSubcategory->id,
            'code'        => 'required|string|max:255|unique:product_subcategories,code,' . $productSubcategory->id,
        ]);

        $productSubcategory->update($request->only('category_id', 'name', 'code', 'description', 'status'));

        return redirect()->route('product_subcategories.index')->with('success', 'Subcategory updated successfully.');
    }

    public function destroy(ProductSubcategory $productSubcategory)
    {
        $productSubcategory->delete();
        return redirect()->route('product_subcategories.index')->with('success', 'Subcategory deleted successfully.');
    }
}
