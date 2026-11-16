<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\ChartOfAccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PurchaseReportController extends Controller
{
    // =========================================================================
    // ENTRY POINT
    // =========================================================================

    public function purchaseReports(Request $request)
    {
        try {
            $from     = $request->from_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
            $to       = $request->to_date   ?? Carbon::now()->format('Y-m-d');
            $tab      = $request->tab       ?? 'PR';
            $vendorId = $request->vendor_id ? (int) $request->vendor_id : null;

            $vendors = ChartOfAccounts::where('account_type', 'vendor')->orderBy('name')->get();

            $purchaseRegister   = collect();
            $vendorWisePurchase = collect();
            $purchaseSummary    = [];
            $itemAnalysis       = collect();
            $paymentSummary     = collect();

            switch ($tab) {
                case 'PR': $purchaseRegister   = $this->buildPurchaseRegister($from, $to, $vendorId);  break;
                case 'VW': $vendorWisePurchase  = $this->buildVendorWise($from, $to, $vendorId);        break;
                case 'PS': $purchaseSummary     = $this->buildPurchaseSummary($from, $to, $vendorId);   break;
                case 'IA': $itemAnalysis         = $this->buildItemAnalysis($from, $to, $vendorId);      break;
                case 'PM': $paymentSummary       = $this->buildPaymentSummary($from, $to, $vendorId);    break;
            }

            return view('reports.purchase_reports', compact(
                'purchaseRegister', 'vendorWisePurchase', 'purchaseSummary',
                'itemAnalysis', 'paymentSummary',
                'vendors', 'from', 'to', 'tab', 'vendorId'
            ));

        } catch (\Throwable $e) {
            Log::error('PurchaseReportController::purchaseReports — ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Error generating purchase report: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 1. PURCHASE REGISTER
    // =========================================================================

    private function buildPurchaseRegister(string $from, string $to, ?int $vendorId): \Illuminate\Support\Collection
    {
        try {
            $query = PurchaseInvoice::with(['vendor', 'items'])
                ->whereBetween('invoice_date', [$from, $to])
                ->orderBy('invoice_date')->orderBy('id');

            if ($vendorId) $query->where('vendor_id', $vendorId);

            return $query->get()->map(function ($inv) {
                return [
                    'id'               => $inv->id,
                    'invoice_no'       => $inv->invoice_no,
                    'invoice_date'     => $inv->invoice_date instanceof Carbon
                        ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                    'is_taxable'       => $inv->is_taxable,
                    'vendor'           => $inv->vendor->name ?? '-',
                    'currency'         => $inv->currency,
                    'exchange_rate'    => $inv->exchange_rate  ?? 1,
                    'gold_rate_aed'    => $inv->gold_rate_aed  ?? 0,
                    'gold_rate_usd'    => $inv->gold_rate_usd  ?? 0,
                    'diamond_rate_aed' => $inv->diamond_rate_aed ?? 0,
                    'payment_method'   => $inv->payment_method  ?? '-',
                    'net_amount'       => $inv->net_amount      ?? 0,
                    'net_amount_aed'   => $inv->net_amount_aed  ?? 0,
                    'total_items'      => $inv->items->count(),
                    'total_material'   => $inv->items->sum('material_value'),
                    'total_making'     => $inv->items->sum('making_value'),
                    'total_vat'        => $inv->items->sum('vat_amount'),
                    'total_gross_wt'   => $inv->items->sum('gross_weight'),
                    'total_net_wt'     => $inv->items->sum('net_weight'),
                    'total_purity_wt'  => $inv->items->sum('purity_weight'),
                ];
            });

        } catch (\Throwable $e) {
            Log::error('PurchaseReportController::buildPurchaseRegister — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 2. VENDOR-WISE PURCHASE
    // =========================================================================

    private function buildVendorWise(string $from, string $to, ?int $vendorId): \Illuminate\Support\Collection
    {
        try {
            $query = PurchaseInvoice::with(['vendor', 'items'])
                ->whereBetween('invoice_date', [$from, $to])
                ->orderBy('vendor_id')->orderBy('invoice_date');

            if ($vendorId) $query->where('vendor_id', $vendorId);

            return $query->get()->groupBy('vendor_id')->map(function ($invoices) {
                $vendor   = $invoices->first()->vendor;
                $allItems = $invoices->flatMap(fn($inv) => $inv->items->map(fn($item) => [
                    'invoice_no'     => $inv->invoice_no,
                    'invoice_date'   => $inv->invoice_date instanceof Carbon
                        ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                    'item_name'      => $item->item_name      ?: '-',
                    'material_type'  => ucfirst($item->material_type),
                    'gross_weight'   => $item->gross_weight,
                    'net_weight'     => $item->net_weight,
                    'purity'         => $item->purity,
                    'purity_weight'  => $item->purity_weight,
                    'making_rate'    => $item->making_rate,
                    'making_value'   => $item->making_value,
                    'material_value' => $item->material_value,
                    'vat_amount'     => $item->vat_amount,
                    'item_total'     => $item->item_total,
                ]));

                return [
                    'vendor_name'     => $vendor->name ?? 'Unknown',
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
            Log::error('PurchaseReportController::buildVendorWise — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 3. PURCHASE SUMMARY
    // =========================================================================

    private function buildPurchaseSummary(string $from, string $to, ?int $vendorId): array
    {
        try {
            $query = PurchaseInvoice::with('items')
                ->whereBetween('invoice_date', [$from, $to]);

            if ($vendorId) $query->where('vendor_id', $vendorId);

            $invoices = $query->get();
            $allItems = $invoices->flatMap->items;

            $byPayment  = $invoices->groupBy('payment_method')->map(fn($g) => [
                'count' => $g->count(), 'amount_aed' => $g->sum('net_amount_aed'),
            ]);
            $byCurrency = $invoices->groupBy('currency')->map(fn($g) => [
                'count' => $g->count(), 'amount' => $g->sum('net_amount'),
            ]);
            $byMaterial = $allItems->groupBy('material_type')->map(fn($g) => [
                'item_count'     => $g->count(),
                'gross_weight'   => $g->sum('gross_weight'),
                'purity_weight'  => $g->sum('purity_weight'),
                'material_value' => $g->sum('material_value'),
                'making_value'   => $g->sum('making_value'),
                'vat_amount'     => $g->sum('vat_amount'),
                'item_total'     => $g->sum('item_total'),
            ]);

            $taxable    = $invoices->where('is_taxable', true);
            $nonTaxable = $invoices->where('is_taxable', false);

            return [
                'total_invoices'     => $invoices->count(),
                'total_amount_aed'   => $invoices->sum('net_amount_aed'),
                'total_material_val' => $allItems->sum('material_value'),
                'total_making_val'   => $allItems->sum('making_value'),
                'total_vat'          => $allItems->sum('vat_amount'),
                'total_gross_wt'     => $allItems->sum('gross_weight'),
                'total_purity_wt'    => $allItems->sum('purity_weight'),
                'by_payment'         => $byPayment,
                'by_currency'        => $byCurrency,
                'by_material'        => $byMaterial,
                'taxable_count'      => $taxable->count(),
                'taxable_amount'     => $taxable->sum('net_amount_aed'),
                'non_taxable_count'  => $nonTaxable->count(),
                'non_taxable_amount' => $nonTaxable->sum('net_amount_aed'),
            ];

        } catch (\Throwable $e) {
            Log::error('PurchaseReportController::buildPurchaseSummary — ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // 4. ITEM ANALYSIS
    // =========================================================================

    private function buildItemAnalysis(string $from, string $to, ?int $vendorId): \Illuminate\Support\Collection
    {
        try {
            return PurchaseInvoiceItem::with(['purchaseInvoice.vendor', 'parts'])
                ->whereHas('purchaseInvoice', function ($q) use ($from, $to, $vendorId) {
                    $q->whereBetween('invoice_date', [$from, $to]);
                    if ($vendorId) $q->where('vendor_id', $vendorId);
                })
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    $inv = $item->purchaseInvoice;
                    return [
                        'invoice_no'     => $inv->invoice_no,
                        'invoice_date'   => $inv->invoice_date instanceof Carbon
                            ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                        'vendor'         => $inv->vendor->name  ?? '-',
                        'item_name'      => $item->item_name    ?: '-',
                        'barcode'        => $item->barcode_number ?? '-',
                        'material_type'  => ucfirst($item->material_type),
                        'purity'         => $item->purity,
                        'gross_weight'   => $item->gross_weight,
                        'net_weight'     => $item->net_weight,
                        'purity_weight'  => $item->purity_weight,
                        'col_995'        => $item->col_995,
                        'making_rate'    => $item->making_rate,
                        'making_value'   => $item->making_value,
                        'material_rate'  => $item->material_rate,
                        'material_value' => $item->material_value,
                        'vat_percent'    => $item->vat_percent,
                        'vat_amount'     => $item->vat_amount,
                        'parts_total'    => $item->parts_total,
                        'item_total'     => $item->item_total,
                        'parts_count'    => $item->parts->count(),
                        'gold_rate_aed'  => $inv->gold_rate_aed ?? 0,
                        'currency'       => $inv->currency,
                    ];
                });

        } catch (\Throwable $e) {
            Log::error('PurchaseReportController::buildItemAnalysis — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 5. PAYMENT SUMMARY
    // =========================================================================

    private function buildPaymentSummary(string $from, string $to, ?int $vendorId): \Illuminate\Support\Collection
    {
        try {
            $query = PurchaseInvoice::with('vendor')
                ->whereBetween('invoice_date', [$from, $to]);

            if ($vendorId) $query->where('vendor_id', $vendorId);

            return $query->get()->map(function ($inv) {
                return [
                    'invoice_no'      => $inv->invoice_no,
                    'invoice_date'    => $inv->invoice_date instanceof Carbon
                        ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                    'vendor'          => $inv->vendor->name ?? '-',
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
            Log::error('PurchaseReportController::buildPaymentSummary — ' . $e->getMessage());
            return collect();
        }
    }
}