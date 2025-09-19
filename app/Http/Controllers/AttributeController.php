<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\AttributeValue;

class AttributeController extends Controller
{
    public function index()
    {
        $attributes = Attribute::with('values')->get();
        return view('products.attributes', compact('attributes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:attributes,slug',
            'values' => 'required|string' // comma-separated string
        ]);

        $attribute = Attribute::create($request->only('name', 'slug'));

        // Split comma-separated values and insert into AttributeValue
        $values = array_map('trim', explode(',', $request->input('values')));
        foreach ($values as $val) {
            if (!empty($val)) {
                AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'value' => $val,
                ]);
            }
        }

        return redirect()->route('attributes.index')->with('success', 'Attribute created successfully.');
    }

    public function update(Request $request, Attribute $attribute)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:attributes,slug,' . $attribute->id,
            'values' => 'required|string' // comma-separated string
        ]);

        // Update attribute itself
        $attribute->update($request->only('name', 'slug'));

        // Parse and sanitize new values
        $incomingValues = array_map('trim', explode(',', $request->input('values')));

        // Get existing values as plain array
        $existingValues = $attribute->values->pluck('value')->map('strtolower')->toArray();

        // Add only new values (case-insensitive)
        foreach ($incomingValues as $val) {
            if (!in_array(strtolower($val), $existingValues) && !empty($val)) {
                $attribute->values()->create(['value' => $val]);
            }
        }

        return redirect()->route('attributes.index')->with('success', 'Attribute updated successfully.');
    }

    public function destroy(Attribute $attribute)
    {
        $attribute->values()->delete(); // Delete related values first
        $attribute->delete();

        return redirect()->route('attributes.index')->with('success', 'Attribute deleted successfully.');
    }
}
