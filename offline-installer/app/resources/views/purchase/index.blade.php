@extends('layouts.app')

@section('title', 'Purchases | All Invoices')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">

      @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show m-3 mb-0" role="alert">
          {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @elseif(session('error'))
        <div class="alert alert-danger alert-dismissible fade show m-3 mb-0" role="alert">
          {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0">All Purchase Invoices</h2>
        <a href="{{ route('purchase_invoices.create') }}" class="btn btn-primary">
          <i class="fas fa-plus"></i> New Invoice
        </a>
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="purchaseInvoiceTable">            
             <thead>
              <tr>
                <th>#</th>
                <th>Invoice No.</th>
                <th>Date</th>
                <th>Vendor</th>
                <th>Type</th>
                <th>Payment</th>
                <th>Net Amount</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($invoices as $index => $invoice)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td><span class="fw-bold text-primary">{{ $invoice->invoice_no ?? 'N/A' }}</span></td>
                <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                <td>{{ $invoice->vendor->name ?? 'N/A' }}</td>
                <td>
                  <span class="badge bg-{{ $invoice->is_taxable ? 'success' : 'secondary' }}">
                    {{ $invoice->is_taxable ? 'Tax' : 'Non-Tax' }}
                  </span>
                </td>
                <td>
                  @php
                    $colors = ['credit'=>'warning','cash'=>'success','cheque'=>'info','bank_transfer'=>'primary','material+making cost'=>'dark'];
                    $color  = $colors[$invoice->payment_method] ?? 'secondary';
                  @endphp
                  <span class="badge bg-{{ $color }}">
                    {{ ucwords(str_replace(['_','+'], [' ',' + '], $invoice->payment_method ?? '')) }}
                  </span>
                </td>
                <td class="text-end fw-semibold">
                  {{ number_format($invoice->net_amount_aed, 2) }}
                  <small class="text-muted">AED</small>
                </td>
                <td class="text-center" style="white-space:nowrap;">
                  <a href="{{ route('purchase_invoices.barcodes', $invoice->id) }}" class="text-success" target="_blank"><i class="fas fa-barcode"></i></a>
                  <a href="{{ route('purchase_invoices.edit', $invoice->id) }}" class="text-primary"><i class="fas fa-edit"></i></a>
                  <a href="{{ route('purchase_invoices.print', $invoice->id) }}" target="_blank" class="text-success"><i class="fas fa-print"></i></a>
                  <form action="{{ route('purchase_invoices.destroy', $invoice->id) }}"
                        method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 m-0 text-danger" title="Delete"
                      onclick="return confirm('Delete invoice {{ $invoice->invoice_no }}? This cannot be undone.')">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

    </section>
  </div>
</div>

<script>
$(document).ready(function() {
    $('#purchaseInvoiceTable').DataTable({
        pageLength: 50,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: 7 }   // disable sort on Actions column
        ],
    });
});
</script>
@endsection