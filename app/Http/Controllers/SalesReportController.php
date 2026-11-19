<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\PurchaseInvoiceItem;
use App\Models\ChartOfAccounts;
use App\Services\myPDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SalesReportController extends Controller
{
    // =========================================================================
    // ENTRY POINT
    // =========================================================================

    public function saleReports(Request $request)
    {
        try {
            $from       = $request->from_date  ?? Carbon::now()->startOfMonth()->format('Y-m-d');
            $to         = $request->to_date    ?? Carbon::now()->format('Y-m-d');
            $tab        = $request->tab        ?? 'SR';
            $customerId = $request->customer_id ? (int) $request->customer_id : null;

            $customers = ChartOfAccounts::where('account_type', 'customer')->orderBy('name')->get();

            $saleRegister       = collect();
            $customerWise       = collect();
            $profitReport       = collect();
            $itemAnalysis       = collect();
            $paymentSummary     = collect();
            $salesReturnRegister = collect();

            switch ($tab) {
                case 'SR':  $saleRegister         = $this->buildSaleRegister($from, $to, $customerId);         break;
                case 'CW':  $customerWise         = $this->buildCustomerWise($from, $to, $customerId);         break;
                case 'PR':  $profitReport         = $this->buildProfitReport($from, $to, $customerId);         break;
                case 'IA':  $itemAnalysis         = $this->buildItemAnalysis($from, $to, $customerId);         break;
                case 'PM':  $paymentSummary       = $this->buildPaymentSummary($from, $to, $customerId);       break;
                case 'SRN': $salesReturnRegister  = $this->buildSalesReturnRegister($from, $to, $customerId);  break;
            }

            return view('reports.sales_reports', compact(
                'saleRegister', 'customerWise', 'profitReport',
                'itemAnalysis', 'paymentSummary', 'salesReturnRegister',
                'customers', 'from', 'to', 'tab', 'customerId'
            ));

        } catch (\Throwable $e) {
            Log::error('SalesReportController::saleReports — ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Error generating sale report: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PRINT PROFIT PDF — per sale invoice
    // =========================================================================

    public function printProfit(int $id)
    {
        try {
            $invoice = SaleInvoice::with(['customer', 'items.parts'])->findOrFail($id);
            $p = $this->calcInvoiceProfit($invoice);
            $profit  = $p['profit'];
            $cost    = $p['cost'];
            $margin  = $p['margin'];
            $revenue = $p['revenue'];
            $returnedAmount = $p['returned_amount'];

            $pdf = new myPDF();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetTitle('Profit — ' . $invoice->invoice_no);
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
                            <strong>YUNEEQ FINE JEWELLERY L.L.C</strong><br>
                            Suite #M04, Mezzanine floor, Al Buteen 2 Building, Gold Souq. Gate no.1, Deira, Dubai<br>
                            TRN No: 104902647700003
                        </td>
                    </tr>
                </table><hr>', true, false, false, false);

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, 'PROFIT ANALYSIS — ' . $invoice->invoice_no, 0, 1, 'C');
            $pdf->Ln(2);
            $pdf->SetFont('helvetica', '', 9);

            $purGoldR = (float) ($invoice->purchase_gold_rate_aed   ?? 0);
            $purMkR   = (float) ($invoice->purchase_making_rate_aed ?? 0);

            $html = '
            <table border="1" cellpadding="4" width="100%" style="font-size:9px;">
                <thead>
                    <tr style="background-color:#f5f5f5;font-weight:bold;text-align:center;">
                        <th>#</th><th>Item</th><th>Gross Wt</th><th>Purity Wt</th>
                        <th>Material Val</th><th>Making Val</th><th>Parts Total</th>
                        <th>VAT</th><th>Sale Total</th><th>Cost</th><th>Profit</th><th>Margin%</th>
                    </tr>
                </thead>
                <tbody>';

            $rowNum = 1;
            foreach ($invoice->items as $item) {
                $purityWt    = (float) $item->purity_weight;
                $grossWt     = (float) $item->gross_weight;
                $costItem    = ($purGoldR * $purityWt) + ($grossWt * $purMkR);
                $saleItem    = (float) $item->item_total;
                $profitItem  = $saleItem - $costItem;
                $marginItem  = $costItem > 0 ? round(($profitItem / $costItem) * 100, 2) : 0;
                $marginColor = $marginItem >= 0 ? '#198754' : '#dc3545';

                $html .= '
                    <tr style="text-align:center;">
                        <td>' . $rowNum++ . '</td>
                        <td style="text-align:left;">' . ($item->item_name ?: '-') . '</td>
                        <td>' . number_format($grossWt, 3) . '</td>
                        <td>' . number_format($purityWt, 3) . '</td>
                        <td>' . number_format($item->material_value, 2) . '</td>
                        <td>' . number_format($item->making_value, 2) . '</td>
                        <td>' . number_format($item->parts_total ?? 0, 2) . '</td>
                        <td>' . number_format($item->vat_amount, 2) . '</td>
                        <td style="font-weight:bold;">' . number_format($saleItem, 2) . '</td>
                        <td>' . number_format($costItem, 2) . '</td>
                        <td style="color:' . $marginColor . ';font-weight:bold;">' . number_format($profitItem, 2) . '</td>
                        <td style="color:' . $marginColor . ';">' . $marginItem . '%</td>
                    </tr>';
            }

            $marginColor = $margin >= 0 ? '#198754' : '#dc3545';
            $html .= '
                <tr style="font-weight:bold;background-color:#f5f5f5;text-align:center;">
                    <td colspan="8" align="right">TOTAL' . ($returnedAmount > 0 ? ' (net of returns)' : '') . '</td>
                    <td>' . number_format($revenue, 2) . '</td>
                    <td>' . number_format($cost, 2) . '</td>
                    <td style="color:' . $marginColor . ';">' . number_format($profit, 2) . '</td>
                    <td style="color:' . $marginColor . ';">' . round($margin, 2) . '%</td>
                </tr>
            </tbody></table>';

            $pdf->writeHTML($html, true, false, false, false);

            $pdf->Ln(4);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell(0, 5,
                'Purchase Gold Rate: AED ' . number_format($purGoldR, 4) . '/gm  |  ' .
                'Purchase Making Rate: AED ' . number_format($purMkR, 4) . '/gm  |  ' .
                'Sale Currency: ' . $invoice->currency .
                ($returnedAmount > 0 ? '  |  Returned: ' . number_format($returnedAmount, 2) . ' (excluded above)' : ''),
                0, 1, 'L');

            return $pdf->Output('profit_' . $invoice->invoice_no . '.pdf', 'I');

        } catch (\Throwable $e) {
            Log::error('SalesReportController::printProfit — ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            abort(500, 'Error generating profit PDF: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PROFIT CALCULATION HELPER
    // Cost  = (purchase_gold_rate × purity_weight) + (gross_weight × purchase_making_rate)
    // Returns [profit, cost, margin%]
    // =========================================================================

    private function calcInvoiceProfit(SaleInvoice $inv): array
    {
        $purGoldR = (float) ($inv->purchase_gold_rate_aed   ?? 0);
        $purMkR   = (float) ($inv->purchase_making_rate_aed ?? 0);

        if (!$inv->relationLoaded('items')) $inv->load('items');

        $grossCost = $inv->items->sum(function ($item) use ($purGoldR, $purMkR) {
            return ($purGoldR * (float) $item->purity_weight)
                 + ((float) $item->gross_weight * $purMkR);
        });

        $grossRevenue = (float) ($inv->net_amount ?? 0);

        // FIX (profit overstated after a Sale Return): this used to report
        // the full original invoice revenue/cost forever, even after some or
        // all of its items were returned via Sale Return — a returned item
        // generates no real profit, but kept counting its original profit
        // here indefinitely. Net out any items of THIS invoice that have
        // since been returned, using the same purchase rates the invoice
        // itself sold under (a return doesn't carry its own purchase rate).
        $itemIds = $inv->items->pluck('id');
        $returnedItems = $itemIds->isEmpty()
            ? collect()
            : SaleReturnItem::whereIn('sale_invoice_item_id', $itemIds)->get();

        $returnedRevenue = (float) $returnedItems->sum('item_total');
        $returnedCost    = (float) $returnedItems->sum(function ($ri) use ($purGoldR, $purMkR) {
            return ($purGoldR * (float) $ri->purity_weight)
                 + ((float) $ri->gross_weight * $purMkR);
        });

        $revenue = $grossRevenue - $returnedRevenue;
        $cost    = $grossCost    - $returnedCost;
        $profit  = $revenue - $cost;
        $margin  = $cost > 0 ? round(($profit / $cost) * 100, 2) : 0;

        $exRate     = (float) ($inv->exchange_rate ?? 1);
        $revenueAed = $inv->currency === 'USD' ? round($revenue * $exRate, 2) : round($revenue, 2);

        return [
            'profit'          => round($profit, 2),
            'cost'            => round($cost, 2),
            'margin'          => $margin,
            'revenue'         => round($revenue, 2),
            'revenue_aed'     => $revenueAed,
            'returned_amount' => round($returnedRevenue, 2),
        ];
    }

    // =========================================================================
    // 1. SALE REGISTER
    // =========================================================================

    private function buildSaleRegister(string $from, string $to, ?int $customerId): \Illuminate\Support\Collection
    {
        try {
            $query = SaleInvoice::with(['customer', 'items'])
                ->whereBetween('invoice_date', [$from, $to])
                ->orderBy('invoice_date')->orderBy('id');

            if ($customerId) $query->where('customer_id', $customerId);

            return $query->get()->map(function ($inv) {
                return [
                    'id'              => $inv->id,
                    'invoice_no'      => $inv->invoice_no,
                    'invoice_date'    => $inv->invoice_date instanceof Carbon
                        ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                    'is_taxable'      => $inv->is_taxable,
                    'customer'        => $inv->customer->name ?? '-',
                    'currency'        => $inv->currency,
                    'exchange_rate'   => $inv->exchange_rate  ?? 1,
                    'gold_rate_aed'   => $inv->gold_rate_aed  ?? 0,
                    'gold_rate_usd'   => $inv->gold_rate_usd  ?? 0,
                    'payment_method'  => $inv->payment_method ?? '-',
                    'net_amount'      => $inv->net_amount      ?? 0,
                    'net_amount_aed'  => $inv->net_amount_aed  ?? 0,
                    'total_items'     => $inv->items->count(),
                    'total_material'  => $inv->items->sum('material_value'),
                    'total_making'    => $inv->items->sum('making_value'),
                    'total_vat'       => $inv->items->sum('vat_amount'),
                    'total_gross_wt'  => $inv->items->sum('gross_weight'),
                    'total_purity_wt' => $inv->items->sum('purity_weight'),
                ];
            });

        } catch (\Throwable $e) {
            Log::error('SalesReportController::buildSaleRegister — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 2. CUSTOMER-WISE SALE
    // =========================================================================

    private function buildCustomerWise(string $from, string $to, ?int $customerId): \Illuminate\Support\Collection
    {
        try {
            $query = SaleInvoice::with(['customer', 'items'])
                ->whereBetween('invoice_date', [$from, $to])
                ->orderBy('customer_id')->orderBy('invoice_date');

            if ($customerId) $query->where('customer_id', $customerId);

            return $query->get()->groupBy('customer_id')->map(function ($invoices) {
                $customer = $invoices->first()->customer;
                $allItems = $invoices->flatMap(fn($inv) => $inv->items->map(fn($item) => [
                    'invoice_no'     => $inv->invoice_no,
                    'invoice_date'   => $inv->invoice_date instanceof Carbon
                        ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                    'item_name'      => $item->item_name   ?: '-',
                    'material_type'  => ucfirst($item->material_type),
                    'gross_weight'   => $item->gross_weight,
                    'purity_weight'  => $item->purity_weight,
                    'making_value'   => $item->making_value,
                    'material_value' => $item->material_value,
                    'vat_amount'     => $item->vat_amount,
                    'item_total'     => $item->item_total,
                ]));

                return [
                    'customer_name'   => $customer->name ?? 'Unknown',
                    'invoice_count'   => $invoices->count(),
                    'items'           => $allItems,
                    'total_gross_wt'  => $allItems->sum('gross_weight'),
                    'total_purity_wt' => $allItems->sum('purity_weight'),
                    'total_making'    => $allItems->sum('making_value'),
                    'total_material'  => $allItems->sum('material_value'),
                    'total_vat'       => $allItems->sum('vat_amount'),
                    'total_amount'    => $invoices->sum('net_amount'),
                    'total_aed'       => $invoices->sum('net_amount_aed'),
                ];
            })->values();

        } catch (\Throwable $e) {
            Log::error('SalesReportController::buildCustomerWise — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 3. PROFIT REPORT
    // =========================================================================

    private function buildProfitReport(string $from, string $to, ?int $customerId): \Illuminate\Support\Collection
    {
        try {
            $query = SaleInvoice::with(['customer', 'items'])
                ->whereBetween('invoice_date', [$from, $to])
                ->orderBy('invoice_date');

            if ($customerId) $query->where('customer_id', $customerId);

            return $query->get()->map(function ($inv) {
                $p = $this->calcInvoiceProfit($inv);

                return [
                    'id'                  => $inv->id,
                    'invoice_no'          => $inv->invoice_no,
                    'invoice_date'        => $inv->invoice_date instanceof Carbon
                        ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                    'customer'            => $inv->customer->name ?? '-',
                    'currency'            => $inv->currency,
                    // FIX: net of any items since returned (see
                    // calcInvoiceProfit) rather than the original gross
                    // invoice total — 'returned_amount' shows how much of
                    // the original was excluded, for transparency.
                    'revenue'             => $p['revenue'],
                    'revenue_aed'         => $p['revenue_aed'],
                    'cost'                => $p['cost'],
                    'profit'              => $p['profit'],
                    'margin'              => $p['margin'],
                    'returned_amount'     => $p['returned_amount'],
                    'purchase_gold_rate'  => $inv->purchase_gold_rate_aed   ?? 0,
                    'purchase_mk_rate'    => $inv->purchase_making_rate_aed ?? 0,
                    'total_gross_wt'      => $inv->items->sum('gross_weight'),
                    'total_purity_wt'     => $inv->items->sum('purity_weight'),
                ];
            });

        } catch (\Throwable $e) {
            Log::error('SalesReportController::buildProfitReport — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 4. ITEM ANALYSIS
    // =========================================================================

    private function buildItemAnalysis(string $from, string $to, ?int $customerId): \Illuminate\Support\Collection
    {
        try {
            return SaleInvoiceItem::with(['saleInvoice.customer', 'parts'])
                ->whereHas('saleInvoice', function ($q) use ($from, $to, $customerId) {
                    $q->whereBetween('invoice_date', [$from, $to]);
                    if ($customerId) $q->where('customer_id', $customerId);
                })
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    $inv      = $item->saleInvoice;
                    $purGoldR = (float) ($inv->purchase_gold_rate_aed   ?? 0);
                    $purMkR   = (float) ($inv->purchase_making_rate_aed ?? 0);
                    $costItem = ($purGoldR * (float) $item->purity_weight) + ((float) $item->gross_weight * $purMkR);
                    $saleItem = (float) $item->item_total;
                    $profit   = $saleItem - $costItem;
                    $margin   = $costItem > 0 ? round(($profit / $costItem) * 100, 2) : 0;

                    return [
                        'invoice_no'     => $inv->invoice_no,
                        'invoice_date'   => $inv->invoice_date instanceof Carbon
                            ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                        'customer'       => $inv->customer->name ?? '-',
                        'item_name'      => $item->item_name     ?: '-',
                        'barcode'        => $item->barcode_number ?? '-',
                        'material_type'  => ucfirst($item->material_type),
                        'purity'         => $item->purity,
                        'gross_weight'   => $item->gross_weight,
                        'purity_weight'  => $item->purity_weight,
                        'making_rate'    => $item->making_rate,
                        'making_value'   => $item->making_value,
                        'material_value' => $item->material_value,
                        'vat_amount'     => $item->vat_amount,
                        'parts_total'    => $item->parts_total ?? 0,
                        'item_total'     => $saleItem,
                        'cost'           => $costItem,
                        'profit'         => $profit,
                        'margin'         => $margin,
                        'currency'       => $inv->currency,
                        'gold_rate_aed'  => $inv->gold_rate_aed ?? 0,
                    ];
                });

        } catch (\Throwable $e) {
            Log::error('SalesReportController::buildItemAnalysis — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 5. PAYMENT SUMMARY
    // =========================================================================

    private function buildPaymentSummary(string $from, string $to, ?int $customerId): \Illuminate\Support\Collection
    {
        try {
            $query = SaleInvoice::with('customer')
                ->whereBetween('invoice_date', [$from, $to]);

            if ($customerId) $query->where('customer_id', $customerId);

            return $query->get()->map(function ($inv) {
                return [
                    'invoice_no'      => $inv->invoice_no,
                    'invoice_date'    => $inv->invoice_date instanceof Carbon
                        ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                    'customer'        => $inv->customer->name ?? '-',
                    'payment_method'  => ucwords(str_replace(['+', '_'], [' + ', ' '], $inv->payment_method ?? '-')),
                    'currency'        => $inv->currency,
                    'net_amount'      => $inv->net_amount      ?? 0,
                    'net_amount_aed'  => $inv->net_amount_aed  ?? 0,
                    'exchange_rate'   => $inv->exchange_rate   ?? 1,
                    'cheque_no'       => $inv->cheque_no       ?? '-',
                    'cheque_date'     => $inv->cheque_date     ?? '-',
                    'transaction_id'  => $inv->transaction_id  ?? '-',
                    'transfer_date'   => $inv->transfer_date   ?? '-',
                    'transfer_amount' => $inv->transfer_amount ?? 0,
                ];
            });

        } catch (\Throwable $e) {
            Log::error('SalesReportController::buildPaymentSummary — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 6. SALES RETURN REGISTER
    // =========================================================================

    private function buildSalesReturnRegister(string $from, string $to, ?int $customerId): \Illuminate\Support\Collection
    {
        try {
            $query = SaleReturn::with(['customer', 'saleInvoice', 'items'])
                ->whereBetween('return_date', [$from, $to])
                ->whereNull('deleted_at')
                ->orderBy('return_date')->orderBy('id');

            if ($customerId) $query->where('customer_id', $customerId);

            return $query->get()->map(function ($ret) {
                return [
                    'id'              => $ret->id,
                    'return_no'       => $ret->return_no,
                    'return_date'     => $ret->return_date instanceof Carbon
                        ? $ret->return_date->format('d-M-Y') : $ret->return_date,
                    'invoice_no'      => $ret->saleInvoice->invoice_no ?? '-',
                    'customer'        => $ret->customer->name ?? '-',
                    'currency'        => $ret->currency,
                    'reason'          => $ret->reason ?? '-',
                    'refund_method'   => ucwords(str_replace('_', ' ', $ret->refund_method ?? '-')),
                    'total_items'     => $ret->items->count(),
                    'total_gross_wt'  => $ret->items->sum('gross_weight'),
                    'total_purity_wt' => $ret->items->sum('purity_weight'),
                    'total_material'  => $ret->items->sum('material_value'),
                    'total_making'    => $ret->items->sum('making_value'),
                    'total_vat'       => $ret->items->sum('vat_amount'),
                    'net_amount'      => $ret->net_amount     ?? 0,
                    'net_amount_aed'  => $ret->net_amount_aed ?? 0,
                ];
            });

        } catch (\Throwable $e) {
            Log::error('SalesReportController::buildSalesReturnRegister — ' . $e->getMessage());
            return collect();
        }
    }
}