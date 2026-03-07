<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcodes — {{ $invoice->invoice_no }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        :root {
            --ink:      #0a0a0a;
            --paper:    #f5f3ef;
            --rule:     #c8c4bc;
            --accent:   #1a1a2e;
            --stamp:    #d64f2a;
            --card-bg:  #ffffff;
            --mono:     'IBM Plex Mono', monospace;
            --sans:     'IBM Plex Sans', sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--sans);
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
        }

        /* ── SCREEN-ONLY CONTROLS ── */
        .controls {
            background: var(--accent);
            color: #fff;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 3px solid var(--stamp);
        }

        .controls-left {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .controls-left .inv-label {
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
        }

        .controls-left .inv-no {
            font-family: var(--mono);
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.06em;
            color: #fff;
        }

        .controls-meta {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .meta-chip {
            font-size: 12px;
            font-family: var(--mono);
            color: rgba(255,255,255,0.65);
            letter-spacing: 0.05em;
        }

        .meta-chip span {
            color: #fff;
            font-weight: 600;
        }

        .controls-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-print {
            background: var(--stamp);
            color: #fff;
            border: none;
            padding: 9px 22px;
            font-family: var(--sans);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.04em;
            cursor: pointer;
            border-radius: 3px;
            transition: background 0.15s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print:hover { background: #bf3f1e; }

        .btn-print svg { width: 15px; height: 15px; fill: #fff; }

        .btn-back {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.8);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 9px 18px;
            font-family: var(--sans);
            font-size: 13px;
            font-weight: 400;
            cursor: pointer;
            border-radius: 3px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }

        .btn-back:hover {
            background: rgba(255,255,255,0.18);
            color: #fff;
        }

        /* ── GRID ── */
        .page-wrap {
            padding: 28px 32px 48px;
        }

        .grid-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--rule);
        }

        .grid-header-label {
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #888;
            font-weight: 600;
            font-family: var(--mono);
        }

        .grid-header-count {
            font-size: 11px;
            font-family: var(--mono);
            background: var(--accent);
            color: #fff;
            padding: 2px 8px;
            border-radius: 2px;
            letter-spacing: 0.06em;
        }

        .barcode-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(195px, 1fr));
            gap: 14px;
        }

        /* ── BARCODE CARD ── */
        .barcode-card {
            background: var(--card-bg);
            border: 1px solid var(--rule);
            border-radius: 4px;
            padding: 14px 14px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
            page-break-inside: avoid;
            break-inside: avoid;
            position: relative;
            transition: box-shadow 0.15s, border-color 0.15s;
        }

        .barcode-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
            border-color: #aaa;
        }

        /* Corner index number */
        .card-index {
            position: absolute;
            top: 7px;
            left: 9px;
            font-family: var(--mono);
            font-size: 9px;
            color: #bbb;
            letter-spacing: 0.04em;
        }

        /* Printed badge */
        .printed-badge {
            position: absolute;
            top: 7px;
            right: 9px;
            font-family: var(--mono);
            font-size: 8px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
            padding: 1px 5px;
            border-radius: 2px;
        }

        .barcode-svg-wrap {
            width: 100%;
            margin-top: 10px;
        }

        .barcode-svg-wrap svg {
            width: 100%;
            height: auto;
            display: block;
        }

        .card-divider {
            width: 100%;
            height: 1px;
            background: var(--rule);
            margin: 9px 0 8px;
        }

        .item-name {
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            line-height: 1.35;
            color: var(--ink);
            letter-spacing: 0.01em;
            width: 100%;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            min-height: 28px;
        }

        .barcode-number {
            font-family: var(--mono);
            font-size: 9.5px;
            color: #888;
            letter-spacing: 0.06em;
            margin-top: 5px;
            text-align: center;
        }

        .invoice-ref {
            font-family: var(--mono);
            font-size: 8.5px;
            color: #bbb;
            letter-spacing: 0.04em;
            margin-top: 3px;
            text-align: center;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #aaa;
        }

        .empty-state .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.4;
        }

        .empty-state p {
            font-family: var(--mono);
            font-size: 13px;
            letter-spacing: 0.08em;
        }

        /* ── PRINT STYLES ── */
        @media print {
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

            body { background: #fff; }

            .controls { display: none; }

            .page-wrap { padding: 8px; }

            .grid-header { display: none; }

            .barcode-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 6px;
            }

            .barcode-card {
                border: 1px solid #ccc;
                border-radius: 3px;
                padding: 8px 8px 6px;
                box-shadow: none !important;
            }

            .barcode-card:hover {
                box-shadow: none;
                border-color: #ccc;
            }

            .card-index { display: none; }

            .item-name { font-size: 9px; }
            .barcode-number { font-size: 8px; }
            .invoice-ref { display: none; }
        }
    </style>
</head>
<body>

{{-- ── SCREEN CONTROLS ── --}}
<div class="controls">
    <div class="controls-left">
        <span class="inv-label">Purchase Invoice</span>
        <span class="inv-no">{{ $invoice->invoice_no }}</span>
    </div>

    <div class="controls-meta">
        <div class="meta-chip">
            Date&nbsp;<span>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</span>
        </div>
        <div class="meta-chip">
            Vendor&nbsp;<span>{{ $invoice->vendor->name ?? '—' }}</span>
        </div>
        <div class="meta-chip">
            Items&nbsp;<span>{{ $invoice->items->count() }}</span>
        </div>
    </div>

    <div class="controls-right">
        <a href="{{ url()->previous() }}" class="btn-back">
            ← Back
        </a>
        <button class="btn-print" onclick="window.print()">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
            </svg>
            Print All Barcodes
        </button>
    </div>
</div>

{{-- ── GRID ── --}}
<div class="page-wrap">

    <div class="grid-header">
        <span class="grid-header-label">Barcode Labels</span>
        <span class="grid-header-count">{{ $invoice->items->count() }} items</span>
    </div>

    @if($invoice->items->count())
    <div class="barcode-grid">
        @foreach($invoice->items as $i => $item)
        <div class="barcode-card">
            <span class="card-index">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>

            @if($item->is_printed)
                <span class="printed-badge">✓ Printed</span>
            @endif

            <div class="barcode-svg-wrap">
                <svg class="barcode" id="bc-{{ $loop->index }}"></svg>
            </div>

            <div class="card-divider"></div>

            <div class="item-name" title="{{ $item->item_name ?: $item->item_description }}">
                {{ $item->item_name ?: $item->item_description ?: '—' }}
            </div>

            <div class="barcode-number">{{ $item->barcode_number }}</div>
            <div class="invoice-ref">{{ $invoice->invoice_no }}</div>
        </div>
        @endforeach
    </div>
    @else
    <div class="empty-state">
        <div class="empty-icon">▭</div>
        <p>No items with barcodes found for this invoice.</p>
    </div>
    @endif

</div>

<script>
    // Render barcodes after DOM is ready
    const barcodeData = @json($invoice->items->map(fn($item) => [
        'index'   => $loop->index ?? 0,
        'barcode' => $item->barcode_number,
    ])->values());

    @foreach($invoice->items as $item)
    (function() {
        const el      = document.getElementById('bc-{{ $loop->index }}');
        const barcode = '{{ $item->barcode_number }}';
        if (el && barcode) {
            try {
                JsBarcode(el, barcode, {
                    format:       'CODE128',
                    width:        1.6,
                    height:       48,
                    displayValue: false,
                    margin:       0,
                    background:   '#ffffff',
                    lineColor:    '#0a0a0a',
                });
            } catch(e) {
                el.parentElement.innerHTML = '<div style="font-size:10px;color:#c00;padding:8px;text-align:center;">Invalid barcode</div>';
            }
        } else if (el) {
            el.parentElement.innerHTML = '<div style="font-size:10px;color:#bbb;padding:8px;text-align:center;font-family:monospace;">No barcode</div>';
        }
    })();
    @endforeach
</script>

</body>
</html>