<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        try {
            $from = $request->from_date ?? Carbon::now()->startOfYear()->format('Y-m-d');
            $to   = $request->to_date   ?? Carbon::now()->format('Y-m-d');
            $tab  = $request->tab       ?? 'SIH';

            $unsoldItems          = collect();
            $purchasedItems       = collect();
            $soldItems            = collect();
            $weightSummary        = [];
            $consignmentInventory = collect();

            switch ($tab) {
                case 'SIH': $unsoldItems          = $this->buildStockInHand($to);                  break;
                case 'PI':  $purchasedItems        = $this->buildPurchasedItems($from, $to);        break;
                case 'SI':  $soldItems             = $this->buildSoldItems($from, $to);             break;
                case 'WS':  $weightSummary         = $this->buildWeightSummary($from, $to);         break;
                case 'CI':  $consignmentInventory  = $this->buildConsignmentInventory($from, $to);  break;
            }

            return view('reports.inventory_reports', compact(
                'unsoldItems', 'purchasedItems', 'soldItems', 'weightSummary',
                'consignmentInventory',
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
    //
    // PARTIAL WEIGHT SUPPORT:
    //   A purchase item is NOT binary sold/unsold. Gold is sold in chunks.
    //   For each purchase item we calculate:
    //     sold_weight      = SUM of gross_weight from all sale_invoice_items
    //                        that share the same barcode_number
    //     outbound_weight  = gross_weight of any active outbound consignment items
    //                        that reference the same barcode (physically away)
    //     remaining_weight = purchased_gross_weight - sold_weight - outbound_weight
    //
    //   Items with remaining_weight > 0 appear in stock with that weight.
    //   Items with remaining_weight <= 0 are fully consumed and excluded.
    // =========================================================================

    private function buildStockInHand(string $to): \Illuminate\Support\Collection
    {
        try {
            // ── Step 1: Build sold-weight lookup keyed by barcode ─────────────
            // For each barcode, sum ALL gross_weight sold up to $to date.
            $soldWeightByBarcode = SaleInvoiceItem::whereNotNull('barcode_number')
                ->whereHas('saleInvoice', function ($q) use ($to) {
                    $q->where('invoice_date', '<=', $to)->whereNull('deleted_at');
                })
                ->selectRaw('barcode_number, SUM(gross_weight) as total_sold_weight')
                ->groupBy('barcode_number')
                ->pluck('total_sold_weight', 'barcode_number')
                ->toArray();

            // ── Step 2: Build outbound-consignment weight lookup by source_barcode ──
            // Items physically at a partner's shop (outbound, still in_stock) reduce
            // our available weight but haven't been sold yet.
            $outboundWeightByBarcode = ConsignmentItem::where('item_status', 'in_stock')
                ->whereHas('consignment', fn($q) => $q->where('direction', 'outbound'))
                ->whereNotNull('source_barcode')
                ->selectRaw('source_barcode, SUM(gross_weight) as total_outbound_weight')
                ->groupBy('source_barcode')
                ->pluck('total_outbound_weight', 'source_barcode')
                ->toArray();

            // ── Step 3: Load all purchase items up to $to ─────────────────────
            $purchaseItems = PurchaseInvoiceItem::with(['purchaseInvoice.vendor'])
                ->whereHas('purchaseInvoice', function ($q) use ($to) {
                    $q->where('invoice_date', '<=', $to)->whereNull('deleted_at');
                })
                ->orderBy('id')
                ->get();

            // ── Step 4: For each item, compute remaining weight ───────────────
            $result = collect();

            foreach ($purchaseItems as $item) {
                $barcode        = $item->barcode_number;
                $purchasedWt    = (float) $item->gross_weight;

                // How much of this barcode has been sold?
                $soldWt         = isset($barcode) ? (float) ($soldWeightByBarcode[$barcode]   ?? 0) : 0;

                // How much is physically away on outbound consignment?
                $outboundWt     = isset($barcode) ? (float) ($outboundWeightByBarcode[$barcode] ?? 0) : 0;

                $remainingWt    = $purchasedWt - $soldWt - $outboundWt;

                // Skip fully consumed items
                if ($remainingWt <= 0.0001) {
                    continue;
                }

                $inv = $item->purchaseInvoice;

                // Scale value fields proportionally when partially sold
                // (e.g. if 65gm remains from 100gm, values are 65% of original)
                $ratio = $purchasedWt > 0 ? $remainingWt / $purchasedWt : 1;

                $result->push([
                    'barcode'           => $barcode          ?? 'N/A',
                    'item_name'         => $item->item_name  ?: '-',
                    'description'       => $item->item_description ?? '-',
                    'vendor'            => $inv->vendor->name ?? '-',
                    'purchase_invoice'  => $inv->invoice_no,
                    'purchase_date'     => $inv->invoice_date instanceof Carbon
                        ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                    'material_type'     => ucfirst($item->material_type),
                    'purity'            => $item->purity,
                    // Weight fields — remaining (not original purchased)
                    'gross_weight'      => $remainingWt,
                    'sold_weight'       => $soldWt,
                    'purchased_weight'  => $purchasedWt,
                    'net_weight'        => (float) $item->net_weight    * $ratio,
                    'purity_weight'     => (float) $item->purity_weight * $ratio,
                    'col_995'           => (float) $item->col_995       * $ratio,
                    // Value fields — scaled proportionally to remaining weight
                    'making_rate'       => $item->making_rate,
                    'making_value'      => (float) $item->making_value  * $ratio,
                    'material_value'    => (float) $item->material_value * $ratio,
                    'vat_amount'        => (float) $item->vat_amount    * $ratio,
                    'item_total'        => (float) $item->item_total    * $ratio,
                    'gold_rate_aed'     => $inv->gold_rate_aed ?? 0,
                    'currency'          => $inv->currency,
                    'is_printed'        => $item->is_printed,
                    'is_partial'        => $soldWt > 0,   // flag for blade to show partial indicator
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error('InventoryReportController::buildStockInHand — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 2. PURCHASED ITEMS (unchanged — shows full purchased quantities always)
    // =========================================================================

    private function buildPurchasedItems(string $from, string $to): \Illuminate\Support\Collection
    {
        try {
            return PurchaseInvoiceItem::with(['purchaseInvoice.vendor'])
                ->whereHas('purchaseInvoice', function ($q) use ($from, $to) {
                    $q->whereBetween('invoice_date', [$from, $to])->whereNull('deleted_at');
                })
                ->orderBy('id')->get()
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
    // 3. SOLD ITEMS (unchanged)
    // =========================================================================

    private function buildSoldItems(string $from, string $to): \Illuminate\Support\Collection
    {
        try {
            return SaleInvoiceItem::with(['saleInvoice.customer'])
                ->whereHas('saleInvoice', function ($q) use ($from, $to) {
                    $q->whereBetween('invoice_date', [$from, $to])->whereNull('deleted_at');
                })
                ->orderBy('id')->get()
                ->map(function ($item) {
                    $inv      = $item->saleInvoice;
                    $purGoldR = (float) ($inv->purchase_gold_rate_aed   ?? 0);
                    $purMkR   = (float) ($inv->purchase_making_rate_aed ?? 0);
                    $cost     = ($purGoldR * (float) $item->purity_weight)
                              + ((float) $item->gross_weight * $purMkR);
                    $sale     = (float) $item->item_total;
                    $profit   = $sale - $cost;
                    $margin   = $cost > 0 ? round(($profit / $cost) * 100, 2) : 0;

                    return [
                        'barcode'        => $item->barcode_number ?? 'N/A',
                        'item_name'      => $item->item_name      ?: '-',
                        'customer'       => $inv->customer->name  ?? '-',
                        'sale_invoice'   => $inv->invoice_no,
                        'sale_date'      => $inv->invoice_date instanceof Carbon
                            ? $inv->invoice_date->format('d-M-Y') : $inv->invoice_date,
                        'material_type'  => ucfirst($item->material_type),
                        'purity'         => $item->purity,
                        'gross_weight'   => $item->gross_weight,
                        'purity_weight'  => $item->purity_weight,
                        'making_value'   => $item->making_value,
                        'material_value' => $item->material_value,
                        'vat_amount'     => $item->vat_amount,
                        'item_total'     => $sale,
                        'cost'           => $cost,
                        'profit'         => $profit,
                        'margin'         => $margin,
                        'currency'       => $inv->currency,
                        'gold_rate_aed'  => $inv->gold_rate_aed ?? 0,
                    ];
                });

        } catch (\Throwable $e) {
            Log::error('InventoryReportController::buildSoldItems — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 4. WEIGHT SUMMARY
    //
    // Now uses remaining_weight (from buildStockInHand) for "In Hand" figures
    // so the gold_inhand_gross correctly shows 65gm not 100gm when 35gm was sold.
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

            // Use buildStockInHand which now returns REMAINING weights
            $inHandItems = $this->buildStockInHand($to);

            $goldP = $purchaseItems->where('material_type', 'gold');
            $goldS = $saleItems->where('material_type', 'gold');
            $goldH = $inHandItems->where('material_type', 'Gold');
            $diaP  = $purchaseItems->where('material_type', 'diamond');
            $diaS  = $saleItems->where('material_type', 'diamond');
            $diaH  = $inHandItems->where('material_type', 'Diamond');

            return [
                // Purchased — always full purchased quantities
                'gold_purchased_gross'   => $goldP->sum('gross_weight'),
                'gold_purchased_net'     => $goldP->sum('net_weight'),
                'gold_purchased_purity'  => $goldP->sum('purity_weight'),
                'gold_purchased_995'     => $goldP->sum('col_995'),
                'gold_purchased_value'   => $goldP->sum('material_value'),
                'gold_purchased_count'   => $goldP->count(),

                // Sold — what actually left the shop
                'gold_sold_gross'        => $goldS->sum('gross_weight'),
                'gold_sold_purity'       => $goldS->sum('purity_weight'),
                'gold_sold_value'        => $goldS->sum('material_value'),
                'gold_sold_count'        => $goldS->count(),

                // In Hand — REMAINING weights (partial items correctly reflected)
                // gross_weight in $inHandItems is already remaining_weight
                'gold_inhand_gross'      => $goldH->sum('gross_weight'),
                'gold_inhand_purity'     => $goldH->sum('purity_weight'),
                'gold_inhand_value'      => $goldH->sum('material_value'),
                'gold_inhand_count'      => $goldH->count(),

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

                'total_purchased_value'    => $purchaseItems->sum('item_total'),
                'total_sold_value'         => $saleItems->sum('item_total'),
                'total_inhand_value'       => $inHandItems->sum('item_total'),
            ];

        } catch (\Throwable $e) {
            Log::error('InventoryReportController::buildWeightSummary — ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // 5. CONSIGNMENT INVENTORY (unchanged)
    // =========================================================================

    private function buildConsignmentInventory(string $from, string $to): \Illuminate\Support\Collection
    {
        try {
            return ConsignmentItem::with(['consignment.partner', 'parts'])
                ->whereHas('consignment', function ($q) use ($from, $to) {
                    $q->whereBetween('start_date', [$from, $to])
                      ->whereNull('deleted_at');
                })
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    $csg      = $item->consignment;
                    $partsVal = $item->parts->sum('total');
                    return [
                        'consignment_no'   => $csg->consignment_no,
                        'direction'        => ucfirst($csg->direction),
                        'partner'          => $csg->partner->name ?? '-',
                        'start_date'       => $csg->start_date->format('d-M-Y'),
                        'barcode_number'   => $item->barcode_number ?? '—',
                        'item_name'        => $item->item_name        ?? '-',
                        'item_description' => $item->item_description ?? '-',
                        'material_type'    => ucfirst($item->material_type),
                        'purity'           => $item->purity,
                        'gross_weight'     => $item->gross_weight,
                        'purity_weight'    => $item->purity_weight,
                        'making_value'     => $item->making_value,
                        'material_value'   => $item->material_value,
                        'parts_total'      => $partsVal,
                        'agreed_value'     => $item->agreed_value,
                        'item_status'      => $item->item_status,
                        'settled_date'     => $item->settled_date
                            ? \Carbon\Carbon::parse($item->settled_date)->format('d-M-Y') : '—',
                    ];
                });
        } catch (\Throwable $e) {
            Log::error('InventoryReportController::buildConsignmentInventory — ' . $e->getMessage());
            return collect();
        }
    }
}