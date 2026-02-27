@extends('layouts.app')

@section('title', 'Sale Invoices')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @elseif (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">All Sale Invoices</h2>
        <a href="{{ route('sale_invoices.create') }}" class="btn btn-primary">
          <i class="fas fa-plus"></i> Sale Invoice
        </a>
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover datatable">
            <thead class="thead-dark">
              <tr>
                <th>#</th>
                <th>Invoice No</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Payment Method</th>
                <th>Currency</th>
                <th>Net Amount</th>
                <th>Type</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($invoices as $invoice)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $invoice->invoice_no }}</td>
                <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                <td>{{ $invoice->customer->name ?? '—' }}</td>
                <td>
                  @php
                    $methodColors = [
                      'cash'                => 'bg-success',
                      'cheque'              => 'bg-info',
                      'bank_transfer'       => 'bg-primary',
                      'credit'              => 'bg-warning',
                      'material+making cost'=> 'bg-secondary',
                    ];
                    $color = $methodColors[$invoice->payment_method] ?? 'bg-dark';
                  @endphp
                  <span class="badge {{ $color }}">
                    {{ ucwords(str_replace('_', ' ', $invoice->payment_method)) }}
                  </span>
                </td>
                <td>{{ $invoice->currency }}</td>
                <td>
                  {{ number_format($invoice->net_amount, 2) }} {{ $invoice->currency }}
                  @if ($invoice->currency === 'USD')
                    <br><small class="text-muted">≈ {{ number_format($invoice->net_amount_aed, 2) }} AED</small>
                  @endif
                </td>
                <td>
                  @if ($invoice->is_taxable)
                    <span class="badge bg-danger">Taxable</span>
                  @else
                    <span class="badge bg-secondary">Non-Taxable</span>
                  @endif
                </td>
                <td class="text-nowrap">
                  <a href="{{ route('sale_invoices.show', $invoice->id) }}" class="text-info" title="View">
                    <i class="fas fa-eye"></i>
                  </a>
                  <a href="{{ route('sale_invoices.edit', $invoice->id) }}" class="text-primary ms-1" title="Edit">
                    <i class="fas fa-edit"></i>
                  </a>
                  <a href="{{ route('sale_invoices.print', $invoice->id) }}" target="_blank" class="text-success ms-1" title="Print">
                    <i class="fas fa-print"></i>
                  </a>
                  <form action="{{ route('sale_invoices.destroy', $invoice->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-danger ms-1" style="border:none; background:none;"
                      onclick="return confirm('Delete Invoice {{ $invoice->invoice_no }}? This will also reverse accounting entries.')">
                      <i class="fas fa-trash"></i>
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
  $(document).ready(function () {
    $('.datatable').DataTable({
      order: [[0, 'desc']],
      pageLength: 25,
    });
  });
</script>
@endsection