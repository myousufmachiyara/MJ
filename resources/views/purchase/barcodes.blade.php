<!DOCTYPE html>
<html>
<head>
    <title>Barcodes — {{ $invoice->invoice_no }}</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #fff; }
        .barcode-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding: 20px;
        }
        .barcode-card {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 12px 16px;
            text-align: center;
            width: 220px;
            page-break-inside: avoid;
        }
        .barcode-card svg { width: 100%; }
        .item-name {
            font-size: 12px;
            font-weight: bold;
            margin-top: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .barcode-number {
            font-size: 11px;
            color: #555;
            margin-top: 2px;
        }
        .print-btn {
            margin: 20px;
            padding: 10px 24px;
            font-size: 16px;
            cursor: pointer;
        }
        @media print {
            .print-btn { display: none; }
            .barcode-grid { padding: 0; }
        }
    </style>
</head>
<body>

<button class="btn btn-primary print-btn" onclick="window.print()">
    🖨 Print All Barcodes
</button>

<div class="barcode-grid">
    @foreach($invoice->items as $item)
    <div class="barcode-card">
        <svg class="barcode"
             data-barcode="{{ $item->barcode_number }}"
             jsbarcode-format="CODE128"
             jsbarcode-width="1.5"
             jsbarcode-height="50"
             jsbarcode-displayvalue="false">
        </svg>
        <div class="item-name" title="{{ $item->item_name }}">
            {{ $item->item_name ?: $item->item_description }}
        </div>
        <div class="barcode-number">{{ $item->barcode_number }}</div>
    </div>
    @endforeach
</div>

<script>
    document.querySelectorAll('.barcode').forEach(function(el) {
        JsBarcode(el, el.getAttribute('data-barcode'), {
            format:       el.getAttribute('jsbarcode-format'),
            width:        parseFloat(el.getAttribute('jsbarcode-width')),
            height:       parseFloat(el.getAttribute('jsbarcode-height')),
            displayValue: false,
        });
    });
</script>

</body>
</html>