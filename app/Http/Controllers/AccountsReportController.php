<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
use App\Models\Voucher;
use App\Models\AccountingEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class AccountsReportController extends Controller
{
    private const DEBIT_NATURES = [
        'asset', 'customer', 'cash', 'bank', 'expenses', 'purchase',
    ];

    // =========================================================================
    // ENTRY POINTS
    // =========================================================================

    public function index(Request $request)
    {
        return $this->accounts($request);
    }

    public function accounts(Request $request)
    {
        try {
            $from      = $request->from_date ?? Carbon::now()->startOfYear()->format('Y-m-d');
            $to        = $request->to_date   ?? Carbon::now()->format('Y-m-d');
            $accountId = $request->account_id ? (int) $request->account_id : null;
            $activeTab = $request->tab ?? 'general_ledger';

            $chartOfAccounts = ChartOfAccounts::orderBy('account_code')->get();
            $reports         = $this->buildReports($activeTab, $from, $to, $accountId);

            return view('reports.accounts_reports', compact(
                'reports', 'from', 'to', 'chartOfAccounts'
            ));

        } catch (\Throwable $e) {
            Log::error('AccountsReportController::accounts — ' . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            return back()->with('error', 'Error generating report: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // DISPATCHER
    // =========================================================================

    private function buildReports(string $tab, string $from, string $to, ?int $accountId): array
    {
        $empty = collect();

        $map = [
            'general_ledger'   => fn() => $this->generalLedger($accountId, $from, $to),
            'party_ledger'     => fn() => $this->partyLedger($accountId, $from, $to),
            'trial_balance'    => fn() => $this->trialBalance($to),
            'profit_loss'      => fn() => $this->profitLoss($from, $to),
            'balance_sheet'    => fn() => $this->balanceSheet($from, $to),
            'receivables'      => fn() => $this->receivables($to),
            'payables'         => fn() => $this->payables($to),
            'cash_book'        => fn() => $this->cashBook($from, $to),
            'bank_book'        => fn() => $this->bankBook($from, $to),
            'journal_book'     => fn() => $this->journalBook($from, $to),
            'expense_analysis' => fn() => $this->expenseAnalysis($from, $to),
            'cash_flow'        => fn() => $this->cashFlow($from, $to),
        ];

        $result = [];
        foreach ($map as $key => $fn) {
            $result[$key] = ($key === $tab) ? $fn() : $empty;
        }
        return $result;
    }

    // =========================================================================
    // EMPTY P&L STRUCTURE — always returned so blade never hits undefined key
    // =========================================================================

    private function emptyPL(): array
    {
        return [
            'revenue'        => collect(),
            'expenses'       => collect(),
            'cogs'           => collect(),
            'total_revenue'  => 0.0,
            'total_cogs'     => 0.0,
            'gross_profit'   => 0.0,
            'total_expenses' => 0.0,
            'net_profit'     => 0.0,
        ];
    }

    // =========================================================================
    // CORE BALANCE ENGINE
    // =========================================================================

    private function getAccountBalance(int $accountId, string $asOfDate): array
    {
        $account = ChartOfAccounts::find($accountId);
        if (!$account) return ['debit' => 0.0, 'credit' => 0.0];

        $openingDr = (float) ($account->opening_debit  ?? $account->receivables ?? 0);
        $openingCr = (float) ($account->opening_credit ?? $account->payables    ?? 0);

        $simpleDr = (float) Voucher::where('ac_dr_sid', $accountId)
            ->whereNull('reference_type')
            ->where('voucher_date', '<=', $asOfDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $simpleCr = (float) Voucher::where('ac_cr_sid', $accountId)
            ->whereNull('reference_type')
            ->where('voucher_date', '<=', $asOfDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $complexDr = (float) AccountingEntry::where('account_id', $accountId)
            ->whereHas('voucher', fn($q) =>
                $q->where('voucher_date', '<=', $asOfDate)->whereNull('deleted_at')
            )
            ->sum('debit');

        $complexCr = (float) AccountingEntry::where('account_id', $accountId)
            ->whereHas('voucher', fn($q) =>
                $q->where('voucher_date', '<=', $asOfDate)->whereNull('deleted_at')
            )
            ->sum('credit');

        return [
            'debit'  => $openingDr + $simpleDr + $complexDr,
            'credit' => $openingCr + $simpleCr + $complexCr,
        ];
    }

    private function getAccountActivity(int $accountId, string $from, string $to): array
    {
        $simpleDr = (float) Voucher::where('ac_dr_sid', $accountId)
            ->whereNull('reference_type')
            ->whereBetween('voucher_date', [$from, $to])
            ->whereNull('deleted_at')
            ->sum('amount');

        $simpleCr = (float) Voucher::where('ac_cr_sid', $accountId)
            ->whereNull('reference_type')
            ->whereBetween('voucher_date', [$from, $to])
            ->whereNull('deleted_at')
            ->sum('amount');

        $complexDr = (float) AccountingEntry::where('account_id', $accountId)
            ->whereHas('voucher', fn($q) =>
                $q->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at')
            )
            ->sum('debit');

        $complexCr = (float) AccountingEntry::where('account_id', $accountId)
            ->whereHas('voucher', fn($q) =>
                $q->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at')
            )
            ->sum('credit');

        return [
            'debit'  => $simpleDr + $complexDr,
            'credit' => $simpleCr + $complexCr,
        ];
    }

    private function getLedgerLines(int $accountId, string $from, string $to): Collection
    {
        $lines = collect();

        Voucher::whereNull('reference_type')
            ->whereBetween('voucher_date', [$from, $to])
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->where('ac_dr_sid', $accountId)->orWhere('ac_cr_sid', $accountId))
            ->get()
            ->each(function ($v) use ($accountId, &$lines) {
                $lines->push([
                    'sort'      => $v->voucher_date->format('Ymd') . str_pad($v->id, 10, '0', STR_PAD_LEFT),
                    'date'      => $v->voucher_date->format('Y-m-d'),
                    'reference' => trim(($v->voucher_no ?? '') . ($v->remarks ? ' — ' . $v->remarks : '')),
                    'dr'        => ($v->ac_dr_sid == $accountId) ? (float) $v->amount : 0.0,
                    'cr'        => ($v->ac_cr_sid == $accountId) ? (float) $v->amount : 0.0,
                ]);
            });

        AccountingEntry::where('account_id', $accountId)
            ->whereHas('voucher', fn($q) =>
                $q->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at')
            )
            ->with('voucher')
            ->get()
            ->each(function ($entry) use (&$lines) {
                $v     = $entry->voucher;
                $vDate = optional($v->voucher_date)->format('Ymd') ?? '00000000';
                $ref   = trim(($v->voucher_no ?? '') . ($entry->narration ? ' — ' . $entry->narration : ''));
                $lines->push([
                    'sort'      => $vDate . str_pad($entry->voucher_id, 10, '0', STR_PAD_LEFT),
                    'date'      => optional($v->voucher_date)->format('Y-m-d') ?? '',
                    'reference' => $ref ?: ($v->remarks ?? ''),
                    'dr'        => (float) $entry->debit,
                    'cr'        => (float) $entry->credit,
                ]);
            });

        return $lines->sortBy('sort')->values();
    }

    // =========================================================================
    // 1. GENERAL LEDGER
    // =========================================================================

    private function generalLedger(?int $accountId, string $from, string $to): Collection
    {
        if (!$accountId) return collect();

        try {
            $account = ChartOfAccounts::find($accountId);
            if (!$account) return collect();

            $isDebit   = $this->isDebitNature($account->account_type);
            $dayBefore = Carbon::parse($from)->subDay()->format('Y-m-d');
            $opBal     = $this->getAccountBalance($accountId, $dayBefore);
            $running   = $isDebit
                ? ($opBal['debit'] - $opBal['credit'])
                : ($opBal['credit'] - $opBal['debit']);

            $result = collect([[
                'date'       => $from,
                'account'    => $account->name,
                'reference'  => 'Opening Balance',
                'debit'      => '',
                'credit'     => '',
                'balance'    => $this->fmt($running),
                'is_opening' => true,
            ]]);

            foreach ($this->getLedgerLines($accountId, $from, $to) as $line) {
                $running += $isDebit
                    ? ($line['dr'] - $line['cr'])
                    : ($line['cr'] - $line['dr']);

                $result->push([
                    'date'       => $line['date'],
                    'account'    => $account->name,
                    'reference'  => $line['reference'],
                    'debit'      => $line['dr'] > 0.001 ? $this->fmt($line['dr']) : '',
                    'credit'     => $line['cr'] > 0.001 ? $this->fmt($line['cr']) : '',
                    'balance'    => $this->fmt($running),
                    'is_opening' => false,
                ]);
            }

            $result->push([
                'date'       => $to,
                'account'    => $account->name,
                'reference'  => 'Closing Balance',
                'debit'      => $isDebit  && $running >= 0 ? $this->fmt($running) : '',
                'credit'     => !$isDebit && $running >= 0 ? $this->fmt($running) : '',
                'balance'    => $this->fmt($running),
                'is_opening' => true,
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error('AccountsReport::generalLedger — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 2. PARTY LEDGER
    // =========================================================================

    private function partyLedger(?int $accountId, string $from, string $to): Collection
    {
        if (!$accountId) return collect();
        $account = ChartOfAccounts::find($accountId);
        if (!$account || !in_array($account->account_type, ['customer', 'vendor'])) {
            return collect();
        }
        return $this->generalLedger($accountId, $from, $to);
    }

    // =========================================================================
    // 3. TRIAL BALANCE
    // =========================================================================

    private function trialBalance(string $to): Collection
    {
        try {
            $rows    = collect();
            $totalDr = 0.0;
            $totalCr = 0.0;

            foreach (ChartOfAccounts::orderBy('account_code')->get() as $account) {
                $bal     = $this->getAccountBalance($account->id, $to);
                $isDebit = $this->isDebitNature($account->account_type);

                if ($isDebit) {
                    $net   = $bal['debit'] - $bal['credit'];
                    $drAmt = $net >= 0 ? $net : 0;
                    $crAmt = $net <  0 ? abs($net) : 0;
                } else {
                    $net   = $bal['credit'] - $bal['debit'];
                    $crAmt = $net >= 0 ? $net : 0;
                    $drAmt = $net <  0 ? abs($net) : 0;
                }

                if ($drAmt < 0.005 && $crAmt < 0.005) continue;

                $totalDr += $drAmt;
                $totalCr += $crAmt;

                $rows->push([
                    'account_code' => $account->account_code,
                    'account_name' => $account->name,
                    'account_type' => ucfirst($account->account_type),
                    'debit'        => $drAmt > 0.001 ? $this->fmt($drAmt) : '—',
                    'credit'       => $crAmt > 0.001 ? $this->fmt($crAmt) : '—',
                    '_is_total'    => false,
                ]);
            }

            $rows->push([
                'account_code' => '',
                'account_name' => 'TOTAL',
                'account_type' => '',
                'debit'        => $this->fmt($totalDr),
                'credit'       => $this->fmt($totalCr),
                '_is_total'    => true,
            ]);

            return $rows;

        } catch (\Throwable $e) {
            Log::error('AccountsReport::trialBalance — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 4. PROFIT & LOSS
    //
    // ALWAYS returns the full array structure with all keys initialised so
    // the blade can never hit "Undefined array key".
    //
    // Revenue  (account_type = 'revenue')  : CR - DR
    // COGS     (account_type = 'purchase') : DR - CR
    // Expenses (account_type = 'expenses') : DR - CR
    // Net Profit = Revenue - COGS - Expenses
    // =========================================================================

    private function profitLoss(string $from, string $to): array
    {
        // Always start with the safe empty structure
        $pl = $this->emptyPL();

        try {
            $accounts = ChartOfAccounts::orderBy('account_code')->get();

            foreach ($accounts as $account) {
                $act  = $this->getAccountActivity($account->id, $from, $to);
                $type = strtolower($account->account_type);

                switch ($type) {
                    case 'revenue':
                        $val = $act['credit'] - $act['debit'];
                        if (abs($val) > 0.005) {
                            $pl['revenue']->push([
                                'name'   => $account->name,
                                'amount' => round($val, 2),
                            ]);
                        }
                        break;

                    case 'purchase':
                        $val = $act['debit'] - $act['credit'];
                        if (abs($val) > 0.005) {
                            $pl['cogs']->push([
                                'name'   => $account->name,
                                'amount' => round($val, 2),
                            ]);
                        }
                        break;

                    case 'expenses':
                        $val = $act['debit'] - $act['credit'];
                        if (abs($val) > 0.005) {
                            $pl['expenses']->push([
                                'name'   => $account->name,
                                'amount' => round($val, 2),
                            ]);
                        }
                        break;
                }
            }

            $pl['total_revenue']  = round($pl['revenue']->sum('amount'), 2);
            $pl['total_cogs']     = round($pl['cogs']->sum('amount'),    2);
            $pl['gross_profit']   = round($pl['total_revenue'] - $pl['total_cogs'], 2);

            // Merge COGS into expenses collection for the two-column blade display
            // (keeps the right column showing all cost items together)
            $allExpenses          = $pl['cogs']->concat($pl['expenses']);
            $pl['expenses']       = $allExpenses;
            $pl['total_expenses'] = round($allExpenses->sum('amount'), 2);
            $pl['net_profit']     = round($pl['gross_profit'] - $pl['expenses']->sum('amount') + $pl['total_cogs'], 2);

            // Recalculate correctly: Revenue - COGS - OpEx = Net Profit
            $totalOpEx        = round($pl['cogs']->sum('amount') + collect($accounts)
                ->filter(fn($a) => strtolower($a->account_type) === 'expenses')
                ->sum(function ($a) use ($from, $to) {
                    $act = $this->getAccountActivity($a->id, $from, $to);
                    return $act['debit'] - $act['credit'];
                }), 2);

            $pl['net_profit'] = round($pl['total_revenue'] - $totalOpEx, 2);

        } catch (\Throwable $e) {
            Log::error('AccountsReport::profitLoss — ' . $e->getMessage() . ' line ' . $e->getLine());
            // $pl already has the safe empty structure — just return it
        }

        return $pl;
    }

    // =========================================================================
    // 5. BALANCE SHEET
    // =========================================================================

    private function balanceSheet(string $from, string $to): array
    {
        $bs = [
            'assets'            => collect(),
            'liabilities'       => collect(),
            'equity'            => collect(),
            'total_assets'      => 0.0,
            'total_liabilities' => 0.0,
            'total_equity'      => 0.0,
        ];

        try {
            foreach (ChartOfAccounts::orderBy('account_code')->get() as $account) {
                $bal  = $this->getAccountBalance($account->id, $to);
                $type = strtolower($account->account_type);
                $code = $account->account_code;

                if ($code === '105001') {
                    $net = $bal['debit'] - $bal['credit'];
                    if (abs($net) > 0.005) {
                        $bs['assets']->push(['name' => $account->name . ' (Recoverable)', 'amount' => $net]);
                    }
                    continue;
                }

                if ($code === '208001') {
                    $net = $bal['credit'] - $bal['debit'];
                    if (abs($net) > 0.005) {
                        $bs['liabilities']->push(['name' => $account->name . ' (Payable)', 'amount' => $net]);
                    }
                    continue;
                }

                switch (true) {
                    case in_array($type, ['cash', 'bank', 'asset', 'customer']):
                        $net = $bal['debit'] - $bal['credit'];
                        if (abs($net) > 0.005) {
                            $bs['assets']->push(['name' => $account->name, 'amount' => $net]);
                        }
                        break;

                    case in_array($type, ['vendor', 'liability']):
                        $net = $bal['credit'] - $bal['debit'];
                        if (abs($net) > 0.005) {
                            $bs['liabilities']->push(['name' => $account->name, 'amount' => $net]);
                        }
                        break;

                    case $type === 'equity':
                        $net = $bal['credit'] - $bal['debit'];
                        if (abs($net) > 0.005) {
                            $bs['equity']->push(['name' => $account->name, 'amount' => $net]);
                        }
                        break;
                }
            }

            $pl = $this->profitLoss($from, $to);
            if (abs($pl['net_profit'] ?? 0) > 0.005) {
                $bs['equity']->push([
                    'name'   => 'Retained Earnings (Net Profit ' . $from . ' → ' . $to . ')',
                    'amount' => round($pl['net_profit'], 2),
                ]);
            }

            $bs['total_assets']      = round($bs['assets']->sum('amount'),      2);
            $bs['total_liabilities'] = round($bs['liabilities']->sum('amount'), 2);
            $bs['total_equity']      = round($bs['equity']->sum('amount'),      2);

        } catch (\Throwable $e) {
            Log::error('AccountsReport::balanceSheet — ' . $e->getMessage());
        }

        return $bs;
    }

    // =========================================================================
    // 6. RECEIVABLES
    // =========================================================================

    private function receivables(string $to): Collection
    {
        try {
            return ChartOfAccounts::where('account_type', 'customer')
                ->orderBy('name')->get()
                ->map(function ($a) use ($to) {
                    $bal = $this->getAccountBalance($a->id, $to);
                    return [
                        'name'   => $a->name,
                        'amount' => round($bal['debit'] - $bal['credit'], 2),
                    ];
                })
                ->filter(fn($r) => $r['amount'] > 0.005)
                ->values();
        } catch (\Throwable $e) {
            Log::error('AccountsReport::receivables — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 7. PAYABLES
    // =========================================================================

    private function payables(string $to): Collection
    {
        try {
            return ChartOfAccounts::where('account_type', 'vendor')
                ->orderBy('name')->get()
                ->map(function ($a) use ($to) {
                    $bal = $this->getAccountBalance($a->id, $to);
                    return [
                        'name'   => $a->name,
                        'amount' => round($bal['credit'] - $bal['debit'], 2),
                    ];
                })
                ->filter(fn($r) => $r['amount'] > 0.005)
                ->values();
        } catch (\Throwable $e) {
            Log::error('AccountsReport::payables — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 8 & 9. CASH BOOK / BANK BOOK
    // =========================================================================

    private function cashBook(string $from, string $to): Collection
    {
        try {
            $ids = ChartOfAccounts::where('account_type', 'cash')->pluck('id')->toArray();
            return $this->buildBook($ids, $from, $to);
        } catch (\Throwable $e) {
            Log::error('AccountsReport::cashBook — ' . $e->getMessage());
            return collect();
        }
    }

    private function bankBook(string $from, string $to): Collection
    {
        try {
            $ids = ChartOfAccounts::where('account_type', 'bank')->pluck('id')->toArray();
            return $this->buildBook($ids, $from, $to);
        } catch (\Throwable $e) {
            Log::error('AccountsReport::bankBook — ' . $e->getMessage());
            return collect();
        }
    }

    private function buildBook(array $accountIds, string $from, string $to): Collection
    {
        if (empty($accountIds)) return collect();

        $dayBefore  = Carbon::parse($from)->subDay()->format('Y-m-d');
        $openingBal = 0.0;
        foreach ($accountIds as $id) {
            $b = $this->getAccountBalance($id, $dayBefore);
            $openingBal += ($b['debit'] - $b['credit']);
        }

        $lines = collect();

        Voucher::whereNull('reference_type')
            ->whereBetween('voucher_date', [$from, $to])
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->whereIn('ac_dr_sid', $accountIds)->orWhereIn('ac_cr_sid', $accountIds))
            ->with(['debitAccount', 'creditAccount'])
            ->get()
            ->each(function ($v) use ($accountIds, &$lines) {
                $lines->push([
                    'sort'       => $v->voucher_date->format('Ymd') . str_pad($v->id, 10, '0', STR_PAD_LEFT),
                    'date'       => $v->voucher_date->format('Y-m-d'),
                    'reference'  => trim(($v->voucher_no ?? '') . ($v->remarks ? ' — ' . $v->remarks : '')),
                    'dr_account' => $v->debitAccount->name  ?? '—',
                    'cr_account' => $v->creditAccount->name ?? '—',
                    'dr'         => in_array($v->ac_dr_sid, $accountIds) ? (float) $v->amount : 0.0,
                    'cr'         => in_array($v->ac_cr_sid, $accountIds) ? (float) $v->amount : 0.0,
                ]);
            });

        AccountingEntry::whereIn('account_id', $accountIds)
            ->whereHas('voucher', fn($q) =>
                $q->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at')
            )
            ->with(['voucher', 'account'])
            ->get()
            ->each(function ($entry) use (&$lines) {
                $v     = $entry->voucher;
                $vDate = optional($v->voucher_date)->format('Ymd') ?? '00000000';
                $ref   = trim(($v->voucher_no ?? '') . ($entry->narration ? ' — ' . $entry->narration : ''));
                $lines->push([
                    'sort'       => $vDate . str_pad($entry->voucher_id, 10, '0', STR_PAD_LEFT),
                    'date'       => optional($v->voucher_date)->format('Y-m-d') ?? '',
                    'reference'  => $ref ?: ($v->remarks ?? ''),
                    'dr_account' => (float) $entry->debit  > 0 ? ($entry->account->name ?? '—') : '—',
                    'cr_account' => (float) $entry->credit > 0 ? ($entry->account->name ?? '—') : '—',
                    'dr'         => (float) $entry->debit,
                    'cr'         => (float) $entry->credit,
                ]);
            });

        $running = $openingBal;
        $result  = collect([[
            'date'       => $from,
            'reference'  => 'Opening Balance',
            'dr_account' => '',
            'cr_account' => '',
            'debit'      => '',
            'credit'     => '',
            'balance'    => $this->fmt($running),
            'is_opening' => true,
        ]]);

        foreach ($lines->sortBy('sort') as $line) {
            $running += ($line['dr'] - $line['cr']);
            $result->push([
                'date'       => $line['date'],
                'reference'  => $line['reference'],
                'dr_account' => $line['dr_account'],
                'cr_account' => $line['cr_account'],
                'debit'      => $line['dr'] > 0.001 ? $this->fmt($line['dr']) : '',
                'credit'     => $line['cr'] > 0.001 ? $this->fmt($line['cr']) : '',
                'balance'    => $this->fmt($running),
                'is_opening' => false,
            ]);
        }

        $result->push([
            'date'       => $to,
            'reference'  => 'Closing Balance',
            'dr_account' => '',
            'cr_account' => '',
            'debit'      => $running >= 0 ? $this->fmt($running) : '',
            'credit'     => $running <  0 ? $this->fmt(abs($running)) : '',
            'balance'    => $this->fmt($running),
            'is_opening' => true,
        ]);

        return $result;
    }

    // =========================================================================
    // 10. JOURNAL / DAY BOOK
    // =========================================================================

    private function journalBook(string $from, string $to): Collection
    {
        try {
            $rows = collect();

            Voucher::whereNull('reference_type')
                ->whereBetween('voucher_date', [$from, $to])
                ->whereNull('deleted_at')
                ->with(['debitAccount', 'creditAccount'])
                ->orderBy('voucher_date')->orderBy('id')
                ->get()
                ->each(function ($v) use (&$rows) {
                    $rows->push([
                        'date'       => $v->voucher_date->format('Y-m-d'),
                        'voucher_no' => $v->voucher_no,
                        'type'       => $this->voucherTypeLabel($v->voucher_type ?? 'journal'),
                        'dr_account' => $v->debitAccount->name  ?? '—',
                        'cr_account' => $v->creditAccount->name ?? '—',
                        'amount'     => $this->fmt((float)($v->amount ?? 0)),
                        'remarks'    => $v->remarks ?? '',
                    ]);
                });

            Voucher::whereNotNull('reference_type')
                ->whereBetween('voucher_date', [$from, $to])
                ->whereNull('deleted_at')
                ->with(['entries.account'])
                ->orderBy('voucher_date')->orderBy('id')
                ->get()
                ->each(function ($v) use (&$rows) {
                    $drEntries = $v->entries->where('debit',  '>', 0)->values();
                    $crEntries = $v->entries->where('credit', '>', 0)->values();

                    $rows->push([
                        'date'       => optional($v->voucher_date)->format('Y-m-d') ?? '',
                        'voucher_no' => $v->voucher_no,
                        'type'       => $this->voucherTypeLabel($v->voucher_type ?? ''),
                        'dr_account' => $drEntries->pluck('account.name')->filter()->implode(', ') ?: '—',
                        'cr_account' => $crEntries->pluck('account.name')->filter()->implode(', ') ?: '—',
                        'amount'     => $this->fmt($drEntries->sum('debit')),
                        'remarks'    => $v->remarks ?? '',
                    ]);
                });

            return $rows->sortBy('date')->values();

        } catch (\Throwable $e) {
            Log::error('AccountsReport::journalBook — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 11. EXPENSE ANALYSIS
    // =========================================================================

    private function expenseAnalysis(string $from, string $to): Collection
    {
        try {
            return ChartOfAccounts::whereIn('account_type', ['expenses', 'purchase'])
                ->orderBy('account_code')->get()
                ->map(function ($a) use ($from, $to) {
                    $act = $this->getAccountActivity($a->id, $from, $to);
                    return [
                        'name'   => '[' . $a->account_code . '] ' . $a->name,
                        'amount' => round($act['debit'] - $act['credit'], 2),
                    ];
                })
                ->filter(fn($r) => abs($r['amount']) > 0.005)
                ->values();
        } catch (\Throwable $e) {
            Log::error('AccountsReport::expenseAnalysis — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 12. CASH FLOW
    // =========================================================================

    private function cashFlow(string $from, string $to): array
    {
        try {
            $ids = ChartOfAccounts::whereIn('account_type', ['cash', 'bank'])->pluck('id')->toArray();
            if (empty($ids)) return ['inflow' => 0, 'outflow' => 0, 'net' => 0];

            $simpleIn  = (float) Voucher::whereNull('reference_type')->whereIn('ac_dr_sid', $ids)
                ->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at')->sum('amount');
            $simpleOut = (float) Voucher::whereNull('reference_type')->whereIn('ac_cr_sid', $ids)
                ->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at')->sum('amount');

            $complexIn  = (float) AccountingEntry::whereIn('account_id', $ids)
                ->whereHas('voucher', fn($q) =>
                    $q->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at')
                )->sum('debit');

            $complexOut = (float) AccountingEntry::whereIn('account_id', $ids)
                ->whereHas('voucher', fn($q) =>
                    $q->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at')
                )->sum('credit');

            $inflow  = round($simpleIn  + $complexIn,  2);
            $outflow = round($simpleOut + $complexOut, 2);

            return ['inflow' => $inflow, 'outflow' => $outflow, 'net' => round($inflow - $outflow, 2)];

        } catch (\Throwable $e) {
            Log::error('AccountsReport::cashFlow — ' . $e->getMessage());
            return ['inflow' => 0, 'outflow' => 0, 'net' => 0];
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function isDebitNature(string $accountType): bool
    {
        return in_array(strtolower($accountType), self::DEBIT_NATURES);
    }

    private function fmt(float $v): string
    {
        return number_format(abs($v), 2);
    }

    private function voucherTypeLabel(string $type): string
    {
        return match (strtolower($type)) {
            'purchase'        => 'Purchase Invoice',
            'purchase_return' => 'Purchase Return',
            'sale'            => 'Sale Invoice',
            'sale_return'     => 'Sale Return',
            'journal'         => 'Journal',
            'payment'         => 'Payment',
            'receipt'         => 'Receipt',
            'contra'          => 'Contra',
            'debit_note'      => 'Debit Note',
            'credit_note'     => 'Credit Note',
            default           => ucwords(str_replace('_', ' ', $type)),
        };
    }
}