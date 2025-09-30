<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductCategory;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::all();
        return view('products.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        ProductCategory::create($request->only('name'));
        return redirect()->route('product_categories.index')->with('success', 'Category created successfully.');
    }

    public function update(Request $request, $id){
        $request->validate(['name' => 'required|string|max:255']);

        $productCategory = ProductCategory::findOrFail($id);
        $productCategory->update($request->only('name'));

        return redirect()->route('product_categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();
        return redirect()->route('product_categories.index')->with('success', 'Category deleted successfully.');
    }   
}
