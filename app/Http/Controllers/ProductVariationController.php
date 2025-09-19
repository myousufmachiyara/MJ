<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductVariation;

class ProductVariationController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|unique:product_variations,sku',
            'price' => 'required|numeric',
            'cost' => 'required|numeric',
            'stock_quantity' => 'required|integer',
            'attribute_value_ids' => 'required|array',
        ]);

        return DB::transaction(function () use ($request) {
            $variation = ProductVariation::create($request->only([
                'product_id', 'sku', 'barcode', 'price', 'cost', 'stock_quantity', 'status'
            ]));

            $variation->attributeValues()->sync($request->input('attribute_value_ids'));

            return $variation->load('attributeValues');
        });
    }

    public function destroy(ProductVariation $productVariation)
    {
        $productVariation->delete();
        return response()->noContent();
    }

}
