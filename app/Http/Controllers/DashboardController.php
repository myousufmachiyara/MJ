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

            // ── Stock in hand ─────────────────────────────────────────────────
            // Stock = purchased items (not soft-deleted) whose barcode
            // does NOT appear in any sale invoice item (not soft-deleted).
            // We also exclude items returned via purchase returns.
            $soldBarcodes = SaleInvoiceItem::whereNotNull('barcode_number')
                ->whereHas('saleInvoice', fn($q) => $q->whereNull('deleted_at'))
                ->pluck('barcode_number')
                ->filter()
                ->unique()
                ->toArray();

            $returnedBarcodes = \App\Models\PurchaseReturnItem::whereNotNull('barcode_number')
                ->pluck('barcode_number')
                ->filter()
                ->unique()
                ->toArray();

            $excludedBarcodes = array_unique(array_merge($soldBarcodes, $returnedBarcodes));

            $stockQuery = PurchaseInvoiceItem::whereHas('purchaseInvoice', fn($q) => $q->whereNull('deleted_at'));

            if (!empty($excludedBarcodes)) {
                $stockQuery->where(function ($q) use ($excludedBarcodes) {
                    $q->whereNull('barcode_number')
                      ->orWhereNotIn('barcode_number', $excludedBarcodes);
                });
            }

            // Execute once, use collection for all four metrics
            $stockItems    = $stockQuery->get(['gross_weight', 'purity_weight', 'item_total']);
            $stockCount    = $stockItems->count();
            $stockValue    = (float) $stockItems->sum('item_total');
            $stockGrossWt  = (float) $stockItems->sum('gross_weight');
            $stockPurityWt = (float) $stockItems->sum('purity_weight');

            // ── Consignment overview ──────────────────────────────────────────
            $csgInStockCount  = (int)   ConsignmentItem::where('item_status', 'in_stock')->count();
            $csgInStockValue  = (float) ConsignmentItem::where('item_status', 'in_stock')->sum('agreed_value');
            $csgSoldCount     = (int)   ConsignmentItem::where('item_status', 'sold')->count();
            $csgSoldValue     = (float) ConsignmentItem::where('item_status', 'sold')->sum('agreed_value');
            $csgReturnedCount = (int)   ConsignmentItem::where('item_status', 'returned')->count();

            $activeConsignments = (int) Consignment::whereIn('status', ['active', 'partially_settled'])->count();

            // Inbound pending = in_stock items from inbound consignments
            $csgInboundCount = (int) ConsignmentItem::where('item_status', 'in_stock')
                ->whereHas('consignment', fn($q) => $q->where('direction', 'inbound'))
                ->count();

            // Outbound pending = in_stock items from outbound consignments
            $csgOutboundCount = (int) ConsignmentItem::where('item_status', 'in_stock')
                ->whereHas('consignment', fn($q) => $q->where('direction', 'outbound'))
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
    //
    // Revenue = sum of net_amount_aed on sale invoices in the period.
    // Cost    = per sale invoice item:
    //             purchase_gold_rate_aed (field on sale invoice) × purity_weight
    //           + purchase_making_rate_aed                       × gross_weight
    // If those cost fields are 0 (not filled), cost = 0 and margin = 100%.
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
            // Margin as % of revenue (standard gross margin formula)
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
    // RECEIVABLES — reads accounting_entries for all customer accounts.
    // Net balance = SUM(debit) - SUM(credit) across both simple vouchers
    // and complex accounting_entries rows.
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
    // PAYABLES — reads accounting_entries for all vendor accounts.
    // Net balance = SUM(credit) - SUM(debit).
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
    // ACCOUNT NET BALANCE — reads BOTH sources:
    //   1. Simple vouchers (ac_dr_sid / ac_cr_sid, reference_type IS NULL)
    //   2. AccountingEntry rows from all modules
    //   3. Opening balance columns on the COA row (receivables / payables)
    //
    // $type = 'receivable' → DR - CR (positive = customer owes us)
    // $type = 'payable'    → CR - DR (positive = we owe vendor)
    // =========================================================================

    private function accountNetBalance(int $accountId, string $type): float
    {
        $account = ChartOfAccounts::find($accountId);
        if (!$account) return 0.0;

        // Opening balances stored on the COA record
        $openingDr = (float) ($account->opening_debit  ?? $account->receivables ?? 0);
        $openingCr = (float) ($account->opening_credit ?? $account->payables    ?? 0);

        // Simple manual vouchers
        $simpleDr = (float) Voucher::where('ac_dr_sid', $accountId)
            ->whereNull('reference_type')->whereNull('deleted_at')->sum('amount');

        $simpleCr = (float) Voucher::where('ac_cr_sid', $accountId)
            ->whereNull('reference_type')->whereNull('deleted_at')->sum('amount');

        // Complex entries from purchase/sale/return modules
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
    // MONTHLY TREND — last 6 calendar months for Chart.js bar chart
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
    // EMPTY DEFAULTS — view never crashes on DB error
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