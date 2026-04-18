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
              <tr class="{{ $row['_is_total'] ? 'table-dark fw-bold' : '' }}">
                <td>{{ $row['account_code'] }}</td>
                <td>{{ $row['account_name'] }}</td>
                <td>{{ $row['account_type'] }}</td>
                <td class="text-end">{{ $row['debit'] }}</td>
                <td class="text-end">{{ $row['credit'] }}</td>
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

      @php $pl = $reports['profit_loss']; @endphp
      @if(!empty($pl))
      <div class="row">
        <div class="col-md-6">
          <div class="card">
            <div class="card-header bg-success text-white"><strong>Revenue</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  @foreach($pl['revenue'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ number_format($row['amount'], 2) }}</td></tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr><td>Total Revenue</td><td class="text-end">{{ number_format($pl['total_revenue'], 2) }}</td></tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card">
            <div class="card-header bg-danger text-white"><strong>Expenses</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  @foreach($pl['expenses'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ number_format($row['amount'], 2) }}</td></tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr><td>Total Expenses</td><td class="text-end">{{ number_format($pl['total_expenses'], 2) }}</td></tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="alert {{ $pl['net_profit'] >= 0 ? 'alert-success' : 'alert-danger' }} mt-3">
        <strong>Net Profit / Loss: AED {{ number_format($pl['net_profit'], 2) }}</strong>
      </div>
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

      @php $bs = $reports['balance_sheet']; @endphp
      @if(!empty($bs))
      <div class="row">
        <div class="col-md-6">
          <div class="card mb-3">
            <div class="card-header bg-primary text-white"><strong>Assets</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  @foreach($bs['assets'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ number_format($row['amount'], 2) }}</td></tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr><td>Total Assets</td><td class="text-end">{{ number_format($bs['total_assets'], 2) }}</td></tr>
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
                  @foreach($bs['liabilities'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ number_format($row['amount'], 2) }}</td></tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr><td>Total Liabilities</td><td class="text-end">{{ number_format($bs['total_liabilities'], 2) }}</td></tr>
                </tfoot>
              </table>
            </div>
          </div>
          <div class="card">
            <div class="card-header bg-secondary text-white"><strong>Equity</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  @foreach($bs['equity'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ number_format($row['amount'], 2) }}</td></tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr><td>Total Equity</td><td class="text-end">{{ number_format($bs['total_equity'], 2) }}</td></tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
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

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr><th>Customer</th><th class="text-end">Outstanding (AED)</th></tr>
          </thead>
          <tbody>
            @forelse($reports['receivables'] as $row)
              <tr>
                <td>{{ $row['name'] }}</td>
                <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">No outstanding receivables.</td></tr>
            @endforelse
          </tbody>
          @if($reports['receivables']->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td>Total Receivable</td>
              <td class="text-end">{{ number_format($reports['receivables']->sum('amount'), 2) }}</td>
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

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr><th>Vendor</th><th class="text-end">Outstanding (AED)</th></tr>
          </thead>
          <tbody>
            @forelse($reports['payables'] as $row)
              <tr>
                <td>{{ $row['name'] }}</td>
                <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">No outstanding payables.</td></tr>
            @endforelse
          </tbody>
          @if($reports['payables']->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td>Total Payable</td>
              <td class="text-end">{{ number_format($reports['payables']->sum('amount'), 2) }}</td>
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
              <tr class="{{ $row['is_opening'] ? 'table-secondary fw-bold' : '' }}">
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['reference'] }}</td>
                <td>{{ $row['dr_account'] }}</td>
                <td>{{ $row['cr_account'] }}</td>
                <td class="text-end">{{ $row['debit'] }}</td>
                <td class="text-end">{{ $row['credit'] }}</td>
                <td class="text-end fw-bold">{{ $row['balance'] }}</td>
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
              <tr class="{{ $row['is_opening'] ? 'table-secondary fw-bold' : '' }}">
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['reference'] }}</td>
                <td>{{ $row['dr_account'] }}</td>
                <td>{{ $row['cr_account'] }}</td>
                <td class="text-end">{{ $row['debit'] }}</td>
                <td class="text-end">{{ $row['credit'] }}</td>
                <td class="text-end fw-bold">{{ $row['balance'] }}</td>
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
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['voucher_no'] }}</td>
                <td><span class="badge bg-secondary">{{ $row['type'] }}</span></td>
                <td>{{ $row['dr_account'] }}</td>
                <td>{{ $row['cr_account'] }}</td>
                <td class="text-end">{{ $row['amount'] }}</td>
                <td class="text-muted small">{{ $row['remarks'] }}</td>
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

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr><th>Expense Account</th><th class="text-end">Amount (AED)</th></tr>
          </thead>
          <tbody>
            @forelse($reports['expense_analysis'] as $row)
              <tr>
                <td>{{ $row['name'] }}</td>
                <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">No expense data found.</td></tr>
            @endforelse
          </tbody>
          @if($reports['expense_analysis']->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td>Total Expenses</td>
              <td class="text-end">{{ number_format($reports['expense_analysis']->sum('amount'), 2) }}</td>
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

      @php $cf = $reports['cash_flow']; @endphp
      @if(!empty($cf))
      <div class="row">
        <div class="col-md-4">
          <div class="card text-center">
            <div class="card-body">
              <h6 class="text-muted">Total Inflow</h6>
              <h3 class="text-success">AED {{ number_format($cf['inflow'], 2) }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-center">
            <div class="card-body">
              <h6 class="text-muted">Total Outflow</h6>
              <h3 class="text-danger">AED {{ number_format($cf['outflow'], 2) }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-center">
            <div class="card-body">
              <h6 class="text-muted">Net Flow</h6>
              <h3 class="{{ $cf['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                AED {{ number_format($cf['net'], 2) }}
              </h3>
            </div>
          </div>
        </div>
      </div>
      @endif
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