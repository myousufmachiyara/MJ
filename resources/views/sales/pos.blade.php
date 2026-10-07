@extends('layouts.app')

@section('title', 'Sale | POS')

{{--
    Sale Invoice POS — barcode-driven counter-sale screen.

    This is an ALTERNATE UI for the existing Sale Invoice module, not a
    separate sales system:
      - It submits to the exact same route('sale_invoices.store') /
        SaleInvoiceController::store() action as resources/views/sales/
        create.blade.php — same invoice numbering, same stock/accounting
        logic (createSaleAccountingEntries()), same customer/payment
        mechanism.
      - Its barcode lookup (route('sale_invoices.pos_scan')) searches ONLY
        PurchaseInvoiceItem — purchased items ARE this app's sellable
        products, there is no separate Product Master — and deliberately
        returns none of the costing fields (Making Cost, Tax breakdown,
        Cost Price, Profit/Margin, Net Weight-as-cost-input) that the full
        Sale Invoice screen shows. See SaleInvoiceController::posScan().
      - The Selling Price shown/used here is the flat, manually-set price
        already saved on the purchased item (Selling Price screen / Purchase
        Invoice screens) — POS only ever retrieves it, never calculates it
        from rate/purity/making charges.

    FIX (USD selling price not picked up): POS used to be hard-wired to AED
    (a hidden currency=AED input, and posScan() only ever read the AED
    `selling_price` column), so an item priced only in USD
    (`selling_price_usd`) was rejected with "Selling price is not set".
    There is now a Currency selector in the header:
      - AED (default) — exactly the previous behavior, reads selling_price.
      - USD — reads selling_price_usd, and the invoice is saved with
        currency=USD plus the Exchange Rate entered here, which is the same
        currency/exchange_rate path the full Sale Invoice screen already
        uses for USD invoices (SaleInvoiceController::store() computes
        net_amount_aed from it).
    FIX (POS reads and shows BOTH prices): posScan() now returns an item's
    AED and USD prices together, and the cart shows both columns side by
    side. An item is only refused when NEITHER price is set. The Currency
    selector decides which price is billed (and what the invoice currency
    is) and can be changed at any time — the cart re-prices instantly from
    the two stored prices. An item with no price in the selected currency
    is highlighted and blocks checkout until it is removed or the currency
    is switched. The two prices are independent; nothing is ever converted
    from one to the other.

    Kept intentionally simple: no is_taxable/VAT%, no gold/diamond rate
    inputs, no parts — those only matter for the detailed costing the POS
    is explicitly not meant to expose. The invoice is created as a
    Non-Taxable Sale Invoice (is_taxable = 0, hidden below); if a taxable
    POS sale is ever needed, that invoice can still be reopened and edited
    from the regular Sale Invoice edit screen like any other invoice.
--}}

@section('content')
<div class="row">
  <div class="col-12">

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form id="posForm" action="{{ route('sale_invoices.store') }}" method="POST">
      @csrf

      {{-- Fields the full Sale Invoice screen exposes but POS intentionally hides — fixed, sane defaults. --}}
      <input type="hidden" name="is_taxable" value="0">
      <input type="hidden" name="invoice_vat_percent" value="0">
      <input type="hidden" name="net_amount" id="net_amount" value="0">

      {{-- Hidden items[] inputs — rebuilt from the cart array on every scan/remove. --}}
      <div id="posHiddenInputs"></div>

      {{-- ===================== HEADER ===================== --}}
      <section class="card mb-3">
        <div class="card-body py-3">
          <div class="row align-items-end g-3">
            <div class="col-auto">
              <h2 class="card-title mb-0"><i class="fas fa-cash-register text-primary"></i> Point of Sale</h2>
              <small class="text-muted">Fast counter sale — scans create a regular Sale Invoice</small>
            </div>
            <div class="col-md-2">
              <label class="fw-bold">Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-3">
              <label class="fw-bold">Customer <span class="text-danger">*</span></label>
              <select name="customer_id" id="customer_id" class="form-control select2-js" required>
                <option value="">Select Customer</option>
                @foreach ($customers as $customer)
                  <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-1">
              <label class="fw-bold">Currency</label>
              <select name="currency" id="pos_currency" class="form-control" required>
                <option value="AED" selected>AED</option>
                <option value="USD">USD</option>
              </select>
            </div>
            <div class="col-md-2 d-none" id="pos_exchange_rate_wrap">
              <label class="fw-bold">Exchange Rate (AED per USD)</label>
              <input type="number" step="any" min="0" name="exchange_rate" id="pos_exchange_rate"
                     class="form-control" value="3.6725" disabled>
            </div>
            <div class="col-auto ms-auto">
              <a href="{{ route('sale_invoices.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-list"></i> Invoices
              </a>
            </div>
          </div>
        </div>
      </section>

      <div class="row">
        {{-- ===================== MAIN: SCAN + CART ===================== --}}
        <div class="col-lg-8">

          <section class="card mb-3 border-primary shadow-sm">
            <div class="card-body py-3 bg-primary bg-opacity-10">
              <label class="text-light fw-bold small mb-1"><i class="fas fa-barcode"></i> Scan Barcode</label>
              <div class="input-group input-group-lg">
                <input type="text" id="pos_barcode_input" class="form-control" autocomplete="off"
                       placeholder="Scan item barcode…" autofocus>
                <button type="button" class="btn btn-primary fw-bold" id="pos_scan_btn">
                  <i class="fas fa-search"></i>
                </button>
              </div>
              <div id="pos_scan_result" class="alert mt-2 mb-0 py-2 px-3 d-none" role="alert"></div>
            </div>
          </section>

          <section class="card">
            <header class="card-header">
              <h2 class="card-title">Cart</h2>
            </header>
            <div class="table-responsive">
              <table class="table table-bordered table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr class="text-center">
                    <th width="3%">#</th>
                    <th>Item</th>
                    <th>Barcode / Code</th>
                    <th>Category</th>
                    <th>Total Wt (g)</th>
                    <th>Gold Wt (g)</th>
                    <th>Diamond (Ct)</th>
                    <th>Stone (Ct)</th>
                    <th class="pos-col-aed">Price (AED)</th>
                    <th class="pos-col-usd">Price (USD)</th>
                    <th class="text-danger" style="min-width:150px;">Discount<br><small class="fw-normal text-muted">Amt or %</small></th>
                    <th>Net (<span class="pos-currency-label">AED</span>)</th>
                    <th width="5%">Action</th>
                  </tr>
                </thead>
                <tbody id="posCartBody">
                  <tr id="posCartEmptyRow">
                    <td colspan="13" class="text-center text-muted py-4">
                      <i class="fas fa-barcode fa-2x d-block mb-2 opacity-25"></i>
                      No items scanned yet
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

        </div>

        {{-- ===================== SIDEBAR: SUMMARY + CHECKOUT ===================== --}}
        <div class="col-lg-4">
          <section class="card mb-3 sticky-top" style="top: 15px;">
            <header class="card-header">
              <h2 class="card-title">Summary</h2>
            </header>
            <div class="card-body">
              <table class="table table-sm mb-3">
                <tbody>
                  <tr><td>Number of Items</td><td class="text-end fw-bold" id="pos_summary_items">0</td></tr>
                  <tr><td>Total Weight</td><td class="text-end" id="pos_summary_weight">0.000 g</td></tr>
                  <tr><td>Total Gold Weight</td><td class="text-end" id="pos_summary_gold">0.000 g</td></tr>
                  <tr><td>Total Diamond Weight</td><td class="text-end" id="pos_summary_diamond">0.000 Ct</td></tr>
                  <tr><td>Total Stone Weight</td><td class="text-end" id="pos_summary_stone">0.000 Ct</td></tr>
                </tbody>
              </table>
              <div id="pos_total_aed_box" class="d-flex justify-content-between align-items-center p-2 border rounded mb-2">
                <span class="fw-bold text-light">Total (AED)</span>
                <span class="fw-bold fs-5 text-light" id="pos_summary_aed">0.00</span>
              </div>
              <div id="pos_total_usd_box" class="d-flex justify-content-between align-items-center p-2 border rounded mb-2">
                <span class="fw-bold text-light">Total (USD)</span>
                <span class="fw-bold fs-5 text-light" id="pos_summary_usd">0.00</span>
              </div>
              <div class="small text-muted mb-1">Billing in <b class="pos-currency-label">AED</b> — change the Currency at the top to bill in the other.</div>
              <div id="pos_missing_price_msg" class="alert alert-danger py-2 px-3 small d-none mb-3"></div>

              {{-- ===== DISCOUNTS (item discounts are in the cart; this is the overall one) ===== --}}
              <table class="table table-sm mb-2">
                <tbody>
                  <tr><td class="text-danger">Item Discounts</td><td class="text-end fw-bold text-danger" id="pos_summary_item_disc">0.00</td></tr>
                  <tr><td>Subtotal</td><td class="text-end" id="pos_summary_subtotal">0.00</td></tr>
                </tbody>
              </table>
              <label class="fw-bold text-danger">Invoice Discount <small class="text-muted fw-normal">(overall)</small></label>
              <div class="input-group mb-1">
                <input type="number" step="any" min="0" name="invoice_discount_value" id="pos_invoice_discount_value"
                       class="form-control border-danger" value="0" placeholder="0">
                <select name="invoice_discount_type" id="pos_invoice_discount_type" class="form-select border-danger" style="max-width:90px;flex:0 0 90px;">
                  <option value="amount">Amount</option>
                  <option value="percent">%</option>
                </select>
              </div>
              <div class="d-flex justify-content-between small mb-2">
                <span class="text-muted">Invoice discount</span>
                <span class="fw-bold text-danger" id="pos_summary_inv_disc">0.00</span>
              </div>
              <div class="d-flex justify-content-between align-items-center p-2 bg-success bg-opacity-10 border border-success rounded mb-3">
                <span class="fw-bold">Net Payable (<span class="pos-currency-label">AED</span>)</span>
                <span class="fw-bold fs-4 text-success" id="pos_summary_net">0.00</span>
              </div>

              <label class="fw-bold">Payment Method <span class="text-danger">*</span></label>
              <select name="payment_method" id="payment_method" class="form-control mb-2" required>
                <option value="">Select Payment Method</option>
                <option value="cash">Cash</option>
                <option value="credit">Credit</option>
                <option value="cheque">Cheque</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>

              <div id="pos_cash_fields" class="mb-2 d-none">
                <label>Amount Received (Cash)</label>
                <input type="number" step="any" name="cash_amount_paid" class="form-control" placeholder="Leave blank = full payment">
              </div>

              <div id="pos_cheque_fields" class="mb-2 d-none">
                <label>Bank</label>
                <select name="bank_name" class="form-control select2-js mb-2">
                  <option value="">Select Bank</option>
                  @foreach ($banks as $bank)
                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                  @endforeach
                </select>
                <label>Cheque No</label>
                <input type="text" name="cheque_no" class="form-control mb-2">
                <label>Cheque Date</label>
                <input type="date" name="cheque_date" class="form-control mb-2">
                <label>Cheque Amount</label>
                <input type="number" step="any" name="cheque_amount" class="form-control" placeholder="Leave blank = full">
              </div>

              <div id="pos_bank_transfer_fields" class="mb-2 d-none">
                <label>Transfer From Bank</label>
                <select name="transfer_from_bank" class="form-control select2-js mb-2">
                  <option value="">Select Bank</option>
                  @foreach ($banks as $bank)
                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                  @endforeach
                </select>
                <label>Transaction Ref No</label>
                <input type="text" name="transaction_id" class="form-control mb-2">
                <label>Transfer Amount</label>
                <input type="number" step="any" name="transfer_amount" class="form-control" placeholder="Leave blank = full">
              </div>

              <button type="submit" class="btn btn-success btn-lg w-100" id="pos_checkout_btn" disabled>
                <i class="fas fa-check-circle"></i> Checkout &amp; Save Invoice
              </button>
            </div>
          </section>
        </div>
      </div>
    </form>

  </div>
</div>

<script>
$(document).ready(function () {
    const POS_SCAN_URL = '{{ route("sale_invoices.pos_scan") }}';

    // Cart is the single client-side source of truth for what's been
    // scanned. Each entry is exactly what posScan() returned — no pricing
    // or weight math happens here, everything is just retrieved/displayed.
    let cart = [];

    // Currency the invoice is billed in (submitted as the invoice's
    // currency). Every cart item carries BOTH prices; this only decides
    // which one is used. Can be changed at any time.
    let activeCurrency = 'AED';

    // The price of `item` in `cur` ('AED' | 'USD'), or null if that
    // currency's price isn't set on the item.
    function priceIn(item, cur) {
        const v = cur === 'USD' ? item.selling_price_usd : item.selling_price_aed;
        return (v === null || v === undefined || v === '') ? null : parseFloat(v);
    }

    // ── Discounts (mirror SaleInvoiceController::resolveDiscount) ────────
    // Each cart item carries discount_type ('amount' | 'percent') and
    // discount_value, entered in the invoice's billing currency. The server
    // recalculates everything; this is only for the on-screen totals.
    function round2(v) { return Math.round((v + Number.EPSILON) * 100) / 100; }
    function resolveDiscountJs(type, value, base) {
        value = Math.max(0, parseFloat(value) || 0);
        if ((type !== 'amount' && type !== 'percent') || value <= 0 || base <= 0) return 0;
        const amount = type === 'percent' ? base * Math.min(value, 100) / 100 : value;
        return round2(Math.min(amount, base));
    }
    function itemBase(item) { return priceIn(item, activeCurrency) || 0; }
    function itemDiscount(item) { return resolveDiscountJs(item.discount_type, item.discount_value, itemBase(item)); }
    function itemNet(item) { return itemBase(item) - itemDiscount(item); }

    $('.select2-js').select2({ width: '100%' });

    function focusScanInput() {
        $('#pos_barcode_input').val('').focus();
    }

    function showScanResult(msg, type) {
        const el = $('#pos_scan_result');
        el.removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-' + type).html(msg);
        clearTimeout(window._posScanTimer);
        window._posScanTimer = setTimeout(() => el.addClass('d-none'), 4000);
    }

    function esc(val) {
        return $('<div>').text(val === null || val === undefined ? '' : val).html();
    }

    function fmt(n, d) {
        return (parseFloat(n) || 0).toFixed(d === undefined ? 3 : d);
    }

    function renderCart() {
        const body = $('#posCartBody').empty();

        if (cart.length === 0) {
            body.append(
                '<tr id="posCartEmptyRow"><td colspan="13" class="text-center text-muted py-4">' +
                '<i class="fas fa-barcode fa-2x d-block mb-2 opacity-25"></i>No items scanned yet</td></tr>'
            );
        }

        cart.forEach((item, idx) => {
            // Both prices side by side; the one being billed is bold + tinted,
            // and a missing price in the billed currency is flagged in red.
            const aed = priceIn(item, 'AED');
            const usd = priceIn(item, 'USD');
            const cell = function (val, cur) {
                const active = cur === activeCurrency;
                if (val === null) {
                    return '<td class="text-center ' + (active ? 'table-danger text-danger fw-bold' : 'text-muted') + '">' +
                           (active ? 'not set' : '—') + '</td>';
                }
                return '<td class="text-end ' + (active ? 'fw-bold table-success' : 'text-muted') + '">' + fmt(val, 2) + '</td>';
            };
            body.append(
                '<tr>' +
                    '<td class="text-center">' + (idx + 1) + '</td>' +
                    '<td>' + esc(item.item_name) + '</td>' +
                    '<td>' + esc(item.barcode_number) + '</td>' +
                    '<td>' + esc(item.category || '-') + '</td>' +
                    '<td class="text-end">' + fmt(item.gross_weight) + '</td>' +
                    '<td class="text-end">' + fmt(item.net_weight) + '</td>' +
                    '<td class="text-end">' + fmt(item.diamond_total_ct) + '</td>' +
                    '<td class="text-end">' + fmt(item.stone_total_ct) + '</td>' +
                    cell(aed, 'AED') +
                    cell(usd, 'USD') +
                    '<td style="min-width:150px;">' +
                        '<div class="input-group input-group-sm">' +
                            '<input type="number" step="any" min="0" class="form-control pos-disc-value" data-idx="' + idx + '" value="' + (parseFloat(item.discount_value) || 0) + '">' +
                            '<select class="form-select pos-disc-type" data-idx="' + idx + '" style="max-width:62px;flex:0 0 62px;">' +
                                '<option value="amount"' + (item.discount_type !== 'percent' ? ' selected' : '') + '>Amt</option>' +
                                '<option value="percent"' + (item.discount_type === 'percent' ? ' selected' : '') + '>%</option>' +
                            '</select>' +
                        '</div>' +
                    '</td>' +
                    '<td class="text-end fw-bold pos-net-cell" data-idx="' + idx + '">' + fmt(itemNet(item), 2) + '</td>' +
                    '<td class="text-center">' +
                        '<button type="button" class="btn btn-sm btn-outline-danger pos-remove-item" data-idx="' + idx + '">' +
                            '<i class="fas fa-trash"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>'
            );
        });

        renderHiddenInputs();
        updateSummary();
    }

    // Rebuilds the items[] hidden inputs the form actually submits.
    // Field names/shape intentionally mirror what SaleInvoiceController::
    // createItems() already expects from the full Sale Invoice screen —
    // purity/making_rate/vat_percent are sent as 0 because a POS item's
    // full value comes entirely from selling_price (see createItems()'s
    // POS branch), not from any rate calculation. selling_price here is
    // already in the invoice's currency (AED or USD), as returned by
    // posScan() for the active currency.
    function renderHiddenInputs() {
        const wrap = $('#posHiddenInputs').empty();
        cart.forEach((item, idx) => {
            const billed = priceIn(item, activeCurrency) || 0;
            wrap.append(
                '<input type="hidden" name="items[' + idx + '][item_name]" value="' + esc(item.item_name) + '">' +
                '<input type="hidden" name="items[' + idx + '][barcode_number]" value="' + esc(item.barcode_number) + '">' +
                '<input type="hidden" name="items[' + idx + '][material_type]" value="' + esc(item.material_type) + '">' +
                '<input type="hidden" name="items[' + idx + '][gross_weight]" value="' + (parseFloat(item.gross_weight) || 0) + '">' +
                '<input type="hidden" name="items[' + idx + '][purity]" value="0">' +
                '<input type="hidden" name="items[' + idx + '][making_rate]" value="0">' +
                '<input type="hidden" name="items[' + idx + '][vat_percent]" value="0">' +
                '<input type="hidden" name="items[' + idx + '][selling_price]" value="' + billed + '">' +
                '<input type="hidden" name="items[' + idx + '][discount_type]" value="' + (item.discount_type === 'percent' ? 'percent' : 'amount') + '">' +
                '<input type="hidden" name="items[' + idx + '][discount_value]" value="' + (parseFloat(item.discount_value) || 0) + '">'
            );
        });
    }

    function updateSummary() {
        const totalItems   = cart.length;
        const totalAed     = cart.reduce((s, i) => s + (priceIn(i, 'AED') || 0), 0);
        const totalUsd     = cart.reduce((s, i) => s + (priceIn(i, 'USD') || 0), 0);
        const totalPrice   = activeCurrency === 'USD' ? totalUsd : totalAed;
        const missing      = cart.filter(i => priceIn(i, activeCurrency) === null);
        const totalWeight  = cart.reduce((s, i) => s + (parseFloat(i.gross_weight)      || 0), 0);
        const totalGold    = cart.reduce((s, i) => s + (parseFloat(i.net_weight)        || 0), 0);
        const totalDiamond = cart.reduce((s, i) => s + (parseFloat(i.diamond_total_ct)  || 0), 0);
        const totalStone   = cart.reduce((s, i) => s + (parseFloat(i.stone_total_ct)    || 0), 0);

        $('#pos_summary_items').text(totalItems);
        $('#pos_summary_aed').text(totalAed.toFixed(2));
        $('#pos_summary_usd').text(totalUsd.toFixed(2));
        $('#pos_total_aed_box').toggleClass('bg-success bg-opacity-10 border-success', activeCurrency === 'AED').toggleClass('bg-light', activeCurrency !== 'AED');
        $('#pos_total_usd_box').toggleClass('bg-success bg-opacity-10 border-success', activeCurrency === 'USD').toggleClass('bg-light', activeCurrency !== 'USD');

        if (missing.length > 0) {
            $('#pos_missing_price_msg').removeClass('d-none').html(
                '<i class="fas fa-exclamation-triangle"></i> ' + missing.length + ' item(s) have no ' + activeCurrency +
                ' price: ' + missing.map(i => esc(i.barcode_number)).join(', ') +
                '. Remove them, or switch the currency.'
            );
        } else {
            $('#pos_missing_price_msg').addClass('d-none').empty();
        }
        $('#pos_summary_weight').text(totalWeight.toFixed(3) + ' g');
        $('#pos_summary_gold').text(totalGold.toFixed(3) + ' g');
        $('#pos_summary_diamond').text(totalDiamond.toFixed(3) + ' Ct');
        $('#pos_summary_stone').text(totalStone.toFixed(3) + ' Ct');

        // Discounts: item discounts first, then the overall invoice discount
        // on the remaining subtotal.
        const itemDiscTotal = cart.reduce((s, i) => s + itemDiscount(i), 0);
        const subtotal      = totalPrice - itemDiscTotal;
        const invDisc       = resolveDiscountJs($('#pos_invoice_discount_type').val(), $('#pos_invoice_discount_value').val(), subtotal);
        const netPayable    = round2(subtotal - invDisc);
        $('#pos_summary_item_disc').text(itemDiscTotal.toFixed(2));
        $('#pos_summary_subtotal').text(subtotal.toFixed(2));
        $('#pos_summary_inv_disc').text(invDisc.toFixed(2));
        $('#pos_summary_net').text(netPayable.toFixed(2));
        $('#net_amount').val(netPayable.toFixed(2));

        $('#pos_checkout_btn').prop('disabled', totalItems === 0 || !$('#customer_id').val() || missing.length > 0);
    }

    // ── Currency selector ────────────────────────────────────────────────
    function applyCurrencyUi(currency) {
        $('.pos-currency-label').text(currency);
        if (currency === 'USD') {
            $('#pos_exchange_rate_wrap').removeClass('d-none');
            $('#pos_exchange_rate').prop('disabled', false);
        } else {
            $('#pos_exchange_rate_wrap').addClass('d-none');
            // disabled inputs aren't submitted, so AED invoices send no
            // exchange_rate at all (nullable on the server for AED).
            $('#pos_exchange_rate').prop('disabled', true);
        }
    }

    $('#pos_currency').on('change', function () {
        activeCurrency = $(this).val();
        // Fixed-amount discounts were typed in the previous currency, so they
        // are cleared on a switch (percent discounts carry over unchanged).
        cart.forEach(function (i) { if (i.discount_type !== 'percent') i.discount_value = 0; });
        if ($('#pos_invoice_discount_type').val() !== 'percent') $('#pos_invoice_discount_value').val(0);
        applyCurrencyUi(activeCurrency);
        renderCart(); // re-price every line from its two stored prices
        focusScanInput();
    });

    function handleScan() {
        const barcode = $('#pos_barcode_input').val().trim();
        if (!barcode) { focusScanInput(); return; }

        // A barcode identifies one unique physical purchased item (not a
        // quantity-based product) — scanning the same one twice in this
        // same cart can only be a duplicate scan, never "add another unit".
        if (cart.some(i => i.barcode_number === barcode)) {
            showScanResult('<i class="fas fa-exclamation-triangle"></i> <strong>' + esc(barcode) + '</strong> is already in the cart.', 'warning');
            focusScanInput();
            return;
        }

        $('#pos_barcode_input').prop('disabled', true);
        $('#pos_scan_btn').prop('disabled', true);

        $.ajax({
            url: POS_SCAN_URL,
            method: 'GET',
            data: { barcode: barcode },
            success: function (data) {
                if (!data.success) {
                    showScanResult('<i class="fas fa-times-circle"></i> ' + data.message, 'danger');
                    return;
                }
                cart.push(data);
                renderCart();
                showScanResult('<i class="fas fa-check-circle"></i> Added: <strong>' + esc(data.item_name) + '</strong>', 'success');
            },
            error: function (xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Scan failed.';
                showScanResult('<i class="fas fa-times-circle"></i> ' + msg, 'danger');
            },
            complete: function () {
                $('#pos_barcode_input').prop('disabled', false);
                $('#pos_scan_btn').prop('disabled', false);
                focusScanInput();
            }
        });
    }

    $('#pos_barcode_input').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); handleScan(); }
    });
    $('#pos_scan_btn').on('click', handleScan);

    // Discount edits: update that cart line + totals WITHOUT re-rendering the
    // table (a re-render would drop the focus while typing).
    function onItemDiscountChange(el) {
        const idx  = parseInt($(el).data('idx'), 10);
        const item = cart[idx];
        if (!item) return;
        const row = $(el).closest('tr');
        item.discount_value = parseFloat(row.find('.pos-disc-value').val()) || 0;
        item.discount_type  = row.find('.pos-disc-type').val() === 'percent' ? 'percent' : 'amount';
        row.find('.pos-net-cell').text(fmt(itemNet(item), 2));
        renderHiddenInputs();
        updateSummary();
    }
    $(document).on('input change', '.pos-disc-value, .pos-disc-type', function () { onItemDiscountChange(this); });
    $('#pos_invoice_discount_value').on('input', updateSummary);
    $('#pos_invoice_discount_type').on('change', updateSummary);

    $(document).on('click', '.pos-remove-item', function () {
        cart.splice($(this).data('idx'), 1);
        renderCart();
        focusScanInput();
    });

    $('#customer_id').on('change', updateSummary);

    // Payment fields reuse the exact same field names as the full Sale
    // Invoice screen (resources/views/sales/create.blade.php) so
    // SaleInvoiceController::store()/createSaleAccountingEntries() need no
    // POS-specific branching for payment handling.
    $('#payment_method').on('change', function () {
        const pm = $(this).val();
        $('#pos_cash_fields, #pos_cheque_fields, #pos_bank_transfer_fields').addClass('d-none');
        if (pm === 'cash')          $('#pos_cash_fields').removeClass('d-none');
        if (pm === 'cheque')        $('#pos_cheque_fields').removeClass('d-none');
        if (pm === 'bank_transfer') $('#pos_bank_transfer_fields').removeClass('d-none');
    });

    $('#posForm').on('submit', function (e) {
        if (cart.length === 0) {
            e.preventDefault();
            showScanResult('<i class="fas fa-exclamation-triangle"></i> Scan at least one item before checkout.', 'warning');
            return;
        }
        if (!$('#customer_id').val()) {
            e.preventDefault();
            showScanResult('<i class="fas fa-exclamation-triangle"></i> Please select a customer.', 'warning');
            return;
        }
        if (cart.some(i => priceIn(i, activeCurrency) === null)) {
            e.preventDefault();
            showScanResult('<i class="fas fa-exclamation-triangle"></i> Some items have no ' + activeCurrency + ' price — remove them or switch the currency.', 'warning');
            return;
        }
        if (activeCurrency === 'USD' && !(parseFloat($('#pos_exchange_rate').val()) > 0)) {
            e.preventDefault();
            showScanResult('<i class="fas fa-exclamation-triangle"></i> Enter an Exchange Rate (AED per USD) for a USD sale.', 'warning');
            return;
        }
    });

    applyCurrencyUi(activeCurrency);
    renderCart();
    focusScanInput();
});
</script>
@endsection
