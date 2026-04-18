@extends('layouts.app')
@section('title', 'Sales Reports')

@section('content')
<div class="tabs">
  <ul class="nav nav-tabs">
    <li class="nav-item">
      <a class="nav-link {{ $tab==='SR'?'active':'' }}" href="{{ route('reports.sale', ['tab'=>'SR','from_date'=>$from,'to_date'=>$to]) }}">
        Sale Register
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='CW'?'active':'' }}" href="{{ route('reports.sale', ['tab'=>'CW','from_date'=>$from,'to_date'=>$to]) }}">
        Customer-Wise
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='PR'?'active':'' }}" href="{{ route('reports.sale', ['tab'=>'PR','from_date'=>$from,'to_date'=>$to]) }}">
        Profit Report
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='IA'?'active':'' }}" href="{{ route('reports.sale', ['tab'=>'IA','from_date'=>$from,'to_date'=>$to]) }}">
        Item Analysis
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='PM'?'active':'' }}" href="{{ route('reports.sale', ['tab'=>'PM','from_date'=>$from,'to_date'=>$to]) }}">
        Payment Summary
      </a>
    </li>
  </ul>

  <div class="tab-content mt-3">

    {{-- ================================================================== --}}
    {{-- 1. SALE REGISTER                                                    --}}
    {{-- ================================================================== --}}
    <div id="SR" class="tab-pane fade {{ $tab==='SR'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.sale') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="SR">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="customer_id" class="form-control">
            <option value="">-- All Customers --</option>
            @foreach($customers as $c)
              <option value="{{ $c->id }}" {{ $customerId==$c->id?'selected':'' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm datatable">
          <thead class="table-light">
            <tr>
              <th>Invoice No</th><th>Date</th><th>Customer</th><th>Type</th><th>Currency</th>
              <th class="text-end">Items</th><th class="text-end">Gross Wt</th>
              <th class="text-end">Purity Wt</th><th class="text-end">Material Val</th>
              <th class="text-end">Making Val</th><th class="text-end">VAT</th>
              <th class="text-end">Net Amount</th><th class="text-end">Net AED</th>
              <th>Payment</th>
            </tr>
          </thead>
          <tbody>
            @forelse($saleRegister as $row)
              <tr>
                <td><strong>{{ $row['invoice_no'] }}</strong></td>
                <td>{{ $row['invoice_date'] }}</td>
                <td>{{ $row['customer'] }}</td>
                <td>
                  <span class="badge bg-{{ $row['is_taxable'] ? 'success' : 'secondary' }}">
                    {{ $row['is_taxable'] ? 'Tax' : 'Non-Tax' }}
                  </span>
                </td>
                <td>{{ $row['currency'] }}</td>
                <td class="text-end">{{ $row['total_items'] }}</td>
                <td class="text-end">{{ number_format($row['total_gross_wt'], 3) }}</td>
                <td class="text-end">{{ number_format($row['total_purity_wt'], 3) }}</td>
                <td class="text-end">{{ number_format($row['total_material'], 2) }}</td>
                <td class="text-end">{{ number_format($row['total_making'], 2) }}</td>
                <td class="text-end">{{ number_format($row['total_vat'], 2) }}</td>
                <td class="text-end">{{ number_format($row['net_amount'], 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($row['net_amount_aed'], 2) }}</td>
                <td>
                  <span class="badge bg-info text-dark">
                    {{ ucwords(str_replace(['+','_'], [' + ',' '], $row['payment_method'])) }}
                  </span>
                </td>
              </tr>
            @empty
              <tr><td colspan="14" class="text-center text-muted">No sale records found.</td></tr>
            @endforelse
          </tbody>
          @if($saleRegister->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="5">Totals ({{ $saleRegister->count() }} invoices)</td>
              <td class="text-end">{{ $saleRegister->sum('total_items') }}</td>
              <td class="text-end">{{ number_format($saleRegister->sum('total_gross_wt'), 3) }}</td>
              <td class="text-end">{{ number_format($saleRegister->sum('total_purity_wt'), 3) }}</td>
              <td class="text-end">{{ number_format($saleRegister->sum('total_material'), 2) }}</td>
              <td class="text-end">{{ number_format($saleRegister->sum('total_making'), 2) }}</td>
              <td class="text-end">{{ number_format($saleRegister->sum('total_vat'), 2) }}</td>
              <td></td>
              <td class="text-end">{{ number_format($saleRegister->sum('net_amount_aed'), 2) }}</td>
              <td></td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 2. CUSTOMER-WISE                                                    --}}
    {{-- ================================================================== --}}
    <div id="CW" class="tab-pane fade {{ $tab==='CW'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.sale') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="CW">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="customer_id" class="form-control">
            <option value="">-- All Customers --</option>
            @foreach($customers as $c)
              <option value="{{ $c->id }}" {{ $customerId==$c->id?'selected':'' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @forelse($customerWise as $cGroup)
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>{{ $cGroup['customer_name'] }}</strong>
            <span class="text-muted small">
              {{ $cGroup['invoice_count'] }} invoice(s) &nbsp;|&nbsp;
              Total AED: <strong>{{ number_format($cGroup['total_aed'], 2) }}</strong>
            </span>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Invoice</th><th>Date</th><th>Item</th><th>Material</th>
                  <th class="text-end">Gross Wt</th><th class="text-end">Purity Wt</th>
                  <th class="text-end">Material Val</th><th class="text-end">Making Val</th>
                  <th class="text-end">Item Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach($cGroup['items'] as $item)
                  <tr>
                    <td>{{ $item['invoice_no'] }}</td>
                    <td>{{ $item['invoice_date'] }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ $item['material_type'] }}</td>
                    <td class="text-end">{{ number_format($item['gross_weight'], 3) }}</td>
                    <td class="text-end">{{ number_format($item['purity_weight'], 3) }}</td>
                    <td class="text-end">{{ number_format($item['material_value'], 2) }}</td>
                    <td class="text-end">{{ number_format($item['making_value'], 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($item['item_total'], 2) }}</td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot class="table-light fw-bold">
                <tr>
                  <td colspan="4">Customer Total</td>
                  <td class="text-end">{{ number_format($cGroup['total_gross_wt'], 3) }}</td>
                  <td class="text-end">{{ number_format($cGroup['total_purity_wt'], 3) }}</td>
                  <td class="text-end">{{ number_format($cGroup['total_material'], 2) }}</td>
                  <td class="text-end">{{ number_format($cGroup['total_making'], 2) }}</td>
                  <td class="text-end">{{ number_format($cGroup['total_amount'], 2) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      @empty
        <div class="alert alert-info">No customer sale data found.</div>
      @endforelse
    </div>

    {{-- ================================================================== --}}
    {{-- 3. PROFIT REPORT                                                    --}}
    {{-- ================================================================== --}}
    <div id="PR" class="tab-pane fade {{ $tab==='PR'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.sale') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="PR">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="customer_id" class="form-control">
            <option value="">-- All Customers --</option>
            @foreach($customers as $c)
              <option value="{{ $c->id }}" {{ $customerId==$c->id?'selected':'' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm datatable">
          <thead class="table-light">
            <tr>
              <th>Invoice No</th><th>Date</th><th>Customer</th><th>Currency</th>
              <th class="text-end">Revenue</th><th class="text-end">Revenue AED</th>
              <th class="text-end">Cost</th>
              <th class="text-end">Profit</th><th class="text-end">Margin %</th>
              <th>Print</th>
            </tr>
          </thead>
          <tbody>
            @forelse($profitReport as $row)
              @php $mc = $row['margin'] >= 0 ? 'text-success' : 'text-danger'; @endphp
              <tr>
                <td><strong>{{ $row['invoice_no'] }}</strong></td>
                <td>{{ $row['invoice_date'] }}</td>
                <td>{{ $row['customer'] }}</td>
                <td>{{ $row['currency'] }}</td>
                <td class="text-end">{{ number_format($row['revenue'], 2) }}</td>
                <td class="text-end">{{ number_format($row['revenue_aed'], 2) }}</td>
                <td class="text-end">{{ number_format($row['cost'], 2) }}</td>
                <td class="text-end {{ $mc }} fw-bold">{{ number_format($row['profit'], 2) }}</td>
                <td class="text-end {{ $mc }}">{{ $row['margin'] }}%</td>
                <td>
                  <a href="{{ route('reports.print-profit', $row['id']) }}" target="_blank"
                     class="btn btn-sm btn-outline-success" title="Print Profit PDF">
                    <i class="fas fa-print"></i>
                  </a>
                </td>
              </tr>
            @empty
              <tr><td colspan="10" class="text-center text-muted">No profit data found.</td></tr>
            @endforelse
          </tbody>
          @if($profitReport->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="4">Totals</td>
              <td></td>
              <td class="text-end">{{ number_format($profitReport->sum('revenue_aed'), 2) }}</td>
              <td class="text-end">{{ number_format($profitReport->sum('cost'), 2) }}</td>
              <td class="text-end {{ $profitReport->sum('profit') >= 0 ? 'text-success' : 'text-danger' }}">
                {{ number_format($profitReport->sum('profit'), 2) }}
              </td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 4. ITEM ANALYSIS                                                    --}}
    {{-- ================================================================== --}}
    <div id="IA" class="tab-pane fade {{ $tab==='IA'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.sale') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="IA">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="customer_id" class="form-control">
            <option value="">-- All Customers --</option>
            @foreach($customers as $c)
              <option value="{{ $c->id }}" {{ $customerId==$c->id?'selected':'' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm datatable">
          <thead class="table-light">
            <tr>
              <th>Invoice</th><th>Date</th><th>Customer</th><th>Item</th><th>Barcode</th>
              <th>Material</th><th class="text-end">Purity</th><th class="text-end">Gross Wt</th>
              <th class="text-end">Purity Wt</th><th class="text-end">Making Rate</th>
              <th class="text-end">Making Val</th><th class="text-end">Material Val</th>
              <th class="text-end">Parts Total</th><th class="text-end">VAT</th>
              <th class="text-end">Sale Total</th><th class="text-end">Cost</th>
              <th class="text-end">Profit</th><th class="text-end">Margin%</th>
            </tr>
          </thead>
          <tbody>
            @forelse($itemAnalysis as $row)
              @php $mc = $row['margin'] >= 0 ? 'text-success' : 'text-danger'; @endphp
              <tr>
                <td>{{ $row['invoice_no'] }}</td>
                <td>{{ $row['invoice_date'] }}</td>
                <td>{{ $row['customer'] }}</td>
                <td>{{ $row['item_name'] }}</td>
                <td><code>{{ $row['barcode'] }}</code></td>
                <td>{{ $row['material_type'] }}</td>
                <td class="text-end">{{ $row['purity'] }}</td>
                <td class="text-end">{{ number_format($row['gross_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['purity_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['making_rate'], 2) }}</td>
                <td class="text-end">{{ number_format($row['making_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['material_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['parts_total'], 2) }}</td>
                <td class="text-end">{{ number_format($row['vat_amount'], 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($row['item_total'], 2) }}</td>
                <td class="text-end">{{ number_format($row['cost'], 2) }}</td>
                <td class="text-end {{ $mc }} fw-bold">{{ number_format($row['profit'], 2) }}</td>
                <td class="text-end {{ $mc }}">{{ $row['margin'] }}%</td>
              </tr>
            @empty
              <tr><td colspan="18" class="text-center text-muted">No item data found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 5. PAYMENT SUMMARY                                                  --}}
    {{-- ================================================================== --}}
    <div id="PM" class="tab-pane fade {{ $tab==='PM'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.sale') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="PM">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="customer_id" class="form-control">
            <option value="">-- All Customers --</option>
            @foreach($customers as $c)
              <option value="{{ $c->id }}" {{ $customerId==$c->id?'selected':'' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm datatable">
          <thead class="table-light">
            <tr>
              <th>Invoice</th><th>Date</th><th>Customer</th><th>Payment Method</th>
              <th>Currency</th><th class="text-end">Net Amount</th><th class="text-end">Net AED</th>
              <th>Cheque No</th><th>Transaction ID</th><th>Transfer Date</th>
              <th class="text-end">Transfer Amount</th>
            </tr>
          </thead>
          <tbody>
            @forelse($paymentSummary as $row)
              <tr>
                <td>{{ $row['invoice_no'] }}</td>
                <td>{{ $row['invoice_date'] }}</td>
                <td>{{ $row['customer'] }}</td>
                <td><span class="badge bg-info text-dark">{{ $row['payment_method'] }}</span></td>
                <td>{{ $row['currency'] }}</td>
                <td class="text-end">{{ number_format($row['net_amount'], 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($row['net_amount_aed'], 2) }}</td>
                <td>{{ $row['cheque_no'] }}</td>
                <td>{{ $row['transaction_id'] }}</td>
                <td>{{ $row['transfer_date'] }}</td>
                <td class="text-end">{{ $row['transfer_amount'] ? number_format($row['transfer_amount'], 2) : '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="11" class="text-center text-muted">No payment data found.</td></tr>
            @endforelse
          </tbody>
          @if($paymentSummary->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="5">Total ({{ $paymentSummary->count() }} invoices)</td>
              <td></td>
              <td class="text-end">{{ number_format($paymentSummary->sum('net_amount_aed'), 2) }}</td>
              <td colspan="4"></td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

  </div>{{-- end tab-content --}}
</div>
@endsection