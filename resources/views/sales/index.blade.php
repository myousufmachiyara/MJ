@extends('layouts.app')

@section('title', 'Sale Invoices')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Sale Invoices</h2>
        <a href="{{ route('sale_invoices.create') }}" class="btn btn-primary">
          <i class="fas fa-plus"></i> New Sale Invoice
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
              <input type="text" id="saleInvoiceSearch" class="form-control"
                     placeholder="Search invoice no, customer, type, payment…">
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped datatable" id="saleInvoicesTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Invoice No</th>
                <th>Type</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Currency</th>
                <th>Net Amount</th>
                <th>Net AED</th>
                <th>VAT</th>
                <th>Grand Total</th>
                <th>Payment</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($invoices as $invoice)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td><strong>{{ $invoice->invoice_no }}</strong></td>
                <td>
                  <span class="badge bg-{{ $invoice->is_taxable ? 'success' : 'secondary' }}">
                    {{ $invoice->is_taxable ? 'Tax' : 'Non-Tax' }}
                  </span>
                </td>
                <td>{{ $invoice->customer->name ?? '-' }}</td>
                <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                <td>{{ $invoice->currency }}</td>
                <td class="text-end">{{ number_format($invoice->net_amount, 2) }}</td>
                <td class="text-end">{{ number_format($invoice->net_amount_aed, 2) }}</td>
                <td class="text-center">
                  @if(($invoice->invoice_vat_amount ?? 0) > 0)
                    <span class="badge bg-warning text-dark">
                      {{ number_format($invoice->invoice_vat_percent, 1) }}%
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="text-end fw-bold {{ ($invoice->invoice_vat_amount ?? 0) > 0 ? 'text-success' : '' }}">
                  {{ number_format($invoice->grand_total ?? $invoice->net_amount_aed, 2) }}
                </td>
                <td>
                  <span class="badge bg-info text-dark" style="font-size:.75rem">
                    {{ ucwords(str_replace(['+','_'], [' + ', ' '], $invoice->payment_method)) }}
                  </span>
                </td>
                <td>
                  <div class="btn-group btn-group-sm">

                    {{-- Edit --}}
                    <a href="{{ route('sale_invoices.edit', $invoice->id) }}"
                       class="btn btn-outline-warning" title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>

                    {{-- Detailed print (B2B) --}}
                    <a href="{{ route('sale_invoices.print', $invoice->id) }}"
                       class="btn btn-outline-primary js-print-link" target="_blank"
                       title="Detailed Invoice (B2B)">
                      <i class="fas fa-file-invoice"></i>
                    </a>

                    {{-- Simple print (Walk-in / Retail) --}}
                    <a href="{{ route('sale_invoices.print_simple', $invoice->id) }}"
                       class="btn btn-outline-success js-print-link" target="_blank"
                       title="Simple Receipt (Walk-in Customer)">
                      <i class="fas fa-receipt"></i>
                    </a>
                    <a href="{{ route('sale_invoices.export_excel', $invoice->id) }}" class="btn btn-outline-success text-success js-print-link" data-download="1" title="Export to Excel"><i class="fas fa-file-excel"></i></a>

                    {{-- Delete --}}
                    <form method="POST" action="{{ route('sale_invoices.destroy', $invoice->id) }}"
                          onsubmit="return confirm('Delete Invoice #{{ $invoice->invoice_no }}? This cannot be undone.')">
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
          <i class="fas fa-file-invoice text-primary me-1"></i> Detailed Invoice (B2B / Wholesale)
          &nbsp;&nbsp;
          <i class="fas fa-receipt text-success me-1"></i> Simple Receipt (Walk-in / Retail)
        </div>

      </div>
    </section>
  </div>
</div>

{{-- FEATURE: ask whether the printed invoice / receipt should include a product picture column --}}
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

    // Both print buttons (Detailed B2B / Simple B2C receipt) ask first.
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
    var $table = $('#saleInvoicesTable');
    var dt = $.fn.DataTable.isDataTable($table) ? $table.DataTable() : $table.DataTable();

    // Hide the plugin's own default search box (if present) since we're
    // using our own input above the table — keeps only one search field visible.
    $table.closest('.dataTables_wrapper').find('.dataTables_filter').hide();

    $('#saleInvoiceSearch').on('keyup', function () {
        dt.search(this.value).draw();
    });
});
</script>
@endsection
