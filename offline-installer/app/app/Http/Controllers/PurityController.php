<?php

namespace App\Http\Controllers;

use App\Models\Purity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurityController extends Controller
{
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'label'      => 'required|string|max:50',
                'value'      => 'required|numeric|min:0.0001|max:1',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            Purity::create([
                'label'      => $data['label'],
                'value'      => $data['value'],
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            return redirect()->route('attributes.index')->with('purity_success', 'Purity added.');

        } catch (\Throwable $e) {
            Log::error('PurityController::store — ' . $e->getMessage());
            return redirect()->route('attributes.index')->with('purity_error', 'Failed to add purity.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'label'      => 'required|string|max:50',
                'value'      => 'required|numeric|min:0.0001|max:1',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            Purity::findOrFail($id)->update([
                'label'      => $data['label'],
                'value'      => $data['value'],
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            return redirect()->route('attributes.index')->with('purity_success', 'Purity updated.');

        } catch (\Throwable $e) {
            Log::error('PurityController::update — ' . $e->getMessage());
            return redirect()->route('attributes.index')->with('purity_error', 'Failed to update purity.');
        }
    }

    public function destroy($id)
    {
        try {
            Purity::findOrFail($id)->delete();
            return redirect()->route('attributes.index')->with('purity_success', 'Purity deleted.');

        } catch (\Throwable $e) {
            Log::error('PurityController::destroy — ' . $e->getMessage());
            return redirect()->route('attributes.index')->with('purity_error', 'Failed to delete purity.');
        }
    }
}