@extends('layouts.app')
@section('title', 'Accounting Reports')

@section('content')
<div class="tabs">

  {{-- ── Tab bar ─────────────────────────────────────────────────────────── --}}
  <ul class="nav nav-tabs" id="reportTabs">
    @foreach ([
      'general_ledger'   => 'General Ledger',
      'party_ledger'     => 'Party Ledger',
      'trial_balance'    => 'Trial Balance',
      'profit_loss'      => 'Profit & Loss',
      'balance_sheet'    => 'Balance Sheet',
      'receivables'      => 'Receivables',
      'payables'         => 'Payables',
      'cash_book'        => 'Cash Book',
      'bank_book'        => 'Bank Book',
      'journal_book'     => 'Journal / Day Book',
      'expense_analysis' => 'Expense Analysis',
      'cash_flow'        => 'Cash Flow',
    ] as $key => $label)
      <li class="nav-item">
        <a class="nav-link {{ $loop->first ? 'active' : '' }}"
           data-bs-toggle="tab" href="#{{ $key }}">{{ $label }}</a>
      </li>
    @endforeach
  </ul>

  <div class="tab-content mt-3">

    {{-- ================================================================== --}}
    {{-- 1. GENERAL LEDGER                                                   --}}
    {{-- ================================================================== --}}
    <div id="general_ledger" class="tab-pane fade show active">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="general_ledger">
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" value="{{ $from }}">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="{{ $to }}">
        </div>
        <div class="col-md-4">
          <select name="account_id" class="form-control select2">
            <option value="">-- Select Account --</option>
            @foreach ($chartOfAccounts as $coa)
              <option value="{{ $coa->id }}" {{ request('account_id') == $coa->id ? 'selected' : '' }}>
                [{{ $coa->account_code }}] {{ $coa->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Account</th>
              <th>Reference / Narration</th>
              <th class="text-end">Debit</th>
              <th class="text-end">Credit</th>
              <th class="text-end">Balance</th>
            </tr>
          </thead>
          <tbody>
            @forelse($reports['general_ledger'] as $row)
              <tr class="{{ $row['is_opening'] ? 'table-secondary fw-bold' : '' }}">
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['account'] }}</td>
                <td>{{ $row['reference'] }}</td>
                <td class="text-end">{{ $row['debit'] }}</td>
                <td class="text-end">{{ $row['credit'] }}</td>
                <td class="text-end fw-bold">{{ $row['balance'] }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">Select an account and date range to view ledger.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 2. PARTY LEDGER                                                     --}}
    {{-- ================================================================== --}}
    <div id="party_ledger" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="party_ledger">
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" value="{{ $from }}">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="{{ $to }}">
        </div>
        <div class="col-md-4">
          <select name="account_id" class="form-control select2">
            <option value="">-- Select Customer / Vendor --</option>
            @foreach ($chartOfAccounts->whereIn('account_type', ['customer','vendor']) as $coa)
              <option value="{{ $coa->id }}" {{ request('account_id') == $coa->id ? 'selected' : '' }}>
                [{{ $coa->account_code }}] {{ $coa->name }} ({{ ucfirst($coa->account_type) }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr>
              <th>Date</th><th>Account</th><th>Reference</th>
              <th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th>
            </tr>
          </thead>
          <tbody>
            @forelse($reports['party_ledger'] as $row)
              <tr class="{{ $row['is_opening'] ? 'table-secondary fw-bold' : '' }}">
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['account'] }}</td>
                <td>{{ $row['reference'] }}</td>
                <td class="text-end">{{ $row['debit'] }}</td>
                <td class="text-end">{{ $row['credit'] }}</td>
                <td class="text-end fw-bold">{{ $row['balance'] }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">Select a customer or vendor account to view party ledger.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 3. TRIAL BALANCE                                                    --}}
    {{-- ================================================================== --}}
    <div id="trial_balance" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="trial_balance">
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" value="{{ $from }}">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="{{ $to }}">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr>
              <th>Code</th><th>Account</th><th>Type</th>
              <th class="text-end">Debit</th><th class="text-end">Credit</th>
            </tr>
          </thead>
          <tbody>
            @forelse($reports['trial_balance'] as $row)
              <tr class="{{ ($row['_is_total'] ?? false) ? 'table-dark fw-bold' : '' }}">
                <td>{{ $row['account_code'] ?? '' }}</td>
                <td>{{ $row['account_name'] ?? '' }}</td>
                <td>{{ $row['account_type'] ?? '' }}</td>
                <td class="text-end">{{ $row['debit'] ?? '' }}</td>
                <td class="text-end">{{ $row['credit'] ?? '' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted">No data found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 4. PROFIT & LOSS                                                    --}}
    {{-- ================================================================== --}}
    <div id="profit_loss" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="profit_loss">
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" value="{{ $from }}">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="{{ $to }}">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @php
        // Safe extraction — never crashes even if keys are missing
        $pl             = $reports['profit_loss'] ?? [];
        $plRevenue      = $pl['revenue']        ?? collect();
        $plExpenses     = $pl['expenses']       ?? collect();
        $plCogs         = $pl['cogs']           ?? collect();
        $plTotalRev     = $pl['total_revenue']  ?? 0;
        $plTotalCogs    = $pl['total_cogs']     ?? 0;
        $plGrossProfit  = $pl['gross_profit']   ?? 0;
        $plTotalExp     = $pl['total_expenses'] ?? 0;
        $plNetProfit    = $pl['net_profit']     ?? 0;
        $hasPLData      = $plRevenue->isNotEmpty() || $plExpenses->isNotEmpty();
      @endphp

      @if($hasPLData)

        {{-- Gross profit info bar (only shown when COGS exist) --}}
        @if($plTotalCogs > 0)
        <div class="alert alert-info py-2 mb-3">
          <strong>Gross Profit:</strong>
          AED {{ number_format($plGrossProfit, 2) }}
          &nbsp;|&nbsp;
          Revenue: AED {{ number_format($plTotalRev, 2) }}
          &minus;
          COGS: AED {{ number_format($plTotalCogs, 2) }}
        </div>
        @endif

        <div class="row">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header bg-success text-white"><strong>Revenue</strong></div>
              <div class="card-body p-0">
                <table class="table table-sm mb-0">
                  <tbody>
                    @foreach($plRevenue as $row)
                      <tr>
                        <td>{{ $row['name'] ?? '' }}</td>
                        <td class="text-end">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                  <tfoot class="table-light fw-bold">
                    <tr>
                      <td>Total Revenue</td>
                      <td class="text-end">{{ number_format($plTotalRev, 2) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header bg-danger text-white"><strong>Cost & Expenses</strong></div>
              <div class="card-body p-0">
                <table class="table table-sm mb-0">
                  <tbody>
                    @foreach($plExpenses as $row)
                      <tr>
                        <td>{{ $row['name'] ?? '' }}</td>
                        <td class="text-end">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                  <tfoot class="table-light fw-bold">
                    <tr>
                      <td>Total Cost & Expenses</td>
                      <td class="text-end">{{ number_format($plTotalExp, 2) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="alert {{ $plNetProfit >= 0 ? 'alert-success' : 'alert-danger' }} mt-3">
          <strong>Net Profit / Loss: AED {{ number_format($plNetProfit, 2) }}</strong>
        </div>

      @else
        <div class="text-muted text-center py-4">No revenue or expense data found for this period.</div>
      @endif
    </div>

    {{-- ================================================================== --}}
    {{-- 5. BALANCE SHEET                                                    --}}
    {{-- ================================================================== --}}
    <div id="balance_sheet" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="balance_sheet">
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" value="{{ $from }}">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="{{ $to }}">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @php
        $bs          = $reports['balance_sheet'] ?? [];
        $bsAssets    = $bs['assets']            ?? collect();
        $bsLiab      = $bs['liabilities']       ?? collect();
        $bsEquity    = $bs['equity']            ?? collect();
        $bsTotAssets = $bs['total_assets']      ?? 0;
        $bsTotLiab   = $bs['total_liabilities'] ?? 0;
        $bsTotEquity = $bs['total_equity']      ?? 0;
        $hasBSData   = $bsAssets->isNotEmpty() || $bsLiab->isNotEmpty();
        $liabEquity  = $bsTotLiab + $bsTotEquity;
        $balanced    = abs($bsTotAssets - $liabEquity) < 1;
      @endphp

      @if($hasBSData)
      <div class="row">
        <div class="col-md-6">
          <div class="card mb-3">
            <div class="card-header bg-primary text-white"><strong>Assets</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  @foreach($bsAssets as $row)
                    <tr>
                      <td>{{ $row['name'] ?? '' }}</td>
                      <td class="text-end">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr>
                    <td>Total Assets</td>
                    <td class="text-end">{{ number_format($bsTotAssets, 2) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card mb-3">
            <div class="card-header bg-warning text-dark"><strong>Liabilities</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  @foreach($bsLiab as $row)
                    <tr>
                      <td>{{ $row['name'] ?? '' }}</td>
                      <td class="text-end">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr>
                    <td>Total Liabilities</td>
                    <td class="text-end">{{ number_format($bsTotLiab, 2) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          <div class="card">
            <div class="card-header bg-secondary text-white"><strong>Equity</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  @foreach($bsEquity as $row)
                    <tr>
                      <td>{{ $row['name'] ?? '' }}</td>
                      <td class="text-end">{{ number_format($row['amount'] ?? 0, 2) }}</td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr>
                    <td>Total Equity</td>
                    <td class="text-end">{{ number_format($bsTotEquity, 2) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          {{-- Balance check --}}
          <div class="alert {{ $balanced ? 'alert-success' : 'alert-warning' }} mt-2 py-2">
            <strong>Liabilities + Equity: AED {{ number_format($liabEquity, 2) }}</strong>
            @if(!$balanced)
              &nbsp;<small class="text-danger">
                ⚠ Difference: AED {{ number_format(abs($bsTotAssets - $liabEquity), 2) }}
              </small>
            @else
              &nbsp;<small>✓ Balanced</small>
            @endif
          </div>
        </div>
      </div>
      @else
        <div class="text-muted text-center py-4">No balance sheet data found for this period.</div>
      @endif
    </div>

    {{-- ================================================================== --}}
    {{-- 6. RECEIVABLES                                                      --}}
    {{-- ================================================================== --}}
    <div id="receivables" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="receivables">
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="{{ $to }}" placeholder="As of Date">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @php $recRows = collect($reports['receivables'] ?? []); @endphp
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr><th>Customer</th><th class="text-end">Outstanding (AED)</th></tr>
          </thead>
          <tbody>
            @forelse($recRows as $row)
              <tr>
                <td>{{ $row['name'] ?? '' }}</td>
                <td class="text-end">{{ number_format($row['amount'] ?? 0, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">No outstanding receivables.</td></tr>
            @endforelse
          </tbody>
          @if($recRows->isNotEmpty())
          <tfoot class="table-light fw-bold">
            <tr>
              <td>Total Receivable</td>
              <td class="text-end">{{ number_format($recRows->sum('amount'), 2) }}</td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 7. PAYABLES                                                         --}}
    {{-- ================================================================== --}}
    <div id="payables" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="payables">
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="{{ $to }}" placeholder="As of Date">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @php $payRows = collect($reports['payables'] ?? []); @endphp
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr><th>Vendor</th><th class="text-end">Outstanding (AED)</th></tr>
          </thead>
          <tbody>
            @forelse($payRows as $row)
              <tr>
                <td>{{ $row['name'] ?? '' }}</td>
                <td class="text-end">{{ number_format($row['amount'] ?? 0, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">No outstanding payables.</td></tr>
            @endforelse
          </tbody>
          @if($payRows->isNotEmpty())
          <tfoot class="table-light fw-bold">
            <tr>
              <td>Total Payable</td>
              <td class="text-end">{{ number_format($payRows->sum('amount'), 2) }}</td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 8. CASH BOOK                                                        --}}
    {{-- ================================================================== --}}
    <div id="cash_book" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="cash_book">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr>
              <th>Date</th><th>Reference</th><th>Dr Account</th><th>Cr Account</th>
              <th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th>
            </tr>
          </thead>
          <tbody>
            @forelse($reports['cash_book'] as $row)
              <tr class="{{ ($row['is_opening'] ?? false) ? 'table-secondary fw-bold' : '' }}">
                <td>{{ $row['date'] ?? '' }}</td>
                <td>{{ $row['reference'] ?? '' }}</td>
                <td>{{ $row['dr_account'] ?? '' }}</td>
                <td>{{ $row['cr_account'] ?? '' }}</td>
                <td class="text-end">{{ $row['debit'] ?? '' }}</td>
                <td class="text-end">{{ $row['credit'] ?? '' }}</td>
                <td class="text-end fw-bold">{{ $row['balance'] ?? '' }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">No cash transactions found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 9. BANK BOOK                                                        --}}
    {{-- ================================================================== --}}
    <div id="bank_book" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="bank_book">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr>
              <th>Date</th><th>Reference</th><th>Dr Account</th><th>Cr Account</th>
              <th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th>
            </tr>
          </thead>
          <tbody>
            @forelse($reports['bank_book'] as $row)
              <tr class="{{ ($row['is_opening'] ?? false) ? 'table-secondary fw-bold' : '' }}">
                <td>{{ $row['date'] ?? '' }}</td>
                <td>{{ $row['reference'] ?? '' }}</td>
                <td>{{ $row['dr_account'] ?? '' }}</td>
                <td>{{ $row['cr_account'] ?? '' }}</td>
                <td class="text-end">{{ $row['debit'] ?? '' }}</td>
                <td class="text-end">{{ $row['credit'] ?? '' }}</td>
                <td class="text-end fw-bold">{{ $row['balance'] ?? '' }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">No bank transactions found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 10. JOURNAL / DAY BOOK                                              --}}
    {{-- ================================================================== --}}
    <div id="journal_book" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="journal_book">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr>
              <th>Date</th><th>Voucher No</th><th>Type</th>
              <th>Dr Account</th><th>Cr Account</th>
              <th class="text-end">Amount</th><th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            @forelse($reports['journal_book'] as $row)
              <tr>
                <td>{{ $row['date'] ?? '' }}</td>
                <td>{{ $row['voucher_no'] ?? '' }}</td>
                <td>
                  @php
                    $jType = $row['type'] ?? '';
                    $badgeClass = match(true) {
                      str_contains($jType, 'Purchase Return') => 'bg-warning text-dark',
                      str_contains($jType, 'Purchase')        => 'bg-danger',
                      str_contains($jType, 'Sale Return')     => 'bg-info text-dark',
                      str_contains($jType, 'Sale')            => 'bg-success',
                      default                                  => 'bg-secondary',
                    };
                  @endphp
                  <span class="badge {{ $badgeClass }}">{{ $jType }}</span>
                </td>
                <td class="small">{{ $row['dr_account'] ?? '' }}</td>
                <td class="small">{{ $row['cr_account'] ?? '' }}</td>
                <td class="text-end">{{ $row['amount'] ?? '' }}</td>
                <td class="text-muted small">{{ Str::limit($row['remarks'] ?? '', 60) }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">No journal entries found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 11. EXPENSE ANALYSIS                                                --}}
    {{-- ================================================================== --}}
    <div id="expense_analysis" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="expense_analysis">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
      </form>

      @php $expRows = collect($reports['expense_analysis'] ?? []); @endphp
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr><th>Expense / Cost Account</th><th class="text-end">Amount (AED)</th></tr>
          </thead>
          <tbody>
            @forelse($expRows as $row)
              <tr>
                <td>{{ $row['name'] ?? '' }}</td>
                <td class="text-end">{{ number_format($row['amount'] ?? 0, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">No expense data found.</td></tr>
            @endforelse
          </tbody>
          @if($expRows->isNotEmpty())
          <tfoot class="table-light fw-bold">
            <tr>
              <td>Total Expenses / COGS</td>
              <td class="text-end">{{ number_format($expRows->sum('amount'), 2) }}</td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 12. CASH FLOW                                                       --}}
    {{-- ================================================================== --}}
    <div id="cash_flow" class="tab-pane fade">
      <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="cash_flow">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
      </form>

      @php
        $cf = $reports['cash_flow'] ?? [];
        $cfInflow  = $cf['inflow']  ?? 0;
        $cfOutflow = $cf['outflow'] ?? 0;
        $cfNet     = $cf['net']     ?? 0;
      @endphp
      <div class="row">
        <div class="col-md-4">
          <div class="card text-center">
            <div class="card-body">
              <h6 class="text-muted">Total Inflow</h6>
              <h3 class="text-success">AED {{ number_format($cfInflow, 2) }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-center">
            <div class="card-body">
              <h6 class="text-muted">Total Outflow</h6>
              <h3 class="text-danger">AED {{ number_format($cfOutflow, 2) }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-center">
            <div class="card-body">
              <h6 class="text-muted">Net Flow</h6>
              <h3 class="{{ $cfNet >= 0 ? 'text-success' : 'text-danger' }}">
                AED {{ number_format($cfNet, 2) }}
              </h3>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>{{-- end tab-content --}}
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || window.location.hash.replace('#', '');
    if (tab) {
        const el = document.querySelector(`.nav-link[href="#${tab}"]`);
        if (el && typeof bootstrap !== 'undefined') {
            new bootstrap.Tab(el).show();
        }
    }
});
</script>
@endsection