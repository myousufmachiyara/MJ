@extends('layouts.app')
@section('title', 'Consignment — ' . $consignment->consignment_no)

@section('content')
@php
  $direction     = $consignment->direction;
  $isInbound     = $direction === 'inbound';
  $totalItems    = $consignment->items->count();
  $inStockItems  = $consignment->items->where('item_status', 'in_stock');
  $soldItems     = $consignment->items->where('item_status', 'sold');
  $returnedItems = $consignment->items->where('item_status', 'returned');
  $inStockCount  = $inStockItems->count();
  $soldCount     = $soldItems->count();
  $returnedCount = $returnedItems->count();
  $totalAgreed   = $consignment->items->sum('agreed_value');
  $pendingValue  = $inStockItems->sum('agreed_value');
  $soldValue     = $soldItems->sum('agreed_value');
  $returnedValue = $returnedItems->sum('agreed_value');
  $canEdit       = in_array($consignment->status, ['active', 'partially_settled']);
  $badgeColor    = match($consignment->status) {
    'active'            => 'success',
    'partially_settled' => 'warning',
    'settled'           => 'primary',
    'returned'          => 'secondary',
    'expired'           => 'danger',
    default             => 'secondary',
  };
@endphp

<div class="row"><div class="col">
<section class="card">

  {{-- ===================== HEADER ===================== --}}
  <header class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="card-title mb-0">
      <i class="fas fa-handshake me-2 text-primary"></i>
      {{ $consignment->consignment_no }}
      <span class="badge bg-{{ $badgeColor }} ms-1">
        {{ ucwords(str_replace('_', ' ', $consignment->status)) }}
      </span>
      @if($isInbound)
        <span class="badge bg-success ms-1">
          <i class="fas fa-arrow-down me-1"></i>Inbound
        </span>
      @else
        <span class="badge bg-primary ms-1">
          <i class="fas fa-arrow-up me-1"></i>Outbound
        </span>
      @endif
    </h2>
    <div class="d-flex gap-2 flex-wrap">
      @if($canEdit)
        <a href="{{ route('consignments.edit', $consignment->id) }}"
           class="btn btn-sm btn-warning">
          <i class="fas fa-edit me-1"></i> Edit
        </a>
      @endif
      <a href="{{ route('consignments.print', $consignment->id) }}"
         class="btn btn-sm btn-outline-secondary" target="_blank">
        <i class="fas fa-file-pdf me-1"></i> Print Doc
      </a>
      @if($isInbound)
        <a href="{{ route('consignments.print_barcodes', $consignment->id) }}"
           class="btn btn-sm btn-outline-dark" target="_blank">
          <i class="fas fa-barcode me-1"></i> Print Barcodes
        </a>
      @endif
      <a href="{{ route('consignments.index') }}"
         class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
      </a>
    </div>
  </header>

  @if(session('success'))
    <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
  @endif

  <div class="card-body">

    {{-- ===================== META ROW ===================== --}}
    <div class="row mb-3 g-3">
      <div class="col-md-3">
        <div class="text-muted small mb-1">Partner</div>
        <div class="fw-bold">{{ $consignment->partner->name ?? '—' }}</div>
        <div class="small text-muted">{{ $consignment->partner->contact_no ?? '' }}</div>
      </div>
      <div class="col-md-2">
        <div class="text-muted small mb-1">Start Date</div>
        <div>{{ $consignment->start_date->format('d-M-Y') }}</div>
      </div>
      <div class="col-md-2">
        <div class="text-muted small mb-1">End Date</div>
        <div>{{ $consignment->end_date ? $consignment->end_date->format('d-M-Y') : '—' }}</div>
        @if($consignment->duration_label)
          <div class="small text-muted">{{ $consignment->duration_label }}</div>
        @endif
      </div>
      <div class="col-md-2">
        <div class="text-muted small mb-1">Created By</div>
        <div>{{ $consignment->createdBy->name ?? '—' }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small mb-1">Remarks</div>
        <div>{{ $consignment->remarks ?: '—' }}</div>
      </div>
    </div>

    {{-- ===================== SUMMARY CARDS ===================== --}}
    <div class="row mb-3 g-2">
      <div class="col-6 col-md-2">
        <div class="card border-0 bg-light text-center py-2">
          <div class="fs-3 fw-bold">{{ $totalItems }}</div>
          <div class="small text-muted">Total Items</div>
          <div class="small fw-bold">AED {{ number_format($totalAgreed, 0) }}</div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card border-0 text-center py-2" style="background:#fff3cd">
          <div class="fs-3 fw-bold text-warning">{{ $inStockCount }}</div>
          <div class="small text-muted">Pending</div>
          <div class="small text-warning fw-bold">AED {{ number_format($pendingValue, 0) }}</div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card border-0 text-center py-2" style="background:#d4edda">
          <div class="fs-3 fw-bold text-success">{{ $soldCount }}</div>
          <div class="small text-muted">Sold</div>
          <div class="small text-success fw-bold">AED {{ number_format($soldValue, 0) }}</div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card border-0 text-center py-2" style="background:#e2e3e5">
          <div class="fs-3 fw-bold text-secondary">{{ $returnedCount }}</div>
          <div class="small text-muted">Returned</div>
          <div class="small text-secondary fw-bold">AED {{ number_format($returnedValue, 0) }}</div>
        </div>
      </div>
      <div class="col-12 col-md-4 d-flex align-items-center">
        @if($isInbound)
          <div class="alert alert-info mb-0 py-2 px-3 w-100 small">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Inbound:</strong> Partner gave us these items.
            Sold = scanned in Sale Invoice.
            <strong>Return to Partner</strong> = we send unsold items back.
          </div>
        @else
          <div class="alert alert-primary mb-0 py-2 px-3 w-100 small">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Outbound:</strong> We sent our items to partner.
            <strong>Mark Returned</strong> = partner sends item back unsold.
            Sold = settle via Sale Invoice.
          </div>
        @endif
      </div>
    </div>

    {{-- ===================== BULK RETURN ===================== --}}
    @if($inStockCount > 0 && $canEdit)
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">
        Items
        <span class="badge bg-secondary">{{ $totalItems }}</span>
      </h6>
      <form method="POST"
            action="{{ route('consignments.return-all', $consignment->id) }}"
            onsubmit="return confirm('Mark ALL {{ $inStockCount }} pending item(s) as returned?\n\n{{ $isInbound ? 'This means you are sending them back to the partner.' : 'This means the partner returned them to you.' }}\n\nThis cannot be undone.')">
        @csrf
        <button class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-undo me-1"></i>
          {{ $isInbound ? 'Return All to Partner' : 'Mark All Returned' }}
          <span class="badge bg-secondary ms-1">{{ $inStockCount }}</span>
        </button>
      </form>
    </div>
    @else
      <h6 class="mb-2">Items <span class="badge bg-secondary">{{ $totalItems }}</span></h6>
    @endif

    {{-- ===================== ITEMS TABLE ===================== --}}
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th width="4%">#</th>
            @if($isInbound)
              <th>Barcode</th>
            @endif
            <th>Item Name</th>
            <th>Description</th>
            <th class="text-center">Purity</th>
            <th class="text-center">Gross Wt</th>
            <th class="text-center">Making Val</th>
            <th class="text-center">Material Val</th>
            <th class="text-center">Agreed Val</th>
            <th class="text-center">Status</th>
            <th class="text-center" width="14%">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($consignment->items as $index => $item)
            @php
              $rowClass = match($item->item_status) {
                'sold'     => 'table-success',
                'returned' => 'table-warning',
                default    => '',
              };
            @endphp

            {{-- Main item row --}}
            <tr class="{{ $rowClass }}">
              <td class="text-center text-muted small">{{ $index + 1 }}</td>

              @if($isInbound)
                <td>
                  @if($item->barcode_number)
                    <code style="font-size:.75rem">{{ $item->barcode_number }}</code>
                    @if($item->is_printed)
                      <i class="fas fa-check-circle text-success ms-1" title="Printed"></i>
                    @endif
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              @endif

              <td>
                <strong>{{ $item->item_name ?? '—' }}</strong>
                @if($item->parts->count() > 0)
                  <span class="badge bg-info ms-1" style="font-size:.65rem"
                        title="{{ $item->parts->count() }} part(s)">
                    <i class="fas fa-gem"></i> {{ $item->parts->count() }}
                  </span>
                @endif
              </td>
              <td class="text-muted small">{{ $item->item_description ?? '—' }}</td>
              <td class="text-center">{{ number_format($item->purity, 3) }}</td>
              <td class="text-center">{{ number_format($item->gross_weight, 3) }}g</td>
              <td class="text-center">{{ number_format($item->making_value, 2) }}</td>
              <td class="text-center">{{ number_format($item->material_value, 2) }}</td>
              <td class="text-center fw-bold">
                AED {{ number_format($item->agreed_value, 2) }}
              </td>
              <td class="text-center">
                @if($item->item_status === 'sold')
                  <span class="badge bg-success">Sold</span>
                  @if($item->settledBySaleInvoice)
                    <div class="small mt-1">
                      <a href="{{ route('sale_invoices.show', $item->settled_by_sale_invoice_id) }}"
                         class="text-decoration-none">
                        {{ $item->settledBySaleInvoice->invoice_no }}
                      </a>
                    </div>
                  @endif
                @elseif($item->item_status === 'returned')
                  <span class="badge bg-secondary">Returned</span>
                  @if($item->settled_date)
                    <div class="small text-muted mt-1">
                      {{ \Carbon\Carbon::parse($item->settled_date)->format('d-M-Y') }}
                    </div>
                  @endif
                @else
                  <span class="badge bg-warning text-dark">In Stock</span>
                @endif
              </td>
              <td class="text-center">
                @if($item->item_status === 'in_stock' && $canEdit)
                  <form method="POST"
                        action="{{ route('consignments.return-item', [$consignment->id, $item->id]) }}"
                        onsubmit="return confirm('{{ $isInbound
                            ? 'Return \'' . addslashes($item->item_name ?? 'item') . '\' back to partner?'
                            : 'Mark \'' . addslashes($item->item_name ?? 'item') . '\' as returned by partner?' }}')">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm" style="font-size:.75rem">
                      <i class="fas fa-undo me-1"></i>
                      {{ $isInbound ? 'Return to Partner' : 'Mark Returned' }}
                    </button>
                  </form>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>

            {{-- Parts sub-row --}}
            @if($item->parts->count() > 0)
              <tr class="{{ $rowClass }}" style="font-size:.8rem;opacity:.85">
                <td></td>
                @if($isInbound)<td></td>@endif
                <td colspan="9" class="ps-4 text-muted">
                  <i class="fas fa-gem me-1 text-info"></i>
                  <strong>Parts:</strong>
                  @foreach($item->parts as $part)
                    <span class="me-3">
                      {{ $part->item_name ?? 'Part' }}
                      @if($part->qty) &nbsp;{{ number_format($part->qty, 3) }} Ct @endif
                      @if($part->rate) @ AED {{ number_format($part->rate, 2) }} @endif
                      @if($part->stone_qty) | Stone {{ number_format($part->stone_qty, 2) }} @endif
                      @if($part->stone_rate) @ {{ number_format($part->stone_rate, 2) }} @endif
                      = <strong>AED {{ number_format($part->total, 2) }}</strong>
                    </span>
                  @endforeach
                </td>
              </tr>
            @endif

          @empty
            <tr>
              <td colspan="{{ $isInbound ? 11 : 10 }}" class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>
                No items in this consignment.
              </td>
            </tr>
          @endforelse
        </tbody>

        {{-- ── Totals footer ── --}}
        @if($totalItems > 0)
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="{{ $isInbound ? 5 : 4 }}" class="text-end">TOTAL</td>
            <td class="text-center">
              {{ number_format($consignment->items->sum('gross_weight'), 3) }}g
            </td>
            <td class="text-center">
              {{ number_format($consignment->items->sum('making_value'), 2) }}
            </td>
            <td class="text-center">
              {{ number_format($consignment->items->sum('material_value'), 2) }}
            </td>
            <td class="text-center text-danger">
              AED {{ number_format($totalAgreed, 2) }}
            </td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>

  </div>{{-- end card-body --}}
</section>
</div></div>
@endsection