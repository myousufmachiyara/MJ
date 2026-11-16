<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
use App\Models\SubHeadOfAccounts;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class COAController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    public function index(Request $request)
    {
        $subHeadOfAccounts = SubHeadOfAccounts::with('headOfAccount')->get();

        $query = ChartOfAccounts::with('subHeadOfAccount');

        if ($request->filled('subhead') && $request->subhead !== 'all') {
            $query->where('shoa_id', $request->subhead);
        }

        $chartOfAccounts = $query->get();

        return view('accounts.coa', compact('chartOfAccounts', 'subHeadOfAccounts'));
    }

    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request)
    {
        try {
            $request->validate([
                'shoa_id'      => 'required|exists:sub_head_of_accounts,id',
                'name'         => [
                    'required', 'string', 'max:255',
                    Rule::unique('chart_of_accounts')->whereNull('deleted_at'),
                ],
                'trn'          => 'nullable|string|max:50',
                'account_type' => 'nullable|string|max:255',
                'receivables'  => 'required|numeric',
                'payables'     => 'required|numeric',
                'credit_limit' => 'required|numeric',
                'opening_date' => 'required|date',
                'remarks'      => 'nullable|string|max:800',
                'address'      => 'nullable|string|max:250',
                'contact_no'   => 'nullable|string|max:250',
            ]);

            // Auto-generate account code from sub-head + HOA
            $subHead  = SubHeadOfAccounts::findOrFail($request->shoa_id);
            $prefix   = $subHead->hoa_id . str_pad($subHead->id, 2, '0', STR_PAD_LEFT);

            // Find the highest existing number for this prefix (including soft-deleted)
            $maxSuffix = ChartOfAccounts::withTrashed()
                ->where('account_code', 'like', $prefix . '%')
                ->pluck('account_code')
                ->map(fn($code) => (int) substr($code, strlen($prefix)))
                ->max() ?? 0;

            $accountCode = $prefix . str_pad($maxSuffix + 1, 3, '0', STR_PAD_LEFT);

            ChartOfAccounts::create([
                'shoa_id'      => $request->shoa_id,
                'account_code' => $accountCode,
                'name'         => $request->name,
                'trn'          => $request->trn,
                'address'      => $request->address,
                'account_type' => $request->account_type,
                'receivables'  => $request->receivables,
                'payables'     => $request->payables,
                'credit_limit' => $request->credit_limit,
                'opening_date' => $request->opening_date,
                'remarks'      => $request->remarks,
                'contact_no'   => $request->contact_no,
                'created_by'   => auth()->id(),
                'updated_by'   => auth()->id(),
            ]);

            Log::info('COA account created', ['code' => $accountCode, 'user' => auth()->id()]);

            return redirect()->route('coa.index')->with('success', 'Account created successfully.');

        } catch (\Throwable $e) {
            Log::error('COAController::store — ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // SHOW (used by JS modal to load account details)
    // =========================================================================

    public function show($id)
    {
        $account = ChartOfAccounts::with('subHeadOfAccount')->findOrFail($id);
        return response()->json($account);
    }

    // =========================================================================
    // EDIT (returns JSON for the edit modal)
    // =========================================================================

    public function edit($id)
    {
        $account = ChartOfAccounts::findOrFail($id);
        return response()->json($account);
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'shoa_id'      => 'required|exists:sub_head_of_accounts,id',
                'name'         => [
                    'required', 'string', 'max:255',
                    Rule::unique('chart_of_accounts', 'name')->ignore($id)->whereNull('deleted_at'),
                ],
                'trn'          => 'nullable|string|max:50',
                'account_type' => 'nullable|string|max:255',
                'receivables'  => 'required|numeric',
                'payables'     => 'required|numeric',
                'credit_limit' => 'required|numeric',
                'opening_date' => 'required|date',
                'remarks'      => 'nullable|string|max:800',
                'address'      => 'nullable|string|max:250',
                'contact_no'   => 'nullable|string|max:250',
            ]);

            $account = ChartOfAccounts::findOrFail($id);

            $account->update([
                'shoa_id'      => $request->shoa_id,
                'name'         => $request->name,
                'trn'          => $request->trn,
                'address'      => $request->address,
                'account_type' => $request->account_type,
                'receivables'  => $request->receivables,
                'payables'     => $request->payables,
                'credit_limit' => $request->credit_limit,
                'opening_date' => $request->opening_date,
                'remarks'      => $request->remarks,
                'contact_no'   => $request->contact_no,
                'updated_by'   => auth()->id(),
            ]);

            Log::info('COA account updated', ['id' => $id, 'user' => auth()->id()]);

            return redirect()->route('coa.index')->with('success', 'Account updated successfully.');

        } catch (\Throwable $e) {
            Log::error('COAController::update — ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy($id)
    {
        try {
            $account = ChartOfAccounts::findOrFail($id);
            $account->delete();

            Log::info('COA account deleted', ['id' => $id, 'user' => auth()->id()]);

            return redirect()->route('coa.index')->with('success', 'Account deleted successfully.');

        } catch (\Throwable $e) {
            Log::error('COAController::destroy — ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Could not delete account: ' . $e->getMessage());
        }
    }
}