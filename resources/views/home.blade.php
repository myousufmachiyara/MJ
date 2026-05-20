@extends('layouts.app')

@section('title', 'Dashboard — Musfira Jewelry L.L.C')

@section('content')
<div class="container-fluid px-3 py-3">

<style>
.mj-dash *{box-sizing:border-box}
.mj-hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:8px}
.mj-hdr h4{margin:0;font-weight:600;font-size:1.1rem}
.mj-hdr p{margin:0;font-size:.8rem;color:#6c757d}
.mj-kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:1rem}
@media(max-width:992px){.mj-kpi-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:576px){.mj-kpi-grid{grid-template-columns:1fr}}
.mj-kpi{background:#fff;border:1px solid #e9ecef;border-radius:10px;padding:14px 16px}
.mj-kpi-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:8px}
.mj-kpi-label{font-size:.72rem;color:#6c757d;font-weight:500;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px}
.mj-kpi-val{font-size:1.35rem;font-weight:700;margin-bottom:2px}
.mj-kpi-sub{font-size:.72rem;color:#6c757d}
.mj-row2{display:grid;grid-template-columns:1.65fr 1fr;gap:12px;margin-bottom:1rem}
@media(max-width:768px){.mj-row2{grid-template-columns:1fr}}
.mj-row3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:1rem}
@media(max-width:992px){.mj-row3{grid-template-columns:repeat(2,1fr)}}
@media(max-width:576px){.mj-row3{grid-template-columns:1fr}}
.mj-row4{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
@media(max-width:992px){.mj-row4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:576px){.mj-row4{grid-template-columns:1fr}}
.mj-card{background:#fff;border:1px solid #e9ecef;border-radius:10px;padding:16px}
.mj-card-hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.mj-card-title{font-size:.82rem;font-weight:600;color:#344050}
.mj-badge{display:inline-block;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:500}
.bg-success-soft{background:#d1fae5;color:#065f46}
.bg-warning-soft{background:#fef3c7;color:#92400e}
.bg-info-soft{background:#dbeafe;color:#1e40af}
.bg-secondary-soft{background:#f1f5f9;color:#475569}
.bg-danger-soft{background:#fee2e2;color:#991b1b}
.mj-csg-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px}
.mj-csg-box{background:#f8f9fa;border-radius:8px;padding:10px;text-align:center}
.mj-csg-val{font-size:1.3rem;font-weight:700}
.mj-csg-lbl{font-size:.7rem;color:#6c757d;margin-top:1px}
.mj-csg-sub{font-size:.72rem;font-weight:600;margin-top:2px}
.mj-tbl{width:100%;border-collapse:collapse;font-size:.78rem}
.mj-tbl th{font-weight:600;color:#6c757d;font-size:.7rem;padding:6px 0;border-bottom:1px solid #f1f3f5;text-align:left;text-transform:uppercase;letter-spacing:.04em}
.mj-tbl td{padding:7px 0;border-bottom:1px solid #f8f9fa;color:#344050;vertical-align:middle}
.mj-tbl tr:last-child td{border-bottom:none}
.mj-tbl .mj-amt{text-align:right;font-weight:600}
.mj-code{font-family:monospace;font-size:.75rem;color:#2563eb}
.mj-mini{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #f8f9fa;font-size:.8rem}
.mj-mini:last-child{border-bottom:none}
.mj-mini-label{color:#6c757d}
.mj-mini-val{font-weight:600}
.mj-prog{height:5px;background:#e9ecef;border-radius:3px;margin:4px 0 8px}
.mj-prog-fill{height:100%;border-radius:3px}
.mj-divider{height:1px;background:#f1f3f5;margin:8px 0}
.text-success-mj{color:#059669!important}
.text-danger-mj{color:#dc2626!important}
.text-primary-mj{color:#2563eb!important}
</style>

<div class="mj-dash">

{{-- Header --}}
<div class="mj-hdr">
    <div>
        <h4><i class="fas fa-gem me-1 text-warning"></i> Musfira Jewelry L.L.C</h4>
        <p>{{ now()->format('l, d F Y') }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('purchase_invoices.create') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-plus me-1"></i>Purchase
        </a>
        <a href="{{ route('sale_invoices.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>Sale
        </a>
        <a href="{{ route('consignments.create') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-handshake me-1"></i>Consignment
        </a>
    </div>
</div>

{{-- KPI Cards --}}
<div class="mj-kpi-grid">
    <div class="mj-kpi">
        <div class="mj-kpi-icon" style="background:#dbeafe">
            <i class="fas fa-shopping-cart" style="color:#2563eb;font-size:16px"></i>
        </div>
        <div class="mj-kpi-label">Sales this month</div>
        <div class="mj-kpi-val text-primary-mj">AED {{ number_format($totalSalesMonth, 0) }}</div>
        <div class="mj-kpi-sub">
            {{ $saleCount }} invoice{{ $saleCount != 1 ? 's' : '' }} &nbsp;·&nbsp;
            Year: AED {{ number_format($totalSalesYear, 0) }}
        </div>
    </div>
    <div class="mj-kpi">
        <div class="mj-kpi-icon" style="background:#fee2e2">
            <i class="fas fa-file-invoice" style="color:#dc2626;font-size:16px"></i>
        </div>
        <div class="mj-kpi-label">Purchases this month</div>
        <div class="mj-kpi-val text-danger-mj">AED {{ number_format($totalPurchasesMonth, 0) }}</div>
        <div class="mj-kpi-sub">
            {{ $purchaseCount }} invoice{{ $purchaseCount != 1 ? 's' : '' }} &nbsp;·&nbsp;
            Year: AED {{ number_format($totalPurchasesYear, 0) }}
        </div>
    </div>
    <div class="mj-kpi">
        <div class="mj-kpi-icon" style="background:#d1fae5">
            <i class="fas fa-chart-line" style="color:#059669;font-size:16px"></i>
        </div>
        <div class="mj-kpi-label">Profit this month</div>
        <div class="mj-kpi-val {{ $monthlyProfit['profit'] >= 0 ? 'text-success-mj' : 'text-danger-mj' }}">
            AED {{ number_format($monthlyProfit['profit'], 0) }}
        </div>
        <div class="mj-kpi-sub">
            {{ $monthlyProfit['margin'] }}% margin &nbsp;·&nbsp;
            Revenue AED {{ number_format($monthlyProfit['revenue'], 0) }}
        </div>
    </div>
    <div class="mj-kpi">
        <div class="mj-kpi-icon" style="background:#fef3c7">
            <i class="fas fa-boxes" style="color:#d97706;font-size:16px"></i>
        </div>
        <div class="mj-kpi-label">Stock in hand</div>
        <div class="mj-kpi-val">{{ number_format($stockCount) }} items</div>
        <div class="mj-kpi-sub">
            AED {{ number_format($stockValue, 0) }} &nbsp;·&nbsp;
            {{ number_format($stockGrossWt, 2) }}g gross
        </div>
    </div>
</div>

{{-- Row 2: Trend chart + Consignment --}}
<div class="mj-row2">
    <div class="mj-card">
        <div class="mj-card-hdr">
            <span class="mj-card-title"><i class="fas fa-chart-bar me-1"></i>Monthly trend — last 6 months</span>
        </div>
        <canvas id="mjTrendChart" height="160"></canvas>
        <div class="d-flex gap-3 mt-2" style="font-size:.72rem;color:#6c757d">
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#2563eb;margin-right:4px"></span>Sales</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#dc2626;margin-right:4px"></span>Purchases</span>
        </div>
    </div>
    <div class="mj-card">
        <div class="mj-card-hdr">
            <span class="mj-card-title"><i class="fas fa-handshake me-1"></i>Consignment overview</span>
            <span class="mj-badge bg-info-soft">{{ $activeConsignments }} active</span>
        </div>
        <div class="mj-csg-grid">
            <div class="mj-csg-box">
                <div class="mj-csg-val" style="color:#d97706">{{ number_format($csgInStockCount) }}</div>
                <div class="mj-csg-lbl">In stock</div>
                <div class="mj-csg-sub" style="color:#d97706">AED {{ number_format($csgInStockValue, 0) }}</div>
            </div>
            <div class="mj-csg-box">
                <div class="mj-csg-val" style="color:#059669">{{ number_format($csgSoldCount) }}</div>
                <div class="mj-csg-lbl">Sold</div>
                <div class="mj-csg-sub" style="color:#059669">AED {{ number_format($csgSoldValue, 0) }}</div>
            </div>
            <div class="mj-csg-box">
                <div class="mj-csg-val" style="color:#2563eb">{{ number_format($csgInboundCount) }}</div>
                <div class="mj-csg-lbl">Inbound pending</div>
            </div>
            <div class="mj-csg-box">
                <div class="mj-csg-val">{{ number_format($csgOutboundCount) }}</div>
                <div class="mj-csg-lbl">Outbound pending</div>
            </div>
        </div>
        <div class="mj-divider"></div>
        <div style="font-size:.72rem;color:#6c757d;margin-bottom:3px">Returned</div>
        <div style="font-size:1rem;font-weight:600">{{ number_format($csgReturnedCount) }} items</div>
    </div>
</div>

{{-- Row 3: Receivables | Payables | Performance --}}
<div class="mj-row3">
    <div class="mj-card">
        <div class="mj-card-hdr">
            <span class="mj-card-title"><i class="fas fa-hand-holding-usd me-1"></i>Receivables</span>
            <span style="font-size:.85rem;font-weight:600;color:#059669">AED {{ number_format($receivables['total'], 0) }}</span>
        </div>
        @forelse($receivables['list'] as $rec)
            <div class="mj-mini">
                <span class="mj-mini-label">{{ $rec['name'] }}</span>
                <span class="mj-mini-val text-success-mj">AED {{ number_format($rec['amount'], 0) }}</span>
            </div>
        @empty
            <div style="font-size:.78rem;color:#6c757d;padding:8px 0">No outstanding receivables</div>
        @endforelse
        <div class="mt-2">
            <a href="{{ route('reports.accounts') }}?tab=receivables" class="btn btn-outline-secondary btn-sm w-100" style="font-size:.75rem">View all ↗</a>
        </div>
    </div>

    <div class="mj-card">
        <div class="mj-card-hdr">
            <span class="mj-card-title"><i class="fas fa-file-invoice-dollar me-1"></i>Payables</span>
            <span style="font-size:.85rem;font-weight:600;color:#dc2626">AED {{ number_format($payables['total'], 0) }}</span>
        </div>
        @forelse($payables['list'] as $pay)
            <div class="mj-mini">
                <span class="mj-mini-label">{{ $pay['name'] }}</span>
                <span class="mj-mini-val text-danger-mj">AED {{ number_format($pay['amount'], 0) }}</span>
            </div>
        @empty
            <div style="font-size:.78rem;color:#6c757d;padding:8px 0">No outstanding payables</div>
        @endforelse
        <div class="mt-2">
            <a href="{{ route('reports.accounts') }}?tab=payables" class="btn btn-outline-secondary btn-sm w-100" style="font-size:.75rem">View all ↗</a>
        </div>
    </div>

    <div class="mj-card">
        <div class="mj-card-hdr">
            <span class="mj-card-title"><i class="fas fa-balance-scale me-1"></i>Business performance</span>
        </div>
        @php
            $perfItems = [
                ['label' => 'Revenue this month',  'val' => 'AED '.number_format($monthlyProfit['revenue'], 0), 'pct' => null, 'color' => null],
                ['label' => 'Cost this month',     'val' => 'AED '.number_format($monthlyProfit['cost'], 0),    'pct' => null, 'color' => null],
                ['label' => 'Gross margin',        'val' => $monthlyProfit['margin'].'%',
                    'pct'   => min(100, abs((float)$monthlyProfit['margin'])),
                    'color' => (float)$monthlyProfit['margin'] >= 0 ? '#059669' : '#dc2626'],
                ['label' => 'Stock value',         'val' => 'AED '.number_format($stockValue, 0),              'pct' => null, 'color' => null],
                ['label' => 'Consignment value',   'val' => 'AED '.number_format($csgInStockValue, 0),         'pct' => null, 'color' => null],
                ['label' => 'Net receivable',      'val' => 'AED '.number_format($receivables['total'], 0),    'pct' => null, 'color' => null],
                ['label' => 'Net payable',         'val' => 'AED '.number_format($payables['total'], 0),       'pct' => null, 'color' => null],
            ];
        @endphp
        @foreach($perfItems as $item)
            @if($item['pct'] !== null)
                <div style="padding:7px 0;border-bottom:1px solid #f8f9fa">
                    <div class="d-flex justify-content-between">
                        <span style="font-size:.8rem;color:#6c757d">{{ $item['label'] }}</span>
                        <span style="font-size:.8rem;font-weight:600">{{ $item['val'] }}</span>
                    </div>
                    <div class="mj-prog"><div class="mj-prog-fill" style="width:{{ $item['pct'] }}%;background:{{ $item['color'] }}"></div></div>
                </div>
            @else
                <div class="mj-mini">
                    <span class="mj-mini-label">{{ $item['label'] }}</span>
                    <span class="mj-mini-val">{{ $item['val'] }}</span>
                </div>
            @endif
        @endforeach
    </div>
</div>

{{-- Row 4: Recent tables --}}
<div class="mj-row4">
    <div class="mj-card">
        <div class="mj-card-hdr">
            <span class="mj-card-title"><i class="fas fa-file-invoice me-1"></i>Recent purchases</span>
            <a href="{{ route('purchase_invoices.index') }}" class="btn btn-outline-secondary btn-sm" style="font-size:.7rem;padding:2px 8px">View all</a>
        </div>
        <table class="mj-tbl">
            <thead><tr><th>Invoice</th><th>Vendor</th><th class="mj-amt">AED</th></tr></thead>
            <tbody>
                @forelse($recentPurchases as $inv)
                    @php
                        $invNo     = is_array($inv) ? ($inv['invoice_no']     ?? '') : ($inv->invoice_no     ?? '');
                        $invDate   = is_array($inv) ? ($inv['invoice_date']   ?? '') : ($inv->invoice_date   ?? '');
                        $invAed    = is_array($inv) ? ($inv['net_amount_aed'] ?? 0)  : ($inv->net_amount_aed ?? 0);
                        $invVendor = is_array($inv) ? ($inv['vendor']['name'] ?? '—') : (optional($inv->vendor)->name ?? '—');
                    @endphp
                    <tr>
                        <td>
                            <span class="mj-code">{{ $invNo }}</span>
                            <div style="font-size:.68rem;color:#6c757d">{{ $invDate ? \Carbon\Carbon::parse($invDate)->format('d-M-Y') : '' }}</div>
                        </td>
                        <td style="font-size:.75rem">{{ $invVendor }}</td>
                        <td class="mj-amt">{{ number_format($invAed, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="font-size:.78rem;color:#6c757d;padding:8px 0">No recent purchases</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mj-card">
        <div class="mj-card-hdr">
            <span class="mj-card-title"><i class="fas fa-shopping-cart me-1"></i>Recent sales</span>
            <a href="{{ route('sale_invoices.index') }}" class="btn btn-outline-secondary btn-sm" style="font-size:.7rem;padding:2px 8px">View all</a>
        </div>
        <table class="mj-tbl">
            <thead><tr><th>Invoice</th><th>Customer</th><th class="mj-amt">AED</th></tr></thead>
            <tbody>
                @forelse($recentSales as $inv)
                    @php
                        $invNo      = is_array($inv) ? ($inv['invoice_no']     ?? '') : ($inv->invoice_no     ?? '');
                        $invDate    = is_array($inv) ? ($inv['invoice_date']   ?? '') : ($inv->invoice_date   ?? '');
                        $invAed     = is_array($inv) ? ($inv['net_amount_aed'] ?? 0)  : ($inv->net_amount_aed ?? 0);
                        $invCustomer= is_array($inv) ? ($inv['customer']['name'] ?? '—') : (optional($inv->customer)->name ?? '—');
                    @endphp
                    <tr>
                        <td>
                            <span class="mj-code">{{ $invNo }}</span>
                            <div style="font-size:.68rem;color:#6c757d">{{ $invDate ? \Carbon\Carbon::parse($invDate)->format('d-M-Y') : '' }}</div>
                        </td>
                        <td style="font-size:.75rem">{{ $invCustomer }}</td>
                        <td class="mj-amt">{{ number_format($invAed, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="font-size:.78rem;color:#6c757d;padding:8px 0">No recent sales</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mj-card">
        <div class="mj-card-hdr">
            <span class="mj-card-title"><i class="fas fa-handshake me-1"></i>Recent consignments</span>
            <a href="{{ route('consignments.index') }}" class="btn btn-outline-secondary btn-sm" style="font-size:.7rem;padding:2px 8px">View all</a>
        </div>
        <table class="mj-tbl">
            <thead><tr><th>No.</th><th>Partner</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($recentConsignments as $c)
                    @php
                        // Support both Eloquent object and legacy array
                        $cStatus  = is_array($c) ? ($c['status']         ?? '') : ($c->status         ?? '');
                        $cNo      = is_array($c) ? ($c['consignment_no'] ?? '') : ($c->consignment_no ?? '');
                        $cDate    = is_array($c) ? ($c['start_date']     ?? '') : ($c->start_date     ?? '');
                        $cPartner = is_array($c) ? ($c['partner']['name'] ?? '—') : (optional($c->partner)->name ?? '—');

                        $badgeClass = match($cStatus) {
                            'active'            => 'bg-success-soft',
                            'partially_settled' => 'bg-warning-soft',
                            'settled'           => 'bg-info-soft',
                            'returned'          => 'bg-secondary-soft',
                            'expired'           => 'bg-danger-soft',
                            default             => 'bg-secondary-soft',
                        };
                        $statusLabel = ucwords(str_replace('_', ' ', $cStatus));
                    @endphp
                    <tr>
                        <td>
                            <span class="mj-code">{{ $cNo }}</span>
                            <div style="font-size:.68rem;color:#6c757d">{{ $cDate ? \Carbon\Carbon::parse($cDate)->format('d-M-Y') : '' }}</div>
                        </td>
                        <td style="font-size:.75rem">{{ $cPartner }}</td>
                        <td><span class="mj-badge {{ $badgeClass }}" style="font-size:.68rem">{{ $statusLabel }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="font-size:.78rem;color:#6c757d;padding:8px 0">No recent consignments</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- /mj-dash --}}

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    var ctx = document.getElementById('mjTrendChart');
    if(!ctx) return;
    new Chart(ctx,{
        type:'bar',
        data:{
            labels: @json($monthlyTrend['months']),
            datasets:[
                {label:'Sales',    data:@json($monthlyTrend['sales']),     backgroundColor:'rgba(37,99,235,0.75)', borderRadius:4, order:2},
                {label:'Purchases',data:@json($monthlyTrend['purchases']), backgroundColor:'rgba(220,38,38,0.65)', borderRadius:4, order:1}
            ]
        },
        options:{
            responsive:true,
            maintainAspectRatio:true,
            plugins:{
                legend:{display:false},
                tooltip:{callbacks:{label:function(c){return c.dataset.label+': AED '+Math.round(c.parsed.y).toLocaleString();}}}
            },
            scales:{
                x:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:10},color:'#9ca3af'}},
                y:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:10},color:'#9ca3af',callback:function(v){return 'AED '+Math.round(v/1000)+'k';}}}
            }
        }
    });
})();
</script>
@endpush

@endsection