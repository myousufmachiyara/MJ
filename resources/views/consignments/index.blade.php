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

    <div class="px-3 pt-3 pb-3">

      {{-- Direction filter buttons --}}
      <div class="mb-3">
        <div class="btn-group" role="group">
          <button type="button" class="btn btn-sm btn-primary active" id="filterAll">
            All <span class="badge bg-white text-primary ms-1">{{ $consignments->count() }}</span>
          </button>
          <button type="button" class="btn btn-sm btn-outline-success" id="filterIn">
            <i class="fas fa-arrow-down me-1"></i>Inbound
            <span class="badge bg-success ms-1">{{ $consignments->where('direction','inbound')->count() }}</span>
          </button>
          <button type="button" class="btn btn-sm btn-outline-primary" id="filterOut">
            <i class="fas fa-arrow-up me-1"></i>Outbound
            <span class="badge bg-primary ms-1">{{ $consignments->where('direction','outbound')->count() }}</span>
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped table-hover align-middle" id="consignmentTable">
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
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            {{--
              IMPORTANT: Do NOT put a colspan @empty row here.
              DataTables counts columns on every <td> in the tbody.
              A colspan="9" row is seen as 1 column → "Incorrect column count" error.
              Let DataTables render its own "No data available" message instead.
            --}}
            @foreach($consignments as $c)
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
              <tr data-direction="{{ $c->direction }}">
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
                    <form method="POST" action="{{ route('consignments.destroy', $c->id) }}" style="display:inline"
                          onsubmit="return confirm('Delete this consignment? This cannot be undone.')">
                      @csrf @method('DELETE')
                      <button class="btn btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<script>
$(document).ready(function () {

    var table = $('#consignmentTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: 8 }
        ],
        language: {
            emptyTable: '<div class="text-center text-muted py-3"><i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>No consignments found.</div>',
            zeroRecords: '<div class="text-center text-muted py-3"><i class="fas fa-filter fa-2x d-block mb-2 opacity-25"></i>No consignments match this filter.</div>',
        },
    });

    // Direction filter using DataTables custom search
    function applyFilter(direction) {
        $.fn.dataTable.ext.search = [];

        if (direction !== 'all') {
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'consignmentTable') return true;
                return $(table.row(dataIndex).node()).data('direction') === direction;
            });
        }

        table.draw();

        // Update button styles
        $('#filterAll').removeClass('btn-primary active').addClass('btn-outline-primary');
        $('#filterIn').removeClass('btn-success active').addClass('btn-outline-success');
        $('#filterOut').removeClass('btn-primary active').addClass('btn-outline-primary');

        if (direction === 'all') {
            $('#filterAll').removeClass('btn-outline-primary').addClass('btn-primary active');
        } else if (direction === 'inbound') {
            $('#filterIn').removeClass('btn-outline-success').addClass('btn-success active');
        } else {
            $('#filterOut').removeClass('btn-outline-primary').addClass('btn-primary active');
        }
    }

    $('#filterAll').on('click', function () { applyFilter('all'); });
    $('#filterIn').on('click',  function () { applyFilter('inbound'); });
    $('#filterOut').on('click', function () { applyFilter('outbound'); });
});
</script>
@endsection