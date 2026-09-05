<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcodes — {{ $invoice->invoice_no }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600;700&family=IBM+Plex+Sans:wght@400;600;700&display=swap" rel="stylesheet">
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
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 3px solid var(--stamp);
        }

        .controls-left { display: flex; flex-direction: column; gap: 2px; }

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

        .controls-meta { display: flex; gap: 24px; align-items: center; flex-wrap: wrap; }

        .meta-chip {
            font-size: 12px;
            font-family: var(--mono);
            color: rgba(255,255,255,0.65);
            letter-spacing: 0.05em;
        }
        .meta-chip span { color: #fff; font-weight: 600; }

        .controls-right { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

        .btn-print, .btn-print-back {
            border: none;
            padding: 9px 20px;
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
            color: #fff;
        }
        .btn-print { background: var(--stamp); }
        .btn-print:hover { background: #bf3f1e; }
        .btn-print-back { background: #2e5f8a; }
        .btn-print-back:hover { background: #234a6c; }
        .btn-print svg, .btn-print-back svg { width: 15px; height: 15px; fill: #fff; }

        .btn-back {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.8);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 9px 18px;
            font-family: var(--sans);
            font-size: 13px;
            cursor: pointer;
            border-radius: 3px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.18); color: #fff; }

        /* ── WORKFLOW NOTE ── */
        .workflow-note {
            margin: 16px 32px 0;
            background: #fff8ec;
            border: 1px solid #e8d9b5;
            border-left: 4px solid #d6a03a;
            padding: 10px 16px;
            font-size: 12.5px;
            color: #5c4a1f;
            border-radius: 3px;
        }
        .workflow-note b { color: #3d3113; }

        /* ── PAGE / TABS ── */
        .page-wrap { padding: 20px 32px 48px; }

        .side-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--rule);
        }
        .side-tab {
            font-family: var(--mono);
            font-size: 12px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 8px 14px;
            cursor: pointer;
            color: #888;
            font-weight: 600;
        }
        .side-tab.active { color: var(--accent); border-bottom-color: var(--stamp); }

        /* ── LABEL SHEETS (also the print layout) ── */
        .label-sheet {
            display: none;
            flex-wrap: wrap;
            gap: 14px;
        }
        .label-sheet.active-preview { display: flex; }

        /* One physical thermal label — exact print dimensions. */
        .label {
            width: 87mm;
            height: 37mm;
            background: var(--card-bg);
            border: 1px solid var(--rule);
            border-radius: 1.2mm;
            box-sizing: border-box;
            overflow: hidden;
            position: relative;
            padding: 2mm 3mm;
            page-break-after: always;
            break-after: page;
        }
        .label:last-child { page-break-after: auto; break-after: auto; }

        .label-index {
            position: absolute;
            top: 1mm;
            left: 1.5mm;
            font-family: var(--mono);
            font-size: 2mm;
            color: #ccc;
        }

        /* -- FRONT label content -- */
        .front-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .front-label .barcode-wrap {
            width: 100%;
            padding: 0 1mm;
        }
        .front-label .barcode-wrap svg {
            width: 100%;
            height: auto;
            display: block;
        }
        .front-label .item-no {
            font-family: var(--mono);
            font-weight: 700;
            font-size: 4.2mm;
            letter-spacing: 0.04em;
            margin-top: 0.8mm;
        }
        .front-label .cert-no {
            font-family: var(--mono);
            font-size: 2.4mm;
            color: #666;
            margin-top: 0.6mm;
            letter-spacing: 0.03em;
        }
        .front-label .cert-no b { color: var(--ink); font-weight: 600; }

        /* -- BACK label content -- */
        .back-label {
            display: flex;
            flex-direction: column;
            font-family: var(--mono);
        }
        .back-top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            border-bottom: 0.3mm solid var(--ink);
            padding-bottom: 0.8mm;
            margin-bottom: 1mm;
        }
        .back-top .bc-no { font-size: 2.6mm; font-weight: 700; letter-spacing: 0.03em; }
        .back-top .gold-wt { font-size: 2.6mm; font-weight: 700; }
        .back-top .gold-wt .u { font-weight: 400; color: #555; font-size: 2mm; }

        .back-body {
            flex: 1;
            display: flex;
            gap: 3mm;
            min-height: 0;
        }
        .back-col {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .back-col-title {
            font-size: 2.1mm;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #666;
            border-bottom: 0.2mm dotted #bbb;
            padding-bottom: 0.4mm;
            margin-bottom: 0.5mm;
        }
        .back-line {
            display: flex;
            justify-content: space-between;
            gap: 1.5mm;
            font-size: 2.3mm;
            line-height: 1.5;
            white-space: nowrap;
        }
        .back-line .nm {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #333;
        }
        .back-line .ct { font-weight: 600; flex-shrink: 0; }
        .back-line.muted { color: #aaa; font-style: italic; }
        .back-total-line {
            display: flex;
            justify-content: space-between;
            font-size: 2.3mm;
            font-weight: 700;
            border-top: 0.2mm dashed #999;
            margin-top: auto;
            padding-top: 0.5mm;
        }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 80px 20px; color: #aaa; }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
        .empty-state p { font-family: var(--mono); font-size: 13px; letter-spacing: 0.08em; }

        /* ── PRINT STYLES ── */
        @media print {
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { size: 87mm 37mm; margin: 0; }
            body { background: #fff; }
            .controls, .workflow-note, .side-tabs { display: none !important; }
            .page-wrap { padding: 0; }
            .label-sheet { display: none !important; gap: 0; }
            .label { border: none; border-radius: 0; margin: 0; }
            .label-index { display: none; }

            body[data-print-side="front"] .front-sheet { display: flex !important; }
            body[data-print-side="back"]  .back-sheet  { display: flex !important; }
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
        <div class="meta-chip">Date&nbsp;<span>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</span></div>
        <div class="meta-chip">Vendor&nbsp;<span>{{ $invoice->vendor->name ?? '—' }}</span></div>
        <div class="meta-chip">Items&nbsp;<span>{{ $invoice->items->count() }}</span></div>
        <div class="meta-chip">Label&nbsp;<span>87×37mm</span></div>
    </div>

    <div class="controls-right">
        <a href="{{ url()->previous() }}" class="btn-back">← Back</a>
        <button class="btn-print" onclick="printSide('front')">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
            Print Fronts (Barcode)
        </button>
        <button class="btn-print-back" onclick="printSide('back')">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
            Print Backs (Weight/Ct)
        </button>
    </div>
</div>

@if($invoice->items->count())
<div class="workflow-note">
    <b>Two-sided label workflow:</b> load blank 87×37mm labels and click <b>“Print Fronts”</b> first — it prints the barcode side for every item, in order.
    Once that batch is done, flip the same stack of labels over in the printer feed (don't reorder them) and click <b>“Print Backs”</b> to print the
    gold/diamond/stone breakdown on the reverse of each label.
</div>
@endif

{{-- ── PAGE ── --}}
<div class="page-wrap">

    @if($invoice->items->count())
    <div class="side-tabs">
        <button type="button" class="side-tab active" data-side="front" onclick="switchPreview('front')">Front — Barcode ({{ $invoice->items->count() }})</button>
        <button type="button" class="side-tab" data-side="back" onclick="switchPreview('back')">Back — Weight / Ct ({{ $invoice->items->count() }})</button>
    </div>

    {{-- FRONT SHEET: barcode + item no + certificate # --}}
    <div id="frontSheet" class="label-sheet front-sheet active-preview">
        @foreach($invoice->items as $i => $item)
        <div class="label front-label" data-index="{{ $i }}">
            <span class="label-index">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
            <div class="barcode-wrap">
                <svg id="bc-{{ $i }}"></svg>
            </div>
            <div class="item-no">{{ $item->barcode_number }}</div>
            <div class="cert-no">Cert# <b>{{ $item->certificate_no ?: '—' }}</b></div>
        </div>
        @endforeach
    </div>

    {{-- BACK SHEET: gold wt + diamond breakdown + stone breakdown --}}
    <div id="backSheet" class="label-sheet back-sheet">
        @foreach($invoice->items as $i => $item)
        @php
            $diamondParts = $item->diamond_parts;
            $stoneParts   = $item->stone_parts;
        @endphp
        <div class="label back-label" data-index="{{ $i }}">
            <span class="label-index">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
            <div class="back-top">
                <span class="bc-no">{{ $item->barcode_number }}</span>
                <span class="gold-wt">{{ number_format($item->net_weight, 3) }}<span class="u">&nbsp;gms Au</span></span>
            </div>
            <div class="back-body">
                <div class="back-col">
                    <div class="back-col-title">Diamond</div>
                    @forelse($diamondParts as $part)
                        <div class="back-line">
                            <span class="nm">{{ $part['name'] }}</span>
                            <span class="ct">{{ number_format($part['ct'], 3) }}</span>
                        </div>
                    @empty
                        <div class="back-line muted">— none —</div>
                    @endforelse
                    <div class="back-total-line">
                        <span>Total Ct</span>
                        <span>{{ number_format($item->diamond_total_ct, 3) }}</span>
                    </div>
                </div>
                <div class="back-col">
                    <div class="back-col-title">Stone</div>
                    @forelse($stoneParts as $part)
                        <div class="back-line">
                            <span class="nm">{{ $part['name'] }}</span>
                            <span class="ct">{{ number_format($part['ct'], 3) }}</span>
                        </div>
                    @empty
                        <div class="back-line muted">— none —</div>
                    @endforelse
                    <div class="back-total-line">
                        <span>Total Ct</span>
                        <span>{{ number_format($item->stone_total_ct, 3) }}</span>
                    </div>
                </div>
            </div>
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
    // ===== screen preview tab switching (has no effect on the print output —
    // print visibility is controlled purely by body[data-print-side] in CSS) =====
    window.switchPreview = function(side) {
        document.querySelectorAll('.label-sheet').forEach(el => el.classList.remove('active-preview'));
        document.getElementById(side === 'front' ? 'frontSheet' : 'backSheet').classList.add('active-preview');
        document.querySelectorAll('.side-tab').forEach(t => t.classList.toggle('active', t.dataset.side === side));
    };

    // ===== print a single side =====
    window.printSide = function(side) {
        document.body.setAttribute('data-print-side', side);
        window.print();
    };

    // ===== render front-side barcodes =====
    @foreach($invoice->items as $i => $item)
    (function() {
        const el      = document.getElementById('bc-{{ $i }}');
        const barcode = @json($item->barcode_number);
        if (el && barcode) {
            try {
                JsBarcode(el, barcode, {
                    format:       'CODE128',
                    width:        1.6,
                    height:       46,
                    displayValue: false,
                    margin:       0,
                    background:   '#ffffff',
                    lineColor:    '#0a0a0a',
                });
            } catch (e) {
                el.parentElement.innerHTML = '<div style="font-size:10px;color:#c00;text-align:center;">Invalid barcode</div>';
            }
        } else if (el) {
            el.parentElement.innerHTML = '<div style="font-size:9px;color:#bbb;text-align:center;font-family:monospace;">No barcode</div>';
        }
    })();
    @endforeach
</script>

</body>
</html>
