<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseReturnItemPart;
use App\Models\PurchaseInvoice;
use App\Models\Voucher;
use App\Models\AccountingEntry;
use App\Models\ChartOfAccounts;
use App\Models\Purity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class PurchaseReturnController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    public function index()
    {
        $returns = PurchaseReturn::with('vendor', 'purchaseInvoice')->latest()->get();
        return view('purchase_return.index', compact('returns'));
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create()
    {
        $invoices = PurchaseInvoice::with('vendor')->latest()->get();
        $vendors  = ChartOfAccounts::where('account_type', 'vendor')->get();
        $banks    = ChartOfAccounts::where('account_type', 'bank')->get();
        $purities = Purity::all();

        return view('purchase_return.create', compact('invoices', 'vendors', 'banks', 'purities'));
    }

    // =========================================================================
    // GET INVOICE ITEMS (AJAX)
    // =========================================================================

    public function getInvoiceItems($invoiceId)
    {
        $invoice = PurchaseInvoice::with(['items.parts', 'vendor'])->findOrFail($invoiceId);

        // ── Find all item IDs already returned against this invoice ──
        $alreadyReturnedItemIds = PurchaseReturnItem::whereIn(
            'purchase_invoice_item_id',
            $invoice->items->pluck('id')->toArray()
        )->pluck('purchase_invoice_item_id')->unique()->toArray();

        return response()->json([
            'success' => true,
            'invoice' => [
                'id'                  => $invoice->id,
                'invoice_no'          => $invoice->invoice_no,
                'currency'            => $invoice->currency,
                'exchange_rate'       => $invoice->exchange_rate,
                'gold_rate_usd'       => $invoice->gold_rate_usd,
                'gold_rate_aed_ounce' => $invoice->gold_rate_aed_ounce,
                'gold_rate_aed'       => $invoice->gold_rate_aed,
                'diamond_rate_usd'    => $invoice->diamond_rate_usd,
                'diamond_rate_aed'    => $invoice->diamond_rate_aed,
                'vendor_id'           => $invoice->vendor_id,
                'vendor_name'         => $invoice->vendor->name ?? '',
            ],
            'items' => $invoice->items->map(function ($item) use ($alreadyReturnedItemIds) {
                return [
                    'id'               => $item->id,
                    'item_name'        => $item->item_name,
                    'item_description' => $item->item_description,
                    'barcode_number'   => $item->barcode_number,
                    'purity'           => $item->purity,
                    'net_weight'       => $item->net_weight,
                    'gross_weight'     => $item->gross_weight,
                    'purity_weight'    => $item->purity_weight,
                    'col_995'          => $item->col_995,
                    'material_type'    => $item->material_type,
                    'material_rate'    => $item->material_rate,
                    'material_value'   => $item->material_value,
                    'making_rate'      => $item->making_rate,
                    'making_value'     => $item->making_value,
                    'taxable_amount'   => $item->taxable_amount,
                    'vat_percent'      => $item->vat_percent,
                    'vat_amount'       => $item->vat_amount,
                    'item_total'       => $item->item_total,
                    // ── Flag: has this item already been returned? ──
                    'already_returned' => in_array($item->id, $alreadyReturnedItemIds),
                    'parts'            => $item->parts->map(function ($part) {
                        return [
                            'item_name'             => $part->item_name,
                            'part_description'      => $part->part_description,
                            'qty'                   => $part->qty,
                            'rate'                  => $part->rate,
                            'stone_qty'             => $part->stone_qty,
                            'stone_rate'            => $part->stone_rate,
                            'certification_charges' => $part->certification_charges,
                            'total'                 => $part->total,
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray(),
        ]);
    }

    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request)
    {
        $this->clearIrrelevantRefundFields($request);
        $this->validateReturn($request);

        try {
            DB::beginTransaction();

            $returnNo = $this->generateReturnNo();

            $purchaseReturn = PurchaseReturn::create([
                'return_no'           => $returnNo,
                'purchase_invoice_id' => $request->purchase_invoice_id,
                'vendor_id'           => $request->vendor_id,
                'return_date'         => $request->return_date,
                'reason'              => $request->reason,
                'remarks'             => $request->remarks,
                'currency'            => $request->currency,
                'exchange_rate'       => $request->exchange_rate,
                'gold_rate_usd'       => $request->gold_rate_usd,
                'gold_rate_aed_ounce' => $request->gold_rate_aed_ounce,
                'gold_rate_aed'       => $request->gold_rate_aed,
                'diamond_rate_usd'    => $request->diamond_rate_usd,
                'diamond_rate_aed'    => $request->diamond_rate_aed,
                'net_amount'          => 0,
                'net_amount_aed'      => 0,
                'refund_method'       => $request->refund_method,
                'bank_name'           => $request->bank_name,
                'cheque_no'           => $request->cheque_no,
                'cheque_date'         => $request->cheque_date,
                'cheque_amount'       => $request->cheque_amount,
                'transfer_from_bank'  => $request->transfer_from_bank,
                'transfer_to_bank'    => $request->transfer_to_bank,
                'account_title'       => $request->account_title,
                'account_no'          => $request->account_no,
                'transaction_id'      => $request->transaction_id,
                'transfer_date'       => $request->transfer_date,
                'transfer_amount'     => $request->transfer_amount,
                'created_by'          => auth()->id(),
            ]);

            $totals = $this->createReturnItems($purchaseReturn, $request->items, $request);

            $calculatedNet    = $purchaseReturn->items()->sum('item_total');
            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $purchaseReturn->update([
                'total_material_value' => round($totals['material'], 2),
                'total_making_value'   => round($totals['making'],   2),
                'total_parts_value'    => round($totals['parts'],    2),
                'total_vat_amount'     => round($totals['vat'],      2),
                'net_amount'           => round($calculatedNet,      2),
                'net_amount_aed'       => $calculatedNetAed,
            ]);

            $this->createReturnAccountingEntries($purchaseReturn, $totals);

            DB::commit();

            return redirect()
                ->route('purchase_return.index')
                ->with('success', 'Return #' . $returnNo . ' created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Purchase Return Store Error', [
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
        $purchaseReturn = PurchaseReturn::with(['items.parts'])->findOrFail($id);
        $invoices       = PurchaseInvoice::with('vendor')->latest()->get();
        $vendors        = ChartOfAccounts::where('account_type', 'vendor')->get();
        $banks          = ChartOfAccounts::where('account_type', 'bank')->get();
        $purities       = Purity::all();

        $itemsData = $purchaseReturn->items->map(function ($item) {
            return [
                'id'                       => $item->id,
                'purchase_invoice_item_id' => $item->purchase_invoice_item_id,
                'item_name'                => $item->item_name,
                'item_description'         => $item->item_description,
                'barcode_number'           => $item->barcode_number,
                'purity'                   => $item->purity,
                'net_weight'               => $item->net_weight,
                'gross_weight'             => $item->gross_weight,
                'purity_weight'            => $item->purity_weight,
                'col_995'                  => $item->col_995,
                'material_type'            => $item->material_type,
                'material_rate'            => $item->material_rate,
                'material_value'           => $item->material_value,
                'making_rate'              => $item->making_rate,
                'making_value'             => $item->making_value,
                'taxable_amount'           => $item->taxable_amount,
                'vat_percent'              => $item->vat_percent,
                'vat_amount'               => $item->vat_amount,
                'item_total'               => $item->item_total,
                'parts' => $item->parts->map(function ($part) {
                    return [
                        'item_name'             => $part->item_name,
                        'part_description'      => $part->part_description,
                        'qty'                   => $part->qty,
                        'rate'                  => $part->rate,
                        'stone_qty'             => $part->stone_qty,
                        'stone_rate'            => $part->stone_rate,
                        'certification_charges' => $part->certification_charges,
                        'total'                 => $part->total,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        return view('purchase_return.edit', compact(
            'purchaseReturn', 'invoices', 'vendors', 'banks', 'purities', 'itemsData'
        ));
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        $purchaseReturn = PurchaseReturn::findOrFail($id);

        $this->clearIrrelevantRefundFields($request);
        $this->validateReturn($request);

        try {
            DB::beginTransaction();

            $purchaseReturn->update([
                'purchase_invoice_id' => $request->purchase_invoice_id,
                'vendor_id'           => $request->vendor_id,
                'return_date'         => $request->return_date,
                'reason'              => $request->reason,
                'remarks'             => $request->remarks,
                'currency'            => $request->currency,
                'exchange_rate'       => $request->exchange_rate,
                'gold_rate_usd'       => $request->gold_rate_usd,
                'gold_rate_aed_ounce' => $request->gold_rate_aed_ounce,
                'gold_rate_aed'       => $request->gold_rate_aed,
                'diamond_rate_usd'    => $request->diamond_rate_usd,
                'diamond_rate_aed'    => $request->diamond_rate_aed,
                'refund_method'       => $request->refund_method,
                'bank_name'           => $request->bank_name,
                'cheque_no'           => $request->cheque_no,
                'cheque_date'         => $request->cheque_date,
                'cheque_amount'       => $request->cheque_amount,
                'transfer_from_bank'  => $request->transfer_from_bank,
                'transfer_to_bank'    => $request->transfer_to_bank,
                'account_title'       => $request->account_title,
                'account_no'          => $request->account_no,
                'transaction_id'      => $request->transaction_id,
                'transfer_date'       => $request->transfer_date,
                'transfer_amount'     => $request->transfer_amount,
            ]);

            // Reverse old accounting entries
            $oldVoucher = Voucher::where('reference_type', PurchaseReturn::class)
                ->where('reference_id', $purchaseReturn->id)
                ->first();
            if ($oldVoucher) {
                AccountingEntry::where('voucher_id', $oldVoucher->id)->delete();
                $oldVoucher->delete();
            }

            // Delete old items and parts
            $purchaseReturn->items()->each(fn($item) => $item->parts()->delete());
            $purchaseReturn->items()->delete();

            $totals = $this->createReturnItems($purchaseReturn, $request->items, $request);

            $calculatedNet    = $purchaseReturn->items()->sum('item_total');
            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $purchaseReturn->update([
                'total_material_value' => round($totals['material'], 2),
                'total_making_value'   => round($totals['making'],   2),
                'total_parts_value'    => round($totals['parts'],    2),
                'total_vat_amount'     => round($totals['vat'],      2),
                'net_amount'           => round($calculatedNet,      2),
                'net_amount_aed'       => $calculatedNetAed,
            ]);

            $this->createReturnAccountingEntries($purchaseReturn, $totals);

            DB::commit();

            return redirect()
                ->route('purchase_return.index')
                ->with('success', 'Return #' . $purchaseReturn->return_no . ' updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Purchase Return Update Error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy($id)
    {
        $purchaseReturn = PurchaseReturn::findOrFail($id);
        $purchaseReturn->delete();

        return redirect()
            ->route('purchase_return.index')
            ->with('success', 'Return #' . $purchaseReturn->return_no . ' deleted.');
    }

    // =========================================================================
    // PRINT
    // =========================================================================

    public function print($id)
    {
        $purchaseReturn = PurchaseReturn::with([
            'vendor', 'purchaseInvoice', 'items.parts',
            'bank', 'transferBank',
        ])->findOrFail($id);

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetTitle($purchaseReturn->return_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setCellPadding(1.2);
        $pdf->AddPage();

        $logoPath = public_path('assets/img/mj-logo.jpeg');
        $logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="85">' : '';

        $pdf->writeHTML('
        <table width="100%" cellpadding="3"><tr>
            <td width="40%">' . $logoHtml . '</td>
            <td width="60%" style="text-align:right;font-size:10px;">
                <strong>MUSFIRA JEWELRY L.L.C</strong><br>
                Suite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai<br>
                TRN No: 104902647700003
            </td>
        </tr></table><hr>', true, false, false, false);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'PURCHASE RETURN NOTE', 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);

        $goldRateUsdOz = $purchaseReturn->gold_rate_usd       ?? 0;
        $goldRateAedOz = $purchaseReturn->gold_rate_aed_ounce ?? 0;

        $pdf->writeHTML('
        <table cellpadding="3" width="100%"><tr>
            <td width="50%">
                <b>Vendor:</b><br>
                ' . ($purchaseReturn->vendor->name    ?? '-') . '<br>
                ' . ($purchaseReturn->vendor->address ?? '-') . '<br>
                Contact: ' . ($purchaseReturn->vendor->contact_no ?? '-') . '<br>
                TRN: '     . ($purchaseReturn->vendor->trn        ?? '-') . '
            </td>
            <td width="50%">
                <table border="1" cellpadding="3" width="100%">
                    <tr><td width="45%"><b>Return No</b></td><td><b>' . $purchaseReturn->return_no . '</b></td></tr>
                    <tr><td><b>Return Date</b></td><td>' . Carbon::parse($purchaseReturn->return_date)->format('d.m.Y') . '</td></tr>
                    <tr><td><b>Ref Invoice</b></td><td>' . ($purchaseReturn->purchaseInvoice->invoice_no ?? '-') . '</td></tr>
                    <tr><td><b>Gold Rate (USD/oz)</b></td><td>' . number_format($goldRateUsdOz, 2) . '</td></tr>
                    <tr><td><b>Gold Rate (AED/oz)</b></td><td>' . number_format($goldRateAedOz, 2) . '</td></tr>
                    <tr><td><b>Gold Rate (AED/g)</b></td><td>'  . number_format($purchaseReturn->gold_rate_aed, 4) . '</td></tr>
                </table>
            </td>
        </tr></table>', true, false, false, false);

        // ── Items Table ──
        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="3%"  rowspan="2">#</th>
                    <th width="12%" rowspan="2">Item Name</th>
                    <th width="10%" rowspan="2">Description</th>
                    <th width="6%"  rowspan="2">Net Wt</th>
                    <th width="6%"  rowspan="2">Purity</th>
                    <th width="7%"  rowspan="2">Purity Wt</th>
                    <th width="6%"  rowspan="2">995</th>
                    <th width="13%" colspan="2">Making</th>
                    <th width="7%"  rowspan="2">Material</th>
                    <th width="8%"  rowspan="2">Material Val</th>
                    <th width="5%"  rowspan="2">VAT%</th>
                    <th width="8%"  rowspan="2">Item Total</th>
                </tr>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="6%">Rate</th>
                    <th width="7%">Value</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($purchaseReturn->items as $index => $item) {
            $hasParts = $item->parts && $item->parts->count() > 0;

            $html .= '
            <tr style="text-align:center;">
                <td width="3%">' . ($index + 1) . '</td>
                <td width="12%" style="text-align:left;">' . ($item->item_name ?? '-') . '</td>
                <td width="10%" style="text-align:left;">' . ($item->item_description ?? '-') . '</td>
                <td width="6%">' . number_format($item->net_weight,    3) . '</td>
                <td width="6%">' . number_format($item->purity,        3) . '</td>
                <td width="7%">' . number_format($item->purity_weight, 3) . '</td>
                <td width="6%">' . number_format($item->col_995,       3) . '</td>
                <td width="13%">' . number_format($item->making_rate,   2) . '</td>
                <td width="7%">' . number_format($item->making_value,  2) . '</td>
                <td width="8%">' . ucfirst($item->material_type)          . '</td>
                <td width="5%">' . number_format($item->material_value, 2) . '</td>
                <td width="8%">' . number_format($item->vat_percent,   0) . '%</td>
                <td style="font-weight:bold;">' . number_format($item->item_total, 2) . '</td>
            </tr>';

            if ($hasParts) {
                $html .= '<tr style="background-color:#f9f9f9;font-style:italic;font-size:7px;">
                            <td></td><td colspan="12"><b>Parts Detail:</b></td>
                          </tr>';
                foreach ($item->parts as $part) {
                    $html .= '
                    <tr style="font-size:7px;background-color:#fcfcfc;text-align:center;">
                        <td></td>
                        <td style="text-align:left;">' . ($part->item_name ?? 'Part') . '</td>
                        <td style="text-align:left;">' . htmlspecialchars($part->part_description ?? '') . '</td>
                        <td colspan="2">' . number_format($part->qty, 3) . ' Ct @ ' . number_format($part->rate, 2) . '</td>
                        <td>St.' . number_format($part->stone_qty ?? 0, 2) . '</td>
                        <td>SR:' . number_format($part->stone_rate ?? 0, 2) . '</td>
                        <td colspan="2">Cert:' . number_format($part->certification_charges ?? 0, 2) . '</td>
                        <td colspan="3"></td>
                        <td style="font-weight:bold;">' . number_format($part->total, 2) . '</td>
                    </tr>';
                }
            }
        }

        $html .= '
            <tr style="font-weight:bold;background-color:#f5f5f5;">
                <td colspan="12" align="right">Total Return Amount</td>
                <td align="right">' . number_format($purchaseReturn->net_amount, 2) . '</td>
            </tr>
            </tbody></table>';

        $pdf->writeHTML($html, true, false, false, false);

        // ── Summary ──
        $aedAmount   = $purchaseReturn->currency === 'USD'
            ? $purchaseReturn->net_amount_aed
            : $purchaseReturn->net_amount;

        $summaryHtml = '
        <table width="100%" cellpadding="0" border="0" style="margin-top:8px;">
            <tr>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td colspan="2"><b>Refund Details</b></td></tr>
                        <tr><td>Method</td><td>' . ucwords(str_replace('_', ' ', $purchaseReturn->refund_method ?? '-')) . '</td></tr>
                        <tr><td>Reason</td><td>' . ($purchaseReturn->reason ?? '-') . '</td></tr>';

        if ($purchaseReturn->refund_method === 'cheque') {
            $summaryHtml .= '
                        <tr><td>Bank</td><td>'        . ($purchaseReturn->bank->name ?? '-') . '</td></tr>
                        <tr><td>Cheque No</td><td>'   . ($purchaseReturn->cheque_no ?? '-') . '</td></tr>
                        <tr><td>Cheque Date</td><td>' . ($purchaseReturn->cheque_date ? Carbon::parse($purchaseReturn->cheque_date)->format('d.m.Y') : '-') . '</td></tr>
                        <tr><td>Cheque Amount</td><td>' . number_format($purchaseReturn->cheque_amount ?? 0, 2) . '</td></tr>';
        }
        if ($purchaseReturn->refund_method === 'bank_transfer') {
            $summaryHtml .= '
                        <tr><td>From Bank</td><td>'     . ($purchaseReturn->transferBank->name ?? '-') . '</td></tr>
                        <tr><td>Vendor Bank</td><td>'   . ($purchaseReturn->transfer_to_bank ?? '-') . '</td></tr>
                        <tr><td>Account No</td><td>'    . ($purchaseReturn->account_no ?? '-') . '</td></tr>
                        <tr><td>Transfer Ref</td><td>'  . ($purchaseReturn->transaction_id ?? '-') . '</td></tr>
                        <tr><td>Transfer Date</td><td>' . ($purchaseReturn->transfer_date ? Carbon::parse($purchaseReturn->transfer_date)->format('d.m.Y') : '-') . '</td></tr>
                        <tr><td>Transfer Amount</td><td>' . number_format($purchaseReturn->transfer_amount ?? 0, 2) . '</td></tr>';
        }

        $summaryHtml .= '
                    </table>
                </td>
                <td width="10%"></td>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td colspan="2" align="center"><b>Summary</b></td></tr>
                        <tr><td width="60%">Material Value Returned</td>
                            <td width="40%" align="right">' . number_format($purchaseReturn->total_material_value, 2) . '</td></tr>
                        <tr><td>Making Charges Returned</td>
                            <td align="right">' . number_format($purchaseReturn->total_making_value, 2) . '</td></tr>
                        <tr><td>Parts Value Returned</td>
                            <td align="right">' . number_format($purchaseReturn->total_parts_value, 2) . '</td></tr>
                        <tr><td>VAT Reversed</td>
                            <td align="right">' . number_format($purchaseReturn->total_vat_amount, 2) . '</td></tr>
                        <tr style="font-weight:bold;background-color:#eeeeee;">
                            <td>Net Return Amount</td>
                            <td align="right">' . number_format($purchaseReturn->net_amount, 2) . '</td>
                        </tr>';

        if ($purchaseReturn->currency === 'USD') {
            $summaryHtml .= '
                        <tr><td>Exchange Rate</td>
                            <td align="right">' . number_format($purchaseReturn->exchange_rate, 4) . '</td></tr>
                        <tr style="font-weight:bold;">
                            <td>Total (AED)</td>
                            <td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        } else {
            $summaryHtml .= '
                        <tr style="font-weight:bold;">
                            <td>Total (AED)</td>
                            <td align="right">' . number_format($aedAmount, 2) . '</td></tr>';
        }

        $summaryHtml .= '</table></td></tr></table>';

        $pdf->Ln(2);
        $pdf->writeHTML($summaryHtml, true, false, false, false);

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $words = $pdf->convertCurrencyToWords($aedAmount, 'AED');
        $pdf->Cell(0, 5, 'Amount in Words (AED): ' . $words, 0, 1, 'L');

        if ($purchaseReturn->remarks) {
            $pdf->Ln(1);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell(0, 5, 'Remarks: ' . $purchaseReturn->remarks, 0, 1, 'L');
        }

        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Line(20,  $y, 80,  $y); $pdf->SetXY(20,  $y + 1); $pdf->Cell(60, 5, "Vendor's Signature",   0, 0, 'C');
        $pdf->Line(130, $y, 190, $y); $pdf->SetXY(130, $y + 1); $pdf->Cell(60, 5, 'Authorized Signature', 0, 0, 'C');

        return $pdf->Output($purchaseReturn->return_no . '.pdf', 'I');
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function generateReturnNo(): string
    {
        $prefix = 'PUR-RET-';

        $last = PurchaseReturn::withTrashed()
            ->whereRaw('return_no REGEXP ?', ['^' . preg_quote($prefix, '/') . '[0-9]+$'])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $next = $last ? ((int) str_replace($prefix, '', $last->return_no)) + 1 : 1;

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    private function createReturnItems(PurchaseReturn $purchaseReturn, array $items, Request $request): array
    {
        $totals = [
            'gold_material'    => 0.0,
            'diamond_material' => 0.0,
            'material'         => 0.0,
            'making'           => 0.0,
            'parts'            => 0.0,
            'gold_parts'       => 0.0,
            'diamond_parts'    => 0.0,
            'vat'              => 0.0,
        ];

        foreach ($items as $itemData) {
            // ── Use the SAVED values from the original invoice item directly ──
            // Do NOT recalculate from gold_rate_aed — the return must mirror
            // the exact values that were originally purchased and booked.
            $netWeight     = (float) ($itemData['net_weight']     ?? 0);
            $grossWeight   = (float) ($itemData['gross_weight']   ?? 0);
            $purity        = (float) ($itemData['purity']         ?? 0);
            $purityWeight  = (float) ($itemData['purity_weight']  ?? 0);
            $col995        = (float) ($itemData['col_995']        ?? 0);
            $makingRate    = (float) ($itemData['making_rate']    ?? 0);
            $makingValue   = (float) ($itemData['making_value']   ?? 0);
            $materialRate  = (float) ($itemData['material_rate']  ?? 0);
            $materialValue = (float) ($itemData['material_value'] ?? 0);
            $taxableAmount = (float) ($itemData['taxable_amount'] ?? 0);
            $vatPercent    = (float) ($itemData['vat_percent']    ?? 0);
            $vatAmount     = (float) ($itemData['vat_amount']     ?? 0);
            $itemTotal     = (float) ($itemData['item_total']     ?? 0);
            $matType       = $itemData['material_type'] ?? 'gold';

            // ── Parts: use saved totals, not recalculated ──
            $partsData  = $itemData['parts'] ?? [];
            $partsTotal = 0.0;

            foreach ($partsData as $partData) {
                $partsTotal += (float) ($partData['total'] ?? 0);
            }

            $returnItem = $purchaseReturn->items()->create([
                'purchase_invoice_item_id' => $itemData['purchase_invoice_item_id'] ?? null,
                'item_name'                => $itemData['item_name']                ?? null,
                'product_id'               => $itemData['product_id']               ?? null,
                'item_description'         => $itemData['item_description']         ?? null,
                'barcode_number'           => $itemData['barcode_number']           ?? null,
                'net_weight'               => $netWeight,
                'gross_weight'             => $grossWeight,
                'purity'                   => $purity,
                'purity_weight'            => $purityWeight,
                'col_995'                  => $col995,
                'material_type'            => $matType,
                'material_rate'            => $materialRate,
                'material_value'           => $materialValue,
                'making_rate'              => $makingRate,
                'making_value'             => $makingValue,
                'parts_total'              => round($partsTotal, 2),
                'taxable_amount'           => $taxableAmount,
                'vat_percent'              => $vatPercent,
                'vat_amount'               => $vatAmount,
                'item_total'               => $itemTotal,
            ]);

            foreach ($partsData as $partData) {
                $returnItem->parts()->create([
                    'item_name'             => $partData['item_name']             ?? null,
                    'part_description'      => $partData['part_description']      ?? null,
                    'qty'                   => (float) ($partData['qty']                   ?? 0),
                    'rate'                  => (float) ($partData['rate']                  ?? 0),
                    'stone_qty'             => (float) ($partData['stone_qty']             ?? 0),
                    'stone_rate'            => (float) ($partData['stone_rate']            ?? 0),
                    'certification_charges' => (float) ($partData['certification_charges'] ?? 0),
                    'total'                 => (float) ($partData['total']                 ?? 0),
                ]);
            }

            // ── Accumulate totals for accounting ──
            if ($matType === 'gold') {
                $totals['gold_material'] += $materialValue;
                $totals['gold_parts']    += $partsTotal;
            } else {
                $totals['diamond_material'] += $materialValue;
                $totals['diamond_parts']    += $partsTotal;
            }

            $totals['material'] += $materialValue;
            $totals['making']   += $makingValue;
            $totals['parts']    += $partsTotal;
            $totals['vat']      += $vatAmount;
        }

        return $totals;
    }

    private function clearIrrelevantRefundFields(Request $request): void
    {
        if ($request->refund_method !== 'cheque') {
            $request->merge([
                'bank_name'     => null,
                'cheque_no'     => null,
                'cheque_date'   => null,
                'cheque_amount' => null,
            ]);
        }
        if ($request->refund_method !== 'bank_transfer') {
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

    private function validateReturn(Request $request): void
    {
        $request->validate([
            'purchase_invoice_id'   => 'required|exists:purchase_invoices,id',
            'vendor_id'             => 'required|exists:chart_of_accounts,id',
            'return_date'           => 'required|date',
            'reason'                => 'required|string',
            'currency'              => 'required|in:AED,USD',
            'exchange_rate'         => 'nullable|required_if:currency,USD|numeric|min:0',
            'refund_method'         => 'required|in:credit_note,cash,bank_transfer,cheque,material_return',
            'gold_rate_aed'         => 'nullable|numeric|min:0',
            'diamond_rate_aed'      => 'nullable|numeric|min:0',
            'items'                 => 'required|array|min:1',
            'items.*.net_weight'    => 'required|numeric|min:0',
            'items.*.purity'        => 'required|numeric|min:0|max:1',
            'items.*.making_rate'   => 'required|numeric|min:0',
            'items.*.material_type' => 'required|in:gold,diamond',
            'items.*.vat_percent'   => 'required|numeric|min:0',
            'bank_name'             => 'nullable|required_if:refund_method,cheque|exists:chart_of_accounts,id',
            'cheque_no'             => 'nullable|required_if:refund_method,cheque|string',
            'cheque_date'           => 'nullable|required_if:refund_method,cheque|date',
            'cheque_amount'         => 'nullable|required_if:refund_method,cheque|numeric|min:0',
            'transfer_from_bank'    => 'nullable|required_if:refund_method,bank_transfer|exists:chart_of_accounts,id',
            'transfer_date'         => 'nullable|required_if:refund_method,bank_transfer|date',
            'transfer_amount'       => 'nullable|required_if:refund_method,bank_transfer|numeric|min:0',
        ]);
    }

    // =========================================================================
    // ACCOUNTING
    //
    // Every method has exactly ONE debit offsetting all the CR purchase reversals.
    //
    // Step 1 — CR each purchase account to reverse the original purchase:
    //   CR 510001  Gold material value
    //   CR 510002  Diamond material value
    //   CR 510003  Making charges
    //   CR 510001/510002  Parts (split by item material type)
    //   CR 105001  Input VAT
    //
    // Step 2 — ONE DR to settle, depending on refund method:
    //   credit_note     → DR Vendor AP  (stays open; vendor owes us)
    //   cash            → DR 101001 Cash
    //   cheque          → DR Bank account
    //   bank_transfer   → DR Bank account
    //   material_return → DR 104001 Gold Inventory
    //
    // DR total always = sum of all CR entries → always balanced.
    // =========================================================================

    protected function createReturnAccountingEntries(PurchaseReturn $purchaseReturn, array $totals): Voucher
    {
        $acct = function (string $code) use ($purchaseReturn): int {
            $account = ChartOfAccounts::where('account_code', $code)->first();
            if (!$account) {
                throw new \Exception(
                    "Account code [{$code}] not found (Return #{$purchaseReturn->return_no})."
                );
            }
            return $account->id;
        };

        $voucher = Voucher::create([
            'voucher_no'     => Voucher::generateVoucherNo('purchase_return'),
            'voucher_type'   => 'purchase_return',
            'voucher_date'   => $purchaseReturn->return_date,
            'reference_type' => PurchaseReturn::class,
            'reference_id'   => $purchaseReturn->id,
            'ac_dr_sid'      => null,
            'ac_cr_sid'      => null,
            'amount'         => null,
            'remarks'        => 'Purchase Return #' . $purchaseReturn->return_no
                                . ' against Invoice #'
                                . ($purchaseReturn->purchaseInvoice->invoice_no ?? '-'),
            'created_by'     => auth()->id(),
        ]);

        $entries = [];

        // ── Individual credit amounts ──
        $goldMaterialCr  = round($totals['gold_material'],    2);
        $diaMaterialCr   = round($totals['diamond_material'], 2);
        $makingCr        = round($totals['making'],           2);
        $goldPartsCr     = round($totals['gold_parts']    ?? 0, 2);
        $diaPartsCr      = round($totals['diamond_parts'] ?? 0, 2);
        $vatCr           = round($totals['vat'],              2);

        // Total return = sum of every CR — this is also the single DR amount
        $totalReturn = round(
            $goldMaterialCr + $diaMaterialCr + $makingCr + $goldPartsCr + $diaPartsCr + $vatCr,
            2
        );

        if ($totalReturn <= 0) {
            throw new \Exception(
                "Return #{$purchaseReturn->return_no} has zero value — no accounting entries created."
            );
        }

        // ── STEP 1: CR each purchase account ──

        if ($goldMaterialCr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('510001'),
                'debit'      => 0,
                'credit'     => $goldMaterialCr,
                'narration'  => 'Gold material returned — Return #' . $purchaseReturn->return_no,
            ];
        }

        if ($diaMaterialCr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('510002'),
                'debit'      => 0,
                'credit'     => $diaMaterialCr,
                'narration'  => 'Diamond material returned — Return #' . $purchaseReturn->return_no,
            ];
        }

        if ($makingCr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('510003'),
                'debit'      => 0,
                'credit'     => $makingCr,
                'narration'  => 'Making charges reversed — Return #' . $purchaseReturn->return_no,
            ];
        }

        if ($goldPartsCr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('510001'),
                'debit'      => 0,
                'credit'     => $goldPartsCr,
                'narration'  => 'Gold item parts reversed — Return #' . $purchaseReturn->return_no,
            ];
        }

        if ($diaPartsCr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('510002'),
                'debit'      => 0,
                'credit'     => $diaPartsCr,
                'narration'  => 'Diamond item parts reversed — Return #' . $purchaseReturn->return_no,
            ];
        }

        if ($vatCr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('105001'),
                'debit'      => 0,
                'credit'     => $vatCr,
                'narration'  => 'Input VAT reversed — Return #' . $purchaseReturn->return_no,
            ];
        }

        // ── STEP 2: ONE DR entry — the settlement account ──

        switch ($purchaseReturn->refund_method) {

            case 'credit_note':
                // Vendor AP is debited — balance stays open as a credit note
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $purchaseReturn->vendor_id,
                    'debit'      => $totalReturn,
                    'credit'     => 0,
                    'narration'  => 'Credit note issued to vendor — Return #' . $purchaseReturn->return_no,
                ];
                break;

            case 'cash':
                // We receive cash back — DR Cash
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $acct('101001'),
                    'debit'      => $totalReturn,
                    'credit'     => 0,
                    'narration'  => 'Cash received from vendor — Return #' . $purchaseReturn->return_no,
                ];
                break;

            case 'cheque':
                if (!$purchaseReturn->bank_name) {
                    throw new \Exception(
                        'Bank account required for cheque refund (Return #' . $purchaseReturn->return_no . ').'
                    );
                }
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $purchaseReturn->bank_name,
                    'debit'      => $totalReturn,
                    'credit'     => 0,
                    'narration'  => 'Cheque #' . $purchaseReturn->cheque_no
                                    . ' received from vendor — Return #' . $purchaseReturn->return_no,
                ];
                break;

            case 'bank_transfer':
                if (!$purchaseReturn->transfer_from_bank) {
                    throw new \Exception(
                        'Transfer bank required for bank transfer refund (Return #' . $purchaseReturn->return_no . ').'
                    );
                }
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $purchaseReturn->transfer_from_bank,
                    'debit'      => $totalReturn,
                    'credit'     => 0,
                    'narration'  => 'Bank transfer Ref# ' . $purchaseReturn->transaction_id
                                    . ' received — Return #' . $purchaseReturn->return_no,
                ];
                break;

            case 'material_return':
                // Vendor returns gold — DR Gold Inventory
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $acct('104001'),
                    'debit'      => $totalReturn,
                    'credit'     => 0,
                    'narration'  => 'Gold inventory received back from vendor — Return #' . $purchaseReturn->return_no,
                ];
                break;

            default:
                throw new \Exception(
                    'Unrecognised refund method: "' . $purchaseReturn->refund_method . '"'
                );
        }

        // ── Persist all entries ──
        foreach ($entries as $entry) {
            AccountingEntry::create($entry);
        }

        // ── Strict balance check ──
        $sumDebits  = round(collect($entries)->sum('debit'),  2);
        $sumCredits = round(collect($entries)->sum('credit'), 2);

        if ($sumDebits !== $sumCredits) {
            throw new \Exception(
                "Accounting imbalance on Return #{$purchaseReturn->return_no}: "
                . "Debits {$sumDebits} ≠ Credits {$sumCredits}."
            );
        }

        Log::info('Purchase return accounting entries created', [
            'return_no'       => $purchaseReturn->return_no,
            'voucher_no'      => $voucher->voucher_no,
            'refund_method'   => $purchaseReturn->refund_method,
            'gold_material_cr'=> $goldMaterialCr,
            'dia_material_cr' => $diaMaterialCr,
            'making_cr'       => $makingCr,
            'gold_parts_cr'   => $goldPartsCr,
            'dia_parts_cr'    => $diaPartsCr,
            'vat_cr'          => $vatCr,
            'total_return'    => $totalReturn,
            'sum_debits'      => $sumDebits,
            'sum_credits'     => $sumCredits,
        ]);

        return $voucher;
    }
}