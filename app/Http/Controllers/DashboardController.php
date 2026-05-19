<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\SaleInvoice;
use App\Models\PurchaseReturn;
use App\Models\SaleReturn;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ChartOfAccounts;
use App\Models\AccountingEntry;
use App\Models\Voucher;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturnItem;
use App\Models\SaleInvoiceItem;
use Illuminate\Support\Facades\DB;
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
            $totalSalesMonth = SaleInvoice::whereBetween('invoice_date', [$monthFrom, $monthTo])
                ->sum('net_amount_aed');

            $totalSalesYear = SaleInvoice::whereBetween('invoice_date', [$yearFrom, $yearTo])
                ->sum('net_amount_aed');

            $saleCount = SaleInvoice::whereBetween('invoice_date', [$monthFrom, $monthTo])
                ->count();

            // ── Purchase KPIs ─────────────────────────────────────────────────
            $totalPurchasesMonth = PurchaseInvoice::whereBetween('invoice_date', [$monthFrom, $monthTo])
                ->sum('net_amount_aed');

            $totalPurchasesYear = PurchaseInvoice::whereBetween('invoice_date', [$yearFrom, $yearTo])
                ->sum('net_amount_aed');

            $purchaseCount = PurchaseInvoice::whereBetween('invoice_date', [$monthFrom, $monthTo])
                ->count();

            // ── Stock in hand ─────────────────────────────────────────────────
            // Stock = purchased items whose barcode is NOT in sold items
            // and NOT in returned purchase items.
            $soldBarcodes = SaleInvoiceItem::pluck('barcode_number')
                ->filter()->unique()->values()->toArray();

            $returnedBarcodes = PurchaseReturnItem::pluck('barcode_number')
                ->filter()->unique()->values()->toArray();

            $excludedBarcodes = array_unique(array_merge($soldBarcodes, $returnedBarcodes));

            $stockQuery = PurchaseInvoiceItem::when(
                count($excludedBarcodes) > 0,
                fn($q) => $q->whereNotIn('barcode_number', $excludedBarcodes)
            );

            $stockCount    = (int)   $stockQuery->count();
            $stockValue    = (float) $stockQuery->sum('item_total');
            $stockGrossWt  = (float) $stockQuery->sum('gross_weight');
            $stockPurityWt = (float) $stockQuery->sum('purity_weight');

            // ── Consignment overview ──────────────────────────────────────────
            $csgInStockCount  = (int)   ConsignmentItem::where('item_status', 'in_stock')->count();
            $csgInStockValue  = (float) ConsignmentItem::where('item_status', 'in_stock')->sum('agreed_value');
            $csgSoldCount     = (int)   ConsignmentItem::where('item_status', 'sold')->count();
            $csgSoldValue     = (float) ConsignmentItem::where('item_status', 'sold')->sum('agreed_value');
            $csgReturnedCount = (int)   ConsignmentItem::where('item_status', 'returned')->count();

            $activeConsignments = (int) Consignment::whereNotIn('status', ['settled', 'expired', 'returned'])->count();

            // Inbound pending = inbound consignments not fully settled/returned
            $csgInboundCount = (int) Consignment::where('direction', 'inbound')
                ->whereNotIn('status', ['settled', 'expired', 'returned'])
                ->count();

            // Outbound pending = outbound consignments not fully settled/returned
            $csgOutboundCount = (int) Consignment::where('direction', 'outbound')
                ->whereNotIn('status', ['settled', 'expired', 'returned'])
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
                ->latest('invoice_date')
                ->take(5)
                ->get()
                ->map(fn($inv) => [
                    'invoice_no'     => $inv->invoice_no,
                    'invoice_date'   => Carbon::parse($inv->invoice_date)->format('d-M-Y'),
                    'vendor'         => ['name' => optional($inv->vendor)->name ?? '—'],
                    'net_amount_aed' => round($inv->net_amount_aed, 2),
                ])->values()->toArray();

            $recentSales = SaleInvoice::with('customer')
                ->latest('invoice_date')
                ->take(5)
                ->get()
                ->map(fn($inv) => [
                    'invoice_no'     => $inv->invoice_no,
                    'invoice_date'   => Carbon::parse($inv->invoice_date)->format('d-M-Y'),
                    'customer'       => ['name' => optional($inv->customer)->name ?? '—'],
                    'net_amount_aed' => round($inv->net_amount_aed, 2),
                ])->values()->toArray();

            $recentConsignments = Consignment::with('partner')
                ->latest('start_date')
                ->take(5)
                ->get()
                ->map(fn($c) => [
                    'consignment_no' => $c->consignment_no,
                    'start_date'     => Carbon::parse($c->start_date)->format('d-M-Y'),
                    'partner'        => ['name' => optional($c->partner)->name ?? '—'],
                    'status'         => $c->status,
                ])->values()->toArray();

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
    // Revenue = sale invoice net_amount_aed for the period
    // Cost    = for each sale invoice item:
    //             purchase_gold_rate_aed (stored on invoice) × purity_weight
    //           + purchase_making_rate_aed                   × gross_weight
    // Profit  = Revenue - Cost
    // Margin  = Profit / Revenue × 100
    //
    // purchase_gold_rate_aed and purchase_making_rate_aed are saved on
    // SaleInvoice at time of invoice creation (the cost-of-goods fields).
    // If these fields are 0 (not filled), cost will be 0 and margin = 100%.
    // =========================================================================

    private function calcMonthlyProfit(string $from, string $to): array
    {
        try {
            $invoices = SaleInvoice::with('items')
                ->whereBetween('invoice_date', [$from, $to])
                ->get();

            $revenue = (float) $invoices->sum('net_amount_aed');

            $cost = 0.0;
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
    //
    // For every customer COA account, read accounting_entries:
    //   net balance = SUM(debit) - SUM(credit)
    //   positive    = customer still owes us money
    //
    // Returns: [ total => float, list => [ [name, amount], … ] ] top-5 desc
    // =========================================================================

    private function calcReceivables(): array
    {
        try {
            $customers = ChartOfAccounts::where('account_type', 'customer')->get();

            $list = [];
            foreach ($customers as $customer) {
                $balance = $this->accountNetBalance($customer->id, 'receivable');
                if ($balance > 0.01) {
                    $list[] = [
                        'name'   => $customer->name,
                        'amount' => round($balance, 2),
                    ];
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
    //
    // For every vendor COA account, read accounting_entries:
    //   net balance = SUM(credit) - SUM(debit)
    //   positive    = we still owe the vendor money
    //
    // Returns: [ total => float, list => [ [name, amount], … ] ] top-5 desc
    // =========================================================================

    private function calcPayables(): array
    {
        try {
            $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();

            $list = [];
            foreach ($vendors as $vendor) {
                $balance = $this->accountNetBalance($vendor->id, 'payable');
                if ($balance > 0.01) {
                    $list[] = [
                        'name'   => $vendor->name,
                        'amount' => round($balance, 2),
                    ];
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
    //
    // Reads only accounting_entries (all purchase/sale/return modules write here).
    //
    // $type = 'receivable'  →  DR - CR  (AR: debit increases receivable)
    // $type = 'payable'     →  CR - DR  (AP: credit increases payable)
    //
    // Returns the net outstanding amount (always >= 0).
    // =========================================================================

    private function accountNetBalance(int $accountId, string $type): float
    {
        $row = AccountingEntry::where('account_id', $accountId)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_dr, COALESCE(SUM(credit), 0) as total_cr')
            ->first();

        if (!$row) return 0.0;

        $dr = (float) $row->total_dr;
        $cr = (float) $row->total_cr;

        return $type === 'receivable'
            ? max(0.0, $dr - $cr)
            : max(0.0, $cr - $dr);
    }

    // =========================================================================
    // MONTHLY TREND — last 6 calendar months
    //
    // Returns arrays for Chart.js bar chart:
    //   months    → ['Dec 2024', 'Jan 2025', …]
    //   purchases → [AED totals …]
    //   sales     → [AED totals …]
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

                $months[] = $month->format('M Y');

                $purchases[] = round(
                    PurchaseInvoice::whereBetween('invoice_date', [$from, $to])
                        ->sum('net_amount_aed'),
                    2
                );

                $sales[] = round(
                    SaleInvoice::whereBetween('invoice_date', [$from, $to])
                        ->sum('net_amount_aed'),
                    2
                );
            }
        } catch (\Throwable $e) {
            Log::error('DashboardController::buildMonthlyTrend — ' . $e->getMessage());
        }

        return compact('months', 'purchases', 'sales');
    }

    // =========================================================================
    // EMPTY DEFAULTS — ensures the view never crashes on DB failure
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
            'recentPurchases'     => [],
            'recentSales'         => [],
            'recentConsignments'  => [],
        ];
    }
}