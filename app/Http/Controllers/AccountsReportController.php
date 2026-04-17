<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
use App\Models\Voucher;
use App\Models\AccountingEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AccountsReportController extends Controller
{
    // account_type values exactly as in the DatabaseSeeder
    private const DEBIT_NATURES = ['asset', 'customer', 'cash', 'bank', 'expenses'];

    // =========================================================================
    // ENTRY POINT
    // =========================================================================

    public function accounts(Request $request)
    {
        try {
            $from      = $request->from_date ?? Carbon::now()->startOfYear()->format('Y-m-d');
            $to        = $request->to_date   ?? Carbon::now()->format('Y-m-d');
            $accountId = $request->account_id ? (int) $request->account_id : null;

            $chartOfAccounts = ChartOfAccounts::orderBy('account_code')->get();

            $reports = [
                'general_ledger'   => $this->generalLedger($accountId, $from, $to),
                'trial_balance'    => $this->trialBalance($from, $to),
                'profit_loss'      => $this->profitLoss($from, $to),
                'balance_sheet'    => $this->balanceSheet($from, $to),
                'party_ledger'     => $this->partyLedger($from, $to, $accountId),
                'receivables'      => $this->receivables($to),
                'payables'         => $this->payables($to),
                'cash_book'        => $this->cashBook($from, $to),
                'bank_book'        => $this->bankBook($from, $to),
                'journal_book'     => $this->journalBook($from, $to),
                'expense_analysis' => $this->expenseAnalysis($from, $to),
                'cash_flow'        => $this->cashFlow($from, $to),
            ];

            return view('reports.accounts_reports', compact(
                'reports', 'from', 'to', 'chartOfAccounts'
            ));

        } catch (\Throwable $e) {
            Log::error('AccountsReportController::accounts — ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Error generating reports: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // CORE BALANCE ENGINE
    // Reads BOTH:
    //   - Simple vouchers  (ac_dr_sid / ac_cr_sid / amount, reference_type IS NULL)
    //   - Complex vouchers (rows in accounting_entries linked by voucher_id)
    // Opening balance from COA receivables / payables columns is included.
    // All date comparisons use 'voucher_date' — never 'date'.
    // =========================================================================

    private function getAccountBalance(int $accountId, string $asOfDate): array
    {
        $account = ChartOfAccounts::find($accountId);
        if (!$account) return ['debit' => 0.0, 'credit' => 0.0];

        $openingDr = (float) ($account->receivables ?? 0);
        $openingCr = (float) ($account->payables    ?? 0);

        // ── Simple vouchers ───────────────────────────────────────────────────
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

        // ── Complex vouchers via accounting_entries ───────────────────────────
        $complexDr = (float) AccountingEntry::where('account_id', $accountId)
            ->whereHas('voucher', function ($q) use ($asOfDate) {
                $q->where('voucher_date', '<=', $asOfDate)
                  ->whereNull('deleted_at');
            })
            ->sum('debit');

        $complexCr = (float) AccountingEntry::where('account_id', $accountId)
            ->whereHas('voucher', function ($q) use ($asOfDate) {
                $q->where('voucher_date', '<=', $asOfDate)
                  ->whereNull('deleted_at');
            })
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
            ->whereHas('voucher', function ($q) use ($from, $to) {
                $q->whereBetween('voucher_date', [$from, $to])
                  ->whereNull('deleted_at');
            })
            ->sum('debit');

        $complexCr = (float) AccountingEntry::where('account_id', $accountId)
            ->whereHas('voucher', function ($q) use ($from, $to) {
                $q->whereBetween('voucher_date', [$from, $to])
                  ->whereNull('deleted_at');
            })
            ->sum('credit');

        return [
            'debit'  => $simpleDr + $complexDr,
            'credit' => $simpleCr + $complexCr,
        ];
    }

    private function isDebitNature(string $accountType): bool
    {
        return in_array(strtolower($accountType), self::DEBIT_NATURES);
    }

    private function fmt(float $v): string
    {
        return number_format($v, 2);
    }

    // =========================================================================
    // 1. GENERAL LEDGER
    // =========================================================================

    private function generalLedger(?int $accountId, string $from, string $to): \Illuminate\Support\Collection
    {
        if (!$accountId) return collect();

        try {
            $account = ChartOfAccounts::find($accountId);
            if (!$account) return collect();

            $dayBefore  = Carbon::parse($from)->subDay()->format('Y-m-d');
            $isDebitNat = $this->isDebitNature($account->account_type);

            $opBal      = $this->getAccountBalance($accountId, $dayBefore);
            $runningBal = $isDebitNat
                ? ($opBal['debit'] - $opBal['credit'])
                : ($opBal['credit'] - $opBal['debit']);

            // Collect all transaction lines with sort key
            $allLines = collect();

            // Simple vouchers in range
            $simpleVouchers = Voucher::whereNull('reference_type')
                ->whereBetween('voucher_date', [$from, $to])
                ->whereNull('deleted_at')
                ->where(function ($q) use ($accountId) {
                    $q->where('ac_dr_sid', $accountId)
                      ->orWhere('ac_cr_sid', $accountId);
                })
                ->get();

            foreach ($simpleVouchers as $v) {
                $allLines->push([
                    'sort_key'  => $v->voucher_date->format('Y-m-d') . str_pad($v->id, 10, '0', STR_PAD_LEFT),
                    'date'      => $v->voucher_date->format('Y-m-d'),
                    'reference' => $v->voucher_no . ($v->remarks ? ' — ' . $v->remarks : ''),
                    'dr_raw'    => ($v->ac_dr_sid == $accountId) ? (float) $v->amount : 0.0,
                    'cr_raw'    => ($v->ac_cr_sid == $accountId) ? (float) $v->amount : 0.0,
                ]);
            }

            // Complex entries in range
            $entries = AccountingEntry::where('account_id', $accountId)
                ->whereHas('voucher', function ($q) use ($from, $to) {
                    $q->whereBetween('voucher_date', [$from, $to])
                      ->whereNull('deleted_at');
                })
                ->with('voucher')
                ->get();

            foreach ($entries as $entry) {
                $vDate = optional($entry->voucher->voucher_date)->format('Y-m-d') ?? '0000-00-00';
                $allLines->push([
                    'sort_key'  => $vDate . str_pad($entry->voucher_id, 10, '0', STR_PAD_LEFT),
                    'date'      => $vDate,
                    'reference' => ($entry->voucher->voucher_no ?? '') . ($entry->narration ? ' — ' . $entry->narration : ''),
                    'dr_raw'    => (float) $entry->debit,
                    'cr_raw'    => (float) $entry->credit,
                ]);
            }

            // Build result with running balance sorted by date then id
            $result = collect([[
                'date'       => $from,
                'account'    => $account->name,
                'reference'  => 'Opening Balance',
                'debit'      => '',
                'credit'     => '',
                'balance'    => $this->fmt($runningBal),
                'is_opening' => true,
            ]]);

            foreach ($allLines->sortBy('sort_key') as $line) {
                $runningBal += $isDebitNat
                    ? ($line['dr_raw'] - $line['cr_raw'])
                    : ($line['cr_raw'] - $line['dr_raw']);

                $result->push([
                    'date'       => $line['date'],
                    'account'    => $account->name,
                    'reference'  => $line['reference'],
                    'debit'      => $line['dr_raw'] > 0 ? $this->fmt($line['dr_raw']) : '',
                    'credit'     => $line['cr_raw'] > 0 ? $this->fmt($line['cr_raw']) : '',
                    'balance'    => $this->fmt($runningBal),
                    'is_opening' => false,
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error('AccountsReportController::generalLedger — ' . $e->getMessage(), [
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return collect();
        }
    }

    // =========================================================================
    // 2. PARTY LEDGER — same as general ledger, validated to vendor/customer
    // =========================================================================

    private function partyLedger(string $from, string $to, ?int $accountId): \Illuminate\Support\Collection
    {
        if ($accountId) {
            $account = ChartOfAccounts::find($accountId);
            if ($account && !in_array($account->account_type, ['customer', 'vendor'])) {
                return collect();
            }
        }
        return $this->generalLedger($accountId, $from, $to);
    }

    // =========================================================================
    // 3. TRIAL BALANCE
    // =========================================================================

    private function trialBalance(string $from, string $to): \Illuminate\Support\Collection
    {
        try {
            $rows       = collect();
            $totalDebit = 0.0;
            $totalCred  = 0.0;

            foreach (ChartOfAccounts::orderBy('account_code')->get() as $account) {
                $bal = $this->getAccountBalance($account->id, $to);

                if ($this->isDebitNature($account->account_type)) {
                    $net    = $bal['debit'] - $bal['credit'];
                    $debit  = $net >= 0 ? $net : 0;
                    $credit = $net <  0 ? abs($net) : 0;
                } else {
                    $net    = $bal['credit'] - $bal['debit'];
                    $credit = $net >= 0 ? $net : 0;
                    $debit  = $net <  0 ? abs($net) : 0;
                }

                if ($debit == 0 && $credit == 0) continue;

                $totalDebit += $debit;
                $totalCred  += $credit;

                $rows->push([
                    'account_code' => $account->account_code,
                    'account_name' => $account->name,
                    'account_type' => ucfirst($account->account_type),
                    'debit'        => $this->fmt($debit),
                    'credit'       => $this->fmt($credit),
                    '_is_total'    => false,
                ]);
            }

            $rows->push([
                'account_code' => '',
                'account_name' => 'TOTAL',
                'account_type' => '',
                'debit'        => $this->fmt($totalDebit),
                'credit'       => $this->fmt($totalCred),
                '_is_total'    => true,
            ]);

            return $rows;

        } catch (\Throwable $e) {
            Log::error('AccountsReportController::trialBalance — ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return collect();
        }
    }

    // =========================================================================
    // 4. PROFIT & LOSS
    // =========================================================================

    private function profitLoss(string $from, string $to): array
    {
        try {
            $revenue  = collect();
            $expenses = collect();

            foreach (ChartOfAccounts::orderBy('account_code')->get() as $account) {
                $activity = $this->getAccountActivity($account->id, $from, $to);
                $type     = strtolower($account->account_type);

                if ($type === 'revenue') {
                    $val = $activity['credit'] - $activity['debit'];
                    if (abs($val) > 0.005) {
                        $revenue->push(['name' => $account->name, 'amount' => $val]);
                    }
                } elseif ($type === 'expenses') {
                    $val = $activity['debit'] - $activity['credit'];
                    if (abs($val) > 0.005) {
                        $expenses->push(['name' => $account->name, 'amount' => $val]);
                    }
                }
            }

            $totalRevenue  = $revenue->sum('amount');
            $totalExpenses = $expenses->sum('amount');

            return [
                'revenue'        => $revenue,
                'expenses'       => $expenses,
                'cogs'           => collect(),
                'total_revenue'  => $totalRevenue,
                'total_cogs'     => 0,
                'gross_profit'   => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_profit'     => $totalRevenue - $totalExpenses,
            ];

        } catch (\Throwable $e) {
            Log::error('AccountsReportController::profitLoss — ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [];
        }
    }

    // =========================================================================
    // 5. BALANCE SHEET
    // =========================================================================

    private function balanceSheet(string $from, string $to): array
    {
        try {
            $assets      = collect();
            $liabilities = collect();
            $equity      = collect();

            foreach (ChartOfAccounts::orderBy('account_code')->get() as $account) {
                $bal  = $this->getAccountBalance($account->id, $to);
                $type = strtolower($account->account_type);

                if (in_array($type, ['cash', 'bank', 'asset', 'customer'])) {
                    $net = $bal['debit'] - $bal['credit'];
                    if (abs($net) > 0.005) $assets->push(['name' => $account->name, 'amount' => $net]);

                } elseif (in_array($type, ['vendor', 'liability'])) {
                    $net = $bal['credit'] - $bal['debit'];
                    if (abs($net) > 0.005) $liabilities->push(['name' => $account->name, 'amount' => $net]);

                } elseif ($type === 'equity') {
                    $net = $bal['credit'] - $bal['debit'];
                    if (abs($net) > 0.005) $equity->push(['name' => $account->name, 'amount' => $net]);
                }
            }

            $pl = $this->profitLoss($from, $to);
            if (!empty($pl) && abs($pl['net_profit'] ?? 0) > 0.005) {
                $equity->push(['name' => 'Retained Earnings (Net Profit)', 'amount' => $pl['net_profit']]);
            }

            return [
                'assets'            => $assets,
                'liabilities'       => $liabilities,
                'equity'            => $equity,
                'total_assets'      => $assets->sum('amount'),
                'total_liabilities' => $liabilities->sum('amount'),
                'total_equity'      => $equity->sum('amount'),
            ];

        } catch (\Throwable $e) {
            Log::error('AccountsReportController::balanceSheet — ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [];
        }
    }

    // =========================================================================
    // 6. RECEIVABLES
    // =========================================================================

    private function receivables(string $to): \Illuminate\Support\Collection
    {
        try {
            return ChartOfAccounts::where('account_type', 'customer')
                ->orderBy('name')->get()
                ->map(function ($a) use ($to) {
                    $bal = $this->getAccountBalance($a->id, $to);
                    return ['name' => $a->name, 'amount' => $bal['debit'] - $bal['credit']];
                })
                ->filter(fn($r) => $r['amount'] > 0.005)
                ->values();
        } catch (\Throwable $e) {
            Log::error('AccountsReportController::receivables — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 7. PAYABLES
    // =========================================================================

    private function payables(string $to): \Illuminate\Support\Collection
    {
        try {
            return ChartOfAccounts::where('account_type', 'vendor')
                ->orderBy('name')->get()
                ->map(function ($a) use ($to) {
                    $bal = $this->getAccountBalance($a->id, $to);
                    return ['name' => $a->name, 'amount' => $bal['credit'] - $bal['debit']];
                })
                ->filter(fn($r) => $r['amount'] > 0.005)
                ->values();
        } catch (\Throwable $e) {
            Log::error('AccountsReportController::payables — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 8. CASH BOOK
    // =========================================================================

    private function cashBook(string $from, string $to): \Illuminate\Support\Collection
    {
        try {
            return $this->buildBook(
                ChartOfAccounts::where('account_type', 'cash')->pluck('id')->toArray(),
                $from, $to
            );
        } catch (\Throwable $e) {
            Log::error('AccountsReportController::cashBook — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 9. BANK BOOK
    // =========================================================================

    private function bankBook(string $from, string $to): \Illuminate\Support\Collection
    {
        try {
            return $this->buildBook(
                ChartOfAccounts::where('account_type', 'bank')->pluck('id')->toArray(),
                $from, $to
            );
        } catch (\Throwable $e) {
            Log::error('AccountsReportController::bankBook — ' . $e->getMessage());
            return collect();
        }
    }

    private function buildBook(array $accountIds, string $from, string $to): \Illuminate\Support\Collection
    {
        if (empty($accountIds)) return collect();

        $dayBefore  = Carbon::parse($from)->subDay()->format('Y-m-d');
        $openingBal = 0.0;
        foreach ($accountIds as $id) {
            $bal         = $this->getAccountBalance($id, $dayBefore);
            $openingBal += ($bal['debit'] - $bal['credit']);
        }

        $allLines = collect();

        Voucher::whereNull('reference_type')
            ->whereBetween('voucher_date', [$from, $to])
            ->whereNull('deleted_at')
            ->where(function ($q) use ($accountIds) {
                $q->whereIn('ac_dr_sid', $accountIds)
                  ->orWhereIn('ac_cr_sid', $accountIds);
            })
            ->with(['debitAccount', 'creditAccount'])
            ->get()
            ->each(function ($v) use ($accountIds, &$allLines) {
                $allLines->push([
                    'sort_key'   => $v->voucher_date->format('Y-m-d') . str_pad($v->id, 10, '0', STR_PAD_LEFT),
                    'date'       => $v->voucher_date->format('Y-m-d'),
                    'reference'  => $v->voucher_no . ($v->remarks ? ' — ' . $v->remarks : ''),
                    'dr_account' => $v->debitAccount->name  ?? '-',
                    'cr_account' => $v->creditAccount->name ?? '-',
                    'dr_raw'     => in_array($v->ac_dr_sid, $accountIds) ? (float) $v->amount : 0.0,
                    'cr_raw'     => in_array($v->ac_cr_sid, $accountIds) ? (float) $v->amount : 0.0,
                ]);
            });

        AccountingEntry::whereIn('account_id', $accountIds)
            ->whereHas('voucher', function ($q) use ($from, $to) {
                $q->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at');
            })
            ->with(['voucher', 'account'])
            ->get()
            ->each(function ($entry) use (&$allLines) {
                $vDate = optional($entry->voucher->voucher_date)->format('Y-m-d') ?? '0000-00-00';
                $allLines->push([
                    'sort_key'   => $vDate . str_pad($entry->voucher_id, 10, '0', STR_PAD_LEFT),
                    'date'       => $vDate,
                    'reference'  => ($entry->voucher->voucher_no ?? '') . ($entry->narration ? ' — ' . $entry->narration : ''),
                    'dr_account' => $entry->account->name ?? '-',
                    'cr_account' => '',
                    'dr_raw'     => (float) $entry->debit,
                    'cr_raw'     => (float) $entry->credit,
                ]);
            });

        $runningBal = $openingBal;
        $result     = collect([[
            'date'       => $from,
            'reference'  => 'Opening Balance',
            'dr_account' => '',
            'cr_account' => '',
            'debit'      => '',
            'credit'     => '',
            'balance'    => $this->fmt($runningBal),
            'is_opening' => true,
        ]]);

        foreach ($allLines->sortBy('sort_key') as $line) {
            $runningBal += ($line['dr_raw'] - $line['cr_raw']);
            $result->push([
                'date'       => $line['date'],
                'reference'  => $line['reference'],
                'dr_account' => $line['dr_account'],
                'cr_account' => $line['cr_account'],
                'debit'      => $line['dr_raw'] > 0 ? $this->fmt($line['dr_raw']) : '',
                'credit'     => $line['cr_raw'] > 0 ? $this->fmt($line['cr_raw']) : '',
                'balance'    => $this->fmt($runningBal),
                'is_opening' => false,
            ]);
        }

        return $result;
    }

    // =========================================================================
    // 10. JOURNAL / DAY BOOK
    // =========================================================================

    private function journalBook(string $from, string $to): \Illuminate\Support\Collection
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
                        'type'       => ucfirst($v->voucher_type),
                        'dr_account' => $v->debitAccount->name  ?? '-',
                        'cr_account' => $v->creditAccount->name ?? '-',
                        'amount'     => $this->fmt($v->amount ?? 0),
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
                    $dr = $v->entries->where('debit',  '>', 0)->values();
                    $cr = $v->entries->where('credit', '>', 0)->values();
                    $rows->push([
                        'date'       => optional($v->voucher_date)->format('Y-m-d'),
                        'voucher_no' => $v->voucher_no,
                        'type'       => ucfirst($v->voucher_type),
                        'dr_account' => $dr->pluck('account.name')->filter()->implode(', ') ?: '-',
                        'cr_account' => $cr->pluck('account.name')->filter()->implode(', ') ?: '-',
                        'amount'     => $this->fmt($dr->sum('debit')),
                        'remarks'    => $v->remarks ?? '',
                    ]);
                });

            return $rows->sortBy('date')->values();

        } catch (\Throwable $e) {
            Log::error('AccountsReportController::journalBook — ' . $e->getMessage());
            return collect();
        }
    }

    // =========================================================================
    // 11. EXPENSE ANALYSIS — account_type = 'expenses' (plural, matches seeder)
    // =========================================================================

    private function expenseAnalysis(string $from, string $to): \Illuminate\Support\Collection
    {
        try {
            return ChartOfAccounts::where('account_type', 'expenses')
                ->orderBy('account_code')->get()
                ->map(function ($a) use ($from, $to) {
                    $act = $this->getAccountActivity($a->id, $from, $to);
                    return ['name' => $a->name, 'amount' => $act['debit'] - $act['credit']];
                })
                ->filter(fn($r) => abs($r['amount']) > 0.005)
                ->values();
        } catch (\Throwable $e) {
            Log::error('AccountsReportController::expenseAnalysis — ' . $e->getMessage());
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
                ->whereHas('voucher', fn($q) => $q->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at'))
                ->sum('debit');

            $complexOut = (float) AccountingEntry::whereIn('account_id', $ids)
                ->whereHas('voucher', fn($q) => $q->whereBetween('voucher_date', [$from, $to])->whereNull('deleted_at'))
                ->sum('credit');

            $inflow  = $simpleIn  + $complexIn;
            $outflow = $simpleOut + $complexOut;

            return ['inflow' => $inflow, 'outflow' => $outflow, 'net' => $inflow - $outflow];

        } catch (\Throwable $e) {
            Log::error('AccountsReportController::cashFlow — ' . $e->getMessage());
            return ['inflow' => 0, 'outflow' => 0, 'net' => 0];
        }
    }
}