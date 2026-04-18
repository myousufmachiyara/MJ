@extends('layouts.app')
@section('title', 'Purchase Reports')

@section('content')
<div class="tabs">
  <ul class="nav nav-tabs">
    <li class="nav-item">
      <a class="nav-link {{ $tab==='PR'?'active':'' }}" href="{{ route('reports.purchase', ['tab'=>'PR','from_date'=>$from,'to_date'=>$to]) }}">
        Purchase Register
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='VW'?'active':'' }}" href="{{ route('reports.purchase', ['tab'=>'VW','from_date'=>$from,'to_date'=>$to]) }}">
        Vendor-Wise
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='PS'?'active':'' }}" href="{{ route('reports.purchase', ['tab'=>'PS','from_date'=>$from,'to_date'=>$to]) }}">
        Summary
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='IA'?'active':'' }}" href="{{ route('reports.purchase', ['tab'=>'IA','from_date'=>$from,'to_date'=>$to]) }}">
        Item Analysis
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab==='PM'?'active':'' }}" href="{{ route('reports.purchase', ['tab'=>'PM','from_date'=>$from,'to_date'=>$to]) }}">
        Payment Summary
      </a>
    </li>
  </ul>

  <div class="tab-content mt-3">

    {{-- ================================================================== --}}
    {{-- 1. PURCHASE REGISTER                                                --}}
    {{-- ================================================================== --}}
    <div id="PR" class="tab-pane fade {{ $tab==='PR'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.purchase') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="PR">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="vendor_id" class="form-control">
            <option value="">-- All Vendors --</option>
            @foreach($vendors as $vendor)
              <option value="{{ $vendor->id }}" {{ $vendorId==$vendor->id?'selected':'' }}>{{ $vendor->name }}</option>
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
              <th>Invoice No</th><th>Date</th><th>Vendor</th><th>Type</th><th>Currency</th>
              <th class="text-end">Items</th><th class="text-end">Gross Wt</th>
              <th class="text-end">Purity Wt</th><th class="text-end">Material Val</th>
              <th class="text-end">Making Val</th><th class="text-end">VAT</th>
              <th class="text-end">Net Amount</th><th class="text-end">Net AED</th>
              <th>Payment</th>
            </tr>
          </thead>
          <tbody>
            @forelse($purchaseRegister as $row)
              <tr>
                <td><strong>{{ $row['invoice_no'] }}</strong></td>
                <td>{{ $row['invoice_date'] }}</td>
                <td>{{ $row['vendor'] }}</td>
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
              <tr><td colspan="14" class="text-center text-muted">No purchase records found.</td></tr>
            @endforelse
          </tbody>
          @if($purchaseRegister->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="5">Totals ({{ $purchaseRegister->count() }} invoices)</td>
              <td class="text-end">{{ $purchaseRegister->sum('total_items') }}</td>
              <td class="text-end">{{ number_format($purchaseRegister->sum('total_gross_wt'), 3) }}</td>
              <td class="text-end">{{ number_format($purchaseRegister->sum('total_purity_wt'), 3) }}</td>
              <td class="text-end">{{ number_format($purchaseRegister->sum('total_material'), 2) }}</td>
              <td class="text-end">{{ number_format($purchaseRegister->sum('total_making'), 2) }}</td>
              <td class="text-end">{{ number_format($purchaseRegister->sum('total_vat'), 2) }}</td>
              <td></td>
              <td class="text-end">{{ number_format($purchaseRegister->sum('net_amount_aed'), 2) }}</td>
              <td></td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 2. VENDOR-WISE                                                      --}}
    {{-- ================================================================== --}}
    <div id="VW" class="tab-pane fade {{ $tab==='VW'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.purchase') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="VW">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="vendor_id" class="form-control">
            <option value="">-- All Vendors --</option>
            @foreach($vendors as $vendor)
              <option value="{{ $vendor->id }}" {{ $vendorId==$vendor->id?'selected':'' }}>{{ $vendor->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @forelse($vendorWisePurchase as $vGroup)
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>{{ $vGroup['vendor_name'] }}</strong>
            <span class="text-muted small">{{ $vGroup['invoice_count'] }} invoice(s) &nbsp;|&nbsp; Total AED: <strong>{{ number_format($vGroup['total_aed'], 2) }}</strong></span>
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
                @foreach($vGroup['items'] as $item)
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
                  <td colspan="4">Vendor Total</td>
                  <td class="text-end">{{ number_format($vGroup['total_gross_wt'], 3) }}</td>
                  <td class="text-end">{{ number_format($vGroup['total_purity_wt'], 3) }}</td>
                  <td class="text-end">{{ number_format($vGroup['total_material'], 2) }}</td>
                  <td class="text-end">{{ number_format($vGroup['total_making'], 2) }}</td>
                  <td class="text-end">{{ number_format($vGroup['total_amount'], 2) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      @empty
        <div class="alert alert-info">No vendor purchase data found.</div>
      @endforelse
    </div>

    {{-- ================================================================== --}}
    {{-- 3. SUMMARY                                                          --}}
    {{-- ================================================================== --}}
    <div id="PS" class="tab-pane fade {{ $tab==='PS'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.purchase') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="PS">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="vendor_id" class="form-control">
            <option value="">-- All Vendors --</option>
            @foreach($vendors as $v)
              <option value="{{ $v->id }}" {{ $vendorId==$v->id?'selected':'' }}>{{ $v->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @if(!empty($purchaseSummary))
      <div class="row mb-3">
        <div class="col-md-3"><div class="card text-center"><div class="card-body py-2">
          <div class="text-muted small">Total Invoices</div>
          <h4 class="text-primary mb-0">{{ $purchaseSummary['total_invoices'] }}</h4>
        </div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body py-2">
          <div class="text-muted small">Total AED</div>
          <h4 class="text-danger mb-0">{{ number_format($purchaseSummary['total_amount_aed'], 2) }}</h4>
        </div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body py-2">
          <div class="text-muted small">Total Gross Wt</div>
          <h4 class="text-success mb-0">{{ number_format($purchaseSummary['total_gross_wt'], 3) }} g</h4>
        </div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body py-2">
          <div class="text-muted small">Total Purity Wt</div>
          <h4 class="text-warning mb-0">{{ number_format($purchaseSummary['total_purity_wt'], 3) }} g</h4>
        </div></div></div>
      </div>

      <div class="row">
        <div class="col-md-4">
          <div class="card mb-3">
            <div class="card-header"><strong>By Material Type</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr><th>Type</th><th class="text-end">Items</th><th class="text-end">Value</th></tr>
                </thead>
                <tbody>
                  @foreach($purchaseSummary['by_material'] as $type => $data)
                    <tr>
                      <td>{{ ucfirst($type) }}</td>
                      <td class="text-end">{{ $data['item_count'] }}</td>
                      <td class="text-end">{{ number_format($data['item_total'], 2) }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card mb-3">
            <div class="card-header"><strong>By Payment Method</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr><th>Method</th><th class="text-end">Count</th><th class="text-end">Amount AED</th></tr>
                </thead>
                <tbody>
                  @foreach($purchaseSummary['by_payment'] as $method => $data)
                    <tr>
                      <td>{{ ucwords(str_replace(['+','_'], [' + ',' '], $method)) }}</td>
                      <td class="text-end">{{ $data['count'] }}</td>
                      <td class="text-end">{{ number_format($data['amount_aed'], 2) }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card mb-3">
            <div class="card-header"><strong>Taxable vs Non-Taxable</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <tbody>
                  <tr>
                    <td>Taxable</td>
                    <td class="text-end">{{ $purchaseSummary['taxable_count'] }}</td>
                    <td class="text-end">{{ number_format($purchaseSummary['taxable_amount'], 2) }}</td>
                  </tr>
                  <tr>
                    <td>Non-Taxable</td>
                    <td class="text-end">{{ $purchaseSummary['non_taxable_count'] }}</td>
                    <td class="text-end">{{ number_format($purchaseSummary['non_taxable_amount'], 2) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      @else
        <div class="alert alert-info">Select filters and click Filter to view summary.</div>
      @endif
    </div>

    {{-- ================================================================== --}}
    {{-- 4. ITEM ANALYSIS                                                    --}}
    {{-- ================================================================== --}}
    <div id="IA" class="tab-pane fade {{ $tab==='IA'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.purchase') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="IA">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="vendor_id" class="form-control">
            <option value="">-- All Vendors --</option>
            @foreach($vendors as $v)
              <option value="{{ $v->id }}" {{ $vendorId==$v->id?'selected':'' }}>{{ $v->name }}</option>
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
              <th>Invoice</th><th>Date</th><th>Vendor</th><th>Item</th><th>Barcode</th>
              <th>Material</th><th class="text-end">Purity</th><th class="text-end">Gross Wt</th>
              <th class="text-end">Net Wt</th><th class="text-end">Purity Wt</th>
              <th class="text-end">995</th><th class="text-end">Making Rate</th>
              <th class="text-end">Making Val</th><th class="text-end">Material Val</th>
              <th class="text-end">Parts Total</th><th class="text-end">VAT%</th>
              <th class="text-end">VAT</th><th class="text-end">Item Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($itemAnalysis as $row)
              <tr>
                <td>{{ $row['invoice_no'] }}</td>
                <td>{{ $row['invoice_date'] }}</td>
                <td>{{ $row['vendor'] }}</td>
                <td>{{ $row['item_name'] }}</td>
                <td><code>{{ $row['barcode'] }}</code></td>
                <td>{{ $row['material_type'] }}</td>
                <td class="text-end">{{ $row['purity'] }}</td>
                <td class="text-end">{{ number_format($row['gross_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['net_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['purity_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['col_995'], 3) }}</td>
                <td class="text-end">{{ number_format($row['making_rate'], 2) }}</td>
                <td class="text-end">{{ number_format($row['making_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['material_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['parts_total'], 2) }}</td>
                <td class="text-end">{{ $row['vat_percent'] }}%</td>
                <td class="text-end">{{ number_format($row['vat_amount'], 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($row['item_total'], 2) }}</td>
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
      <form method="GET" action="{{ route('reports.purchase') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="PM">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-3">
          <select name="vendor_id" class="form-control">
            <option value="">-- All Vendors --</option>
            @foreach($vendors as $v)
              <option value="{{ $v->id }}" {{ $vendorId==$v->id?'selected':'' }}>{{ $v->name }}</option>
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
              <th>Invoice</th><th>Date</th><th>Vendor</th><th>Payment Method</th>
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
                <td>{{ $row['vendor'] }}</td>
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
              <td class="text-end">—</td>
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