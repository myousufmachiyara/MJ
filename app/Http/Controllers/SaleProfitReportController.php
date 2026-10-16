<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoiceItem;
use App\Models\SaleInvoiceItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * FEATURE: two Sale Profit reports.
 *
 *  1. Profit on GOLD RATE  — same gold, bought at one gold rate and sold at
 *     another:   (sale gold rate − purchase gold rate) × pure gold weight.
 *     e.g. bought when gold was 10.29, sold when it was 15.65  ->  profit
 *     of (15.65 − 10.29) per gram of pure gold.
 *
 *  2. Profit on ITEM PRICE — what the piece cost vs what it sold for:
 *     sale amount − purchase cost.  e.g. bought for 100, sold for 109  ->
 *     profit 9 (9 %).
 *
 * How a sold item is linked to the purchase it came from: the barcode_number
 * (the same link the POS / stock logic already relies on). Sold lines that
 * have no matching purchased item (consignment items, manually typed lines)
 * cannot be costed and are counted but left out.
 *
 * Read-only: nothing here writes to the database. All amounts are the AED
 * values already stored on the invoice items.
 */
class SaleProfitReportController extends Controller
{
    public function goldRate(Request $request)
    {
        return $this->respond($request, 'gold_rate');
    }

    public function itemPrice(Request $request)
    {
        return $this->respond($request, 'item_price');
    }

    private function respond(Request $request, string $type)
    {
        $data = $this->build($request, $type);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($type, $data);
        }

        return view('reports.sale-profit', $data + ['type' => $type]);
    }

    // =========================================================================
    // DATA
    // =========================================================================

    private function build(Request $request, string $type): array
    {
        $from       = $request->query('from');
        $to         = $request->query('to');
        $search     = trim((string) $request->query('q', ''));
        $result     = in_array($request->query('result'), ['profit', 'loss'], true) ? $request->query('result') : 'all';
        // item-price report: compare with or without VAT (VAT is not income)
        $includeVat = $request->query('amounts') === 'with_vat';

        $query = SaleInvoiceItem::with(['saleInvoice.customer'])
            ->whereNotNull('barcode_number')
            ->where('barcode_number', '!=', '')
            ->whereHas('saleInvoice', function ($q) use ($from, $to) {
                if ($from) {
                    $q->whereDate('invoice_date', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('invoice_date', '<=', $to);
                }
            });

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('item_name', 'like', $like)
                  ->orWhere('barcode_number', 'like', $like)
                  ->orWhereHas('saleInvoice', fn($s) => $s->where('invoice_no', 'like', $like))
                  ->orWhereHas('saleInvoice.customer', fn($c) => $c->where('name', 'like', $like));
            });
        }

        $saleItems = $query->orderByDesc('id')->get()->filter(fn($i) => $i->saleInvoice);

        // purchased item behind each sold barcode (latest purchase row wins)
        $purchaseByBarcode = collect();
        foreach ($saleItems->pluck('barcode_number')->unique()->values()->chunk(1000) as $chunk) {
            $purchaseByBarcode = $purchaseByBarcode->merge(
                PurchaseInvoiceItem::with('purchaseInvoice.vendor')
                    ->whereIn('barcode_number', $chunk->all())
                    ->orderBy('id')
                    ->get()
                    ->keyBy('barcode_number')
            );
        }

        // Σ item_total per sale invoice, to spread an invoice-level discount
        $invoiceItemSums = collect();
        foreach ($saleItems->pluck('sale_invoice_id')->unique()->values()->chunk(1000) as $chunk) {
            $invoiceItemSums = $invoiceItemSums->merge(
                SaleInvoiceItem::whereIn('sale_invoice_id', $chunk->all())
                    ->selectRaw('sale_invoice_id, SUM(item_total) as s')
                    ->groupBy('sale_invoice_id')
                    ->pluck('s', 'sale_invoice_id')
            );
        }

        return $this->compute($type, $saleItems, $purchaseByBarcode, $invoiceItemSums, $result, $includeVat, [
            'from' => $from, 'to' => $to, 'q' => $search,
        ]);
    }

    /**
     * Pure calculation step (no database access) — builds the report rows and
     * totals from already-loaded sold items, their purchased items (keyed by
     * barcode) and the per-invoice sum of item totals.
     */
    private function compute(string $type, $saleItems, $purchaseByBarcode, $invoiceItemSums, string $result, bool $includeVat, array $filters): array
    {
        $rows            = [];
        $withoutPurchase = 0;
        $skippedOther    = 0;   // gold report: non-gold lines

        foreach ($saleItems as $s) {
            $p = $purchaseByBarcode->get($s->barcode_number);
            if (!$p) {
                $withoutPurchase++;
                continue;
            }

            $inv  = $s->saleInvoice;
            $pInv = $p->purchaseInvoice;

            $base = [
                'sale_invoice_id' => $inv->id,
                'sale_invoice_no' => $inv->invoice_no,
                'sale_date'       => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d-M-Y') : '',
                'customer'        => $inv->customer->name ?? '',
                'item_name'       => $s->item_name,
                'barcode'         => $s->barcode_number,
                'purchase_invoice_no' => $pInv->invoice_no ?? '',
                'purchase_date'   => ($pInv && $pInv->invoice_date) ? Carbon::parse($pInv->invoice_date)->format('d-M-Y') : '',
                'vendor'          => $pInv->vendor->name ?? '',
            ];

            if ($type === 'gold_rate') {
                if (($s->material_type ?? 'gold') !== 'gold' || ($p->material_type ?? 'gold') !== 'gold') {
                    $skippedOther++;
                    continue;
                }

                $purchaseRate = (float) ($p->material_rate ?: ($pInv->gold_rate_aed ?? 0));
                $saleRate     = (float) ($s->material_rate ?: ($inv->gold_rate_aed ?? 0));
                // gold actually bought (pure weight); sold weight only as a fallback
                $weight       = (float) ($p->purity_weight ?: $s->purity_weight);
                $diff         = $saleRate - $purchaseRate;
                $profit       = round($diff * $weight, 2);

                $row = $base + [
                    'purity'        => $p->purity,
                    'weight'        => $weight,
                    'purchase_rate' => $purchaseRate,
                    'sale_rate'     => $saleRate,
                    'rate_diff'     => $diff,
                    'profit'        => $profit,
                    'missing_rate'  => $purchaseRate <= 0 || $saleRate <= 0,
                ];
            } else {
                $invSum = (float) ($invoiceItemSums->get($s->sale_invoice_id) ?? 0);
                $invDisc = (float) ($inv->discount_amount ?? 0);
                $g = ($invSum > 0 && $invDisc > 0) ? max(0, ($invSum - $invDisc) / $invSum) : 1.0;

                $cost = (float) $p->item_total - ($includeVat ? 0 : (float) $p->vat_amount);
                $sale = ((float) $s->item_total - ($includeVat ? 0 : (float) $s->vat_amount)) * $g;
                $profit = round($sale - $cost, 2);

                $row = $base + [
                    'cost'         => round($cost, 2),
                    'sale'         => round($sale, 2),
                    'profit'       => $profit,
                    'profit_pct'   => $cost > 0 ? round($profit / $cost * 100, 2) : null,
                    'margin_pct'   => $sale > 0 ? round($profit / $sale * 100, 2) : null,
                    'missing_rate' => $cost <= 0,
                ];
            }

            if ($result === 'profit' && $row['profit'] <= 0) {
                continue;
            }
            if ($result === 'loss' && $row['profit'] >= 0) {
                continue;
            }

            $rows[] = $row;
        }

        // totals
        $totals = ['count' => count($rows), 'profit' => 0.0];
        if ($type === 'gold_rate') {
            $totals += ['weight' => 0.0, 'purchase_value' => 0.0, 'sale_value' => 0.0];
            foreach ($rows as $r) {
                $totals['weight']         += $r['weight'];
                $totals['purchase_value'] += $r['purchase_rate'] * $r['weight'];
                $totals['sale_value']     += $r['sale_rate'] * $r['weight'];
                $totals['profit']         += $r['profit'];
            }
            $totals['avg_purchase_rate'] = $totals['weight'] > 0 ? $totals['purchase_value'] / $totals['weight'] : 0;
            $totals['avg_sale_rate']     = $totals['weight'] > 0 ? $totals['sale_value'] / $totals['weight'] : 0;
        } else {
            $totals += ['cost' => 0.0, 'sale' => 0.0];
            foreach ($rows as $r) {
                $totals['cost']   += $r['cost'];
                $totals['sale']   += $r['sale'];
                $totals['profit'] += $r['profit'];
            }
            $totals['profit_pct'] = $totals['cost'] > 0 ? $totals['profit'] / $totals['cost'] * 100 : null;
            $totals['margin_pct'] = $totals['sale'] > 0 ? $totals['profit'] / $totals['sale'] * 100 : null;
        }
        $totals['profit'] = round($totals['profit'], 2);

        return [
            'rows'            => $rows,
            'totals'          => $totals,
            'withoutPurchase' => $withoutPurchase,
            'skippedOther'    => $skippedOther,
            'filters'         => [
                'from'    => $filters['from'],
                'to'      => $filters['to'],
                'q'       => $filters['q'],
                'result'  => $result,
                'amounts' => $includeVat ? 'with_vat' : 'ex_vat',
            ],
        ];
    }

    // =========================================================================
    // CSV
    // =========================================================================

    private function exportCsv(string $type, array $data)
    {
        $filename = ($type === 'gold_rate' ? 'sale_profit_gold_rate_' : 'sale_profit_item_price_') . date('Ymd_His') . '.csv';

        if ($type === 'gold_rate') {
            $head = ['Sale Invoice', 'Sale Date', 'Customer', 'Item', 'Barcode', 'Purchase Invoice', 'Purchase Date', 'Vendor',
                     'Purity', 'Pure Gold Wt (g)', 'Purchase Gold Rate', 'Sale Gold Rate', 'Rate Difference', 'Profit / Loss (AED)'];
            $map  = fn($r) => [$r['sale_invoice_no'], $r['sale_date'], $r['customer'], $r['item_name'], $r['barcode'],
                     $r['purchase_invoice_no'], $r['purchase_date'], $r['vendor'], $r['purity'],
                     round($r['weight'], 4), round($r['purchase_rate'], 4), round($r['sale_rate'], 4),
                     round($r['rate_diff'], 4), $r['profit']];
            $foot = ['TOTAL', '', '', '', '', '', '', '', '', round($data['totals']['weight'], 4),
                     round($data['totals']['avg_purchase_rate'], 4), round($data['totals']['avg_sale_rate'], 4), '', $data['totals']['profit']];
        } else {
            $head = ['Sale Invoice', 'Sale Date', 'Customer', 'Item', 'Barcode', 'Purchase Invoice', 'Purchase Date', 'Vendor',
                     'Purchase Cost (AED)', 'Sale Amount (AED)', 'Profit / Loss (AED)', 'Profit % (on cost)', 'Margin % (on sale)'];
            $map  = fn($r) => [$r['sale_invoice_no'], $r['sale_date'], $r['customer'], $r['item_name'], $r['barcode'],
                     $r['purchase_invoice_no'], $r['purchase_date'], $r['vendor'],
                     $r['cost'], $r['sale'], $r['profit'], $r['profit_pct'], $r['margin_pct']];
            $foot = ['TOTAL', '', '', '', '', '', '', '', round($data['totals']['cost'], 2), round($data['totals']['sale'], 2),
                     $data['totals']['profit'],
                     $data['totals']['profit_pct'] !== null ? round($data['totals']['profit_pct'], 2) : '',
                     $data['totals']['margin_pct'] !== null ? round($data['totals']['margin_pct'], 2) : ''];
        }

        return response()->streamDownload(function () use ($head, $map, $foot, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
            fputcsv($out, $head);
            foreach ($data['rows'] as $r) {
                fputcsv($out, $map($r));
            }
            fputcsv($out, $foot);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
