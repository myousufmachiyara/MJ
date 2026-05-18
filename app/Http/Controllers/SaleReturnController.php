<?php
// app/Http/Controllers/SaleReturnController.php

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleReturnItemPart;
use App\Models\SaleInvoice;
use App\Models\Voucher;
use App\Models\AccountingEntry;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class SaleReturnController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    public function index()
    {
        $returns = SaleReturn::with('customer', 'saleInvoice')->latest()->get();
        return view('sale_return.index', compact('returns'));
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create()
    {
        $invoices  = SaleInvoice::with('customer')->latest()->get();
        $customers = ChartOfAccounts::where('account_type', 'customer')->get();
        $banks     = ChartOfAccounts::where('account_type', 'bank')->get();

        return view('sale_return.create', compact('invoices', 'customers', 'banks'));
    }

    // =========================================================================
    // GET INVOICE ITEMS (AJAX)
    // =========================================================================

    public function getInvoiceItems($invoiceId)
    {
        $invoice = SaleInvoice::with(['items.parts', 'customer'])->findOrFail($invoiceId);

        // ── Find already-returned item IDs ──
        $alreadyReturnedItemIds = SaleReturnItem::whereIn(
            'sale_invoice_item_id',
            $invoice->items->pluck('id')->toArray()
        )->pluck('sale_invoice_item_id')->unique()->toArray();

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
                'customer_id'         => $invoice->customer_id,
                'customer_name'       => $invoice->customer->name ?? '',
            ],
            'items' => $invoice->items->map(function ($item) use ($alreadyReturnedItemIds) {
                return [
                    'id'               => $item->id,
                    'item_name'        => $item->item_name,
                    'item_description' => $item->item_description,
                    'barcode_number'   => $item->barcode_number,
                    'purity'           => $item->purity,
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
                    'already_returned' => in_array($item->id, $alreadyReturnedItemIds),
                    'parts'            => $item->parts->map(function ($part) {
                        return [
                            'item_name'        => $part->item_name,
                            'part_description' => $part->part_description,
                            'qty'              => $part->qty,
                            'rate'             => $part->rate,
                            'stone_qty'        => $part->stone_qty,
                            'stone_rate'       => $part->stone_rate,
                            'total'            => $part->total,
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

            $saleReturn = SaleReturn::create([
                'return_no'           => $returnNo,
                'sale_invoice_id'     => $request->sale_invoice_id,
                'customer_id'         => $request->customer_id,
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

            $totals = $this->createReturnItems($saleReturn, $request->items);

            $calculatedNet    = $saleReturn->items()->sum('item_total');
            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $saleReturn->update([
                'total_material_value' => round($totals['material'], 2),
                'total_making_value'   => round($totals['making'],   2),
                'total_parts_value'    => round($totals['parts'],    2),
                'total_vat_amount'     => round($totals['vat'],      2),
                'net_amount'           => round($calculatedNet,      2),
                'net_amount_aed'       => $calculatedNetAed,
            ]);

            $this->createReturnAccountingEntries($saleReturn, $totals);

            DB::commit();

            return redirect()
                ->route('sale_return.index')
                ->with('success', 'Return #' . $returnNo . ' created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Sale Return Store Error', [
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
        $saleReturn = SaleReturn::with(['items.parts'])->findOrFail($id);
        $invoices   = SaleInvoice::with('customer')->latest()->get();
        $customers  = ChartOfAccounts::where('account_type', 'customer')->get();
        $banks      = ChartOfAccounts::where('account_type', 'bank')->get();

        $itemsData = $saleReturn->items->map(function ($item) {
            return [
                'id'                    => $item->id,
                'sale_invoice_item_id'  => $item->sale_invoice_item_id,
                'item_name'             => $item->item_name,
                'item_description'      => $item->item_description,
                'barcode_number'        => $item->barcode_number,
                'purity'                => $item->purity,
                'gross_weight'          => $item->gross_weight,
                'purity_weight'         => $item->purity_weight,
                'col_995'               => $item->col_995,
                'material_type'         => $item->material_type,
                'material_rate'         => $item->material_rate,
                'material_value'        => $item->material_value,
                'making_rate'           => $item->making_rate,
                'making_value'          => $item->making_value,
                'taxable_amount'        => $item->taxable_amount,
                'vat_percent'           => $item->vat_percent,
                'vat_amount'            => $item->vat_amount,
                'item_total'            => $item->item_total,
                'parts' => $item->parts->map(function ($part) {
                    return [
                        'item_name'        => $part->item_name,
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

        return view('sale_return.edit', compact(
            'saleReturn', 'invoices', 'customers', 'banks', 'itemsData'
        ));
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        $saleReturn = SaleReturn::findOrFail($id);

        $this->clearIrrelevantRefundFields($request);
        $this->validateReturn($request);

        try {
            DB::beginTransaction();

            $saleReturn->update([
                'sale_invoice_id'     => $request->sale_invoice_id,
                'customer_id'         => $request->customer_id,
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
            $oldVoucher = Voucher::where('reference_type', SaleReturn::class)
                ->where('reference_id', $saleReturn->id)
                ->first();
            if ($oldVoucher) {
                AccountingEntry::where('voucher_id', $oldVoucher->id)->delete();
                $oldVoucher->delete();
            }

            // Delete old items and parts
            $saleReturn->items()->each(fn($item) => $item->parts()->delete());
            $saleReturn->items()->delete();

            $totals = $this->createReturnItems($saleReturn, $request->items);

            $calculatedNet    = $saleReturn->items()->sum('item_total');
            $calculatedNetAed = $request->currency === 'USD'
                ? round($calculatedNet * ($request->exchange_rate ?? 1), 2)
                : $calculatedNet;

            $saleReturn->update([
                'total_material_value' => round($totals['material'], 2),
                'total_making_value'   => round($totals['making'],   2),
                'total_parts_value'    => round($totals['parts'],    2),
                'total_vat_amount'     => round($totals['vat'],      2),
                'net_amount'           => round($calculatedNet,      2),
                'net_amount_aed'       => $calculatedNetAed,
            ]);

            $this->createReturnAccountingEntries($saleReturn, $totals);

            DB::commit();

            return redirect()
                ->route('sale_return.index')
                ->with('success', 'Return #' . $saleReturn->return_no . ' updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Sale Return Update Error', [
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
        $saleReturn = SaleReturn::findOrFail($id);
        $saleReturn->delete();

        return redirect()
            ->route('sale_return.index')
            ->with('success', 'Return #' . $saleReturn->return_no . ' deleted.');
    }

    // =========================================================================
    // PRINT
    // =========================================================================

    public function print($id)
    {
        $saleReturn = SaleReturn::with([
            'customer', 'saleInvoice', 'items.parts',
            'bank', 'transferBank',
        ])->findOrFail($id);

        $pdf = new myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetTitle($saleReturn->return_no);
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
        $pdf->Cell(0, 6, 'SALE RETURN NOTE', 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 9);

        $pdf->writeHTML('
        <table cellpadding="3" width="100%"><tr>
            <td width="50%">
                <b>Customer:</b><br>
                ' . ($saleReturn->customer->name    ?? '-') . '<br>
                ' . ($saleReturn->customer->address ?? '-') . '<br>
                Contact: ' . ($saleReturn->customer->contact_no ?? '-') . '<br>
                TRN: '     . ($saleReturn->customer->trn        ?? '-') . '
            </td>
            <td width="50%">
                <table border="1" cellpadding="3" width="100%">
                    <tr><td width="45%"><b>Return No</b></td><td><b>' . $saleReturn->return_no . '</b></td></tr>
                    <tr><td><b>Return Date</b></td><td>' . Carbon::parse($saleReturn->return_date)->format('d.m.Y') . '</td></tr>
                    <tr><td><b>Ref Invoice</b></td><td>' . ($saleReturn->saleInvoice->invoice_no ?? '-') . '</td></tr>
                    <tr><td><b>Gold Rate (USD/oz)</b></td><td>' . number_format($saleReturn->gold_rate_usd ?? 0, 2) . '</td></tr>
                    <tr><td><b>Gold Rate (AED/oz)</b></td><td>' . number_format($saleReturn->gold_rate_aed_ounce ?? 0, 2) . '</td></tr>
                    <tr><td><b>Gold Rate (AED/g)</b></td><td>'  . number_format($saleReturn->gold_rate_aed ?? 0, 4) . '</td></tr>
                </table>
            </td>
        </tr></table>', true, false, false, false);

        // Items table
        $html = '
        <table border="1" cellpadding="3" width="100%" style="font-size:8px;">
            <thead>
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <th width="3%"  rowspan="2">#</th>
                    <th width="12%" rowspan="2">Item Name</th>
                    <th width="10%" rowspan="2">Description</th>
                    <th width="6%"  rowspan="2">Gross Wt</th>
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

        foreach ($saleReturn->items as $index => $item) {
            $hasParts = $item->parts && $item->parts->count() > 0;

            $html .= '
            <tr style="text-align:center;">
                <td>' . ($index + 1) . '</td>
                <td style="text-align:left;">' . ($item->item_name ?? '-') . '</td>
                <td style="text-align:left;">' . ($item->item_description ?? '-') . '</td>
                <td>' . number_format($item->gross_weight,  3) . '</td>
                <td>' . number_format($item->purity,        3) . '</td>
                <td>' . number_format($item->purity_weight, 3) . '</td>
                <td>' . number_format($item->col_995,       3) . '</td>
                <td>' . number_format($item->making_rate,   2) . '</td>
                <td>' . number_format($item->making_value,  2) . '</td>
                <td>' . ucfirst($item->material_type)          . '</td>
                <td>' . number_format($item->material_value, 2) . '</td>
                <td>' . number_format($item->vat_percent,   0) . '%</td>
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
                        <td colspan="5"></td>
                        <td style="font-weight:bold;">' . number_format($part->total, 2) . '</td>
                    </tr>';
                }
            }
        }

        $html .= '
            <tr style="font-weight:bold;background-color:#f5f5f5;">
                <td colspan="12" align="right">Total Return Amount</td>
                <td align="right">' . number_format($saleReturn->net_amount, 2) . '</td>
            </tr>
            </tbody></table>';

        $pdf->writeHTML($html, true, false, false, false);

        $aedAmount = $saleReturn->currency === 'USD'
            ? $saleReturn->net_amount_aed
            : $saleReturn->net_amount;

        $summaryHtml = '
        <table width="100%" cellpadding="0" border="0" style="margin-top:8px;">
            <tr>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td colspan="2"><b>Refund Details</b></td></tr>
                        <tr><td>Method</td><td>' . ucwords(str_replace('_', ' ', $saleReturn->refund_method ?? '-')) . '</td></tr>
                        <tr><td>Reason</td><td>' . ($saleReturn->reason ?? '-') . '</td></tr>';

        if ($saleReturn->refund_method === 'cheque') {
            $summaryHtml .= '
                        <tr><td>Bank</td><td>'        . ($saleReturn->bank->name ?? '-') . '</td></tr>
                        <tr><td>Cheque No</td><td>'   . ($saleReturn->cheque_no ?? '-') . '</td></tr>
                        <tr><td>Cheque Date</td><td>' . ($saleReturn->cheque_date ? Carbon::parse($saleReturn->cheque_date)->format('d.m.Y') : '-') . '</td></tr>
                        <tr><td>Amount</td><td>'      . number_format($saleReturn->cheque_amount ?? 0, 2) . '</td></tr>';
        }
        if ($saleReturn->refund_method === 'bank_transfer') {
            $summaryHtml .= '
                        <tr><td>From Bank</td><td>'     . ($saleReturn->transferBank->name ?? '-') . '</td></tr>
                        <tr><td>Customer Bank</td><td>' . ($saleReturn->transfer_to_bank ?? '-') . '</td></tr>
                        <tr><td>Account No</td><td>'    . ($saleReturn->account_no ?? '-') . '</td></tr>
                        <tr><td>Transfer Ref</td><td>'  . ($saleReturn->transaction_id ?? '-') . '</td></tr>
                        <tr><td>Transfer Date</td><td>' . ($saleReturn->transfer_date ? Carbon::parse($saleReturn->transfer_date)->format('d.m.Y') : '-') . '</td></tr>
                        <tr><td>Amount</td><td>'        . number_format($saleReturn->transfer_amount ?? 0, 2) . '</td></tr>';
        }

        $summaryHtml .= '
                    </table>
                </td>
                <td width="10%"></td>
                <td width="45%" valign="top">
                    <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                        <tr style="background-color:#f5f5f5;"><td colspan="2" align="center"><b>Summary</b></td></tr>
                        <tr><td width="60%">Material Value</td>
                            <td width="40%" align="right">' . number_format($saleReturn->total_material_value, 2) . '</td></tr>
                        <tr><td>Making Charges</td>
                            <td align="right">' . number_format($saleReturn->total_making_value, 2) . '</td></tr>
                        <tr><td>Parts Value</td>
                            <td align="right">' . number_format($saleReturn->total_parts_value, 2) . '</td></tr>
                        <tr><td>VAT Reversed</td>
                            <td align="right">' . number_format($saleReturn->total_vat_amount, 2) . '</td></tr>
                        <tr style="font-weight:bold;background-color:#eeeeee;">
                            <td>Net Return Amount</td>
                            <td align="right">' . number_format($saleReturn->net_amount, 2) . '</td></tr>';

        if ($saleReturn->currency === 'USD') {
            $summaryHtml .= '
                        <tr><td>Exchange Rate</td>
                            <td align="right">' . number_format($saleReturn->exchange_rate, 4) . '</td></tr>
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

        if ($saleReturn->remarks) {
            $pdf->Ln(1);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell(0, 5, 'Remarks: ' . $saleReturn->remarks, 0, 1, 'L');
        }

        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Line(20,  $y, 80,  $y); $pdf->SetXY(20,  $y + 1); $pdf->Cell(60, 5, "Customer's Signature",  0, 0, 'C');
        $pdf->Line(130, $y, 190, $y); $pdf->SetXY(130, $y + 1); $pdf->Cell(60, 5, 'Authorized Signature',  0, 0, 'C');

        return $pdf->Output($saleReturn->return_no . '.pdf', 'I');
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function generateReturnNo(): string
    {
        $prefix = 'SAL-RET-';

        $last = SaleReturn::withTrashed()
            ->whereRaw('return_no REGEXP ?', ['^' . preg_quote($prefix, '/') . '[0-9]+$'])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $next = $last ? ((int) str_replace($prefix, '', $last->return_no)) + 1 : 1;

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    private function createReturnItems(SaleReturn $saleReturn, array $items): array
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
            // Use saved values directly — do not recalculate
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

            $partsData  = $itemData['parts'] ?? [];
            $partsTotal = 0.0;
            foreach ($partsData as $partData) {
                $partsTotal += (float) ($partData['total'] ?? 0);
            }

            $returnItem = $saleReturn->items()->create([
                'sale_invoice_item_id' => $itemData['sale_invoice_item_id'] ?? null,
                'item_name'            => $itemData['item_name']            ?? null,
                'product_id'           => $itemData['product_id']           ?? null,
                'item_description'     => $itemData['item_description']     ?? null,
                'barcode_number'       => $itemData['barcode_number']       ?? null,
                'gross_weight'         => $grossWeight,
                'purity'               => $purity,
                'purity_weight'        => $purityWeight,
                'col_995'              => $col995,
                'material_type'        => $matType,
                'material_rate'        => $materialRate,
                'material_value'       => $materialValue,
                'making_rate'          => $makingRate,
                'making_value'         => $makingValue,
                'parts_total'          => round($partsTotal, 2),
                'taxable_amount'       => $taxableAmount,
                'vat_percent'          => $vatPercent,
                'vat_amount'           => $vatAmount,
                'item_total'           => $itemTotal,
            ]);

            foreach ($partsData as $partData) {
                $returnItem->parts()->create([
                    'item_name'        => $partData['item_name']        ?? null,
                    'part_description' => $partData['part_description'] ?? null,
                    'qty'              => (float) ($partData['qty']        ?? 0),
                    'rate'             => (float) ($partData['rate']       ?? 0),
                    'stone_qty'        => (float) ($partData['stone_qty']  ?? 0),
                    'stone_rate'       => (float) ($partData['stone_rate'] ?? 0),
                    'total'            => (float) ($partData['total']      ?? 0),
                ]);
            }

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
            $request->merge(['bank_name' => null, 'cheque_no' => null,
                             'cheque_date' => null, 'cheque_amount' => null]);
        }
        if ($request->refund_method !== 'bank_transfer') {
            $request->merge(['transfer_from_bank' => null, 'transfer_to_bank' => null,
                             'account_title' => null, 'account_no' => null,
                             'transaction_id' => null, 'transfer_date' => null,
                             'transfer_amount' => null]);
        }
    }

    private function validateReturn(Request $request): void
    {
        $request->validate([
            'sale_invoice_id'       => 'required|exists:sale_invoices,id',
            'customer_id'           => 'required|exists:chart_of_accounts,id',
            'return_date'           => 'required|date',
            'reason'                => 'required|string',
            'currency'              => 'required|in:AED,USD',
            'exchange_rate'         => 'nullable|required_if:currency,USD|numeric|min:0',
            'refund_method'         => 'required|in:credit_note,cash,bank_transfer,cheque,material_return',
            'items'                 => 'required|array|min:1',
            'items.*.gross_weight'  => 'required|numeric|min:0',
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
    // Sale return reverses the original sale entries:
    //
    // Original sale DR: Customer AR / Cash / Bank
    //           CR: Revenue accounts + VAT output
    //
    // Return — reverse:
    // Step 1 DRs (reverse the revenue CRs):
    //   DR 410001  Gold revenue (material)
    //   DR 410002  Diamond revenue (material)
    //   DR 410003  Making revenue
    //   DR 410001/410002  Parts revenue (by material type)
    //   DR 205001  Output VAT (reverse)
    //
    // Step 2 ONE CR — how we pay the customer back:
    //   credit_note     → CR Customer AR  (stays open)
    //   cash            → CR 101001 Cash
    //   cheque          → CR Bank account
    //   bank_transfer   → CR Bank account
    //   material_return → CR 104001 Gold Inventory (customer returns gold)
    // =========================================================================

    protected function createReturnAccountingEntries(SaleReturn $saleReturn, array $totals): Voucher
    {
        $acct = function (string $code) use ($saleReturn): int {
            $account = ChartOfAccounts::where('account_code', $code)->first();
            if (!$account) {
                throw new \Exception(
                    "Account code [{$code}] not found (Return #{$saleReturn->return_no})."
                );
            }
            return $account->id;
        };

        $voucher = Voucher::create([
            'voucher_no'     => Voucher::generateVoucherNo('sale_return'),
            'voucher_type'   => 'sale_return',
            'voucher_date'   => $saleReturn->return_date,
            'reference_type' => SaleReturn::class,
            'reference_id'   => $saleReturn->id,
            'ac_dr_sid'      => null,
            'ac_cr_sid'      => null,
            'amount'         => null,
            'remarks'        => 'Sale Return #' . $saleReturn->return_no
                                . ' against Invoice #'
                                . ($saleReturn->saleInvoice->invoice_no ?? '-'),
            'created_by'     => auth()->id(),
        ]);

        $entries = [];

        $goldMaterialDr = round($totals['gold_material'],    2);
        $diaMaterialDr  = round($totals['diamond_material'], 2);
        $makingDr       = round($totals['making'],           2);
        $goldPartsDr    = round($totals['gold_parts']    ?? 0, 2);
        $diaPartsDr     = round($totals['diamond_parts'] ?? 0, 2);
        $vatDr          = round($totals['vat'],              2);

        $totalReturn = round(
            $goldMaterialDr + $diaMaterialDr + $makingDr + $goldPartsDr + $diaPartsDr + $vatDr,
            2
        );

        if ($totalReturn <= 0) {
            throw new \Exception(
                "Return #{$saleReturn->return_no} has zero value — no accounting entries created."
            );
        }

        // ── STEP 1: DR each revenue account (reverse the original sale CRs) ──
        // Matching exact codes from seeder:
        //   401001 = Gold Sales Revenue
        //   401002 = Diamond Sales Revenue
        //   402001 = Making Charges Income
        //   208001 = Output VAT Payable (liability — DR reduces the liability)

        if ($goldMaterialDr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('401001'),   // Gold Sales Revenue
                'debit'      => $goldMaterialDr,
                'credit'     => 0,
                'narration'  => 'Gold revenue reversed — Return #' . $saleReturn->return_no,
            ];
        }

        if ($diaMaterialDr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('401002'),   // Diamond Sales Revenue
                'debit'      => $diaMaterialDr,
                'credit'     => 0,
                'narration'  => 'Diamond revenue reversed — Return #' . $saleReturn->return_no,
            ];
        }

        if ($makingDr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('402001'),   // Making Charges Income
                'debit'      => $makingDr,
                'credit'     => 0,
                'narration'  => 'Making revenue reversed — Return #' . $saleReturn->return_no,
            ];
        }

        // Gold parts → Gold Sales Revenue account
        if ($goldPartsDr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('401001'),   // Gold Sales Revenue
                'debit'      => $goldPartsDr,
                'credit'     => 0,
                'narration'  => 'Gold parts revenue reversed — Return #' . $saleReturn->return_no,
            ];
        }

        // Diamond parts → Diamond Sales Revenue account
        if ($diaPartsDr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('401002'),   // Diamond Sales Revenue
                'debit'      => $diaPartsDr,
                'credit'     => 0,
                'narration'  => 'Diamond parts revenue reversed — Return #' . $saleReturn->return_no,
            ];
        }

        // VAT → Output VAT Payable (DR reduces the liability)
        if ($vatDr > 0) {
            $entries[] = [
                'voucher_id' => $voucher->id,
                'account_id' => $acct('208001'),   // Output VAT Payable
                'debit'      => $vatDr,
                'credit'     => 0,
                'narration'  => 'Output VAT reversed — Return #' . $saleReturn->return_no,
            ];
        }

        // ── STEP 2: ONE CR entry — how we pay the customer back ──

        switch ($saleReturn->refund_method) {

            case 'credit_note':
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $saleReturn->customer_id,
                    'debit'      => 0,
                    'credit'     => $totalReturn,
                    'narration'  => 'Credit note issued to customer — Return #' . $saleReturn->return_no,
                ];
                break;

            case 'cash':
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $acct('101001'),   // Cash in Hand
                    'debit'      => 0,
                    'credit'     => $totalReturn,
                    'narration'  => 'Cash refunded to customer — Return #' . $saleReturn->return_no,
                ];
                break;

            case 'cheque':
                if (!$saleReturn->bank_name) {
                    throw new \Exception(
                        'Bank account required for cheque refund (Return #' . $saleReturn->return_no . ').'
                    );
                }
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $saleReturn->bank_name,
                    'debit'      => 0,
                    'credit'     => $totalReturn,
                    'narration'  => 'Cheque #' . $saleReturn->cheque_no
                                    . ' issued to customer — Return #' . $saleReturn->return_no,
                ];
                break;

            case 'bank_transfer':
                if (!$saleReturn->transfer_from_bank) {
                    throw new \Exception(
                        'Transfer bank required for bank transfer refund (Return #' . $saleReturn->return_no . ').'
                    );
                }
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $saleReturn->transfer_from_bank,
                    'debit'      => 0,
                    'credit'     => $totalReturn,
                    'narration'  => 'Bank transfer Ref# ' . $saleReturn->transaction_id
                                    . ' to customer — Return #' . $saleReturn->return_no,
                ];
                break;

            case 'material_return':
                // Customer returns gold — CR Gold Inventory (we give back gold)
                $entries[] = [
                    'voucher_id' => $voucher->id,
                    'account_id' => $acct('104001'),   // Gold Inventory
                    'debit'      => 0,
                    'credit'     => $totalReturn,
                    'narration'  => 'Gold inventory given back to customer — Return #' . $saleReturn->return_no,
                ];
                break;

            default:
                throw new \Exception(
                    'Unrecognised refund method: "' . $saleReturn->refund_method . '"'
                );
        }

        foreach ($entries as $entry) {
            AccountingEntry::create($entry);
        }

        $sumDebits  = round(collect($entries)->sum('debit'),  2);
        $sumCredits = round(collect($entries)->sum('credit'), 2);

        if ($sumDebits !== $sumCredits) {
            throw new \Exception(
                "Accounting imbalance on Return #{$saleReturn->return_no}: "
                . "Debits {$sumDebits} ≠ Credits {$sumCredits}."
            );
        }

        Log::info('Sale return accounting entries created', [
            'return_no'        => $saleReturn->return_no,
            'refund_method'    => $saleReturn->refund_method,
            'gold_material_dr' => $goldMaterialDr,
            'dia_material_dr'  => $diaMaterialDr,
            'making_dr'        => $makingDr,
            'gold_parts_dr'    => $goldPartsDr,
            'dia_parts_dr'     => $diaPartsDr,
            'vat_dr'           => $vatDr,
            'total_return'     => $totalReturn,
            'sum_debits'       => $sumDebits,
            'sum_credits'      => $sumCredits,
        ]);

        return $voucher;
    }
}