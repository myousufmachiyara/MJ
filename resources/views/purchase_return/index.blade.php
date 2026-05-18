@extends('layouts.app')
@section('title', 'Purchase Returns')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show m-3 mb-0" role="alert">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @elseif(session('error'))
      <div class="alert alert-danger alert-dismissible fade show m-3 mb-0" role="alert">
        {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title mb-0">All Purchase Returns</h2>
      <a href="{{ route('purchase_return.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> New Return
      </a>
    </header>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered table-striped" id="returnTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Return No.</th>
              <th>Date</th>
              <th>Vendor</th>
              <th>Ref Invoice</th>
              <th>Refund Method</th>
              <th>Net Amount</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($returns as $index => $return)
            <tr>
              <td>{{ $index + 1 }}</td>
              <td><span class="fw-bold text-danger">{{ $return->return_no }}</span></td>
              <td>{{ \Carbon\Carbon::parse($return->return_date)->format('d M Y') }}</td>
              <td>{{ $return->vendor->name ?? 'N/A' }}</td>
              <td><span class="text-primary">{{ $return->purchaseInvoice->invoice_no ?? 'N/A' }}</span></td>
              <td>
                @php
                  $colors = ['credit_note'=>'warning','cash'=>'success','cheque'=>'info','bank_transfer'=>'primary','material_return'=>'dark'];
                  $color  = $colors[$return->refund_method] ?? 'secondary';
                @endphp
                <span class="badge bg-{{ $color }}">
                  {{ ucwords(str_replace('_', ' ', $return->refund_method ?? '')) }}
                </span>
              </td>
              <td class="text-end fw-semibold">
                {{ number_format($return->net_amount_aed, 2) }}
                <small class="text-muted">AED</small>
              </td>
              <td class="text-center" style="white-space:nowrap;">
                <a href="{{ route('purchase_return.print', $return->id) }}" target="_blank" class="text-success"><i class="fas fa-print"></i></a>
                <a href="{{ route('purchase_return.edit', $return->id) }}" class="text-primary"><i class="fas fa-edit"></i></a>
                <form action="{{ route('purchase_return.destroy', $return->id) }}" method="POST" style="display:inline;">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-link p-0 m-0 text-danger"
                    onclick="return confirm('Delete Return {{ $return->return_no }}? This cannot be undone.')">
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
</div></div>
<script>
$(document).ready(function() {
    $('#returnTable').DataTable({ pageLength: 50, order: [[0, 'desc']], columnDefs: [{ orderable: false, targets: 7 }] });
});
</script>
@endsection