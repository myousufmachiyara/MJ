<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ChartOfAccounts;
use App\Models\AccountingEntry;
use App\Models\Voucher;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $now       = Carbon::now();
            $monthFrom = $now->copy()->startOfMonth()->toDateString();
            $monthTo   = $now->copy()->endOfMonth()->toDateString();
            $yearFrom  = $now->copy()->startOfYear()->toDateString();
            $yearTo    = $now->copy()->endOfYear()->toDateString();
            $today     = $now->toDateString();

            // ── Sales KPIs ────────────────────────────────────────────────────
            $totalSalesMonth = (float) SaleInvoice::whereBetween('invoice_date', [$monthFrom, $monthTo])
                ->whereNull('deleted_at')->sum('net_amount_aed');

            $totalSalesYear = (float) SaleInvoice::whereBetween('invoice_date', [$yearFrom, $yearTo])
                ->whereNull('deleted_at')->sum('net_amount_aed');

            $saleCount = (int) SaleInvoice::whereBetween('invoice_date', [$monthFrom, $monthTo])
                ->whereNull('deleted_at')->count();

            // ── Purchase KPIs ─────────────────────────────────────────────────
            $totalPurchasesMonth = (float) PurchaseInvoice::whereBetween('invoice_date', [$monthFrom, $monthTo])
                ->whereNull('deleted_at')->sum('net_amount_aed');

            $totalPurchasesYear = (float) PurchaseInvoice::whereBetween('invoice_date', [$yearFrom, $yearTo])
                ->whereNull('deleted_at')->sum('net_amount_aed');

            $purchaseCount = (int) PurchaseInvoice::whereBetween('invoice_date', [$monthFrom, $monthTo])
                ->whereNull('deleted_at')->count();

            // ── Stock in hand (PARTIAL WEIGHT SUPPORT) ────────────────────────
            //
            // Gold is purchased in bulk and sold in chunks (e.g. buy 100gm, sell
            // 35gm, 42gm separately). A purchase item is NOT fully consumed until
            // the sum of sold weights equals the purchased weight.
            //
            // Algorithm:
            //   1. Build sold_weight per barcode from sale_invoice_items
            //   2. Build outbound_weight per barcode from active outbound consignments
            //   3. Build returned_weight per barcode from purchase_return_items
            //   4. For each purchase item: remaining = purchased - sold - outbound - returned
            //   5. Items with remaining > 0 are in stock; sum their remaining weights
            //
            // This replaces the old binary "barcode in $soldBarcodes → exclude" logic.

            // Step 1: sold weight per barcode (all time, not soft-deleted invoices)
            $soldWeightByBarcode = SaleInvoiceItem::whereNotNull('barcode_number')
                ->whereHas('saleInvoice', fn($q) => $q->whereNull('deleted_at'))
                ->selectRaw('barcode_number, SUM(gross_weight) as total_sold')
                ->groupBy('barcode_number')
                ->pluck('total_sold', 'barcode_number')
                ->toArray();

            // Step 2: outbound consignment weight per source_barcode (currently away)
            $outboundWeightByBarcode = ConsignmentItem::where('item_status', 'in_stock')
                ->whereHas('consignment', fn($q) => $q->where('direction', 'outbound'))
                ->whereNotNull('source_barcode')
                ->selectRaw('source_barcode, SUM(gross_weight) as total_outbound')
                ->groupBy('source_barcode')
                ->pluck('total_outbound', 'source_barcode')
                ->toArray();

            // Step 3: returned weight per barcode (purchase returns)
            $returnedWeightByBarcode = [];
            if (class_exists(\App\Models\PurchaseReturnItem::class)) {
                $returnedWeightByBarcode = \App\Models\PurchaseReturnItem::whereNotNull('barcode_number')
                    ->selectRaw('barcode_number, SUM(gross_weight) as total_returned')
                    ->groupBy('barcode_number')
                    ->pluck('total_returned', 'barcode_number')
                    ->toArray();
            }

            // Step 4: load all purchase items (not soft-deleted) and compute remaining
            $allPurchaseItems = PurchaseInvoiceItem::whereHas(
                'purchaseInvoice', fn($q) => $q->whereNull('deleted_at')
            )->get(['barcode_number', 'gross_weight', 'purity_weight', 'item_total', 'material_type']);

            $stockCount    = 0;
            $stockValue    = 0.0;
            $stockGrossWt  = 0.0;
            $stockPurityWt = 0.0;

            foreach ($allPurchaseItems as $item) {
                $barcode     = $item->barcode_number;
                $purchasedWt = (float) $item->gross_weight;

                $soldWt     = $barcode ? (float) ($soldWeightByBarcode[$barcode]     ?? 0) : 0.0;
                $outboundWt = $barcode ? (float) ($outboundWeightByBarcode[$barcode] ?? 0) : 0.0;
                $returnedWt = $barcode ? (float) ($returnedWeightByBarcode[$barcode] ?? 0) : 0.0;

                $remainingWt = $purchasedWt - $soldWt - $outboundWt - $returnedWt;

                if ($remainingWt <= 0.0001) {
                    continue; // fully consumed — not in stock
                }

                // Scale value/weight proportionally to remaining
                $ratio = $purchasedWt > 0 ? $remainingWt / $purchasedWt : 1;

                $stockCount++;
                $stockGrossWt  += $remainingWt;
                $stockPurityWt += (float) $item->purity_weight * $ratio;
                $stockValue    += (float) $item->item_total    * $ratio;
            }

            // ── Consignment overview ──────────────────────────────────────────
            $csgInStockCount  = (int)   ConsignmentItem::where('item_status', 'in_stock')
                ->whereHas('consignment', fn($q) => $q->whereNull('deleted_at'))
                ->count();

            $csgInStockValue  = (float) ConsignmentItem::where('item_status', 'in_stock')
                ->whereHas('consignment', fn($q) => $q->whereNull('deleted_at'))
                ->sum('agreed_value');

            $csgSoldCount     = (int)   ConsignmentItem::where('item_status', 'sold')
                ->whereHas('consignment', fn($q) => $q->whereNull('deleted_at'))
                ->count();

            $csgSoldValue     = (float) ConsignmentItem::where('item_status', 'sold')
                ->whereHas('consignment', fn($q) => $q->whereNull('deleted_at'))
                ->sum('agreed_value');

            $csgReturnedCount = (int)   ConsignmentItem::where('item_status', 'returned')
                ->whereHas('consignment', fn($q) => $q->whereNull('deleted_at'))
                ->count();

            $activeConsignments = (int) Consignment::whereIn('status', ['active', 'partially_settled'])
                ->whereNull('deleted_at')
                ->count();

            $csgInboundCount = (int) ConsignmentItem::where('item_status', 'in_stock')
                ->whereHas('consignment', fn($q) => $q->where('direction', 'inbound')->whereNull('deleted_at'))
                ->count();

            $csgOutboundCount = (int) ConsignmentItem::where('item_status', 'in_stock')
                ->whereHas('consignment', fn($q) => $q->where('direction', 'outbound')->whereNull('deleted_at'))
                ->count();

            // ── Monthly profit ────────────────────────────────────────────────
            $monthlyProfit = $this->calcMonthlyProfit($monthFrom, $monthTo);

            // ── Receivables & Payables ────────────────────────────────────────
            $receivables = $this->calcReceivables();
            $payables    = $this->calcPayables();

            // ── Monthly trend (last 6 months) ─────────────────────────────────
            $monthlyTrend = $this->buildMonthlyTrend();

            // ── Recent activity ───────────────────────────────────────────────
            $recentPurchases = PurchaseInvoice::with('vendor')
                ->whereNull('deleted_at')
                ->latest('invoice_date')->take(5)->get();

            $recentSales = SaleInvoice::with('customer')
                ->whereNull('deleted_at')
                ->latest('invoice_date')->take(5)->get();

            $recentConsignments = Consignment::with('partner')
                ->latest('start_date')->take(5)->get();

            return view('home', compact(
                'totalSalesMonth',
                'totalSalesYear',
                'saleCount',
                'totalPurchasesMonth',
                'totalPurchasesYear',
                'purchaseCount',
                'stockCount',
                'stockValue',
                'stockGrossWt',
                'stockPurityWt',
                'csgInStockCount',
                'csgInStockValue',
                'csgSoldCount',
                'csgSoldValue',
                'csgReturnedCount',
                'activeConsignments',
                'csgInboundCount',
                'csgOutboundCount',
                'monthlyProfit',
                'receivables',
                'payables',
                'monthlyTrend',
                'recentPurchases',
                'recentSales',
                'recentConsignments'
            ));

        } catch (\Throwable $e) {
            Log::error('DashboardController::index — ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return view('home', $this->emptyDefaults());
        }
    }

    // =========================================================================
    // MONTHLY PROFIT
    // =========================================================================

    private function calcMonthlyProfit(string $from, string $to): array
    {
        try {
            $invoices = SaleInvoice::with('items')
                ->whereBetween('invoice_date', [$from, $to])
                ->whereNull('deleted_at')
                ->get();

            $revenue = (float) $invoices->sum('net_amount_aed');
            $cost    = 0.0;

            foreach ($invoices as $invoice) {
                $goldRate   = (float) ($invoice->purchase_gold_rate_aed   ?? 0);
                $makingRate = (float) ($invoice->purchase_making_rate_aed ?? 0);
                foreach ($invoice->items as $item) {
                    $cost += $goldRate   * (float) ($item->purity_weight ?? 0);
                    $cost += $makingRate * (float) ($item->gross_weight  ?? 0);
                }
            }

            $profit = $revenue - $cost;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0;

            return [
                'revenue' => round($revenue, 2),
                'cost'    => round($cost,    2),
                'profit'  => round($profit,  2),
                'margin'  => $margin,
            ];

        } catch (\Throwable $e) {
            Log::error('DashboardController::calcMonthlyProfit — ' . $e->getMessage());
            return ['revenue' => 0, 'cost' => 0, 'profit' => 0, 'margin' => 0];
        }
    }

    // =========================================================================
    // RECEIVABLES
    // =========================================================================

    private function calcReceivables(): array
    {
        try {
            $customers = ChartOfAccounts::where('account_type', 'customer')->get();
            $list      = [];

            foreach ($customers as $customer) {
                $balance = $this->accountNetBalance($customer->id, 'receivable');
                if ($balance > 0.01) {
                    $list[] = ['name' => $customer->name, 'amount' => round($balance, 2)];
                }
            }

            usort($list, fn($a, $b) => $b['amount'] <=> $a['amount']);

            return [
                'total' => round(array_sum(array_column($list, 'amount')), 2),
                'list'  => array_slice($list, 0, 5),
            ];

        } catch (\Throwable $e) {
            Log::error('DashboardController::calcReceivables — ' . $e->getMessage());
            return ['total' => 0, 'list' => []];
        }
    }

    // =========================================================================
    // PAYABLES
    // =========================================================================

    private function calcPayables(): array
    {
        try {
            $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
            $list    = [];

            foreach ($vendors as $vendor) {
                $balance = $this->accountNetBalance($vendor->id, 'payable');
                if ($balance > 0.01) {
                    $list[] = ['name' => $vendor->name, 'amount' => round($balance, 2)];
                }
            }

            usort($list, fn($a, $b) => $b['amount'] <=> $a['amount']);

            return [
                'total' => round(array_sum(array_column($list, 'amount')), 2),
                'list'  => array_slice($list, 0, 5),
            ];

        } catch (\Throwable $e) {
            Log::error('DashboardController::calcPayables — ' . $e->getMessage());
            return ['total' => 0, 'list' => []];
        }
    }

    // =========================================================================
    // ACCOUNT NET BALANCE
    // =========================================================================

    private function accountNetBalance(int $accountId, string $type): float
    {
        $account = ChartOfAccounts::find($accountId);
        if (!$account) return 0.0;

        $openingDr = (float) ($account->opening_debit  ?? $account->receivables ?? 0);
        $openingCr = (float) ($account->opening_credit ?? $account->payables    ?? 0);

        $simpleDr = (float) Voucher::where('ac_dr_sid', $accountId)
            ->whereNull('reference_type')->whereNull('deleted_at')->sum('amount');

        $simpleCr = (float) Voucher::where('ac_cr_sid', $accountId)
            ->whereNull('reference_type')->whereNull('deleted_at')->sum('amount');

        $row = AccountingEntry::where('account_id', $accountId)
            ->whereHas('voucher', fn($q) => $q->whereNull('deleted_at'))
            ->selectRaw('COALESCE(SUM(debit),0) as total_dr, COALESCE(SUM(credit),0) as total_cr')
            ->first();

        $complexDr = $row ? (float) $row->total_dr : 0.0;
        $complexCr = $row ? (float) $row->total_cr : 0.0;

        $totalDr = $openingDr + $simpleDr + $complexDr;
        $totalCr = $openingCr + $simpleCr + $complexCr;

        return $type === 'receivable'
            ? max(0.0, $totalDr - $totalCr)
            : max(0.0, $totalCr - $totalDr);
    }

    // =========================================================================
    // MONTHLY TREND
    // =========================================================================

    private function buildMonthlyTrend(): array
    {
        $months    = [];
        $purchases = [];
        $sales     = [];

        try {
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $from  = $month->copy()->startOfMonth()->toDateString();
                $to    = $month->copy()->endOfMonth()->toDateString();

                $months[]    = $month->format('M Y');
                $purchases[] = round(
                    (float) PurchaseInvoice::whereBetween('invoice_date', [$from, $to])
                        ->whereNull('deleted_at')->sum('net_amount_aed'),
                    2
                );
                $sales[] = round(
                    (float) SaleInvoice::whereBetween('invoice_date', [$from, $to])
                        ->whereNull('deleted_at')->sum('net_amount_aed'),
                    2
                );
            }
        } catch (\Throwable $e) {
            Log::error('DashboardController::buildMonthlyTrend — ' . $e->getMessage());
        }

        return compact('months', 'purchases', 'sales');
    }

    // =========================================================================
    // EMPTY DEFAULTS
    // =========================================================================

    private function emptyDefaults(): array
    {
        return [
            'totalSalesMonth'     => 0,
            'totalSalesYear'      => 0,
            'saleCount'           => 0,
            'totalPurchasesMonth' => 0,
            'totalPurchasesYear'  => 0,
            'purchaseCount'       => 0,
            'stockCount'          => 0,
            'stockValue'          => 0,
            'stockGrossWt'        => 0,
            'stockPurityWt'       => 0,
            'csgInStockCount'     => 0,
            'csgInStockValue'     => 0,
            'csgSoldCount'        => 0,
            'csgSoldValue'        => 0,
            'csgReturnedCount'    => 0,
            'activeConsignments'  => 0,
            'csgInboundCount'     => 0,
            'csgOutboundCount'    => 0,
            'monthlyProfit'       => ['revenue' => 0, 'cost' => 0, 'profit' => 0, 'margin' => 0],
            'receivables'         => ['total' => 0, 'list' => []],
            'payables'            => ['total' => 0, 'list' => []],
            'monthlyTrend'        => ['months' => [], 'purchases' => [], 'sales' => []],
            'recentPurchases'     => collect(),
            'recentSales'         => collect(),
            'recentConsignments'  => collect(),
        ];
    }
}