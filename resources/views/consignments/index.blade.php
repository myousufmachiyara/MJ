@extends('layouts.app')
@section('title', 'Consignments')

@section('content')
<div class="card">
  <div class="card-body p-0">

    <div class="d-flex justify-content-between align-items-center border-bottom px-3 py-2">
      <h5 class="mb-0"><i class="fas fa-handshake me-2 text-primary"></i>Consignments</h5>
      <a href="{{ route('consignments.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> New Consignment
      </a>
    </div>

    @if(session('success'))
      <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
    @endif

    <div class="px-3 pt-3">

      {{-- Direction filter tabs --}}
      <ul class="nav nav-tabs mb-0" id="csgTabs" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" data-bs-toggle="tab" href="#tabAll" role="tab">
            All <span class="badge bg-secondary ms-1">{{ $consignments->count() }}</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabIn" role="tab">
            <i class="fas fa-arrow-down text-success me-1"></i>Inbound
            <span class="badge bg-success ms-1">{{ $consignments->where('direction','inbound')->count() }}</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabOut" role="tab">
            <i class="fas fa-arrow-up text-primary me-1"></i>Outbound
            <span class="badge bg-primary ms-1">{{ $consignments->where('direction','outbound')->count() }}</span>
          </a>
        </li>
      </ul>

      <div class="tab-content border border-top-0 rounded-bottom p-3">

        @php
          $tabs = [
            'tabAll' => $consignments,
            'tabIn'  => $consignments->where('direction','inbound'),
            'tabOut' => $consignments->where('direction','outbound'),
          ];
        @endphp

        @foreach($tabs as $tabId => $rows)
        <div class="tab-pane fade {{ $tabId === 'tabAll' ? 'show active' : '' }}" id="{{ $tabId }}" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-bordered table-striped consignmentTable">
              <thead class="table-light">
                <tr>
                  <th>Consignment No</th>
                  <th>Direction</th>
                  <th>Partner</th>
                  <th>Start</th>
                  <th>End / Duration</th>
                  <th class="text-center">Items</th>
                  <th class="text-center">Sold</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($rows as $c)
                  @php
                    $badge = match($c->status) {
                      'active'            => 'success',
                      'partially_settled' => 'warning',
                      'settled'           => 'primary',
                      'returned'          => 'secondary',
                      'expired'           => 'danger',
                      default             => 'secondary',
                    };
                  @endphp
                  <tr>
                    <td><strong class="text-primary">{{ $c->consignment_no }}</strong></td>
                    <td>
                      @if($c->direction === 'inbound')
                        <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>Inbound</span>
                      @else
                        <span class="badge bg-primary"><i class="fas fa-arrow-up me-1"></i>Outbound</span>
                      @endif
                    </td>
                    <td>{{ $c->partner->name ?? '-' }}</td>
                    <td>{{ $c->start_date->format('d-M-Y') }}</td>
                    <td>
                    {!! $c->end_date 
                            ? $c->end_date->format('d-M-Y') 
                            : '<span class="text-muted fst-italic">Open</span>' !!}
                    @if($c->duration_label)
                        <small class="text-muted d-block">{{ $c->duration_label }}</small>
                    @endif
                    </td>
                    <td class="text-center">{{ $c->items_count }}</td>
                    <td class="text-center">
                      <span class="{{ $c->sold_count > 0 ? 'fw-bold text-success' : 'text-muted' }}">
                        {{ $c->sold_count }}
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-{{ $badge }}">
                        {{ ucwords(str_replace('_', ' ', $c->status)) }}
                      </span>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <a href="{{ route('consignments.show', $c->id) }}" class="btn btn-outline-info" title="View">
                          <i class="fas fa-eye"></i>
                        </a>
                        @if(in_array($c->status, ['active','partially_settled']))
                          <a href="{{ route('consignments.edit', $c->id) }}" class="btn btn-outline-warning" title="Edit">
                            <i class="fas fa-edit"></i>
                          </a>
                        @endif
                        <a href="{{ route('consignments.print', $c->id) }}" class="btn btn-outline-secondary" target="_blank" title="Print Document">
                          <i class="fas fa-file-pdf"></i>
                        </a>
                        @if($c->direction === 'inbound')
                          <a href="{{ route('consignments.barcodes', $c->id) }}" class="btn btn-outline-dark" target="_blank" title="Print Barcodes">
                            <i class="fas fa-barcode"></i>
                          </a>
                        @endif
                        <form method="POST" action="{{ route('consignments.destroy', $c->id) }}"
                              onsubmit="return confirm('Delete this consignment? This cannot be undone.')">
                          @csrf @method('DELETE')
                          <button class="btn btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                      <i class="fas fa-inbox fa-3x d-block mb-2 opacity-25"></i>No consignments found.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        @endforeach

      </div>
    </div>

  </div>
</div>

<script>
$(document).ready(function() {
    $('.consignmentTable').DataTable({
        pageLength: 50,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: 8 } // correct index for Actions column
        ],
    });
});
</script>
@endsection