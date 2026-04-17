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

        <div class="table-responsive">
          <table class="table table-bordered table-striped datatable">
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
                    {{ $invoice->is_taxable ? 'Taxable' : 'Non-Taxable' }}
                  </span>
                </td>
                <td>{{ $invoice->customer->name ?? '-' }}</td>
                <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                <td>{{ $invoice->currency }}</td>
                <td class="text-end">{{ number_format($invoice->net_amount, 2) }}</td>
                <td class="text-end">{{ number_format($invoice->net_amount_aed, 2) }}</td>
                <td>
                  <span class="badge bg-info">{{ ucwords(str_replace(['+','_'], [' + ', ' '], $invoice->payment_method)) }}</span>
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('sale_invoices.edit', $invoice->id) }}" class="btn btn-warning" title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>
                    <a href="{{ route('sale_invoices.print', $invoice->id) }}" class="btn btn-secondary" target="_blank" title="Print">
                      <i class="fas fa-print"></i>
                    </a>
                    <form method="POST" action="{{ route('sale_invoices.destroy', $invoice->id) }}"
                          onsubmit="return confirm('Delete Invoice #{{ $invoice->invoice_no }}? This cannot be undone.')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-danger" title="Delete">
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

      </div>
    </section>
  </div>
</div>
@endsection