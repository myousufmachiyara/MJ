@extends('layouts.app')
@section('title', 'Inventory Reports')

@section('content')
<div class="tabs">
  <ul class="nav nav-tabs">
    <li class="nav-item">
      <a class="nav-link {{ $tab=='SIH'?'active':'' }}" href="{{ route('reports.inventory', ['tab'=>'SIH','from_date'=>$from,'to_date'=>$to]) }}">
        Stock In Hand
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='PI'?'active':'' }}" href="{{ route('reports.inventory', ['tab'=>'PI','from_date'=>$from,'to_date'=>$to]) }}">
        Purchased Items
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='SI'?'active':'' }}" href="{{ route('reports.inventory', ['tab'=>'SI','from_date'=>$from,'to_date'=>$to]) }}">
        Sold Items
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='WS'?'active':'' }}" href="{{ route('reports.inventory', ['tab'=>'WS','from_date'=>$from,'to_date'=>$to]) }}">
        Weight Summary
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='CI'?'active':'' }}" href="{{ route('reports.inventory', ['tab'=>'CI','from_date'=>$from,'to_date'=>$to]) }}">
        <i class="fas fa-handshake me-1"></i> Consignment Inventory
      </a>
    </li>
  </ul>

  <div class="tab-content mt-3">

    {{-- ================================================================== --}}
    {{-- 1. STOCK IN HAND                                                    --}}
    {{-- ================================================================== --}}
    <div id="SIH" class="tab-pane fade {{ $tab=='SIH'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.inventory') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="SIH">
        <div class="col-md-2">
          <label class="small text-muted">As of Date</label>
          <input type="date" name="to_date" class="form-control" value="{{ $to }}">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @php
        $totalItems    = $unsoldItems->count();
        $totalValue    = $unsoldItems->sum('item_total');
        $totalGrossWt  = $unsoldItems->sum('gross_weight');
        $totalPurityWt = $unsoldItems->sum('purity_weight');
      @endphp

      <div class="row mb-3">
        <div class="col-md-3"><div class="card text-center"><div class="card-body py-2">
          <div class="text-muted small">Items In Stock</div>
          <h4 class="text-primary mb-0">{{ $totalItems }}</h4>
        </div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body py-2">
          <div class="text-muted small">Total Stock Value (AED)</div>
          <h4 class="text-danger mb-0">{{ number_format($totalValue, 2) }}</h4>
        </div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body py-2">
          <div class="text-muted small">Total Gross Weight (g)</div>
          <h4 class="text-success mb-0">{{ number_format($totalGrossWt, 3) }}</h4>
        </div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body py-2">
          <div class="text-muted small">Total Purity Weight (g)</div>
          <h4 class="text-warning mb-0">{{ number_format($totalPurityWt, 3) }}</h4>
        </div></div></div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm datatable">
          <thead class="table-light">
            <tr>
              <th>Barcode</th><th>Item Name</th><th>Vendor</th><th>Invoice</th><th>Date</th>
              <th>Material</th><th class="text-end">Purity</th><th class="text-end">Gross Wt</th>
              <th class="text-end">Purity Wt</th><th class="text-end">Material Val</th>
              <th class="text-end">Making Val</th><th class="text-end">Item Total</th><th>Printed</th>
            </tr>
          </thead>
          <tbody>
            @forelse($unsoldItems as $row)
              <tr>
                <td><code>{{ $row['barcode'] }}</code></td>
                <td>{{ $row['item_name'] }}</td>
                <td>{{ $row['vendor'] }}</td>
                <td>{{ $row['purchase_invoice'] }}</td>
                <td>{{ $row['purchase_date'] }}</td>
                <td><span class="badge bg-{{ $row['material_type']==='Gold'?'warning text-dark':'info text-dark' }}">{{ $row['material_type'] }}</span></td>
                <td class="text-end">{{ $row['purity'] }}</td>
                <td class="text-end">{{ number_format($row['gross_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['purity_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['material_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['making_value'], 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($row['item_total'], 2) }}</td>
                <td>
                  @if($row['is_printed'])
                    <span class="badge bg-success">Yes</span>
                  @else
                    <span class="badge bg-secondary">No</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="13" class="text-center text-muted">No stock in hand.</td></tr>
            @endforelse
          </tbody>
          @if($unsoldItems->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="7">Totals</td>
              <td class="text-end">{{ number_format($totalGrossWt, 3) }}</td>
              <td class="text-end">{{ number_format($totalPurityWt, 3) }}</td>
              <td class="text-end">{{ number_format($unsoldItems->sum('material_value'), 2) }}</td>
              <td class="text-end">{{ number_format($unsoldItems->sum('making_value'), 2) }}</td>
              <td class="text-end">{{ number_format($totalValue, 2) }}</td>
              <td></td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 2. PURCHASED ITEMS                                                  --}}
    {{-- ================================================================== --}}
    <div id="PI" class="tab-pane fade {{ $tab=='PI'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.inventory') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="PI">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm datatable">
          <thead class="table-light">
            <tr>
              <th>Barcode</th><th>Item Name</th><th>Vendor</th><th>Invoice</th><th>Date</th>
              <th>Material</th><th class="text-end">Purity</th><th class="text-end">Gross Wt</th>
              <th class="text-end">Purity Wt</th><th class="text-end">Material Val</th>
              <th class="text-end">Making Val</th><th class="text-end">VAT</th>
              <th class="text-end">Item Total</th><th>Currency</th>
            </tr>
          </thead>
          <tbody>
            @forelse($purchasedItems as $row)
              <tr>
                <td><code>{{ $row['barcode'] }}</code></td>
                <td>{{ $row['item_name'] }}</td>
                <td>{{ $row['vendor'] }}</td>
                <td>{{ $row['purchase_invoice'] }}</td>
                <td>{{ $row['purchase_date'] }}</td>
                <td><span class="badge bg-{{ $row['material_type']==='Gold'?'warning text-dark':'info text-dark' }}">{{ $row['material_type'] }}</span></td>
                <td class="text-end">{{ $row['purity'] }}</td>
                <td class="text-end">{{ number_format($row['gross_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['purity_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['material_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['making_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['vat_amount'], 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($row['item_total'], 2) }}</td>
                <td>{{ $row['currency'] }}</td>
              </tr>
            @empty
              <tr><td colspan="14" class="text-center text-muted">No purchased items found.</td></tr>
            @endforelse
          </tbody>
          @if($purchasedItems->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="8">Totals</td>
              <td class="text-end">{{ number_format($purchasedItems->sum('purity_weight'), 3) }}</td>
              <td class="text-end">{{ number_format($purchasedItems->sum('material_value'), 2) }}</td>
              <td class="text-end">{{ number_format($purchasedItems->sum('making_value'), 2) }}</td>
              <td class="text-end">{{ number_format($purchasedItems->sum('vat_amount'), 2) }}</td>
              <td class="text-end">{{ number_format($purchasedItems->sum('item_total'), 2) }}</td>
              <td></td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 3. SOLD ITEMS                                                       --}}
    {{-- ================================================================== --}}
    <div id="SI" class="tab-pane fade {{ $tab=='SI'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.inventory') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="SI">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm datatable">
          <thead class="table-light">
            <tr>
              <th>Barcode</th><th>Item Name</th><th>Customer</th><th>Invoice</th><th>Date</th>
              <th>Material</th><th class="text-end">Purity</th><th class="text-end">Gross Wt</th>
              <th class="text-end">Purity Wt</th><th class="text-end">Material Val</th>
              <th class="text-end">Making Val</th><th class="text-end">Sale Total</th>
              <th class="text-end">Cost</th><th class="text-end">Profit</th><th class="text-end">Margin%</th>
            </tr>
          </thead>
          <tbody>
            @forelse($soldItems as $row)
              @php $marginColor = $row['margin'] >= 0 ? 'text-success' : 'text-danger'; @endphp
              <tr>
                <td><code>{{ $row['barcode'] }}</code></td>
                <td>{{ $row['item_name'] }}</td>
                <td>{{ $row['customer'] }}</td>
                <td>{{ $row['sale_invoice'] }}</td>
                <td>{{ $row['sale_date'] }}</td>
                <td><span class="badge bg-{{ $row['material_type']==='Gold'?'warning text-dark':'info text-dark' }}">{{ $row['material_type'] }}</span></td>
                <td class="text-end">{{ $row['purity'] }}</td>
                <td class="text-end">{{ number_format($row['gross_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['purity_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['material_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['making_value'], 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($row['item_total'], 2) }}</td>
                <td class="text-end">{{ number_format($row['cost'], 2) }}</td>
                <td class="text-end {{ $marginColor }} fw-bold">{{ number_format($row['profit'], 2) }}</td>
                <td class="text-end {{ $marginColor }}">{{ $row['margin'] }}%</td>
              </tr>
            @empty
              <tr><td colspan="15" class="text-center text-muted">No sold items found.</td></tr>
            @endforelse
          </tbody>
          @if($soldItems->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="11">Totals</td>
              <td class="text-end">{{ number_format($soldItems->sum('item_total'), 2) }}</td>
              <td class="text-end">{{ number_format($soldItems->sum('cost'), 2) }}</td>
              <td class="text-end {{ $soldItems->sum('profit') >= 0 ? 'text-success' : 'text-danger' }}">
                {{ number_format($soldItems->sum('profit'), 2) }}
              </td>
              <td></td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ================================================================== --}}
    {{-- 4. WEIGHT SUMMARY                                                   --}}
    {{-- ================================================================== --}}
    <div id="WS" class="tab-pane fade {{ $tab=='WS'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.inventory') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="WS">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @if(!empty($weightSummary))
      <div class="row">
        <div class="col-md-6">
          <div class="card mb-3">
            <div class="card-header bg-warning text-dark"><strong>Gold Movement</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr><th>Period</th><th class="text-end">Items</th><th class="text-end">Gross Wt</th><th class="text-end">Purity Wt</th><th class="text-end">Value (AED)</th></tr>
                </thead>
                <tbody>
                  <tr><td>Purchased</td><td class="text-end">{{ $weightSummary['gold_purchased_count'] }}</td><td class="text-end">{{ number_format($weightSummary['gold_purchased_gross'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['gold_purchased_purity'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['gold_purchased_value'], 2) }}</td></tr>
                  <tr><td>Sold</td><td class="text-end">{{ $weightSummary['gold_sold_count'] }}</td><td class="text-end">{{ number_format($weightSummary['gold_sold_gross'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['gold_sold_purity'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['gold_sold_value'], 2) }}</td></tr>
                  <tr class="table-warning fw-bold"><td>In Hand</td><td class="text-end">{{ $weightSummary['gold_inhand_count'] }}</td><td class="text-end">{{ number_format($weightSummary['gold_inhand_gross'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['gold_inhand_purity'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['gold_inhand_value'], 2) }}</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card mb-3">
            <div class="card-header bg-info text-dark"><strong>Diamond Movement</strong></div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr><th>Period</th><th class="text-end">Items</th><th class="text-end">Gross Wt</th><th class="text-end">Purity Wt</th><th class="text-end">Value (AED)</th></tr>
                </thead>
                <tbody>
                  <tr><td>Purchased</td><td class="text-end">{{ $weightSummary['diamond_purchased_count'] }}</td><td class="text-end">{{ number_format($weightSummary['diamond_purchased_gross'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['diamond_purchased_purity'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['diamond_purchased_value'], 2) }}</td></tr>
                  <tr><td>Sold</td><td class="text-end">{{ $weightSummary['diamond_sold_count'] }}</td><td class="text-end">{{ number_format($weightSummary['diamond_sold_gross'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['diamond_sold_purity'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['diamond_sold_value'], 2) }}</td></tr>
                  <tr class="table-info fw-bold"><td>In Hand</td><td class="text-end">{{ $weightSummary['diamond_inhand_count'] }}</td><td class="text-end">{{ number_format($weightSummary['diamond_inhand_gross'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['diamond_inhand_purity'], 3) }}</td><td class="text-end">{{ number_format($weightSummary['diamond_inhand_value'], 2) }}</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div class="row text-center">
                <div class="col-md-4">
                  <div class="text-muted small">Total Purchased Value (AED)</div>
                  <h4 class="text-primary">{{ number_format($weightSummary['total_purchased_value'], 2) }}</h4>
                </div>
                <div class="col-md-4">
                  <div class="text-muted small">Total Sold Value (AED)</div>
                  <h4 class="text-success">{{ number_format($weightSummary['total_sold_value'], 2) }}</h4>
                </div>
                <div class="col-md-4">
                  <div class="text-muted small">Total In Hand Value (AED)</div>
                  <h4 class="text-danger">{{ number_format($weightSummary['total_inhand_value'], 2) }}</h4>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      @else
        <div class="alert alert-info">Select a date range and click Filter to see weight summary.</div>
      @endif
    </div>

    {{-- ================================================================== --}}
    {{-- 5. CONSIGNMENT INVENTORY                                            --}}
    {{-- ================================================================== --}}
    <div id="CI" class="tab-pane fade {{ $tab=='CI'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.inventory') }}" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="CI">
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
      </form>

      @if($tab === 'CI')
      @php
        $ci          = $consignmentInventory;
        $ciInStock   = $ci->where('item_status','in_stock');
        $ciSold      = $ci->where('item_status','sold');
        $ciReturned  = $ci->where('item_status','returned');
        $ciInbound   = $ci->where('direction','Inbound');
        $ciOutbound  = $ci->where('direction','Outbound');
      @endphp

      {{-- Summary Cards --}}
      <div class="row mb-3 g-2">
        <div class="col-6 col-md-2"><div class="card text-center border-0 bg-light"><div class="card-body py-2">
          <div class="fs-4 fw-bold">{{ $ci->count() }}</div><div class="small text-muted">Total Items</div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card text-center border-0" style="background:#fff3cd"><div class="card-body py-2">
          <div class="fs-4 fw-bold text-warning">{{ $ciInStock->count() }}</div>
          <div class="small text-muted">In Stock</div>
          <div class="small text-warning fw-bold">AED {{ number_format($ciInStock->sum('agreed_value'), 0) }}</div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card text-center border-0" style="background:#d4edda"><div class="card-body py-2">
          <div class="fs-4 fw-bold text-success">{{ $ciSold->count() }}</div>
          <div class="small text-muted">Sold</div>
          <div class="small text-success fw-bold">AED {{ number_format($ciSold->sum('agreed_value'), 0) }}</div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card text-center border-0" style="background:#e2e3e5"><div class="card-body py-2">
          <div class="fs-4 fw-bold text-secondary">{{ $ciReturned->count() }}</div>
          <div class="small text-muted">Returned</div>
          <div class="small text-secondary fw-bold">AED {{ number_format($ciReturned->sum('agreed_value'), 0) }}</div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card text-center border-0" style="background:#cff4fc"><div class="card-body py-2">
          <div class="fs-4 fw-bold text-info">{{ $ciInbound->count() }}</div>
          <div class="small text-muted">Inbound Items</div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card text-center border-0" style="background:#cfe2ff"><div class="card-body py-2">
          <div class="fs-4 fw-bold text-primary">{{ $ciOutbound->count() }}</div>
          <div class="small text-muted">Outbound Items</div>
        </div></div></div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm datatable">
          <thead class="table-light">
            <tr>
              <th>Consignment No</th>
              <th>Direction</th>
              <th>Partner</th>
              <th>Start Date</th>
              <th>Barcode</th>
              <th>Item Name</th>
              <th>Material</th>
              <th class="text-end">Purity</th>
              <th class="text-end">Gross Wt</th>
              <th class="text-end">Purity Wt</th>
              <th class="text-end">Making Val</th>
              <th class="text-end">Material Val</th>
              <th class="text-end">Parts Val</th>
              <th class="text-end">Agreed Val</th>
              <th class="text-center">Status</th>
              <th>Settled Date</th>
            </tr>
          </thead>
          <tbody>
            @forelse($consignmentInventory as $row)
              @php
                $rowClass = match($row['item_status']) {
                  'sold'     => 'table-success',
                  'returned' => 'table-warning',
                  default    => '',
                };
              @endphp
              <tr class="{{ $rowClass }}">
                <td><strong class="text-primary">{{ $row['consignment_no'] }}</strong></td>
                <td>
                  @if($row['direction'] === 'Inbound')
                    <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>Inbound</span>
                  @else
                    <span class="badge bg-primary"><i class="fas fa-arrow-up me-1"></i>Outbound</span>
                  @endif
                </td>
                <td>{{ $row['partner'] }}</td>
                <td>{{ $row['start_date'] }}</td>
                <td>
                  @if($row['barcode_number'] !== '—')
                    <code style="font-size:.75rem">{{ $row['barcode_number'] }}</code>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>{{ $row['item_name'] }}</td>
                <td><span class="badge bg-{{ $row['material_type']==='Gold'?'warning text-dark':'info text-dark' }}">{{ $row['material_type'] }}</span></td>
                <td class="text-end">{{ $row['purity'] }}</td>
                <td class="text-end">{{ number_format($row['gross_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['purity_weight'], 3) }}</td>
                <td class="text-end">{{ number_format($row['making_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['material_value'], 2) }}</td>
                <td class="text-end">{{ number_format($row['parts_total'], 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($row['agreed_value'], 2) }}</td>
                <td class="text-center">
                  @if($row['item_status'] === 'sold')
                    <span class="badge bg-success">Sold</span>
                  @elseif($row['item_status'] === 'returned')
                    <span class="badge bg-secondary">Returned</span>
                  @else
                    <span class="badge bg-warning text-dark">In Stock</span>
                  @endif
                </td>
                <td class="small text-muted">{{ $row['settled_date'] }}</td>
              </tr>
            @empty
              <tr><td colspan="16" class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>
                No consignment items found for this period.
              </td></tr>
            @endforelse
          </tbody>
          @if($consignmentInventory->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="8">Totals ({{ $consignmentInventory->count() }} items)</td>
              <td class="text-end">{{ number_format($consignmentInventory->sum('gross_weight'), 3) }}</td>
              <td class="text-end">{{ number_format($consignmentInventory->sum('purity_weight'), 3) }}</td>
              <td class="text-end">{{ number_format($consignmentInventory->sum('making_value'), 2) }}</td>
              <td class="text-end">{{ number_format($consignmentInventory->sum('material_value'), 2) }}</td>
              <td class="text-end">{{ number_format($consignmentInventory->sum('parts_total'), 2) }}</td>
              <td class="text-end text-danger">{{ number_format($consignmentInventory->sum('agreed_value'), 2) }}</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
      @endif
    </div>

  </div>{{-- end tab-content --}}
</div>
@endsection