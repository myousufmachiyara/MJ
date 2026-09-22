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
            --barcode-w: 15mm; /* the physical max this half-tag can hold (unit is 41.5mm minus 1.8mm padding each side); pushed to the ceiling to give the barcode every fraction of a mm it can get */
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
            /* FIX (tray/barcode/cert touching the bottom edge): this used to
               be `0mm 1.8mm` — zero top/bottom padding — combined with
               .unit-fold's flex:1 (which claims ALL leftover vertical space),
               that left nothing to stop .unit-barcode from sitting flush
               against the physical bottom edge of the 37mm label. A small
               bottom padding here reserves a little breathing room for BOTH
               halves uniformly, without changing the overall stacked
               item-info/fold/barcode structure. */
            padding: 0mm 1.8mm 2mm;
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

        /* the RIGHT-hand item is mirrored top-to-bottom: its barcode sits
           at the top right, item info at the bottom — so the two tags
           aren't just duplicates sitting in an identical layout */

        .unit-info {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 0.4mm;
            margin-top: 12.5mm; /* pushes item #/gold/dia/stone further down */
        }
        .unit:nth-child(2) .unit-info {
            margin-top: 2mm;
            align-items: flex-end;
            text-align: right;
            margin-left: auto;
            margin-right: 0;
            width: fit-content;
        }
        .unit-info .tag-no {
            font-weight: 700;
            font-size: 1.8mm;
            letter-spacing: 0.01em;
            line-height: 1;
            word-break: break-all;
            margin-bottom: 0.3mm;
        }
        .unit-info .tag-line {
            font-size: 2mm;
            font-weight: 600;
            line-height: 1.3;
            color: #000000;
            white-space: nowrap;
        }
        .unit-info .tag-line b { color: var(--ink); font-weight: 700; }
        .unit-info .tag-line .lbl { color: #000000; }

        /* the blank vertical gap in the middle of each tag — this is
           what you fold through a ring and press it to itself */
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
            display: block;
            gap: 0mm;
        }
        .unit-barcode svg {
            width: 100%;
            height: auto;
            display: block;
            /* FIX (unscannable barcode): forces the browser/printer to keep
               each bar's edges pixel-crisp instead of anti-aliasing them as
               the SVG scales to fit --barcode-w. At the module widths this
               18mm ceiling forces for a long barcode, any blur/soft edging
               from scaling is often the difference between a scanner
               locking on and not. */
            shape-rendering: crispEdges;
        }
        /* mirrored: right unit's barcode+cert hug the right edge instead
           (top right, since the unit itself is now reversed top-to-bottom).
           Uses margin-left:auto on a fixed-width block rather than flex
           cross-axis alignment, which is a more bulletproof way to force
           right-alignment regardless of the parent's own flex settings. */
        .unit:nth-child(2) .unit-barcode {
            margin-left: auto;
            margin-right: 0;
            margin-bottom: 25mm;
        }
        .tag-cert {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .unit:nth-child(2) .tag-cert {
            align-items: flex-end;
            width: fit-content;
            margin-left: auto;
            margin-right: 0;
        }
        .tag-cert .cert-lbl {
            font-size: 1.5mm;
            color: #000000;
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
            .controls, .selection-bar { display: none !important; }
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
                    <div class="tag-line"><b>GOLD:</b> {{ number_format($item->net_weight, 3) }}</div>
                    <div class="tag-line"><b>DIA:</b> {{ number_format($diamondCt, 3) }}</div>
                    <div class="tag-line"><b>STONE:</b> {{ number_format($stoneCt, 3) }}</div>
                </div>
                <div class="unit-fold">
                    <span class="unit-index">{{ str_pad($itemCounter, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="unit-select-wrap">
                        <input type="checkbox" class="label-select" checked onchange="updateSelectionCount()">
                    </span>
                </div>

                <div class="unit-barcode">
                    <div class="tag-cert">
                        <b><span class="cert-lbl">{{ $item->tray_no ?: '—' }}</span></b>
                    </div>
                    <svg id="bc-{{ $item->id }}"></svg>
                    <div class="tag-cert">
                        <b><span class="cert-lbl">Cert#: {{ $item->certificate_no ?: '—' }}</span></b>
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
                    // FIX (unscannable barcode), two changes here:
                    // 1. width bumped from 1.2 -> 2 (an integer). JsBarcode
                    //    draws each bar this many SVG px wide; a fractional
                    //    value (1.2) means bar edges land on fractional
                    //    pixels, which the browser then anti-aliases/blurs
                    //    when the SVG is scaled to fit --barcode-w. Integer
                    //    widths keep every bar edge crisp through that scale.
                    // 2. marginLeft/marginRight raised from 0 -> 10. A
                    //    barcode needs a blank "quiet zone" on each side for
                    //    a scanner to find where it starts/stops — at 0
                    //    there was none at all, which on its own can be
                    //    enough to make an otherwise-fine barcode unreadable.
                    // Because --barcode-w scales the whole SVG to a fixed physical
                    // width regardless of these pixel values, this quiet zone
                    // is reserved as a proportion of that 18mm, not extra space
                    // on top of it — some of the already-tight width now goes
                    // to margin instead of bars. That's an unavoidable trade:
                    // without it most scanners can't lock on at all. Note also
                    // that at 18mm, a long barcode (12+ characters, e.g. the
                    // legacy MJT-/MJ- format) is right at or below what a
                    // typical label printer can resolve reliably — this change
                    // gets it as scannable as this physical width allows, but
                    // shorter barcode text (e.g. the newer {code}-00001 codes)
                    // will scan more reliably than the long legacy ones.
                    width:        2,
                    height:       45,
                    displayValue: false,
                    marginTop:    0,
                    marginBottom: 0,
                    marginLeft:   10,
                    marginRight:  10,
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