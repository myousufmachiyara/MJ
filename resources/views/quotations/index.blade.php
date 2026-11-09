@extends('layouts.app')

@section('title', 'Quotations')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Quotations</h2>
        <a href="{{ route('quotations.create') }}" class="btn btn-primary">
          <i class="fas fa-plus"></i> New Quotation
        </a>
      </header>
      <div class="card-body">

        @if(session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- Search box — filters the table below via DataTables --}}
        <div class="row mb-3">
          <div class="col-md-4">
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
              <input type="text" id="quotationSearch" class="form-control"
                     placeholder="Search quotation no, customer, type…">
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped datatable" id="quotationsTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Quotation No</th>
                <th>Type</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Currency</th>
                <th>Net Amount</th>
                <th>Net AED</th>
                <th>VAT</th>
                <th>Grand Total</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($quotations as $quotation)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td><strong>{{ $quotation->quotation_no }}</strong></td>
                <td>
                  <span class="badge bg-{{ $quotation->is_taxable ? 'success' : 'secondary' }}">
                    {{ $quotation->is_taxable ? 'Tax' : 'Non-Tax' }}
                  </span>
                </td>
                <td>{{ $quotation->customer->name ?? '-' }}</td>
                <td>{{ \Carbon\Carbon::parse($quotation->quotation_date)->format('d M Y') }}</td>
                <td>{{ $quotation->currency }}</td>
                <td class="text-end">{{ number_format($quotation->net_amount, 2) }}</td>
                <td class="text-end">{{ number_format($quotation->net_amount_aed, 2) }}</td>
                <td class="text-center">
                  @if(($quotation->vat_amount ?? 0) > 0)
                    <span class="badge bg-warning text-dark">
                      {{ number_format($quotation->vat_percent, 1) }}%
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="text-end fw-bold {{ ($quotation->vat_amount ?? 0) > 0 ? 'text-success' : '' }}">
                  {{ number_format($quotation->grand_total ?? $quotation->net_amount_aed, 2) }}
                </td>
                <td>
                  <div class="btn-group btn-group-sm">

                    {{-- Edit --}}
                    <a href="{{ route('quotations.edit', $quotation->id) }}"
                       class="btn btn-outline-warning" title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>

                    {{-- Detailed print --}}
                    <a href="{{ route('quotations.print', $quotation->id) }}"
                       class="btn btn-outline-primary js-print-link" target="_blank"
                       title="Detailed Quotation">
                      <i class="fas fa-file-invoice"></i>
                    </a>

                    {{-- Simple print --}}
                    <a href="{{ route('quotations.print_simple', $quotation->id) }}"
                       class="btn btn-outline-success js-print-link" target="_blank"
                       title="Simple Quotation">
                      <i class="fas fa-receipt"></i>
                    </a>
                    <a href="{{ route('quotations.export_excel', $quotation->id) }}" class="btn btn-outline-success text-success js-print-link" data-download="1" title="Export to Excel"><i class="fas fa-file-excel"></i></a>

                    {{-- Delete --}}
                    <form method="POST" action="{{ route('quotations.destroy', $quotation->id) }}"
                          onsubmit="return confirm('Delete Quotation #{{ $quotation->quotation_no }}? This cannot be undone.')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger" title="Delete">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>

                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Legend for print buttons --}}
        <div class="mt-2 text-muted" style="font-size:.8rem;">
          <i class="fas fa-file-invoice text-primary me-1"></i> Detailed Quotation
          &nbsp;&nbsp;
          <i class="fas fa-receipt text-success me-1"></i> Simple Quotation
        </div>

      </div>
    </section>
  </div>
</div>

{{-- FEATURE: ask whether the printed quotation should include a product picture column --}}
<div id="printPictureModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:8px; max-width:420px; width:92%; padding:24px; box-shadow:0 10px 40px rgba(0,0,0,0.25);">
    <h5 class="mb-2"><i class="fas fa-image text-primary"></i> Show product pictures?</h5>
    <p class="text-muted mb-3">Add a <strong>Picture</strong> column with each item's photo to this print / export?</p>
    <div class="d-grid gap-2">
      <button type="button" id="printPicYes" class="btn btn-primary w-100 mb-2"><i class="fas fa-image"></i> Yes — with pictures</button>
      <button type="button" id="printPicNo" class="btn btn-outline-secondary w-100 mb-2">No — print as usual</button>
      <button type="button" id="printPicCancel" class="btn btn-link w-100">Cancel</button>
    </div>
  </div>
</div>

<script>
$(function () {
    var pendingPrintUrl = null;
    var pendingDownload = false;   // Excel links download in this tab instead of opening a new one
    var $modal = $('#printPictureModal');

    // Both print buttons (Detailed / Simple) ask first.
    $(document).on('click', '.js-print-link', function (e) {
        e.preventDefault();
        pendingPrintUrl = $(this).attr('href');
        pendingDownload = !!$(this).data('download');
        $modal.css('display', 'flex');
    });

    function openPrint(withImages) {
        if (!pendingPrintUrl) return;
        var url = pendingPrintUrl + (withImages ? (pendingPrintUrl.indexOf('?') > -1 ? '&' : '?') + 'images=1' : '');
        $modal.hide();
        pendingPrintUrl = null;
        if (pendingDownload) { window.location.href = url; } else { window.open(url, '_blank'); }
    }

    $('#printPicYes').on('click', function () { openPrint(true); });
    $('#printPicNo').on('click',  function () { openPrint(false); });
    $('#printPicCancel').on('click', function () { $modal.hide(); pendingPrintUrl = null; });
});
</script>

<script>
$(function () {
    // Reuse the existing DataTables instance if layouts.app already initialized
    // one for `.datatable` tables; otherwise initialize it here. Either way,
    // this never double-initializes (jQuery DataTables throws if you do).
    var $table = $('#quotationsTable');
    var dt = $.fn.DataTable.isDataTable($table) ? $table.DataTable() : $table.DataTable();

    // Hide the plugin's own default search box (if present) since we're
    // using our own input above the table — keeps only one search field visible.
    $table.closest('.dataTables_wrapper').find('.dataTables_filter').hide();

    $('#quotationSearch').on('keyup', function () {
        dt.search(this.value).draw();
    });
});
</script>
@endsection
