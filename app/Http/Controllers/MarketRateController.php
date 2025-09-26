<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\MarketRate;
use Illuminate\Http\Request;

class MarketRateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $rates = MarketRate::with(['product', 'variation'])
            ->orderBy('effective_date', 'desc')
            ->get();
        $products = Product::all();

        return view('products.market-rates', compact('rates','products'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::all();
        return view('market_rates.create', compact('products'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'      => 'required|exists:products,id',
            'variation_id'    => 'nullable|exists:product_variations,id',
            'rate_per_unit'   => 'required|numeric|min:0',
            'effective_date'  => 'required|date',
        ]);

        MarketRate::create($validated);

        return redirect()->route('market_rates.index')
            ->with('success', 'Market rate added successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MarketRate $market_rate)
    {
        $products = Product::all();
        $variations = ProductVariation::where('product_id', $market_rate->product_id)->get();

        return view('market_rates.edit', compact('market_rate', 'products', 'variations'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MarketRate $market_rate)
    {
        $validated = $request->validate([
            'product_id'      => 'required|exists:products,id',
            'variation_id'    => 'nullable|exists:product_variations,id',
            'rate_per_unit'   => 'required|numeric|min:0',
            'effective_date'  => 'required|date',
        ]);

        $market_rate->update($validated);

        return redirect()->route('market_rates.index')
            ->with('success', 'Market rate updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MarketRate $market_rate)
    {
        $market_rate->delete();

        return redirect()->route('market_rates.index')
            ->with('success', 'Market rate deleted successfully.');
    }
}
