<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductCategoryController extends Controller
{
    public function index()
    {
        try {
            $categories = ProductCategory::all();
            return view('products.categories', compact('categories'));
        } catch (Exception $e) {
            Log::error('Error loading categories: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load categories. Please try again.');
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:product_categories,name',
                'code' => 'required|string|max:255|unique:product_categories,code',
            ]);

            ProductCategory::create($request->only('name', 'code'));

            return redirect()->route('product_categories.index')
                             ->with('success', 'Category created successfully.');
        } catch (Exception $e) {
            Log::error('Error creating category: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to create category. Please try again.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $productCategory = ProductCategory::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255|unique:product_categories,name,' . $id,
                'code' => 'required|string|max:255|unique:product_categories,code,' . $id,
            ]);

            $productCategory->update($request->only('name', 'code'));

            return redirect()->route('product_categories.index')
                            ->with('success', 'Category updated successfully.');
        } catch (Exception $e) {
            Log::error('Error updating category: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to update category. Please try again.');
        }
    }

    public function destroy(ProductCategory $productCategory)
    {
        try {
            $productCategory->delete();

            return redirect()->route('product_categories.index')
                             ->with('success', 'Category deleted successfully.');
        } catch (Exception $e) {
            Log::error('Error deleting category: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete category. Please try again.');
        }
    }
}
