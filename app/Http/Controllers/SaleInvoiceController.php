<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleInvoiceItemPart;
use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\AccountingEntry;
use App\Models\Purity;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class SaleInvoiceController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    public function index()
    {
        $invoices = SaleInvoice::with('customer', 'attachments')->get();
        return view('sales.index', compact('invoices'));
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create()
    {
        $customers = ChartOfAccounts::where('account_type', 'customer')->get();
        $banks     = ChartOfAccounts::where('account_type', 'bank')->get();
        $products  = Product::with('measurementUnit')->get();
        $purities  = Purity::all();

        return view('sales.create', compact('products', 'customers', 'banks', 'purities'));
    }

    // =========================================================================
    // BARCODE SCAN — Ajax endpoint
    // =========================================================================

    public function scanBarcode(Request $request)
    {
        $barcode = trim($request->get('barcode'));

        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'No barcode provided.'], 422);
        }

        // 1. Look in sale_invoice_items first (previously sold items)
        $saleItem = SaleInvoiceItem::with('parts')
            ->where('barcode_number', $barcode)
            ->latest()
            ->first();

        if ($saleItem) {
            return response()->json([
                'success'          => true,
                'source'           => 'sale',
                'barcode_number'   => $saleItem->barcode_number,
                'item_name'        => $saleItem->item_name,
                'item_description' => $saleItem->item_description,
                'purity'           => $saleItem->purity,
                'gross_weight'     => $saleItem->gross_weight,
                'making_rate'      => $saleItem->making_rate,
                'material_type'    => $saleItem->material_type,
                'vat_percent'      => $saleItem->vat_percent,
                'parts'            => $saleItem->parts->map(fn($p) => [
                    'item_name'        => $p->item_name,
                    'part_description' => $p->part_description,
                    'qty'              => $p->qty,
                    'rate'             => $p->rate,
                    'stone_qty'        => $p->stone_qty,
                    'stone_rate'       => $p->stone_rate,
                    'total'            => $p->total,
                ])->values()->toArray(),
            ]);
        }

        // 2. Look in purchase_invoice_items
        $purchaseItem = PurchaseInvoiceItem::with('parts')
            ->where('barcode_number', $barcode)
            ->latest()
            ->first();

        if ($purchaseItem) {
            return response()->json([
                'success'          => true,
                'source'           => 'purchase',
                'barcode_number'   => $purchaseItem->barcode_number,
                'item_name'        => $purchaseItem->item_name,
                'item_description' => $purchaseItem->item_description,
                'purity'           => $purchaseItem->purity,
                'gross_weight'     => $purchaseItem->gross_weight,
                'making_rate'      => $purchaseItem->making_rate,
                'material_type'    => $purchaseItem->material_type,
                'vat_percent'      => $purchaseItem->vat_percent,
                'parts'            => $purchaseItem->parts->map(fn($p) => [
                    'item_name'        => $p->item_name,
                    'part_description' => $p->part_description,
                    'qty'              => $p->qty,
                    'rate'             => $p->rate,
                    'stone_qty'        => $p->stone_qty,
                    'stone_rate'       => $p->stone_rate,
                    'total'            => $p->total,
                ])->values()->toArray(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Barcode "' . $barcode . '" not found in any record.',
        ], 404);
    }

    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request)
    {
        $this->clearIrrelevantPaymentFields($request);
        $this->validateInvoice($request);

        try {
            DB::beginTransaction();

            $isTaxable   = $request->boolean('is_taxable');
            $prefix      = $isTaxable ? 'SAL-TAX-' : 'SAL-';
            $lastInvoice = SaleInvoice::withTrashed()
                ->where('invoice_no', 'LIKE', $prefix . '%')
                ->orderBy('id', 'desc')
                ->first();
            $nextNumber = $lastInvoice ? ((int) str_replace($prefix, '', $lastInvoice->invoice_no)) + 1 : 1;
            $invoiceNo  = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            $invoice = SaleInvoice::create([
                'invoice_no'               => $invoiceNo,
                'is_taxable'               => $isTaxable,
                'customer_id'              => $request->customer_id,
                'invoice_date'             => $request->invoice_date,
                'remarks'                  => $request->remarks,
                'currency'                 => $request->currency,
                'exchange_rate'            => $request->exchange_rate,
                'gold_rate_usd'            => $request->gold_rate_usd,
                'gold_rate_aed_ounce'      => $request->gold_rate_aed_ounce,
                'gold_rate_aed'            => $request->gold_rate_aed,
                'diamond_rate_usd'         => $request->diamond_rate_usd,
                'diamond_rate_aed'         => $request->diamond_rate_aed,
                'purchase_gold_rate_aed'   => $request->purchase_gold_rate_aed,
                'purchase_making_rate_aed' => $request->purchase_making_rate_aed,
                'net_amount'               => 0,
                'net_amount_aed'           => 0,
                'payment_method'           => $request->payment_method,
                'payment_term'             => $request->payment_term,
                'bank_name'                => $request->bank_name,
                'cheque_no'                => $request->cheque_no,
                'cheque_date'              => $request->cheque_date,
                'cheque_amount'            => $request->cheque_amount,
                'transfer_from_bank'       => $request->transfer_from_bank,
                'transfer_to_bank'         => $request->transfer_to_bank,
                'account_title'            => $request->account_title,
                'account_no'               => $request->account_no,
                'transaction_id'           => $request->transaction_id,
                'transfer_date'            => $request->transfer_date,
                'transfer_amount'          => $request->transfer_amount,
                'material_received_by'     => $request->material_received_by,
                'material_given_by'        => $request->material_given_by,
                'created_by'               => auth()->id(),
            ]);

            [$totals] = $this->createItems($invoice, $request->items, $request);

            $calculatedNet    = $invoice->items()->sum('item_total');
            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $invoice->update([
                'net_amount'     => round($calculatedNet, 2),
                'net_amount_aed' => $calculatedNetAed,
            ]);

            $this->storeAttachments($request, $invoice);
            $this->createSaleAccountingEntries($invoice, $totals);

            DB::commit();

            return redirect()
                ->route('sale_invoices.index')
                ->with('success', 'Invoice #' . $invoiceNo . ' saved successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Sale Invoice Store Error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // EDIT
    // =========================================================================

    public function edit($id)
    {
        $saleInvoice = SaleInvoice::with(['items.parts', 'attachments'])->findOrFail($id);
        $purities    = Purity::all();
        $customers   = ChartOfAccounts::where('account_type', 'customer')->get();
        $banks       = ChartOfAccounts::where('account_type', 'bank')->get();
        $products    = Product::with('measurementUnit')->get();

        $goldAedOunce    = ($saleInvoice->gold_rate_aed ?? 0) * 31.1035;
        $diamondAedOunce = ($saleInvoice->diamond_rate_aed ?? 0) * 31.1035;

        $itemsData = $saleInvoice->items->map(function ($item) {
            return [
                'item_name'        => $item->item_name,
                'barcode_number'   => $item->barcode_number,
                'is_printed'       => $item->is_printed,
                'product_id'       => $item->product_id,
                'item_description' => $item->item_description,
                'purity'           => $item->purity,
                'gross_weight'     => $item->gross_weight,
                'making_rate'      => $item->making_rate,
                'material_type'    => $item->material_type,
                'vat_percent'      => $item->vat_percent,
                'purity_weight'    => $item->purity_weight,
                'col_995'          => $item->col_995,
                'making_value'     => $item->making_value,
                'material_value'   => $item->material_value,
                'taxable_amount'   => $item->taxable_amount,
                'vat_amount'       => $item->vat_amount,
                'item_total'       => $item->item_total,
                'parts' => $item->parts->map(function ($part) {
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
            'itemsData', 'goldAedOunce', 'diamondAedOunce', 'purities'
        ));
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        $invoice = SaleInvoice::findOrFail($id);

        $incomingBarcodes  = collect($request->items)->pluck('barcode_number')->filter()->values();
        $printedAndDeleted = $invoice->items()
            ->where('is_printed', true)
            ->whereNotIn('barcode_number', $incomingBarcodes->toArray())
            ->pluck('barcode_number');

        if ($printedAndDeleted->isNotEmpty() && !$request->boolean('confirm_delete_printed')) {
            return back()
                ->withInput()
                ->with('printed_delete_warning', $printedAndDeleted->join(', '));
        }

        $this->clearIrrelevantPaymentFields($request);
        $this->validateInvoice($request);

        try {
            DB::beginTransaction();

            $invoice->update([
                'is_taxable'               => $request->boolean('is_taxable'),
                'customer_id'              => $request->customer_id,
                'invoice_date'             => $request->invoice_date,
                'remarks'                  => $request->remarks,
                'currency'                 => $request->currency,
                'exchange_rate'            => $request->exchange_rate,
                'gold_rate_usd'            => $request->gold_rate_usd,
                'gold_rate_aed_ounce'      => $request->gold_rate_aed_ounce,
                'gold_rate_aed'            => $request->gold_rate_aed,
                'diamond_rate_usd'         => $request->diamond_rate_usd,
                'diamond_rate_aed'         => $request->diamond_rate_aed,
                'purchase_gold_rate_aed'   => $request->purchase_gold_rate_aed,
                'purchase_making_rate_aed' => $request->purchase_making_rate_aed,
                'payment_method'           => $request->payment_method,
                'payment_term'             => $request->payment_term,
                'bank_name'                => $request->bank_name,
                'cheque_no'                => $request->cheque_no,
                'cheque_date'              => $request->cheque_date,
                'cheque_amount'            => $request->cheque_amount,
                'transfer_from_bank'       => $request->transfer_from_bank,
                'transfer_to_bank'         => $request->transfer_to_bank,
                'account_title'            => $request->account_title,
                'account_no'               => $request->account_no,
                'transaction_id'           => $request->transaction_id,
                'transfer_date'            => $request->transfer_date,
                'transfer_amount'          => $request->transfer_amount,
                'material_received_by'     => $request->material_received_by,
                'material_given_by'        => $request->material_given_by,
            ]);

            foreach ($invoice->items as $oldItem) {
                $oldItem->parts()->delete();
            }
            $invoice->items()->delete();

            [$totals] = $this->createItems($invoice, $request->items, $request, preservePrinted: true);

            $calculatedNet    = $invoice->items()->sum('item_total');
            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $invoice->update([
                'net_amount'     => round($calculatedNet, 2),
                'net_amount_aed' => $calculatedNetAed,
            ]);

            $this->storeAttachments($request, $invoice);

            $oldVoucher = Voucher::where('reference_type', SaleInvoice::class)
                ->where('reference_id', $invoice->id)
                ->first();
            if ($oldVoucher) {
                AccountingEntry::where('voucher_id', $oldVoucher->id)->delete();
                $oldVoucher->delete();
            }

            $this->createSaleAccountingEntries($invoice, $totals);

            DB::commit();

            return redirect()
                ->route('sale_invoices.index')
                ->with('success', 'Invoice #' . $invoice->invoice_no . ' updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Sale Invoice Update Error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PRINT
    // =========================================================================

    public function print($id)
    {
        $invoice = SaleInvoice::with([
            'customer',
            'items',
            'items.product.measurementUnit',
            'items.parts',
            'items.parts.product.measurementUnit',
            'bank',
            'transferBank',
            'vouchers.entries.account',
        ])->findOrFail($id);

        $totalMaterialAed = $invoice->items->sum('material_value');
        $totalMakingAed   = $invoice->items->sum('making_value');
        $totalVatAed      = $invoice->items->sum('vat_amount');
        $totalTaxableAed  = $invoice->items->sum('taxable_amount');

        $totalDiamondVal = $invoice->items->sum(function ($item) {
            return $item->parts->sum(fn($p) => $p->qty * $p->rate);
        });
        $totalStoneVal = $invoice->items->sum(function ($item) {
            return $item->parts->sum(fn($p) => ($p->stone_qty ?? 0) * ($p->stone_rate ?? 0));
        });
        $totalPartsAed = $invoice->items->sum(function ($item) {
            return $item->parts->sum('total');
        });

        $totalCurrencyPayable = $totalMakingAed + $totalPartsAed + $totalVatAed;

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetTitle($invoice->invoice_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.2);

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

        $goldRateUsdOz      = $invoice->gold_rate_usd        ?? 0;
        $goldRateAedOz      = $invoice->gold_rate_aed_ounce  ?? 0;
        $diamondRateDisplay = $invoice->currency === 'USD' ? $invoice->diamond_rate_usd : $invoice->diamond_rate_aed;

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
                        <tr><td><b>Gold Rate (USD/oz)</b></td><td>' . number_format($goldRateUsdOz, 2)  . '</td></tr>
                        <tr><td><b>Gold Rate (AED/oz)</b></td><td>' . number_format($goldRateAedOz, 2)  . '</td></tr>
                        <tr><td><b>Gold Rate (AED/g)</b></td><td>'  . number_format($invoice->gold_rate_aed, 4) . '</td></tr>
                        <tr><td><b>Diamond Rate (' . $invoice->currency . '/Ct)</b></td><td>' . number_format($diamondRateDisplay, 2) . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>';
        $pdf->writeHTML($customerHtml, true, false, false, false);

        // Items table
        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="3%"  rowspan="2">#</th>
                    <th width="10%" rowspan="2">Item Name</th>
                    <th width="10%" rowspan="2">Description</th>
                    <th width="6%"  rowspan="2">Gross Wt</th>
                    <th width="6%"  rowspan="2">Purity</th>
                    <th width="6%"  rowspan="2">Purity Wt</th>
                    <th width="6%"  rowspan="2">995</th>
                    <th width="13%" colspan="2">Making</th>
                    <th width="7%"  rowspan="2">Material</th>
                    <th width="8%"  rowspan="2">Material Val</th>
                    <th width="6%"  rowspan="2">MC</th>
                    <th width="5%"  rowspan="2">VAT%</th>
                    <th width="7%"  rowspan="2">Item Total</th>
                </tr>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="6%">Rate</th>
                    <th width="7%">Value</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($invoice->items as $index => $item) {
            $hasParts    = $item->parts && $item->parts->count() > 0;
            $productTotal = $item->item_total;

            $html .= '
                <tr style="text-align:center;background-color:#ffffff;">
                    <td width="3%">' . ($index + 1) . '</td>
                    <td width="10%">' . ($item->item_name ?: ($item->product->name ?? '-')) . '</td>
                    <td width="10%">' . ($item->item_description ?? '-') . '</td>
                    <td width="6%">' . number_format($item->gross_weight, 3) . '</td>
                    <td width="6%">' . number_format($item->purity, 3) . '</td>
                    <td width="6%">' . number_format($item->purity_weight, 3) . '</td>
                    <td width="6%">' . number_format($item->col_995 ?? 0, 3) . '</td>
                    <td width="6%">' . number_format($item->making_rate ?? 0, 2) . '</td>
                    <td width="7%">' . number_format($item->making_value, 2) . '</td>
                    <td width="7%">' . ucfirst($item->material_type) . '</td>
                    <td width="8%">' . number_format($item->material_value, 2) . '</td>
                    <td width="6%">' . number_format($item->taxable_amount, 2) . '</td>
                    <td width="5%">' . number_format($item->vat_percent, 0) . '%</td>
                    <td width="7%" style="font-weight:bold;">' . number_format($item->item_total, 2) . '</td>
                </tr>';

            if ($hasParts) {
                $html .= '<tr style="background-color:#f9f9f9;font-style:italic;font-size:7px;">
                            <td></td><td colspan="13"><b>Parts Detail:</b></td>
                          </tr>';

                foreach ($item->parts as $part) {
                    $displayPartName = $part->item_name ?: ($part->product->name ?? 'Part');
                    $html .= '
                    <tr style="font-size:7px;background-color:#fcfcfc;text-align:center;">
                        <td width="3%"></td>
                        <td width="10%" style="text-align:left;">' . $displayPartName . '</td>
                        <td width="10%" style="text-align:left;">' . htmlspecialchars($part->part_description ?? '') . '</td>
                        <td width="6%">' . number_format($part->qty, 3) . ' Ct</td>
                        <td width="6%">Rate:' . number_format($part->rate, 2) . '</td>
                        <td width="6%">St.' . number_format($part->stone_qty ?? 0, 2) . '</td>
                        <td width="6%">SR:' . number_format($part->stone_rate ?? 0, 2) . '</td>
                        <td width="6%" colspan="2"></td>
                        <td width="7%"></td>
                        <td width="8%"></td>
                        <td width="6%"></td>
                        <td width="5%"></td>
                        <td width="7%" style="font-weight:bold;">' . number_format($part->total, 2) . '</td>
                    </tr>';
                }

                $html .= '
                    <tr style="background-color:#eeeeee;font-weight:bold;font-size:8px;">
                        <td colspan="13" align="right">Product Grand Total (Material + MC + Parts + VAT):</td>
                        <td align="right">' . number_format($productTotal, 2) . '</td>
                    </tr>';
            }
        }

        $html .= '
                <tr style="font-weight:bold;background-color:#f5f5f5;">
                    <td colspan="13" align="right">Net Invoice Amount</td>
                    <td align="right">' . number_format($invoice->net_amount, 2) . '</td>
                </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, false, false);

        $aedAmount = $invoice->currency === 'USD' ? $invoice->net_amount_aed : $invoice->net_amount;

        // Summary
        $summaryHtml = '
        <table width="100%" cellpadding="0" border="0" style="margin-top:10px;">
            <tr>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td><b>Payment Details</b></td><td><b>Value</b></td></tr>
                        <tr><td>Method</td><td>' . ucfirst($invoice->payment_method) . '</td></tr>';

        if ($invoice->payment_method === 'credit') {
            $summaryHtml .= '<tr><td>Payment Term</td><td>' . ($invoice->payment_term ?? '-') . '</td></tr>';
        }
        if ($invoice->payment_method === 'cheque') {
            $summaryHtml .= '
            <tr><td>Bank Name</td><td>'   . ($invoice->bank->name ?? '-') . '</td></tr>
            <tr><td>Cheque No</td><td>'   . ($invoice->cheque_no ?? '-') . '</td></tr>
            <tr><td>Cheque Date</td><td>' . ($invoice->cheque_date ? Carbon::parse($invoice->cheque_date)->format('d.m.Y') : '-') . '</td></tr>';
        }
        if ($invoice->payment_method === 'bank_transfer') {
            $summaryHtml .= '
            <tr><td>From Bank</td><td>'       . ($invoice->transferBank->name ?? '-') . '</td></tr>
            <tr><td>Customer Bank</td><td>'   . ($invoice->transfer_to_bank ?? '-') . '</td></tr>
            <tr><td>Account Title</td><td>'   . ($invoice->account_title ?? '-') . '</td></tr>
            <tr><td>Account No</td><td>'      . ($invoice->account_no ?? '-') . '</td></tr>
            <tr><td>Transfer Date</td><td>'   . ($invoice->transfer_date ? Carbon::parse($invoice->transfer_date)->format('d.m.Y') : '-') . '</td></tr>
            <tr><td>Transaction Ref</td><td>' . ($invoice->transaction_id ?? '-') . '</td></tr>
            <tr><td>Transfer Amount</td><td>' . number_format($invoice->transfer_amount ?? 0, 2) . '</td></tr>';
        }
        if (str_contains($invoice->payment_method, 'material')) {
            $totalPureWeight = $invoice->items->sum('purity_weight');
            $summaryHtml .= '
            <tr><td>Material Given By</td><td>'    . ($invoice->material_given_by ?? '-') . '</td></tr>
            <tr><td>Material Received By</td><td>' . ($invoice->material_received_by ?? '-') . '</td></tr>
            <tr><td>Total Pure Weight</td><td>'    . number_format($totalPureWeight, 3) . ' gms</td></tr>
            <tr><td>Making Charges</td><td>'       . number_format($totalMakingAed, 2) . ' AED</td></tr>';
        }

        $summaryHtml .= '</table>
                </td>
                <td width="10%"></td>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td colspan="2" align="center"><b>Summary (' . $invoice->currency . ')</b></td></tr>
                        <tr><td width="60%">Material Value</td>              <td width="40%" align="right">' . number_format($totalMaterialAed, 2) . '</td></tr>
                        <tr><td>Diamond Parts Val.</td>                      <td align="right">' . number_format($totalDiamondVal, 2) . '</td></tr>
                        <tr><td>Stone Parts Val.</td>                        <td align="right">' . number_format($totalStoneVal, 2) . '</td></tr>
                        <tr><td>Making Charges (MC)</td>                     <td align="right">' . number_format($totalMakingAed, 2) . '</td></tr>
                        <tr><td>Total VAT (on MC)</td>                       <td align="right">' . number_format($totalVatAed, 2) . '</td></tr>
                        <tr style="font-weight:bold;background-color:#ddeeee;">
                            <td>Currency Payable (MC + Parts + VAT)</td>
                            <td align="right">' . number_format($totalCurrencyPayable, 2) . '</td>
                        </tr>
                        <tr style="font-weight:bold;background-color:#eeeeee;">
                            <td>Invoice Total</td>
                            <td align="right">' . number_format($invoice->net_amount, 2) . '</td>
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

        $remainingSpace = $pdf->getPageHeight() - $pdf->GetY() - $pdf->getBreakMargin();
        if ($remainingSpace < 70) {
            $pdf->AddPage();
        }

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $wordsAED = $pdf->convertCurrencyToWords($aedAmount, 'AED');
        if ($invoice->currency === 'USD') {
            $wordsUSD = $pdf->convertCurrencyToWords($invoice->net_amount, 'USD');
            $pdf->Cell(0, 5, 'Amount in Words (USD): ' . $wordsUSD, 0, 1, 'L');
            $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $wordsAED, 0, 1, 'L');
        } else {
            $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $wordsAED, 0, 1, 'L');
        }

        $pdf->Ln(2);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);
        $termsHtml = '
            <div style="line-height:8px;text-align:justify;color:#333;">
                <b>TERMS & CONDITIONS:</b> Goods sold on credit, if not paid when due, or in case of law suit arising there from,
                the purchaser agrees to pay the seller all expense of recovery, collection, etc., including attorney fees,
                legal expense and/or recovery-agent charges. <b>GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED.</b>
                Any dispute arising out of or in connection with this sale shall be subject to the exclusive jurisdiction of Dubai Courts.
            </div>';
        $pdf->writeHTML($termsHtml, true, false, false, false);

        $pdf->Ln(26);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 80, $y);
        $pdf->Line(130, $y, 190, $y);
        $pdf->SetXY(20, $y);
        $pdf->Cell(50, 5, "Customer's Signature", 0, 0, 'C');
        $pdf->SetXY(130, $y);
        $pdf->Cell(50, 5, "Authorized Signature", 0, 0, 'C');

        return $pdf->Output($invoice->invoice_no . '.pdf', 'I');
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy($id)
    {
        $invoice = SaleInvoice::findOrFail($id);

        DB::beginTransaction();
        try {
            $voucher = Voucher::where('reference_type', SaleInvoice::class)
                ->where('reference_id', $invoice->id)
                ->first();
            if ($voucher) {
                AccountingEntry::where('voucher_id', $voucher->id)->delete();
                $voucher->delete();
            }
            $invoice->delete();
            DB::commit();
            return redirect()->route('sale_invoices.index')->with('success', 'Invoice deleted.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function createItems(
        SaleInvoice $invoice,
        array $items,
        Request $request,
        int $startPosition = 1,
        bool $preservePrinted = false
    ): array {
        $totals = [
            'gold_material'    => 0.0,
            'diamond_material' => 0.0,
            'material'         => 0.0,
            'making'           => 0.0,
            'gold_parts'       => 0.0,
            'diamond_parts'    => 0.0,
            'diamond_val'      => 0.0,
            'stone_val'        => 0.0,
            'vat'              => 0.0,
        ];

        $position        = $startPosition;
        $goldRateAedGram = (float) ($request->gold_rate_aed    ?? 0);
        $diamondRateAed  = (float) ($request->diamond_rate_aed ?? 0);

        foreach ($items as $itemData) {
            $grossWeight = (float) ($itemData['gross_weight'] ?? 0);
            $purity      = (float) ($itemData['purity']       ?? 0);
            $makingRate  = (float) ($itemData['making_rate']  ?? 0);
            $vatPercent  = (float) ($itemData['vat_percent']  ?? 0);
            $matType     = $itemData['material_type'] ?? 'gold';

            // Same formulas as purchase:
            // purity_weight = gross_weight × purity
            // making_value  = gross_weight × making_rate
            // material_value = rate × purity_weight
            $purityWeight  = $grossWeight * $purity;
            $col995        = $purityWeight > 0 ? $purityWeight / 0.995 : 0;
            $makingValue   = $grossWeight * $makingRate;
            $rate          = $matType === 'gold' ? $goldRateAedGram : $diamondRateAed;
            $materialValue = $rate * $purityWeight;

            $partsData      = $itemData['parts'] ?? [];
            $partsTotal     = 0.0;
            $itemDiamondVal = 0.0;
            $itemStoneVal   = 0.0;

            foreach ($partsData as $partData) {
                $qty       = (float) ($partData['qty']        ?? 0);
                $partRate  = (float) ($partData['rate']       ?? 0);
                $stoneQty  = (float) ($partData['stone_qty']  ?? 0);
                $stoneRate = (float) ($partData['stone_rate'] ?? 0);

                $diaValue   = $qty      * $partRate;
                $stoneValue = $stoneQty * $stoneRate;
                $partTotal  = $diaValue + $stoneValue;

                $partsTotal     += $partTotal;
                $itemDiamondVal += $diaValue;
                $itemStoneVal   += $stoneValue;
            }

            // VAT on making only (same as purchase)
            $taxableAmount = $makingValue;
            $vatAmount     = $taxableAmount * ($vatPercent / 100);
            $itemTotal     = $materialValue + $makingValue + $partsTotal + $vatAmount;

            $existingBarcode   = $itemData['barcode_number'] ?? null;
            $wasAlreadyPrinted = false;
            if ($preservePrinted && $existingBarcode) {
                $wasAlreadyPrinted = SaleInvoiceItem::where('barcode_number', $existingBarcode)
                    ->value('is_printed') ?? false;
            }

            $invoiceItem = $invoice->items()->create([
                'item_name'        => $itemData['item_name']        ?? null,
                'product_id'       => $itemData['product_id']       ?? null,
                'item_description' => $itemData['item_description'] ?? null,
                'gross_weight'     => $grossWeight,
                'purity'           => $purity,
                'purity_weight'    => round($purityWeight, 4),
                'col_995'          => round($col995, 4),
                'making_rate'      => $makingRate,
                'making_value'     => round($makingValue, 2),
                'material_type'    => $matType,
                'material_rate'    => $rate,
                'material_value'   => round($materialValue, 2),
                'parts_total'      => round($partsTotal, 2),
                'taxable_amount'   => round($taxableAmount, 2),
                'vat_percent'      => $vatPercent,
                'vat_amount'       => round($vatAmount, 2),
                'item_total'       => round($itemTotal, 2),
                'barcode_number'   => $existingBarcode,
                'is_printed'       => $wasAlreadyPrinted,
            ]);

            foreach ($partsData as $partData) {
                $qty       = (float) ($partData['qty']        ?? 0);
                $partRate  = (float) ($partData['rate']       ?? 0);
                $stoneQty  = (float) ($partData['stone_qty']  ?? 0);
                $stoneRate = (float) ($partData['stone_rate'] ?? 0);
                $partTotal = ($qty * $partRate) + ($stoneQty * $stoneRate);

                $invoiceItem->parts()->create([
                    'product_id'       => $partData['product_id']       ?? null,
                    'item_name'        => $partData['item_name']        ?? null,
                    'part_description' => $partData['part_description'] ?? null,
                    'qty'              => $qty,
                    'rate'             => $partRate,
                    'stone_qty'        => $stoneQty,
                    'stone_rate'       => $stoneRate,
                    'total'            => round($partTotal, 2),
                ]);
            }

            if ($matType === 'gold') {
                $totals['gold_material'] += $materialValue;
                $totals['gold_parts']    += $partsTotal;
            } else {
                $totals['diamond_material'] += $materialValue;
                $totals['diamond_parts']    += $partsTotal;
            }
            $totals['material']    += $materialValue;
            $totals['making']      += $makingValue;
            $totals['diamond_val'] += $itemDiamondVal;
            $totals['stone_val']   += $itemStoneVal;
            $totals['vat']         += $vatAmount;

            $position++;
        }

        return [$totals, $position];
    }

    private function storeAttachments(Request $request, SaleInvoice $invoice): void
    {
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('sale_invoices', 'public');
                $invoice->attachments()->create(['file_path' => $path]);
            }
        }
    }

    private function clearIrrelevantPaymentFields(Request $request): void
    {
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
    }

    private function validateInvoice(Request $request): void
    {
        $request->validate([
            'is_taxable'             => 'required|boolean',
            'customer_id'            => 'required|exists:chart_of_accounts,id',
            'invoice_date'           => 'required|date',
            'currency'               => 'required|in:AED,USD',
            'exchange_rate'          => 'nullable|required_if:currency,USD|numeric|min:0',
            'net_amount'             => 'required|numeric|min:0',
            'payment_method'         => 'required|in:credit,cash,cheque,bank_transfer,material+making cost',
            'payment_term'           => 'nullable|string',
            'gold_rate_usd'          => 'nullable|numeric|min:0',
            'gold_rate_aed_ounce'    => 'nullable|numeric|min:0',
            'gold_rate_aed'          => 'nullable|numeric|min:0',
            'diamond_rate_usd'       => 'nullable|numeric|min:0',
            'diamond_rate_aed'       => 'nullable|numeric|min:0',
            'purchase_gold_rate_aed' => 'nullable|numeric|min:0',
            'purchase_making_rate_aed' => 'nullable|numeric|min:0',
            'bank_name'              => 'nullable|required_if:payment_method,cheque|exists:chart_of_accounts,id',
            'cheque_no'              => 'nullable|required_if:payment_method,cheque|string',
            'cheque_date'            => 'nullable|required_if:payment_method,cheque|date',
            'cheque_amount'          => 'nullable|required_if:payment_method,cheque|numeric|min:0',
            'transfer_from_bank'     => 'nullable|required_if:payment_method,bank_transfer|exists:chart_of_accounts,id',
            'transfer_to_bank'       => 'nullable|string',
            'account_title'          => 'nullable|string',
            'account_no'             => 'nullable|string',
            'transaction_id'         => 'nullable|string',
            'transfer_date'          => 'nullable|required_if:payment_method,bank_transfer|date',
            'transfer_amount'        => 'nullable|required_if:payment_method,bank_transfer|numeric|min:0',
            'items'                  => 'required|array|min:1',
            'items.*.item_name'      => 'nullable|string|required_without:items.*.product_id',
            'items.*.product_id'     => 'nullable|exists:products,id|required_without:items.*.item_name',
            'items.*.gross_weight'   => 'required|numeric|min:0',
            'items.*.purity'         => 'required|numeric|min:0|max:1',
            'items.*.making_rate'    => 'required|numeric|min:0',
            'items.*.material_type'  => 'required|in:gold,diamond',
            'items.*.vat_percent'    => 'required|numeric|min:0',
            'material_given_by'      => 'nullable|required_if:payment_method,material+making cost|string',
            'material_received_by'   => 'nullable|required_if:payment_method,material+making cost|string',
        ]);
    }

    /**
     * Create accounting voucher + double-entry lines for a sale invoice.
     *
     * CREDIT entries (revenue):
     *   400001  Gold Revenue
     *   400002  Diamond Revenue
     *   400003  Making Charges Revenue
     *   200001  Output VAT Payable
     *
     * DEBIT entries (what we receive):
     *   credit       → customer AR (full amount)
     *   cash         → 101001 Cash
     *   cheque       → bank account
     *   bank_transfer→ transfer bank
     *   material+MC  → 104001 Gold Inventory (material) + customer AR (making+vat)
     */
    protected function createSaleAccountingEntries(SaleInvoice $invoice, array $totals): Voucher
    {
        $acct = function (string $code) use ($invoice): int {
            $account = ChartOfAccounts::where('account_code', $code)->first();
            if (!$account) {
                throw new \Exception(
                    "Account code [{$code}] not found in Chart of Accounts (Invoice #{$invoice->invoice_no})."
                );
            }
            return $account->id;
        };

        $isMaterial = str_contains($invoice->payment_method, 'material');

        $voucher = Voucher::create([
            'voucher_no'     => Voucher::generateVoucherNo('sale'),
            'voucher_type'   => 'sale',
            'voucher_date'   => $invoice->invoice_date,
            'reference_type' => SaleInvoice::class,
            'reference_id'   => $invoice->id,
            'ac_dr_sid'      => null,
            'ac_cr_sid'      => null,
            'amount'         => null,
            'remarks'        => 'Sale Invoice #' . $invoice->invoice_no
                                . ($isMaterial ? ' [Metal Receipt + Currency Collection]' : ''),
            'created_by'     => auth()->id(),
        ]);

        $entries = [];

        // ── CREDIT entries (revenue) ──────────────────────────────────────────
        $goldRev    = round($totals['gold_material']    + $totals['gold_parts'],    2);
        $diamondRev = round($totals['diamond_material'] + $totals['diamond_parts'], 2);

        if ($goldRev > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('400001'),
                'debit'      => 0,
                'credit'     => $goldRev,
                'narration'  => 'Gold material + parts revenue — Inv# ' . $invoice->invoice_no,
            ];
        }

        if ($diamondRev > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('400002'),
                'debit'      => 0,
                'credit'     => $diamondRev,
                'narration'  => 'Diamond material + parts revenue — Inv# ' . $invoice->invoice_no,
            ];
        }

        if ($totals['making'] > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('400003'),
                'debit'      => 0,
                'credit'     => round($totals['making'], 2),
                'narration'  => 'Making charges revenue — Inv# ' . $invoice->invoice_no,
            ];
        }

        if ($totals['vat'] > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('200001'),
                'debit'      => 0,
                'credit'     => round($totals['vat'], 2),
                'narration'  => 'Output VAT payable — Inv# ' . $invoice->invoice_no,
            ];
        }

        $totalCredit = round(collect($entries)->sum('credit'), 2);

        if ($totalCredit <= 0) {
            throw new \Exception(
                "Invoice #{$invoice->invoice_no} has zero accounting value — no entries created."
            );
        }

        // ── Debit split ───────────────────────────────────────────────────────
        $materialDebit  = round($totals['gold_material'] + $totals['diamond_material'], 2);
        $currencyDebit  = round($totalCredit - $materialDebit, 2);

        // ── DEBIT entries ─────────────────────────────────────────────────────
        switch ($invoice->payment_method) {

            case 'credit':
                if ($materialDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $materialDebit,
                        'credit'     => 0,
                        'narration'  => 'Material receivable from customer (credit sale) — Inv# ' . $invoice->invoice_no,
                    ];
                }
                if ($currencyDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $currencyDebit,
                        'credit'     => 0,
                        'narration'  => 'MC + parts + VAT receivable from customer (credit) — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;

            case 'cash':
                if ($materialDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $materialDebit,
                        'credit'     => 0,
                        'narration'  => 'Material receivable from customer (cash sale) — Inv# ' . $invoice->invoice_no,
                    ];
                }
                if ($currencyDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $acct('101001'),
                        'debit'      => $currencyDebit,
                        'credit'     => 0,
                        'narration'  => 'Cash received for MC + parts + VAT — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;

            case 'cheque':
                if (!$invoice->bank_name) {
                    throw new \Exception(
                        'Bank account required for cheque payment (Inv# ' . $invoice->invoice_no . ').'
                    );
                }
                if ($materialDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $materialDebit,
                        'credit'     => 0,
                        'narration'  => 'Material receivable from customer (cheque) — Inv# ' . $invoice->invoice_no,
                    ];
                }
                if ($currencyDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->bank_name,
                        'debit'      => $currencyDebit,
                        'credit'     => 0,
                        'narration'  => 'Cheque #' . $invoice->cheque_no . ' received for MC + parts + VAT — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;

            case 'bank_transfer':
                if (!$invoice->transfer_from_bank) {
                    throw new \Exception(
                        'Transfer-from bank required for bank transfer (Inv# ' . $invoice->invoice_no . ').'
                    );
                }
                if ($materialDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $materialDebit,
                        'credit'     => 0,
                        'narration'  => 'Material receivable from customer (bank transfer) — Inv# ' . $invoice->invoice_no,
                    ];
                }
                if ($currencyDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->transfer_from_bank,
                        'debit'      => $currencyDebit,
                        'credit'     => 0,
                        'narration'  => 'Bank transfer Ref# ' . $invoice->transaction_id
                                        . ' received for MC + parts + VAT — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;

            case 'material+making cost':
                // Customer hands over their gold inventory as part payment.
                // Material portion: we receive gold → DR 104001 Gold Inventory
                // Currency portion: making + parts + vat → DR customer AR
                if ($materialDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $acct('104001'),
                        'debit'      => $materialDebit,
                        'credit'     => 0,
                        'narration'  => 'Gold inventory received from customer as material payment'
                                        . ' (' . ($invoice->material_received_by ?? 'customer') . ')'
                                        . ' — Inv# ' . $invoice->invoice_no,
                    ];
                }
                if ($currencyDebit > 0) {
                    $entries[] = [
                        'voucher_id' => $voucher->id,
                        'account_id' => $invoice->customer_id,
                        'debit'      => $currencyDebit,
                        'credit'     => 0,
                        'narration'  => 'Currency receivable — MC + parts + VAT outstanding from customer'
                                        . ' — Inv# ' . $invoice->invoice_no,
                    ];
                }
                break;

            default:
                throw new \Exception('Unrecognised payment method: "' . $invoice->payment_method . '"');
        }

        foreach ($entries as $entry) {
            AccountingEntry::create($entry);
        }

        // ── Balance check ─────────────────────────────────────────────────────
        $sumDebits  = round(collect($entries)->sum('debit'),  2);
        $sumCredits = round(collect($entries)->sum('credit'), 2);

        if ($sumDebits !== $sumCredits) {
            throw new \Exception(
                "Accounting imbalance on Invoice #{$invoice->invoice_no}: " .
                "Debits {$sumDebits} ≠ Credits {$sumCredits}."
            );
        }

        Log::info('Sale accounting entries created', [
            'invoice_no'          => $invoice->invoice_no,
            'voucher_no'          => $voucher->voucher_no,
            'payment_method'      => $invoice->payment_method,
            'cr_400001_gold'      => $goldRev,
            'cr_400002_diamond'   => $diamondRev,
            'cr_400003_making'    => round($totals['making'], 2),
            'cr_200001_vat'       => round($totals['vat'], 2),
            'dr_material'         => $materialDebit,
            'dr_currency'         => $currencyDebit,
            'total_debit'         => $sumDebits,
            'total_credit'        => $sumCredits,
        ]);

        return $voucher;
    }
}