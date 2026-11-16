@extends('layouts.app')
@section('title', 'Sale Returns')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Sale Returns</h2>
      <a href="{{ route('sale_return.create') }}" class="btn btn-danger">
        <i class="fas fa-plus"></i> New Sale Return
      </a>
    </header>
    <div class="card-body">
      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      <table class="table table-bordered table-hover datatable">
        <thead>
          <tr>
            <th>#</th>
            <th>Return No</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Ref Invoice</th>
            <th>Refund Method</th>
            <th>Net Amount (AED)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($returns as $i => $return)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td><span class="text-danger fw-bold">{{ $return->return_no }}</span></td>
              <td>{{ \Carbon\Carbon::parse($return->return_date)->format('d M Y') }}</td>
              <td>{{ $return->customer->name ?? '-' }}</td>
              <td><small class="text-muted">{{ $return->saleInvoice->invoice_no ?? '-' }}</small></td>
              <td>
                @php
                  $badge = match($return->refund_method) {
                      'credit_note'    => 'warning',
                      'cash'           => 'success',
                      'cheque'         => 'info',
                      'bank_transfer'  => 'primary',
                      'material_return'=> 'dark',
                      default          => 'secondary',
                  };
                @endphp
                <span class="badge bg-{{ $badge }}">
                  {{ ucwords(str_replace('_', ' ', $return->refund_method)) }}
                </span>
              </td>
              <td class="fw-bold">{{ number_format($return->net_amount_aed, 2) }}</td>
              <td>
                <a href="{{ route('sale_return.print', $return->id) }}"
                   class="btn btn-sm btn-outline-secondary" target="_blank" title="Print">
                  <i class="fas fa-print"></i>
                </a>
                <a href="{{ route('sale_return.edit', $return->id) }}"
                   class="btn btn-sm btn-outline-primary ms-1" title="Edit">
                  <i class="fas fa-edit"></i>
                </a>
                <form action="{{ route('sale_return.destroy', $return->id) }}" method="POST"
                      class="d-inline" onsubmit="return confirm('Delete {{ $return->return_no }}?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-danger ms-1" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
@endsection