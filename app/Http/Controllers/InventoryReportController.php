<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InventoryReportController extends Controller
{
    // =========================================================================
    // ENTRY POINT
    // =========================================================================

    public function inventoryReports(Request $request)
    {
        try {
            $from = $request->from_date ?? Carbon::now()->startOfYear()->format('Y-m-d');
            $to   = $request->to_date   ?? Carbon::now()->format('Y-m-d');
            $tab  = $request->tab       ?? 'SIH';

            $unsoldItems    = collect();
            $purchasedItems = collect();
            $soldItems      = collect();
            $weightSummary  = [];

            switch ($tab) {
                case 'SIH': $unsoldItems    = $this->buildStockInHand($to);              break;
                case 'PI':  $purchasedItems = $this->buildPurchasedItems($from, $to);    break;
                case 'SI':  $soldItems      = $this->buildSoldItems($from, $to);          break;
                case 'WS':  $weightSummary  = $this->buildWeightSummary($from, $to);     break;
            }

            return view('reports.inventory_reports', compact(
                'unsoldItems', 'purchasedItems', 'soldItems', 'weightSummary',
                'from', 'to', 'tab'
            ));

        } catch (\Throwable $e) {
            Log::error('InventoryReportController::inventoryReports — ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Error generating inventory report: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 1. STOCK IN HAND
    // Logic: every purchased item (with a barcode) whose barcode does NOT appear
    // in sale_invoice_items is considered "in hand" as of $to date.
    // Items without barcodes are included with barcode = 'N/A'.
    // =========================================================================

    private function buildStockInHand(string $to): \Illuminate\Support\Collection
    {
        try {
            // Collect all barcodes that have been sold up to $to
            $soldBarcodes = SaleInvoiceItem::whereNotNull('barcode_number')
                ->whereHas('saleInvoice', function ($q) use ($to) {
                    $q->where('invoice_date', '<=', $to)
                      ->whereNull('deleted_at');
                })
                ->pluck('barcode_number')
                ->toArray();

            // All purchased items up to $to — exclude sold barcodes
            return PurchaseInvoiceItem::with(['purchaseInvoice.vendor'])
                ->whereHas('purchaseInvoice', function ($q) use ($to) {
                    $q->where('invoice_date', '<=', $to)
                      ->whereNull('deleted_at');
                })
                ->where(function ($q) use ($soldBarcodes) {
                    // Include items with no barcode (always in hand)
                    // OR items whose barcode was NOT sold
                    $q->whereNull('barcode_number')
                      ->orWhereNotIn('barcode_number', $soldBarcodes);
                })
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    $inv = $item->purchaseInvoice;
                    return [
                        'barcode'          => $item->barcode_number   ?? 'N/A',
                        'item_name'        => $item->item_name        ?: '-',
                        'description'      => $item->item_description ?? '-',
                        'vendor'           => $inv->vendor->name      ?? '-',
                        'purchase_invoice' => $inv->invoice_no,
                        'purchase_date'    => $inv->invoice_date instanceof Carbon
                            ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                        'material_type'    => ucfirst($item->material_type),
                        'purity'           => $item->purity,
                        'gross_weight'     => $item->gross_weight,
                        'net_weight'       => $item->net_weight,
                        'purity_weight'    => $item->purity_weight,
                        'col_995'          => $item->col_995,
                        'making_rate'      => $item->making_rate,
                        'making_value'     => $item->making_value,
                        'material_value'   => $item->material_value,
                        'vat_amount'       => $item->vat_amount,
                        'item_total'       => $item->item_total,
                        'gold_rate_aed'    => $inv->gold_rate_aed ?? 0,
                        'currency'         => $inv->currency,
                        'is_printed'       => $item->is_printed,
                    ];
                });

        } catch (\Throwable $e) {
            Log::error('InventoryReportController::buildStockInHand — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 2. PURCHASED ITEMS — all items received in date range
    // =========================================================================

    private function buildPurchasedItems(string $from, string $to): \Illuminate\Support\Collection
    {
        try {
            return PurchaseInvoiceItem::with(['purchaseInvoice.vendor'])
                ->whereHas('purchaseInvoice', function ($q) use ($from, $to) {
                    $q->whereBetween('invoice_date', [$from, $to])
                      ->whereNull('deleted_at');
                })
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    $inv = $item->purchaseInvoice;
                    return [
                        'barcode'          => $item->barcode_number   ?? 'N/A',
                        'item_name'        => $item->item_name        ?: '-',
                        'description'      => $item->item_description ?? '-',
                        'vendor'           => $inv->vendor->name      ?? '-',
                        'purchase_invoice' => $inv->invoice_no,
                        'purchase_date'    => $inv->invoice_date instanceof Carbon
                            ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                        'material_type'    => ucfirst($item->material_type),
                        'purity'           => $item->purity,
                        'gross_weight'     => $item->gross_weight,
                        'net_weight'       => $item->net_weight,
                        'purity_weight'    => $item->purity_weight,
                        'making_value'     => $item->making_value,
                        'material_value'   => $item->material_value,
                        'vat_amount'       => $item->vat_amount,
                        'item_total'       => $item->item_total,
                        'gold_rate_aed'    => $inv->gold_rate_aed ?? 0,
                        'currency'         => $inv->currency,
                        'is_printed'       => $item->is_printed,
                    ];
                });

        } catch (\Throwable $e) {
            Log::error('InventoryReportController::buildPurchasedItems — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 3. SOLD ITEMS — all items sold in date range with profit
    // =========================================================================

    private function buildSoldItems(string $from, string $to): \Illuminate\Support\Collection
    {
        try {
            return SaleInvoiceItem::with(['saleInvoice.customer'])
                ->whereHas('saleInvoice', function ($q) use ($from, $to) {
                    $q->whereBetween('invoice_date', [$from, $to])
                      ->whereNull('deleted_at');
                })
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    $inv      = $item->saleInvoice;
                    $purGoldR = (float) ($inv->purchase_gold_rate_aed   ?? 0);
                    $purMkR   = (float) ($inv->purchase_making_rate_aed ?? 0);
                    $cost     = ($purGoldR * (float) $item->purity_weight) + ((float) $item->gross_weight * $purMkR);
                    $sale     = (float) $item->item_total;
                    $profit   = $sale - $cost;
                    $margin   = $cost > 0 ? round(($profit / $cost) * 100, 2) : 0;

                    // Original purchase cost from purchase item if barcode matches
                    $purchaseCost = 0;
                    if ($item->barcode_number) {
                        $pi = PurchaseInvoiceItem::where('barcode_number', $item->barcode_number)->value('item_total');
                        $purchaseCost = (float) ($pi ?? 0);
                    }

                    return [
                        'barcode'       => $item->barcode_number ?? 'N/A',
                        'item_name'     => $item->item_name      ?: '-',
                        'customer'      => $inv->customer->name  ?? '-',
                        'sale_invoice'  => $inv->invoice_no,
                        'sale_date'     => $inv->invoice_date instanceof Carbon
                            ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                        'material_type' => ucfirst($item->material_type),
                        'purity'        => $item->purity,
                        'gross_weight'  => $item->gross_weight,
                        'purity_weight' => $item->purity_weight,
                        'making_value'  => $item->making_value,
                        'material_value'=> $item->material_value,
                        'vat_amount'    => $item->vat_amount,
                        'item_total'    => $sale,
                        'cost'          => $cost,
                        'profit'        => $profit,
                        'margin'        => $margin,
                        'purchase_cost' => $purchaseCost,
                        'currency'      => $inv->currency,
                        'gold_rate_aed' => $inv->gold_rate_aed ?? 0,
                    ];
                });

        } catch (\Throwable $e) {
            Log::error('InventoryReportController::buildSoldItems — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 4. WEIGHT SUMMARY — gold & diamond movement analysis
    // =========================================================================

    private function buildWeightSummary(string $from, string $to): array
    {
        try {
            $purchaseItems = PurchaseInvoiceItem::whereHas('purchaseInvoice', function ($q) use ($from, $to) {
                $q->whereBetween('invoice_date', [$from, $to])->whereNull('deleted_at');
            })->get();

            $saleItems = SaleInvoiceItem::whereHas('saleInvoice', function ($q) use ($from, $to) {
                $q->whereBetween('invoice_date', [$from, $to])->whereNull('deleted_at');
            })->get();

            $inHandItems = $this->buildStockInHand($to);

            $goldP  = $purchaseItems->where('material_type', 'gold');
            $goldS  = $saleItems->where('material_type', 'gold');
            $goldH  = $inHandItems->where('material_type', 'Gold');

            $diaP   = $purchaseItems->where('material_type', 'diamond');
            $diaS   = $saleItems->where('material_type', 'diamond');
            $diaH   = $inHandItems->where('material_type', 'Diamond');

            return [
                // Gold
                'gold_purchased_gross'   => $goldP->sum('gross_weight'),
                'gold_purchased_net'     => $goldP->sum('net_weight'),
                'gold_purchased_purity'  => $goldP->sum('purity_weight'),
                'gold_purchased_995'     => $goldP->sum('col_995'),
                'gold_purchased_value'   => $goldP->sum('material_value'),
                'gold_purchased_count'   => $goldP->count(),

                'gold_sold_gross'        => $goldS->sum('gross_weight'),
                'gold_sold_purity'       => $goldS->sum('purity_weight'),
                'gold_sold_value'        => $goldS->sum('material_value'),
                'gold_sold_count'        => $goldS->count(),

                'gold_inhand_gross'      => $goldH->sum('gross_weight'),
                'gold_inhand_purity'     => $goldH->sum('purity_weight'),
                'gold_inhand_value'      => $goldH->sum('material_value'),
                'gold_inhand_count'      => $goldH->count(),

                // Diamond
                'diamond_purchased_gross'  => $diaP->sum('gross_weight'),
                'diamond_purchased_purity' => $diaP->sum('purity_weight'),
                'diamond_purchased_value'  => $diaP->sum('material_value'),
                'diamond_purchased_count'  => $diaP->count(),

                'diamond_sold_gross'       => $diaS->sum('gross_weight'),
                'diamond_sold_purity'      => $diaS->sum('purity_weight'),
                'diamond_sold_value'       => $diaS->sum('material_value'),
                'diamond_sold_count'       => $diaS->count(),

                'diamond_inhand_gross'     => $diaH->sum('gross_weight'),
                'diamond_inhand_purity'    => $diaH->sum('purity_weight'),
                'diamond_inhand_value'     => $diaH->sum('material_value'),
                'diamond_inhand_count'     => $diaH->count(),

                // Value totals
                'total_purchased_value'    => $purchaseItems->sum('item_total'),
                'total_sold_value'         => $saleItems->sum('item_total'),
                'total_inhand_value'       => $inHandItems->sum('item_total'),
            ];

        } catch (\Throwable $e) {
            Log::error('InventoryReportController::buildWeightSummary — ' . $e->getMessage());
            return [];
        }
    }
}