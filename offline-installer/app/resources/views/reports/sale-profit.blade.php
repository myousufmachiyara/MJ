@extends('layouts.app')

@section('title', $type === 'gold_rate' ? 'Reports | Sale Profit — Gold Rate' : 'Reports | Sale Profit — Item Price')

@section('content')
@php
    $isGold   = $type === 'gold_rate';
    $route    = $isGold ? 'reports.profit_gold_rate' : 'reports.profit_item_price';
    $f        = $filters;
    $fmt      = fn($v, $d = 2) => number_format((float) $v, $d);
    $cls      = fn($v) => $v > 0 ? 'text-success' : ($v < 0 ? 'text-danger' : 'text-muted');
    $csvUrl   = route($route, array_merge(request()->query(), ['export' => 'csv']));
@endphp

<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="card-title mb-0">
          {{ $isGold ? 'Sale Profit — On Gold Rate' : 'Sale Profit — On Item Price' }}
        </h2>
        <div>
          <a href="{{ route('reports.profit_gold_rate', request()->only('from', 'to', 'q', 'result')) }}"
             class="btn btn-sm {{ $isGold ? 'btn-primary' : 'btn-outline-primary' }}">Profit on Gold Rate</a>
          <a href="{{ route('reports.profit_item_price', request()->only('from', 'to', 'q', 'result')) }}"
             class="btn btn-sm {{ !$isGold ? 'btn-primary' : 'btn-outline-primary' }}">Profit on Item Price</a>
        </div>
      </header>

      <div class="card-body">
        <p class="text-muted mb-3" style="font-size:.88rem;">
          @if($isGold)
            Same gold, bought at one gold rate and sold at another.
            <strong>Profit = (Sale gold rate − Purchase gold rate) × Pure gold weight</strong>
            (e.g. bought when gold was 10.29, sold when it was 15.65).
          @else
            What the piece cost vs. what it sold for.
            <strong>Profit = Sale amount − Purchase cost</strong>
            (e.g. bought for 100, sold for 109 → profit 9, 9 %).
          @endif
          Sold items are linked to their purchase by barcode. Amounts in AED.
        </p>

        <form method="GET" action="{{ route($route) }}" class="row g-2 align-items-end mb-3">
          <div class="col-6 col-md-2">
            <label class="form-label mb-1">Sale date from</label>
            <input type="date" name="from" value="{{ $f['from'] }}" class="form-control form-control-sm">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label mb-1">to</label>
            <input type="date" name="to" value="{{ $f['to'] }}" class="form-control form-control-sm">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label mb-1">Search</label>
            <input type="text" name="q" value="{{ $f['q'] }}" class="form-control form-control-sm" placeholder="Item, barcode, invoice no, customer">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label mb-1">Show</label>
            <select name="result" class="form-select form-select-sm form-control form-control-sm">
              <option value="all"    {{ $f['result'] === 'all'    ? 'selected' : '' }}>All</option>
              <option value="profit" {{ $f['result'] === 'profit' ? 'selected' : '' }}>Profit only</option>
              <option value="loss"   {{ $f['result'] === 'loss'   ? 'selected' : '' }}>Loss only</option>
            </select>
          </div>
          @unless($isGold)
          <div class="col-6 col-md-2">
            <label class="form-label mb-1">Amounts</label>
            <select name="amounts" class="form-select form-select-sm form-control form-control-sm">
              <option value="ex_vat"   {{ $f['amounts'] === 'ex_vat'   ? 'selected' : '' }}>Excluding VAT</option>
              <option value="with_vat" {{ $f['amounts'] === 'with_vat' ? 'selected' : '' }}>Including VAT</option>
            </select>
          </div>
          @endunless
          <div class="col-12 col-md-auto">
            <button class="btn btn-primary btn-sm">Apply</button>
            <a href="{{ route($route) }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            <a href="{{ $csvUrl }}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Export CSV</a>
            <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
          </div>
        </form>

        {{-- SUMMARY --}}
        <div class="row g-2 mb-3">
          <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="text-muted small">Items sold</div><div class="fs-5 fw-bold">{{ $totals['count'] }}</div></div></div>
          @if($isGold)
            <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="text-muted small">Pure gold (g)</div><div class="fs-5 fw-bold">{{ $fmt($totals['weight'], 3) }}</div></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="text-muted small">Avg purchase rate</div><div class="fs-5 fw-bold">{{ $fmt($totals['avg_purchase_rate'], 4) }}</div></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="text-muted small">Avg sale rate</div><div class="fs-5 fw-bold">{{ $fmt($totals['avg_sale_rate'], 4) }}</div></div></div>
          @else
            <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="text-muted small">Purchase cost</div><div class="fs-5 fw-bold">{{ $fmt($totals['cost']) }}</div></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="text-muted small">Sale amount</div><div class="fs-5 fw-bold">{{ $fmt($totals['sale']) }}</div></div></div>
          @endif
          <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="text-muted small">Total profit / (loss)</div><div class="fs-5 fw-bold {{ $cls($totals['profit']) }}">{{ $fmt($totals['profit']) }}</div></div></div>
          @unless($isGold)
            <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="text-muted small">Profit % on cost</div><div class="fs-5 fw-bold {{ $cls($totals['profit']) }}">{{ $totals['profit_pct'] !== null ? $fmt($totals['profit_pct']) . ' %' : '—' }}</div></div></div>
          @endunless
        </div>

        @if($withoutPurchase > 0 || $skippedOther > 0)
          <div class="alert alert-light border py-2" style="font-size:.85rem;">
            @if($withoutPurchase > 0)
              {{ $withoutPurchase }} sold line(s) have no matching purchased item (barcode not found in purchases — e.g. consignment or manually typed items) and are not included.
            @endif
            @if($skippedOther > 0)
              {{ $skippedOther }} non-gold line(s) were left out of this gold-rate report.
            @endif
          </div>
        @endif

        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle" style="font-size:.85rem;">
            <thead class="table-light">
              <tr>
                <th>Sale Inv.</th><th>Date</th><th>Customer</th><th>Item</th><th>Barcode</th><th>Purchase Inv.</th><th>Purch. Date</th>
                @if($isGold)
                  <th class="text-end">Pure Gold (g)</th>
                  <th class="text-end">Purchase Rate</th>
                  <th class="text-end">Sale Rate</th>
                  <th class="text-end">Rate Diff.</th>
                @else
                  <th class="text-end">Purchase Cost</th>
                  <th class="text-end">Sale Amount</th>
                @endif
                <th class="text-end">Profit / (Loss)</th>
                @unless($isGold)<th class="text-end">Profit %</th><th class="text-end">Margin %</th>@endunless
              </tr>
            </thead>
            <tbody>
              @forelse($rows as $r)
                <tr class="{{ $r['missing_rate'] ? 'table-warning' : '' }}">
                  <td><a href="{{ route('sale_invoices.show', $r['sale_invoice_id']) }}">{{ $r['sale_invoice_no'] }}</a></td>
                  <td>{{ $r['sale_date'] }}</td>
                  <td>{{ $r['customer'] }}</td>
                  <td>{{ $r['item_name'] }}</td>
                  <td><code>{{ $r['barcode'] }}</code></td>
                  <td>{{ $r['purchase_invoice_no'] }}</td>
                  <td>{{ $r['purchase_date'] }}</td>
                  @if($isGold)
                    <td class="text-end">{{ $fmt($r['weight'], 3) }}</td>
                    <td class="text-end">{{ $fmt($r['purchase_rate'], 4) }}</td>
                    <td class="text-end">{{ $fmt($r['sale_rate'], 4) }}</td>
                    <td class="text-end {{ $cls($r['rate_diff']) }}">{{ $fmt($r['rate_diff'], 4) }}</td>
                  @else
                    <td class="text-end">{{ $fmt($r['cost']) }}</td>
                    <td class="text-end">{{ $fmt($r['sale']) }}</td>
                  @endif
                  <td class="text-end fw-bold {{ $cls($r['profit']) }}">{{ $fmt($r['profit']) }}</td>
                  @unless($isGold)
                    <td class="text-end {{ $cls($r['profit']) }}">{{ $r['profit_pct'] !== null ? $fmt($r['profit_pct']) . ' %' : '—' }}</td>
                    <td class="text-end {{ $cls($r['profit']) }}">{{ $r['margin_pct'] !== null ? $fmt($r['margin_pct']) . ' %' : '—' }}</td>
                  @endunless
                </tr>
              @empty
                <tr><td colspan="{{ $isGold ? 12 : 12 }}" class="text-center text-muted py-4">No sold items found for these filters.</td></tr>
              @endforelse
            </tbody>
            @if(count($rows))
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="7" class="text-end">TOTAL</td>
                @if($isGold)
                  <td class="text-end">{{ $fmt($totals['weight'], 3) }}</td>
                  <td class="text-end">{{ $fmt($totals['avg_purchase_rate'], 4) }}</td>
                  <td class="text-end">{{ $fmt($totals['avg_sale_rate'], 4) }}</td>
                  <td></td>
                @else
                  <td class="text-end">{{ $fmt($totals['cost']) }}</td>
                  <td class="text-end">{{ $fmt($totals['sale']) }}</td>
                @endif
                <td class="text-end {{ $cls($totals['profit']) }}">{{ $fmt($totals['profit']) }}</td>
                @unless($isGold)
                  <td class="text-end">{{ $totals['profit_pct'] !== null ? $fmt($totals['profit_pct']) . ' %' : '—' }}</td>
                  <td class="text-end">{{ $totals['margin_pct'] !== null ? $fmt($totals['margin_pct']) . ' %' : '—' }}</td>
                @endunless
              </tr>
            </tfoot>
            @endif
          </table>
        </div>

        <p class="text-muted small mb-0">
          @if($isGold)
            Rates are the AED-per-gram gold rates stored on the purchased and sold item. Weight is the pure gold weight bought.
            Yellow rows have a missing (0) rate — check those invoices. Only gold items are included.
          @else
            Sale amount = the item total on the sale invoice after the item discount, with any invoice-level discount shared across its items in proportion.
            Cost = the item total on the purchase invoice. Choose "Excluding VAT" to compare without VAT (default).
            Yellow rows have a 0 purchase cost.
          @endif
        </p>
      </div>
    </section>
  </div>
</div>
@endsection
