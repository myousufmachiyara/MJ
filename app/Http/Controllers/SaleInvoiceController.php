<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\MeasurementUnit;
use App\Models\AccountingEntry;
use App\Models\ChartOfAccounts;
use App\Models\PurchaseInvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class SaleInvoiceController extends Controller
{
    public function index()
    {
        $invoices = SaleInvoice::with('customer', 'attachments')->get();
        return view('sales.index', compact('invoices'));
    }

    public function create()
    {
        $customers = ChartOfAccounts::where('account_type', 'customer')->get();
        $banks     = ChartOfAccounts::where('account_type', 'bank')->get();
        $products  = Product::with('measurementUnit')->get();
        return view('sales.create', compact('products', 'customers', 'banks'));
    }

    public function store(Request $request)
    {
        // Clear cheque fields if not cheque
        if ($request->payment_method !== 'cheque') {
            $request->merge([
                'bank_name'     => null,
                'cheque_no'     => null,
                'cheque_date'   => null,
                'cheque_amount' => null,
            ]);
        }

        // Clear bank transfer fields if not bank_transfer
        if ($request->payment_method !== 'bank_transfer') {
            $request->merge([
                'transfer_from_bank' => null,
                'transfer_to_bank'   => null,
                'account_title'      => null,
                'account_no'         => null,
                'transaction_id'     => null,
                'transfer_date'      => null,
                'transfer_amount'    => null,
            ]);
        }

        $request->validate([
            'is_taxable'                => 'required|boolean',
            'customer_id'               => 'required|exists:chart_of_accounts,id',
            'invoice_date'              => 'required|date',
            'currency'                  => 'required|in:AED,USD',
            'exchange_rate'             => 'nullable|required_if:currency,USD|numeric|min:0',
            'net_amount'                => 'required|numeric|min:0',
            'payment_method'            => 'required|in:credit,cash,cheque,bank_transfer,material+making cost',
            'payment_term'              => 'nullable|string',
            'gold_rate_aed'             => 'nullable|numeric|min:0',
            'gold_rate_usd'             => 'nullable|numeric|min:0',
            'diamond_rate_aed'          => 'nullable|numeric|min:0',
            'diamond_rate_usd'          => 'nullable|numeric|min:0',
            'purchase_gold_rate_aed'    => 'nullable|numeric|min:0',
            'purchase_making_rate_aed'  => 'nullable|numeric|min:0',
            'bank_name'                 => 'nullable|required_if:payment_method,cheque|exists:chart_of_accounts,id',
            'cheque_no'                 => 'nullable|required_if:payment_method,cheque|string',
            'cheque_date'               => 'nullable|required_if:payment_method,cheque|date',
            'cheque_amount'             => 'nullable|required_if:payment_method,cheque|numeric|min:0',
            'transfer_from_bank'        => 'nullable|required_if:payment_method,bank_transfer|exists:chart_of_accounts,id',
            'transfer_to_bank'          => 'nullable|string',
            'account_title'             => 'nullable|string',
            'account_no'                => 'nullable|string',
            'transaction_id'            => 'nullable|string',
            'transfer_date'             => 'nullable|required_if:payment_method,bank_transfer|date',
            'transfer_amount'           => 'nullable|required_if:payment_method,bank_transfer|numeric|min:0',
            'items'                     => 'required|array|min:1',
            'items.*.item_name'         => 'nullable|string|required_without:items.*.product_id',
            'items.*.product_id'        => 'nullable|exists:products,id|required_without:items.*.item_name',
            'items.*.gross_weight'      => 'required|numeric|min:0',
            'items.*.purity'            => 'required|numeric|min:0|max:1',
            'items.*.making_rate'       => 'required|numeric|min:0',
            'items.*.material_type'     => 'required|in:gold,diamond',
            'items.*.vat_percent'       => 'required|numeric|min:0',
            'material_given_by'         => 'nullable|required_if:payment_method,material+making cost|string',
            'material_received_by'      => 'nullable|required_if:payment_method,material+making cost|string',
        ]);

        try {
            DB::beginTransaction();

            // 1. Generate Invoice Number
            $isTaxable = $request->boolean('is_taxable');
            $prefix    = $isTaxable ? 'SAL-TAX-' : 'SAL-';
            $lastInvoice = SaleInvoice::withTrashed()
                ->where('invoice_no', 'LIKE', $prefix . '%')
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = $lastInvoice
                ? intval(str_replace($prefix, '', $lastInvoice->invoice_no)) + 1
                : 1;
            $invoiceNo = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            $netAmountAed = $request->currency === 'USD'
                ? round($request->net_amount * $request->exchange_rate, 2)
                : $request->net_amount;

            // 2. Create Invoice Header
            $invoice = SaleInvoice::create([
                'invoice_no'              => $invoiceNo,
                'is_taxable'              => $isTaxable,
                'customer_id'             => $request->customer_id,
                'invoice_date'            => $request->invoice_date,
                'remarks'                 => $request->remarks,
                'currency'                => $request->currency,
                'exchange_rate'           => $request->exchange_rate,
                'gold_rate_aed'           => $request->gold_rate_aed,
                'gold_rate_usd'           => $request->gold_rate_usd,
                'diamond_rate_aed'        => $request->diamond_rate_aed,
                'diamond_rate_usd'        => $request->diamond_rate_usd,
                'purchase_gold_rate_aed'  => $request->purchase_gold_rate_aed,
                'purchase_making_rate_aed'=> $request->purchase_making_rate_aed,
                'net_amount'              => $request->net_amount,
                'net_amount_aed'          => $netAmountAed,
                'payment_method'          => $request->payment_method,
                'payment_term'            => $request->payment_term,
                'bank_name'               => $request->bank_name,
                'cheque_no'               => $request->cheque_no,
                'cheque_date'             => $request->cheque_date,
                'cheque_amount'           => $request->cheque_amount,
                'transfer_from_bank'      => $request->transfer_from_bank,
                'transfer_to_bank'        => $request->transfer_to_bank,
                'account_title'           => $request->account_title,
                'account_no'              => $request->account_no,
                'transaction_id'          => $request->transaction_id,
                'transfer_date'           => $request->transfer_date,
                'transfer_amount'         => $request->transfer_amount,
                'material_received_by'    => $request->material_received_by,
                'material_given_by'       => $request->material_given_by,
                'created_by'              => auth()->id(),
            ]);

            $totalVatAed      = 0;
            $totalMaterialAed = 0;
            $totalMakingAed   = 0;
            $totalPartsAed    = 0;

            // 3. Create Items and Parts
            foreach ($request->items as $itemData) {
                $purityWeight = $itemData['gross_weight'] * $itemData['purity'];
                $col995       = $purityWeight / 0.995;
                $makingValue  = $itemData['gross_weight'] * $itemData['making_rate'];

                $metalRate = ($itemData['material_type'] === 'gold')
                    ? ($request->gold_rate_aed ?? 0)
                    : ($request->diamond_rate_aed ?? 0);

                $materialValue = $purityWeight * $metalRate;

                $itemPartsTotal = 0;
                if (!empty($itemData['parts'])) {
                    foreach ($itemData['parts'] as $partData) {
                        $itemPartsTotal += ($partData['qty'] * $partData['rate'])
                            + (($partData['stone_qty'] ?? 0) * ($partData['stone_rate'] ?? 0));
                    }
                }

                $taxable   = $makingValue + $itemPartsTotal;
                $vatAmount = $taxable * ($itemData['vat_percent'] / 100);
                $itemTotal = $materialValue + $makingValue + $vatAmount;

                // Profit % calculation
                $purchaseGoldRate   = $request->purchase_gold_rate_aed   ?? 0;
                $purchaseMakingRate = $request->purchase_making_rate_aed  ?? 0;
                $costTotal          = ($purchaseGoldRate * $purityWeight) + ($itemData['gross_weight'] * $purchaseMakingRate);
                $profitPct          = ($costTotal > 0) ? (($itemTotal - $costTotal) / $costTotal) * 100 : null;

                $invoiceItem = $invoice->items()->create([
                    'item_name'         => $itemData['item_name'] ?? null,
                    'product_id'        => $itemData['product_id'] ?? null,
                    'item_description'  => $itemData['item_description'] ?? null,
                    'gross_weight'      => $itemData['gross_weight'],
                    'purity'            => $itemData['purity'],
                    'purity_weight'     => $purityWeight,
                    'col_995'           => $col995,
                    'making_rate'       => $itemData['making_rate'],
                    'making_value'      => $makingValue,
                    'material_type'     => $itemData['material_type'],
                    'material_rate'     => $metalRate,
                    'material_value'    => $materialValue,
                    'taxable_amount'    => $taxable,
                    'vat_percent'       => $itemData['vat_percent'],
                    'vat_amount'        => $vatAmount,
                    'item_total'        => $itemTotal,
                    'profit_pct'        => $profitPct !== null ? round($profitPct, 2) : null,
                ]);

                $totalVatAed      += $vatAmount;
                $totalMaterialAed += $materialValue;
                $totalMakingAed   += $makingValue;
                $totalPartsAed    += $itemPartsTotal;

                if (!empty($itemData['parts'])) {
                    foreach ($itemData['parts'] as $partData) {
                        $partTotal = ($partData['qty'] * $partData['rate'])
                            + (($partData['stone_qty'] ?? 0) * ($partData['stone_rate'] ?? 0));

                        $invoiceItem->parts()->create([
                            'product_id'       => $partData['product_id'] ?? null,
                            'item_name'        => $partData['item_name'] ?? null,
                            'qty'              => $partData['qty'],
                            'rate'             => $partData['rate'],
                            'stone_qty'        => $partData['stone_qty'] ?? 0,
                            'stone_rate'       => $partData['stone_rate'] ?? 0,
                            'total'            => $partTotal,
                            'part_description' => $partData['part_description'] ?? null,
                        ]);
                    }
                }
            }

            // 4. Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('sale_invoices', 'public');
                    $invoice->attachments()->create(['file_path' => $path]);
                }
            }

            // 5. Accounting Entries
            $this->createSaleAccountingEntries(
                $invoice,
                $totalMaterialAed,
                $totalMakingAed,
                $totalPartsAed,
                $totalVatAed
            );

            DB::commit();
            return redirect()->route('sale_invoices.index')
                ->with('success', 'Invoice #' . $invoiceNo . ' saved successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Sale Invoice Error: " . $e->getMessage(), [
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $saleInvoice = SaleInvoice::findOrFail($id);
        $saleInvoice->load(['items.parts', 'attachments']);

        $customers       = ChartOfAccounts::where('account_type', 'customer')->get();
        $banks           = ChartOfAccounts::where('account_type', 'bank')->get();
        $products        = Product::with('measurementUnit')->get();
        $goldAedOunce    = ($saleInvoice->gold_rate_aed    ?? 0) * 31.1034768;
        $diamondAedOunce = ($saleInvoice->diamond_rate_aed ?? 0) * 31.1034768;

        $itemsData = $saleInvoice->items->map(function ($item) {
            return [
                'item_name'         => $item->item_name,
                'is_printed'        => $item->is_printed,
                'product_id'        => $item->product_id,
                'item_description'  => $item->item_description,
                'purity'            => $item->purity,
                'gross_weight'      => $item->gross_weight,
                'making_rate'       => $item->making_rate,
                'material_type'     => $item->material_type,
                'vat_percent'       => $item->vat_percent,
                'purity_weight'     => $item->purity_weight,
                'col_995'           => $item->col_995,
                'making_value'      => $item->making_value,
                'material_value'    => $item->material_value,
                'taxable_amount'    => $item->taxable_amount,
                'vat_amount'        => $item->vat_amount,
                'item_total'        => $item->item_total,
                'profit_pct'        => $item->profit_pct,
                'parts'             => $item->parts->map(function ($part) {
                    return [
                        'item_name'        => $part->item_name,
                        'product_id'       => $part->product_id,
                        'part_description' => $part->part_description,
                        'qty'              => $part->qty,
                        'rate'             => $part->rate,
                        'stone_qty'        => $part->stone_qty,
                        'stone_rate'       => $part->stone_rate,
                        'total'            => $part->total,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        return view('sales.edit', compact(
            'saleInvoice', 'customers', 'banks', 'products',
            'itemsData', 'goldAedOunce', 'diamondAedOunce'
        ));
    }

    public function update(Request $request, $id)
    {
        $invoice = SaleInvoice::findOrFail($id);

        if ($request->payment_method !== 'cheque') {
            $request->merge([
                'bank_name'     => null,
                'cheque_no'     => null,
                'cheque_date'   => null,
                'cheque_amount' => null,
            ]);
        }

        if ($request->payment_method !== 'bank_transfer') {
            $request->merge([
                'transfer_from_bank' => null,
                'transfer_to_bank'   => null,
                'account_title'      => null,
                'account_no'         => null,
                'transaction_id'     => null,
                'transfer_date'      => null,
                'transfer_amount'    => null,
            ]);
        }

        $request->validate([
            'is_taxable'                => 'required|boolean',
            'customer_id'               => 'required|exists:chart_of_accounts,id',
            'invoice_date'              => 'required|date',
            'currency'                  => 'required|in:AED,USD',
            'exchange_rate'             => 'nullable|required_if:currency,USD|numeric|min:0',
            'net_amount'                => 'required|numeric|min:0',
            'payment_method'            => 'required|in:credit,cash,cheque,bank_transfer,material+making cost',
            'payment_term'              => 'nullable|string',
            'gold_rate_aed'             => 'nullable|numeric|min:0',
            'gold_rate_usd'             => 'nullable|numeric|min:0',
            'diamond_rate_aed'          => 'nullable|numeric|min:0',
            'diamond_rate_usd'          => 'nullable|numeric|min:0',
            'purchase_gold_rate_aed'    => 'nullable|numeric|min:0',
            'purchase_making_rate_aed'  => 'nullable|numeric|min:0',
            'bank_name'                 => 'nullable|required_if:payment_method,cheque|exists:chart_of_accounts,id',
            'cheque_no'                 => 'nullable|required_if:payment_method,cheque|string',
            'cheque_date'               => 'nullable|required_if:payment_method,cheque|date',
            'cheque_amount'             => 'nullable|required_if:payment_method,cheque|numeric|min:0',
            'transfer_from_bank'        => 'nullable|required_if:payment_method,bank_transfer|exists:chart_of_accounts,id',
            'transfer_to_bank'          => 'nullable|string',
            'account_title'             => 'nullable|string',
            'account_no'                => 'nullable|string',
            'transaction_id'            => 'nullable|string',
            'transfer_date'             => 'nullable|required_if:payment_method,bank_transfer|date',
            'transfer_amount'           => 'nullable|required_if:payment_method,bank_transfer|numeric|min:0',
            'items'                     => 'required|array|min:1',
            'items.*.item_name'         => 'nullable|string|required_without:items.*.product_id',
            'items.*.product_id'        => 'nullable|exists:products,id|required_without:items.*.item_name',
            'items.*.gross_weight'      => 'required|numeric|min:0',
            'items.*.purity'            => 'required|numeric|min:0|max:1',
            'items.*.making_rate'       => 'required|numeric|min:0',
            'items.*.material_type'     => 'required|in:gold,diamond',
            'items.*.vat_percent'       => 'required|numeric|min:0',
            'material_given_by'         => 'nullable|required_if:payment_method,material+making cost|string',
            'material_received_by'      => 'nullable|required_if:payment_method,material+making cost|string',
        ]);

        try {
            DB::beginTransaction();

            $netAmountAed = $request->currency === 'USD'
                ? round($request->net_amount * $request->exchange_rate, 2)
                : $request->net_amount;

            // 1. Update header
            $invoice->update([
                'is_taxable'              => $request->boolean('is_taxable'),
                'customer_id'             => $request->customer_id,
                'invoice_date'            => $request->invoice_date,
                'remarks'                 => $request->remarks,
                'currency'                => $request->currency,
                'exchange_rate'           => $request->exchange_rate,
                'gold_rate_aed'           => $request->gold_rate_aed,
                'gold_rate_usd'           => $request->gold_rate_usd,
                'diamond_rate_aed'        => $request->diamond_rate_aed,
                'diamond_rate_usd'        => $request->diamond_rate_usd,
                'purchase_gold_rate_aed'  => $request->purchase_gold_rate_aed,
                'purchase_making_rate_aed'=> $request->purchase_making_rate_aed,
                'net_amount'              => $request->net_amount,
                'net_amount_aed'          => $netAmountAed,
                'payment_method'          => $request->payment_method,
                'payment_term'            => $request->payment_term,
                'bank_name'               => $request->bank_name,
                'cheque_no'               => $request->cheque_no,
                'cheque_date'             => $request->cheque_date,
                'cheque_amount'           => $request->cheque_amount,
                'transfer_from_bank'      => $request->transfer_from_bank,
                'transfer_to_bank'        => $request->transfer_to_bank,
                'account_title'           => $request->account_title,
                'account_no'              => $request->account_no,
                'transaction_id'          => $request->transaction_id,
                'transfer_date'           => $request->transfer_date,
                'transfer_amount'         => $request->transfer_amount,
                'material_received_by'    => $request->material_received_by,
                'material_given_by'       => $request->material_given_by,
            ]);

            // 2. Delete old items and parts
            foreach ($invoice->items as $oldItem) {
                $oldItem->parts()->delete();
            }
            $invoice->items()->delete();

            $totalVatAed      = 0;
            $totalMaterialAed = 0;
            $totalMakingAed   = 0;
            $totalPartsAed    = 0;

            // 3. Re-create items and parts
            foreach ($request->items as $itemData) {
                $purityWeight = $itemData['gross_weight'] * $itemData['purity'];
                $col995       = $purityWeight / 0.995;
                $makingValue  = $itemData['gross_weight'] * $itemData['making_rate'];

                $metalRate = ($itemData['material_type'] === 'gold')
                    ? ($request->gold_rate_aed ?? 0)
                    : ($request->diamond_rate_aed ?? 0);

                $materialValue = $purityWeight * $metalRate;

                $itemPartsTotal = 0;
                if (!empty($itemData['parts'])) {
                    foreach ($itemData['parts'] as $partData) {
                        $itemPartsTotal += ($partData['qty'] * $partData['rate'])
                            + (($partData['stone_qty'] ?? 0) * ($partData['stone_rate'] ?? 0));
                    }
                }

                $taxable   = $makingValue + $itemPartsTotal;
                $vatAmount = $taxable * ($itemData['vat_percent'] / 100);
                $itemTotal = $materialValue + $makingValue + $vatAmount;

                // Profit % calculation
                $purchaseGoldRate   = $request->purchase_gold_rate_aed   ?? 0;
                $purchaseMakingRate = $request->purchase_making_rate_aed  ?? 0;
                $costTotal          = ($purchaseGoldRate * $purityWeight) + ($itemData['gross_weight'] * $purchaseMakingRate);
                $profitPct          = ($costTotal > 0) ? (($itemTotal - $costTotal) / $costTotal) * 100 : null;

                $invoiceItem = $invoice->items()->create([
                    'item_name'         => $itemData['item_name'] ?? null,
                    'product_id'        => $itemData['product_id'] ?? null,
                    'item_description'  => $itemData['item_description'] ?? null,
                    'gross_weight'      => $itemData['gross_weight'],
                    'purity'            => $itemData['purity'],
                    'purity_weight'     => $purityWeight,
                    'col_995'           => $col995,
                    'making_rate'       => $itemData['making_rate'],
                    'making_value'      => $makingValue,
                    'material_type'     => $itemData['material_type'],
                    'material_rate'     => $metalRate,
                    'material_value'    => $materialValue,
                    'taxable_amount'    => $taxable,
                    'vat_percent'       => $itemData['vat_percent'],
                    'vat_amount'        => $vatAmount,
                    'item_total'        => $itemTotal,
                    'profit_pct'        => $profitPct !== null ? round($profitPct, 2) : null,
                ]);

                $totalVatAed      += $vatAmount;
                $totalMaterialAed += $materialValue;
                $totalMakingAed   += $makingValue;
                $totalPartsAed    += $itemPartsTotal;

                if (!empty($itemData['parts'])) {
                    foreach ($itemData['parts'] as $partData) {
                        $partTotal = ($partData['qty'] * $partData['rate'])
                            + (($partData['stone_qty'] ?? 0) * ($partData['stone_rate'] ?? 0));

                        $invoiceItem->parts()->create([
                            'product_id'       => $partData['product_id'] ?? null,
                            'item_name'        => $partData['item_name'] ?? null,
                            'qty'              => $partData['qty'],
                            'rate'             => $partData['rate'],
                            'stone_qty'        => $partData['stone_qty'] ?? 0,
                            'stone_rate'       => $partData['stone_rate'] ?? 0,
                            'total'            => $partTotal,
                            'part_description' => $partData['part_description'] ?? null,
                        ]);
                    }
                }
            }

            // 4. New attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('sale_invoices', 'public');
                    $invoice->attachments()->create(['file_path' => $path]);
                }
            }

            // 5. Reverse old accounting entries and recreate
            $oldVoucher = Voucher::where('reference_type', 'App\Models\SaleInvoice')
                ->where('reference_id', $invoice->id)
                ->first();

            if ($oldVoucher) {
                AccountingEntry::where('voucher_id', $oldVoucher->id)->delete();
                $oldVoucher->delete();
            }

            $this->createSaleAccountingEntries(
                $invoice,
                $totalMaterialAed,
                $totalMakingAed,
                $totalPartsAed,
                $totalVatAed
            );

            DB::commit();
            return redirect()->route('sale_invoices.index')
                ->with('success', 'Invoice #' . $invoice->invoice_no . ' updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Sale Invoice Update Error: " . $e->getMessage(), [
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function scanBarcode(Request $request)
    {
        $barcode = trim($request->query('barcode', ''));

        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'No barcode provided.'], 422);
        }

        // Look up the item wherever it lives — sale or purchase invoice items
        // Priority: sale invoice items first, then purchase invoice items (as a "catalogue lookup")
        $item = PurchaseInvoiceItem::with(['product.measurementUnit', 'parts'])->where('barcode_number', $barcode)->first();

        // If not found in sales, fall back to purchase invoice items
        // (useful when scanning a barcode that was created during purchase)
        if (!$item) {
            $item = \App\Models\PurchaseInvoiceItem::with(['product.measurementUnit', 'parts'])
                ->where('barcode_number', $barcode)
                ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => "Barcode '{$barcode}' not found in any invoice.",
                ], 404);
            }

            // Map purchase item fields to a unified shape
            return response()->json([
                'success'          => true,
                'source'           => 'purchase',
                'item_name'        => $item->item_name ?? ($item->product->name ?? ''),
                'item_description' => $item->item_description ?? '',
                'purity'           => $item->purity,
                'gross_weight'     => $item->gross_weight,
                'making_rate'      => $item->making_rate,
                'material_type'    => $item->material_type,
                'vat_percent'      => $item->vat_percent,
                'barcode_number'   => $barcode,
                'parts'            => $item->parts->map(fn($p) => [
                    'item_name'        => $p->item_name ?? ($p->product->name ?? ''),
                    'part_description' => $p->part_description ?? '',
                    'qty'              => $p->qty,
                    'rate'             => $p->rate,
                    'stone_qty'        => $p->stone_qty,
                    'stone_rate'       => $p->stone_rate,
                ])->values()->toArray(),
            ]);
        }

        return response()->json([
            'success'          => true,
            'source'           => 'sale',
            'item_name'        => $item->item_name ?? ($item->product->name ?? ''),
            'item_description' => $item->item_description ?? '',
            'purity'           => $item->purity,
            'gross_weight'     => $item->gross_weight,
            'making_rate'      => $item->making_rate,
            'material_type'    => $item->material_type,
            'vat_percent'      => $item->vat_percent,
            'barcode_number'   => $barcode,
            'parts'            => $item->parts->map(fn($p) => [
                'item_name'        => $p->item_name ?? ($p->product->name ?? ''),
                'part_description' => $p->part_description ?? '',
                'qty'              => $p->qty,
                'rate'             => $p->rate,
                'stone_qty'        => $p->stone_qty,
                'stone_rate'       => $p->stone_rate,
            ])->values()->toArray(),
        ]);
    }

    public function print($id)
    {
        $invoice = SaleInvoice::with([
            'customer',
            'items',
            'items.product.measurementUnit',
            'items.parts',
            'items.parts.product.measurementUnit',
            'items.parts.variation.attributeValues.attribute',
            'bank',
            'transferBank',
            'vouchers.entries.account',
        ])->findOrFail($id);

        $totalMaterialAed   = $invoice->items->sum('material_value');
        $totalMakingAed     = $invoice->items->sum('making_value');
        $totalPartsAed      = $invoice->items->sum(fn($item) => $item->parts->sum('total'));
        $totalVatAed        = $invoice->items->sum('vat_amount');
        $totalTaxableAed    = $invoice->items->sum('taxable_amount');

        // Overall profit % for print
        $purchaseGoldRate    = $invoice->purchase_gold_rate_aed   ?? 0;
        $purchaseMakingRate  = $invoice->purchase_making_rate_aed  ?? 0;
        $totalCost           = $invoice->items->sum(fn($item) =>
            ($purchaseGoldRate * $item->purity_weight) + ($item->gross_weight * $purchaseMakingRate)
        );
        $overallProfitPct    = ($totalCost > 0)
            ? round((($invoice->net_amount - $totalCost) / $totalCost) * 100, 2)
            : null;

        $pdf = new MyPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetTitle($invoice->invoice_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.2);

        // =========================================================================
        // PAGE 1: SALE INVOICE
        // =========================================================================
        $pdf->AddPage();

        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="85">' : '';
        $pdf->writeHTML('
            <table width="100%" cellpadding="3">
                <tr>
                    <td width="40%">' . $logoHtml . '</td>
                    <td width="60%" style="text-align:right;font-size:10px;">
                        <strong>MUSFIRA JEWELRY L.L.C</strong><br>
                        Suite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai<br>
                        TRN No: 104902647700003
                    </td>
                </tr>
            </table><hr>', true, false, false, false);

        $title = $invoice->is_taxable ? 'TAX INVOICE (SALE)' : 'SALE INVOICE';
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, $title, 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);

        $customerHtml = '
        <table cellpadding="3" width="100%">
            <tr>
                <td width="50%">
                    <b>To:</b><br>
                    ' . ($invoice->customer->name ?? '-') . '<br>
                    ' . ($invoice->customer->address ?? '-') . '<br>
                    Contact: ' . ($invoice->customer->contact_no ?? '-') . '<br>
                    TRN: ' . ($invoice->customer->trn ?? '-') . '<br>
                </td>
                <td width="50%">
                    <table border="1" cellpadding="3" width="100%">
                        <tr><td width="45%"><b>Date</b></td><td width="55%">' . Carbon::parse($invoice->invoice_date)->format('d.m.Y') . '</td></tr>
                        <tr><td><b>Invoice No</b></td><td>' . $invoice->invoice_no . '</td></tr>
                        <tr>
                            <td><b>Gold Rate (' . $invoice->currency . ')</b></td>
                            <td>' . number_format($invoice->currency === 'USD' ? $invoice->gold_rate_usd : $invoice->gold_rate_aed, 2) . '</td>
                        </tr>
                        <tr>
                            <td><b>Diamond Rate (' . $invoice->currency . ')</b></td>
                            <td>' . number_format($invoice->currency === 'USD' ? $invoice->diamond_rate_usd : $invoice->diamond_rate_aed, 2) . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($customerHtml, true, false, false, false);

        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="3%" rowspan="2">#</th>
                    <th width="10%" rowspan="2">Item Name</th>
                    <th width="10%" rowspan="2">Description</th>
                    <th width="7%" rowspan="2">Gross Wt</th>
                    <th width="6%" rowspan="2">Purity</th>
                    <th width="7%" rowspan="2">Purity Wt</th>
                    <th width="6%" rowspan="2">995</th>
                    <th width="14%" colspan="2">Making</th>
                    <th width="8%" rowspan="2">Material</th>
                    <th width="8%" rowspan="2">Material Val</th>
                    <th width="7%" rowspan="2">Taxable</th>
                    <th width="6%" rowspan="2">VAT %</th>
                    <th width="8%" rowspan="2">Item Total</th>
                    <th width="6%" rowspan="2">Profit %</th>
                </tr>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="7%">Rate</th>
                    <th width="7%">Value</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($invoice->items as $index => $item) {
            $hasParts    = $item->parts && $item->parts->count() > 0;
            $partsSum    = $hasParts ? $item->parts->sum('total') : 0;
            $itemOnlyTotal = $item->item_total;
            $productTotal  = $item->item_total + $partsSum;
            $vatPercent    = ($item->taxable_amount > 0) ? ($item->vat_amount / $item->taxable_amount) * 100 : 0;

            $profitDisplay = $item->profit_pct !== null
                ? number_format($item->profit_pct, 2) . '%'
                : 'N/A';

            $html .= '
                <tr style="text-align:center; background-color:#ffffff;">
                    <td width="3%">' . ($index + 1) . '</td>
                    <td width="10%">' . ($item->item_name ?: ($item->product->name ?? '-')) . '</td>
                    <td width="10%">' . ($item->item_description ?? '-') . '</td>
                    <td width="7%">' . number_format($item->gross_weight, 3) . '</td>
                    <td width="6%">' . number_format($item->purity, 3) . '</td>
                    <td width="7%">' . number_format($item->purity_weight, 3) . '</td>
                    <td width="6%">' . number_format($item->col_995 ?? 0, 3) . '</td>
                    <td width="7%">' . number_format($item->making_rate ?? 0, 2) . '</td>
                    <td width="7%">' . number_format($item->making_value, 2) . '</td>
                    <td width="8%">' . ucfirst($item->material_type) . '</td>
                    <td width="8%">' . number_format($item->material_value, 2) . '</td>
                    <td width="7%">' . number_format($item->taxable_amount, 2) . '</td>
                    <td width="6%">' . round($vatPercent, 0) . '%</td>
                    <td width="8%" style="font-weight:bold;">' . number_format($itemOnlyTotal, 2) . '</td>
                    <td width="6%" style="font-weight:bold;">' . $profitDisplay . '</td>
                </tr>';

            if ($hasParts) {
                $html .= '<tr style="background-color:#f9f9f9; font-style:italic; font-size:7px;">
                            <td width="3%"></td>
                            <td colspan="14" width="97%"><b>Parts Detail:</b></td>
                          </tr>';

                foreach ($item->parts as $part) {
                    $partUnit        = $part->product->measurementUnit->shortcode ?? $part->product->measurementUnit->name ?? 'Ct.';
                    $displayPartName = $part->item_name ?: ($part->product->name ?? 'Part');

                    $html .= '
                    <tr style="font-size:7.5px; background-color:#fcfcfc;">
                        <td width="3%"></td>
                        <td width="20%" colspan="2" style="text-align:left;">' . $displayPartName . '</td>
                        <td width="20%" colspan="1" style="text-align:left;">' . htmlspecialchars($part->part_description ?? '') . '</td>
                        <td width="10%" colspan="2" style="text-align:center;">' . $part->qty . ' ' . $partUnit . '</td>
                        <td width="10%" colspan="2" style="text-align:center;">Rate: ' . number_format($part->rate, 2) . '</td>
                        <td width="11%" colspan="1" style="text-align:center;">St. Qty: ' . number_format($part->stone_qty ?? 0, 0) . '</td>
                        <td width="12%" colspan="1" style="text-align:center;">St. Rate: ' . number_format($part->stone_rate ?? 0, 2) . '</td>
                        <td width="14%" colspan="2" style="text-align:right; padding-right:10px;"><b>' . number_format($part->total, 2) . '</b></td>
                    </tr>';
                }

                $html .= '
                    <tr style="background-color:#eeeeee; font-weight:bold; font-size:8px;">
                        <td colspan="10" align="right" style="padding-right:10px;">Product Grand Total (Item + Parts):</td>
                        <td colspan="2" align="right">' . number_format($productTotal, 2) . '</td>
                    </tr>';
            }
        }

        // Overall profit in summary row
        $overallProfitDisplay = $overallProfitPct !== null
            ? number_format($overallProfitPct, 2) . '%'
            : 'N/A';

        $html .= '
                <tr style="font-weight:bold; background-color:#f5f5f5;">
                    <td colspan="10" align="right">Net Invoice Amount (Incl. VAT)</td>
                    <td colspan="2" align="right">' . number_format($invoice->net_amount, 2) . '</td>
                    <td colspan="2" align="right">Overall Profit: ' . $overallProfitDisplay . '</td>
                </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, false, false);

        /* ================= SUMMARY SECTION ================= */
        $aedAmount = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;

        $summaryHtml = '
        <table width="100%" cellpadding="0" border="0" style="margin-top:10px;">
            <tr>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td><b>Payment Details</b></td><td><b>Value</b></td></tr>
                        <tr><td>Method</td><td>' . ucfirst($invoice->payment_method) . '</td></tr>';

        if ($invoice->payment_method === 'credit') {
            $summaryHtml .= '<tr><td>Payment Term:</td><td>' . ($invoice->payment_term ?? '-') . '</td></tr>';
        }
        if ($invoice->payment_method === 'cheque') {
            $summaryHtml .= '
            <tr><td>Bank Name</td><td>' . ($invoice->bank->name ?? '-') . '</td></tr>
            <tr><td>Cheque No</td><td>' . ($invoice->cheque_no ?? '-') . '</td></tr>
            <tr><td>Cheque Date</td><td>' . ($invoice->cheque_date ? Carbon::parse($invoice->cheque_date)->format('d.m.Y') : '-') . '</td></tr>';
        }
        if ($invoice->payment_method === 'bank_transfer') {
            $summaryHtml .= '
            <tr><td>From Bank</td><td>' . ($invoice->transferBank->name ?? '-') . '</td></tr>
            <tr><td>Customer Bank</td><td>' . ($invoice->transfer_to_bank ?? '-') . '</td></tr>
            <tr><td>Account Title</td><td>' . ($invoice->account_title ?? '-') . '</td></tr>
            <tr><td>Account No</td><td>' . ($invoice->account_no ?? '-') . '</td></tr>
            <tr><td>Transfer Date</td><td>' . ($invoice->transfer_date ? Carbon::parse($invoice->transfer_date)->format('d.m.Y') : '-') . '</td></tr>
            <tr><td>Transaction Ref</td><td>' . ($invoice->transaction_id ?? '-') . '</td></tr>
            <tr><td>Transfer Amount</td><td>' . number_format($invoice->transfer_amount ?? 0, 2) . '</td></tr>';
        }
        if (str_contains($invoice->payment_method, 'material')) {
            $totalPureWeight = $invoice->items->sum('purity_weight');
            $summaryHtml .= '
            <tr><td>Material Given By</td><td>' . ($invoice->material_given_by ?? '-') . '</td></tr>
            <tr><td>Material Received By</td><td>' . ($invoice->material_received_by ?? '-') . '</td></tr>
            <tr><td>Total Pure Weight</td><td>' . number_format($totalPureWeight, 3) . ' gms</td></tr>
            <tr><td>Making + Parts</td><td>' . number_format($totalMakingAed + $totalPartsAed, 2) . ' AED</td></tr>';
        }

        $summaryHtml .= '</table>
                </td>
                <td width="10%"></td>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td colspan="2" align="center"><b>Summary (' . $invoice->currency . ')</b></td></tr>
                        <tr><td width="60%">Material Value</td><td width="40%" align="right">' . number_format($totalMaterialAed, 2) . '</td></tr>
                        <tr><td>Making Charges</td><td align="right">' . number_format($totalMakingAed, 2) . '</td></tr>
                        <tr><td>Parts Value</td><td align="right">' . number_format($totalPartsAed, 2) . '</td></tr>
                        <tr><td>Taxable Amount</td><td align="right">' . number_format($totalTaxableAed, 2) . '</td></tr>
                        <tr><td>Total VAT</td><td align="right">' . number_format($totalVatAed, 2) . '</td></tr>
                        <tr style="font-weight:bold; background-color:#eeeeee;">
                            <td>Invoice Total</td>
                            <td align="right">' . number_format($invoice->net_amount, 2) . '</td>
                        </tr>
                        <tr style="color: ' . ($overallProfitPct >= 0 ? 'green' : 'red') . '; font-weight:bold;">
                            <td>Overall Profit %</td>
                            <td align="right">' . $overallProfitDisplay . '</td>
                        </tr>';

        if ($invoice->currency === 'USD') {
            $summaryHtml .= '
                <tr><td>Exchange Rate</td><td align="right">' . number_format($invoice->exchange_rate, 4) . '</td></tr>
                <tr style="font-weight:bold;"><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        } else {
            $summaryHtml .= '<tr style="font-weight:bold;"><td>Total (AED)</td><td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        }

        $summaryHtml .= '</table></td></tr></table>';

        $pdf->Ln(2);
        $pdf->writeHTML($summaryHtml, true, false, false, false);

        /* ================= AMOUNT IN WORDS ================= */
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $wordsAED = $pdf->convertCurrencyToWords($aedAmount, 'AED');
        if ($invoice->currency === 'USD') {
            $wordsUSD = $pdf->convertCurrencyToWords($invoice->net_amount, 'USD');
            $pdf->Cell(0, 5, "Amount in Words (USD): " . $wordsUSD, 0, 1, 'L');
            $pdf->Cell(0, 5, "Amount in Words (AED): " . $wordsAED, 0, 1, 'L');
        } else {
            $pdf->Cell(0, 5, "Amount in Words (AED): " . $wordsAED, 0, 1, 'L');
        }

        /* ================= TERMS & CONDITIONS ================= */
        $pdf->Ln(2);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);
        $termsHtml = '
            <div style="line-height: 8px; text-align: justify; color: #333;">
                <b>TERMS & CONDITIONS:</b> Goods sold on credit, if not paid when due, or in case of law suit arising there from,
                the purchaser agrees to pay the seller all expense of recovery, collection, etc., including attorney fees,
                legal expense and/or recovery-agent charges. <b>GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED.</b>
                Any dispute, difference, controversy or claim arising out of or in connection with this sale,
                including (but not limited to) any issue regarding its existence, validity, interpretation, performance,
                discharge and other applicable remedies, shall be subject to the exclusive jurisdiction of Dubai Courts.
            </div>';
        $pdf->writeHTML($termsHtml, true, false, false, false);

        // Signatures
        $pdf->Ln(40);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 80, $y);
        $pdf->Line(130, $y, 190, $y);
        $pdf->SetXY(20, $y);
        $pdf->Cell(60, 5, "Customer's Signature", 0, 0, 'C');
        $pdf->SetXY(130, $y);
        $pdf->Cell(60, 5, "Authorized Signature", 0, 0, 'C');

        // Currency payment pages (Making + Parts + VAT)
        $pdf->AddPage();
        $this->renderCurrencyReceiptPage($pdf, $invoice, $totalMakingAed, $totalPartsAed, $totalVatAed, 'CUSTOMER COPY');

        $pdf->AddPage();
        $this->renderCurrencyReceiptPage($pdf, $invoice, $totalMakingAed, $totalPartsAed, $totalVatAed, 'ACCOUNTS COPY');

        return $pdf->Output($invoice->invoice_no . '.pdf', 'I');
    }

    private function renderCurrencyReceiptPage($pdf, $invoice, $totalMaking, $totalParts, $totalVat, $copyType = 'CUSTOMER COPY')
    {
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="80">' : '';
        $header   = '<table width="100%" cellpadding="2"><tr><td width="30%">' . $logoHtml . '</td><td width="70%" style="text-align:right; font-size:9px;"><strong style="font-size:12px;">MUSFIRA JEWELRY L.L.C</strong><br>M04 Al Buteen 2 Building, Old Baldiya Street,<br>Gold Souq Gate 1 Dubai UAE. Tel: +971 4 2202622<br>TRN: 104902647700003</td></tr></table><hr>';
        $pdf->writeHTML($header, true, false, false, false);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(120, 8, 'SALE RECEIPT', 0, 0, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(70, 8, strtoupper($copyType), 0, 1, 'R');
        $pdf->Ln(2);

        $makingAED    = $invoice->currency === 'USD' ? round($totalMaking * $invoice->exchange_rate, 2) : $totalMaking;
        $partsAED     = $invoice->currency === 'USD' ? round($totalParts  * $invoice->exchange_rate, 2) : $totalParts;
        $vatAED       = $invoice->currency === 'USD' ? round($totalVat    * $invoice->exchange_rate, 2) : $totalVat;
        $payableAmount = $makingAED + $partsAED + $vatAED;

        $pdf->SetFont('helvetica', '', 9);
        $htmlInfo = '
        <table width="100%" cellpadding="0">
            <tr>
                <td width="60%">
                    <b>To:</b><br>
                    ' . ($invoice->customer->name ?? '-') . '<br>
                    ' . ($invoice->customer->address ?? '-') . '<br>
                    Contact: ' . ($invoice->customer->contact_no ?? '-') . '<br>
                    TRN: ' . ($invoice->customer->trn ?? '-') . '
                </td>
                <td width="40%">
                    <table border="1" cellpadding="3" width="100%">
                        <tr><td><b>RECEIPT NO</b></td><td><b>' . $invoice->invoice_no . '</b></td></tr>
                        <tr><td><b>DATE</b></td><td><b>' . Carbon::parse($invoice->invoice_date)->format('d/m/Y') . '</b></td></tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($htmlInfo, true, false, false, false);
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', '', 8);
        $tableHtml = '
        <table width="100%" cellpadding="5" border="1" style="border-collapse:collapse;">
            <tr style="background-color:#f2f2f2; font-weight:bold; text-align:center;">
                <th width="10%">No.</th>
                <th width="50%">Description</th>
                <th width="20%">Amount (AED)</th>
                <th width="20%">Total (AED)</th>
            </tr>
            <tr style="text-align:center;">
                <td>1</td>
                <td align="left">
                    <b>Making Charges, Parts &amp; VAT</b><br>
                    (Making: ' . number_format($makingAED, 2) . ' + Parts: ' . number_format($partsAED, 2) . ' + VAT: ' . number_format($vatAED, 2) . ')<br>
                    Against Sale Invoice # ' . $invoice->invoice_no . '
                </td>
                <td>' . number_format($payableAmount, 2) . '</td>
                <td>' . number_format($payableAmount, 2) . '</td>
            </tr>
            <tr style="font-weight:bold; background-color:#f9f9f9;">
                <td colspan="2" align="right">Total Received</td>
                <td align="center">' . number_format($payableAmount, 2) . '</td>
                <td align="center">' . number_format($payableAmount, 2) . '</td>
            </tr>
        </table>';
        $pdf->writeHTML($tableHtml, true, false, false, false);

        $pdf->Ln(2);
        $words      = $pdf->convertCurrencyToWords($payableAmount, 'AED');
        $htmlStatus = '
        <table width="100%" cellpadding="4" style="border:1px solid #000;">
            <tr>
                <td width="30%" style="border:1px solid #000; background-color:#f2f2f2;">Amount Received:</td>
                <td width="70%">' . strtoupper($words) . '</td>
            </tr>
            <tr>
                <td style="border-right:0.5px solid #000;"><b>AED ' . number_format($payableAmount, 2) . ' CREDITED</b></td>
                <td>Being receipt for making charges, parts and tax.</td>
            </tr>
        </table>';
        $pdf->writeHTML($htmlStatus, true, false, false, false);

        $pdf->Ln(30);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Line(10, $y, 55, $y);
        $pdf->SetXY(10, $y + 1);
        $pdf->Cell(45, 5, "CUSTOMER'S SIGNATURE", 0, 0, 'C');
        $pdf->Line(65, $y, 95, $y);
        $pdf->SetXY(65, $y + 1);
        $pdf->Cell(30, 5, "Prepared By", 0, 0, 'C');
        $pdf->Line(110, $y, 140, $y);
        $pdf->SetXY(110, $y + 1);
        $pdf->Cell(30, 5, "Checked By", 0, 0, 'C');
        $pdf->SetXY(150, $y - 9);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(50, 3, "For MUSFIRA JEWELRY L L C", 0, 0, 'C');
        $pdf->Line(155, $y, 195, $y);
        $pdf->SetXY(155, $y + 1);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(40, 5, "AUTHORISED SIGNATORY", 0, 0, 'C');
    }

    protected function createSaleAccountingEntries($invoice, $totalMaterialAed, $totalMakingAed, $totalPartsAed, $totalVatAed)
    {
        $getAccount = function ($code) {
            $account = ChartOfAccounts::where('account_code', $code)->first();
            if (!$account) {
                throw new \Exception("COA Missing: {$code}");
            }
            return $account->id;
        };

        DB::beginTransaction();

        $voucher = Voucher::create([
            'voucher_no'     => Voucher::generateVoucherNo('sale'),
            'voucher_type'   => 'sale',
            'voucher_date'   => $invoice->invoice_date,
            'reference_type' => 'App\Models\SaleInvoice',
            'reference_id'   => $invoice->id,
            'remarks'        => 'Sale Invoice #' . $invoice->invoice_no,
            'created_by'     => auth()->id(),
        ]);

        $entries = [];

        /* -------------------------------------------------
            1️⃣ DEBIT SIDE — WHAT WE RECEIVE
        --------------------------------------------------*/
        $totalReceivable = round($totalMaterialAed + $totalMakingAed + $totalPartsAed + $totalVatAed, 2);

        $debitAccount = match ($invoice->payment_method) {
            'credit' => $invoice->customer_id,
            'cash' => $getAccount('101001'),
            'cheque' => $invoice->bank_name,
            'bank_transfer' => $invoice->transfer_from_bank,
            'material+making cost' => $invoice->customer_id,
            default => throw new \Exception('Invalid payment method'),
        };

        $entries[] = [
            'voucher_id' => $voucher->id,
            'account_id' => $debitAccount,
            'debit'      => $totalReceivable,
            'credit'     => 0,
            'narration'  => 'Sale Invoice ' . $invoice->invoice_no,
        ];

        /* -------------------------------------------------
            2️⃣ CREDIT SIDE — ACTUAL REVENUE BREAKUP
        --------------------------------------------------*/

        // GOLD SALES
        if ($totalMaterialAed > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $getAccount('401001'),
                'debit'      => 0,
                'credit'     => round($totalMaterialAed, 2),
                'narration'  => 'Gold Value',
            ];
        }

        // MAKING INCOME (SERVICE REVENUE)
        if ($totalMakingAed > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $getAccount('402001'),
                'debit'      => 0,
                'credit'     => round($totalMakingAed, 2),
                'narration'  => 'Making Charges Income',
            ];
        }

        // PARTS SALES
        if ($totalPartsAed > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $getAccount('403001'),
                'debit'      => 0,
                'credit'     => round($totalPartsAed, 2),
                'narration'  => 'Parts / Stones Sales',
            ];
        }

        // VAT OUTPUT LIABILITY
        if ($totalVatAed > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $getAccount('511002'),
                'debit'      => 0,
                'credit'     => round($totalVatAed, 2),
                'narration'  => 'VAT Output',
            ];
        }

        /* -------------------------------------------------
            3️⃣ SAVE & VERIFY
        --------------------------------------------------*/
        foreach ($entries as $entry) {
            AccountingEntry::create($entry);
        }

        $debit  = collect($entries)->sum('debit');
        $credit = collect($entries)->sum('credit');

        if (round($debit,2) !== round($credit,2)) {
            throw new \Exception("Journal imbalance DR {$debit} CR {$credit}");
        }

        DB::commit();

        return $voucher;
    }
    // protected function createSaleAccountingEntries($invoice, $totalMaterialAed, $totalMakingAed, $totalPartsAed, $totalVatAed)
    // {
    //     $getAccountByCode = function ($code) {
    //         $account = ChartOfAccounts::where('account_code', $code)->first();
    //         if (!$account) {
    //             throw new \Exception("Account code {$code} not found in Chart of Accounts");
    //         }
    //         return $account->id;
    //     };

    //     $voucher = Voucher::create([
    //         'voucher_no'     => Voucher::generateVoucherNo('sale'),
    //         'voucher_type'   => 'sale',
    //         'voucher_date'   => $invoice->invoice_date,
    //         'reference_type' => 'App\Models\SaleInvoice',
    //         'reference_id'   => $invoice->id,
    //         'ac_dr_sid'      => null,
    //         'ac_cr_sid'      => null,
    //         'amount'         => null,
    //         'remarks'        => 'Sale Invoice #' . $invoice->invoice_no,
    //         'created_by'     => auth()->id(),
    //     ]);

    //     $entries = [];

    //     // Total sale revenue = material + making + parts
    //     $totalRevenueValue  = $totalMaterialAed + $totalMakingAed + $totalPartsAed;
    //     $totalReceivable    = $totalRevenueValue + $totalVatAed;

    //     // DEBIT: Receivable / Payment account
    //     switch ($invoice->payment_method) {
    //         case 'credit':
    //             $entries[] = [
    //                 'voucher_id' => $voucher->id,
    //                 'account_id' => $invoice->customer_id,
    //                 'debit'      => round($totalReceivable, 2),
    //                 'credit'     => 0,
    //                 'narration'  => 'Sale on Credit',
    //             ];
    //             break;

    //         case 'cash':
    //             $entries[] = [
    //                 'voucher_id' => $voucher->id,
    //                 'account_id' => $getAccountByCode('101001'),
    //                 'debit'      => round($totalReceivable, 2),
    //                 'credit'     => 0,
    //                 'narration'  => 'Cash Receipt',
    //             ];
    //             break;

    //         case 'cheque':
    //             if (!$invoice->bank_name) {
    //                 throw new \Exception('Bank account is required for cheque payment');
    //             }
    //             $entries[] = [
    //                 'voucher_id' => $voucher->id,
    //                 'account_id' => $invoice->bank_name,
    //                 'debit'      => round($totalReceivable, 2),
    //                 'credit'     => 0,
    //                 'narration'  => 'Cheque Receipt #' . $invoice->cheque_no,
    //             ];
    //             break;

    //         case 'bank_transfer':
    //             if (!$invoice->transfer_from_bank) {
    //                 throw new \Exception('Transfer bank is required for bank transfer');
    //             }
    //             $entries[] = [
    //                 'voucher_id' => $voucher->id,
    //                 'account_id' => $invoice->transfer_from_bank,
    //                 'debit'      => round($totalReceivable, 2),
    //                 'credit'     => 0,
    //                 'narration'  => 'Bank Transfer #' . $invoice->transaction_id,
    //             ];
    //             break;

    //         case 'material+making cost':
    //             $entries[] = [
    //                 'voucher_id' => $voucher->id,
    //                 'account_id' => $invoice->customer_id,
    //                 'debit'      => round($totalReceivable, 2),
    //                 'credit'     => 0,
    //                 'narration'  => 'Material Received from ' . ($invoice->material_given_by ?? 'Customer'),
    //             ];
    //             break;

    //         default:
    //             throw new \Exception('Invalid payment method: ' . $invoice->payment_method);
    //     }

    //     // CREDIT: Sales Revenue
    //     $materialType     = $invoice->items->first()->material_type;
    //     $revenueAccountCode = $materialType === 'gold' ? '401001' : '401002'; // adjust to your COA

    //     if ($totalRevenueValue > 0) {
    //         $entries[] = [
    //             'voucher_id' => $voucher->id,
    //             'account_id' => $getAccountByCode($revenueAccountCode),
    //             'debit'      => 0,
    //             'credit'     => round($totalRevenueValue, 2),
    //         ];
    //     }

    //     // CREDIT: VAT Output Tax
    //     if ($totalVatAed > 0) {
    //         $entries[] = [
    //             'voucher_id' => $voucher->id,
    //             'account_id' => $getAccountByCode('511002'), // VAT Output account — adjust to your COA
    //             'debit'      => 0,
    //             'credit'     => round($totalVatAed, 2),
    //             'narration'  => 'VAT Output Tax',
    //         ];
    //     }

    //     foreach ($entries as $entry) {
    //         AccountingEntry::create($entry);
    //     }

    //     $totalDebits  = collect($entries)->sum('debit');
    //     $totalCredits = collect($entries)->sum('credit');

    //     if (round($totalDebits, 2) !== round($totalCredits, 2)) {
    //         throw new \Exception("Accounting entry imbalance: Debits ({$totalDebits}) ≠ Credits ({$totalCredits})");
    //     }

    //     Log::info('Sale Accounting Entries Created', [
    //         'invoice_no'    => $invoice->invoice_no,
    //         'voucher_no'    => $voucher->voucher_no,
    //         'material'      => $totalMaterialAed,
    //         'making'        => $totalMakingAed,
    //         'parts'         => $totalPartsAed,
    //         'vat'           => $totalVatAed,
    //         'total_debit'   => $totalDebits,
    //         'total_credit'  => $totalCredits,
    //     ]);

    //     return $voucher;
    // }
}