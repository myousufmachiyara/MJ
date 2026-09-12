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

            /* ─────────────────────────────────────────────────────────────
               PHYSICAL LABEL — "Jewellery Label 83 x 37mm - 1000 - 40mm
               Core" (Godex EZ120 stock). ONE 83x37mm sheet now holds TWO
               DIFFERENT ITEMS — one complete tag on the left half, one
               complete tag on the right half. Each half, top to bottom:
                 - item #
                 - gold / diamond / stone
                 - a blank vertical gap (fold this through a ring and
                   press it to itself to attach the tag)
                 - barcode
                 - certificate #
               Tear down the middle to separate the two items' tags.
               ───────────────────────────────────────────────────────────── */
            --label-w:   83mm;
            --label-h:   37mm;
            --unit-w:    41.5mm;
            --barcode-w: 14mm; /* width of the barcode+cert# block — narrower, left-aligned in the wider tag */
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

        .btn-print {
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
            background: var(--stamp);
        }
        .btn-print:hover { background: #bf3f1e; }
        .btn-print:disabled { background: #6b6b6b; cursor: not-allowed; }
        .btn-print svg { width: 15px; height: 15px; fill: #fff; }

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

        /* ── SELECTION BAR ── */
        .selection-bar {
            margin: 16px 32px 0;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .sel-btn {
            font-family: var(--mono);
            font-size: 11.5px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            background: #fff;
            border: 1px solid var(--rule);
            color: var(--accent);
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: 600;
        }
        .sel-btn:hover { background: #eeeae2; }
        .sel-count {
            font-family: var(--mono);
            font-size: 12px;
            color: #555;
        }
        .sel-count b { color: var(--ink); }

        /* ── WORKFLOW NOTE ── */
        .workflow-note {
            margin: 12px 32px 0;
            background: #fff8ec;
            border: 1px solid #e8d9b5;
            border-left: 4px solid #d6a03a;
            padding: 10px 16px;
            font-size: 12.5px;
            color: #5c4a1f;
            border-radius: 3px;
        }
        .workflow-note b { color: #3d3113; }

        /* ── PAGE ── */
        .page-wrap { padding: 20px 32px 48px; }

        .label-sheet {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        /* One physical 83x37mm sheet — two completely separate item tags,
           side by side, torn apart down the middle. */
        .label-tag {
            width: var(--label-w);
            height: var(--label-h);
            background: var(--card-bg);
            border: 1px solid var(--rule);
            box-sizing: border-box;
            overflow: hidden;
            position: relative;
            display: flex;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .label-tag.excluded { display: none; }

        /* -- one half of the sheet = one complete item tag, stacked
              top to bottom: item info / fold gap / barcode+cert -- */
        .unit {
            width: var(--unit-w);
            flex-shrink: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            padding: 1.6mm 1.8mm;
            font-family: var(--mono);
        }
        /* soft guide down the middle, screen only — this is where you tear
           to separate the two items */
        .unit + .unit { border-left: 1px dashed #ccc; }

        /* an unchecked item's half stays blank (keeps its physical space
           on the sheet) rather than disappearing */
        .unit.unit-excluded { visibility: hidden; }

        /* the second half is left blank when an odd number of items
           means there's no second item for this sheet */
        .unit.unit-blank { }

        .unit-info {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 0.4mm;
        }
        .unit-info .tag-no {
            font-weight: 700;
            font-size: 2.9mm;
            letter-spacing: 0.01em;
            line-height: 1.1;
            word-break: break-all;
            margin-bottom: 1mm;
        }
        .unit-info .tag-line {
            font-size: 2.1mm;
            line-height: 1.3;
            color: #333;
            white-space: nowrap;
        }
        .unit-info .tag-line b { color: var(--ink); font-weight: 700; }
        .unit-info .tag-line .lbl { color: #888; }

        /* the blank vertical gap in the middle of each tag — this is
           what you fold through a ring and press to itself */
        .unit-fold {
            flex: 1;
            min-height: 3mm;
            position: relative;
        }
        .unit-index {
            position: absolute;
            top: 1mm;
            left: 50%;
            transform: translateX(-50%);
            font-family: var(--mono);
            font-size: 2mm;
            color: #ccc;
            z-index: 5;
        }
        .unit-select-wrap {
            position: absolute;
            bottom: 1mm;
            left: 50%;
            transform: translateX(-50%);
            z-index: 5;
        }
        .unit-select-wrap input { width: 14px; height: 14px; cursor: pointer; }

        .unit-barcode {
            width: var(--barcode-w);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            align-self: flex-start;
            gap: 0.3mm;
        }
        .unit-barcode svg {
            width: 100%;
            height: auto;
            display: block;
        }
        .tag-cert {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.3mm;
        }
        .tag-cert .cert-lbl {
            font-size: 1.5mm;
            color: #888;
            white-space: nowrap;
        }
        .tag-cert b {
            font-size: 2.1mm;
            color: var(--ink);
            font-weight: 600;
            white-space: nowrap;
        }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 80px 20px; color: #aaa; }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
        .empty-state p { font-family: var(--mono); font-size: 13px; letter-spacing: 0.08em; }

        /* ── PRINT STYLES ── */
        @media print {
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { size: 83mm 37mm; margin: 0; }
            body { background: #fff; }
            .controls, .workflow-note, .selection-bar { display: none !important; }
            .page-wrap { padding: 0; }
            .label-sheet { gap: 0; }
            .label-tag { border: none; margin: 0; page-break-after: always; break-after: page; }
            .label-tag:last-child { page-break-after: auto; break-after: auto; }
            .unit-index, .unit-select-wrap { display: none !important; }
            .unit + .unit { border-left: none; } /* screen-only guide, nothing to print here */
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
        <div class="meta-chip">Label&nbsp;<span id="labelSizeChip">83×37mm (2 items/sheet)</span></div>
    </div>

    <div class="controls-right">
        <a href="{{ url()->previous() }}" class="btn-back">← Back</a>
        <button class="btn-print" id="printBtn" onclick="printSelected()">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
            Print Selected
        </button>
    </div>
</div>

@if($invoice->items->count())
<div class="selection-bar">
    <button type="button" class="sel-btn" onclick="selectAll(true)">Select All</button>
    <button type="button" class="sel-btn" onclick="selectAll(false)">Select None</button>
    <span class="sel-count"><b id="selCount">{{ $invoice->items->count() }}</b> of {{ $invoice->items->count() }} selected</span>
</div>

<div class="workflow-note">
    <b>How this label prints:</b> each 83×37mm sheet holds TWO items — one complete tag on the left half, one complete tag
    on the right half. Each half has (top to bottom) the item #, gold/diamond/stone, a blank gap, then the barcode and
    certificate #. Tear the sheet down the middle to separate the two items' tags, then fold each one at its blank gap
    through a ring and press it to itself. Load your labels as usual and click <b>“Print Selected.”</b> Uncheck any items
    you don't want to print in this batch — an unchecked item's half prints blank rather than shifting the layout.
</div>
@endif

{{-- ── PAGE ── --}}
<div class="page-wrap">

    @if($invoice->items->count())
    @php
        $pairs = collect($invoice->items)->chunk(2)->values();
        $itemCounter = 0;
    @endphp
    <div id="labelSheet" class="label-sheet">
        @foreach($pairs as $sheetIndex => $pair)
        <div class="label-tag" data-index="{{ $sheetIndex }}">
            @foreach($pair as $item)
            @php
                $itemCounter++;
                $diamondCt = $item->diamond_total_ct ?? 0;
                $stoneCt   = $item->stone_total_ct ?? 0;
            @endphp
            <div class="unit" data-item-id="{{ $item->id }}">
                <div class="unit-info">
                    <div class="tag-no">{{ $item->barcode_number }}</div>
                    <div class="tag-line"><span class="lbl">Au</span> <b>{{ number_format($item->net_weight, 3) }}</b> gm</div>
                    <div class="tag-line"><span class="lbl">Dia</span> <b>{{ number_format($diamondCt, 3) }}</b> ct</div>
                    <div class="tag-line"><span class="lbl">Stn</span> <b>{{ number_format($stoneCt, 3) }}</b> ct</div>
                </div>
                <div class="unit-fold">
                    <span class="unit-index">{{ str_pad($itemCounter, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="unit-select-wrap">
                        <input type="checkbox" class="label-select" checked onchange="updateSelectionCount()">
                    </span>
                </div>
                <div class="unit-barcode">
                    <svg id="bc-{{ $item->id }}"></svg>
                    <div class="tag-cert">
                        <span class="cert-lbl">Cert#</span>
                        <b>{{ $item->certificate_no ?: '—' }}</b>
                    </div>
                </div>
            </div>
            @endforeach
            @if($pair->count() < 2)
            <div class="unit unit-blank"></div>
            @endif
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
    const invoiceId = {{ $invoice->id }};
    const markPrintedUrl = @json(route('purchase_invoices.mark_printed', $invoice->id));

    // ===== selection helpers =====
    window.selectAll = function(state) {
        document.querySelectorAll('.label-select').forEach(cb => cb.checked = state);
        updateSelectionCount();
    };

    window.updateSelectionCount = function() {
        const total    = document.querySelectorAll('.label-select').length;
        const selected = document.querySelectorAll('.label-select:checked').length;
        document.getElementById('selCount').textContent = selected;
        document.getElementById('printBtn').disabled = selected === 0;
    };

    // ===== print only the selected items =====
    // Each physical sheet can hold 2 different items. If only one of the
    // two is checked, that sheet still prints (it's one physical label)
    // but the unchecked item's half is left blank instead of printed.
    window.printSelected = function() {
        const sheets = document.querySelectorAll('.label-tag');
        const selectedItemIds = [];

        sheets.forEach(sheet => {
            const units = sheet.querySelectorAll('.unit[data-item-id]');
            let anySelected = false;

            units.forEach(u => {
                const cb = u.querySelector('.label-select');
                const checked = cb ? cb.checked : false;
                u.classList.toggle('unit-excluded', !checked);
                if (checked) {
                    anySelected = true;
                    selectedItemIds.push(u.dataset.itemId);
                }
            });

            sheet.classList.toggle('excluded', !anySelected);
        });

        if (selectedItemIds.length === 0) return;

        const restore = () => {
            sheets.forEach(sheet => {
                sheet.classList.remove('excluded');
                sheet.querySelectorAll('.unit-excluded').forEach(u => u.classList.remove('unit-excluded'));
            });
            window.removeEventListener('afterprint', restore);
        };
        window.addEventListener('afterprint', restore);

        window.print();

        // Mark only the printed items as printed (fire-and-forget; safe to fail silently).
        fetch(markPrintedUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ item_ids: selectedItemIds }),
        }).catch(err => console.error('Failed to mark items as printed', err));
    };

    // ===== render barcodes =====
    @foreach($invoice->items as $item)
    (function() {
        const el      = document.getElementById('bc-{{ $item->id }}');
        const barcode = @json($item->barcode_number);
        if (el && barcode) {
            try {
                JsBarcode(el, barcode, {
                    format:       'CODE128',
                    width:        1.2,
                    height:       42,
                    displayValue: false,
                    margin:       0,
                    background:   '#ffffff',
                    lineColor:    '#0a0a0a',
                });
            } catch (e) {
                el.parentElement.innerHTML = '<div style="font-size:8px;color:#c00;text-align:center;">Invalid barcode</div>';
            }
        } else if (el) {
            el.parentElement.innerHTML = '<div style="font-size:7px;color:#bbb;text-align:center;font-family:monospace;">No barcode</div>';
        }
    })();
    @endforeach

    updateSelectionCount();
</script>

</body>
</html>