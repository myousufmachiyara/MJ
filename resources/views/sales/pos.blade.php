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
        already saved on the purchased item (Purchase Invoice create/edit
        screens) — POS only ever retrieves it, never calculates it from
        rate/purity/making charges.

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
      <input type="hidden" name="currency" value="AED">
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
              <label class="text-dark fw-bold small mb-1"><i class="fas fa-barcode"></i> Scan Barcode</label>
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
                    <th>Selling Price</th>
                    <th width="5%">Action</th>
                  </tr>
                </thead>
                <tbody id="posCartBody">
                  <tr id="posCartEmptyRow">
                    <td colspan="10" class="text-center text-muted py-4">
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
              <div class="d-flex justify-content-between align-items-center p-2 bg-light border rounded mb-3">
                <span class="fw-bold">Total Selling Price</span>
                <span class="fw-bold fs-4 text-success" id="pos_summary_price">0.00</span>
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
                '<tr id="posCartEmptyRow"><td colspan="10" class="text-center text-muted py-4">' +
                '<i class="fas fa-barcode fa-2x d-block mb-2 opacity-25"></i>No items scanned yet</td></tr>'
            );
        }

        cart.forEach((item, idx) => {
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
                    '<td class="text-end fw-bold">' + fmt(item.selling_price, 2) + '</td>' +
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
    // POS branch), not from any rate calculation.
    function renderHiddenInputs() {
        const wrap = $('#posHiddenInputs').empty();
        cart.forEach((item, idx) => {
            wrap.append(
                '<input type="hidden" name="items[' + idx + '][item_name]" value="' + esc(item.item_name) + '">' +
                '<input type="hidden" name="items[' + idx + '][barcode_number]" value="' + esc(item.barcode_number) + '">' +
                '<input type="hidden" name="items[' + idx + '][material_type]" value="' + esc(item.material_type) + '">' +
                '<input type="hidden" name="items[' + idx + '][gross_weight]" value="' + (parseFloat(item.gross_weight) || 0) + '">' +
                '<input type="hidden" name="items[' + idx + '][purity]" value="0">' +
                '<input type="hidden" name="items[' + idx + '][making_rate]" value="0">' +
                '<input type="hidden" name="items[' + idx + '][vat_percent]" value="0">' +
                '<input type="hidden" name="items[' + idx + '][selling_price]" value="' + (parseFloat(item.selling_price) || 0) + '">'
            );
        });
    }

    function updateSummary() {
        const totalItems   = cart.length;
        const totalPrice   = cart.reduce((s, i) => s + (parseFloat(i.selling_price)    || 0), 0);
        const totalWeight  = cart.reduce((s, i) => s + (parseFloat(i.gross_weight)      || 0), 0);
        const totalGold    = cart.reduce((s, i) => s + (parseFloat(i.net_weight)        || 0), 0);
        const totalDiamond = cart.reduce((s, i) => s + (parseFloat(i.diamond_total_ct)  || 0), 0);
        const totalStone   = cart.reduce((s, i) => s + (parseFloat(i.stone_total_ct)    || 0), 0);

        $('#pos_summary_items').text(totalItems);
        $('#pos_summary_price').text(totalPrice.toFixed(2));
        $('#pos_summary_weight').text(totalWeight.toFixed(3) + ' g');
        $('#pos_summary_gold').text(totalGold.toFixed(3) + ' g');
        $('#pos_summary_diamond').text(totalDiamond.toFixed(3) + ' Ct');
        $('#pos_summary_stone').text(totalStone.toFixed(3) + ' Ct');
        $('#net_amount').val(totalPrice.toFixed(2));

        $('#pos_checkout_btn').prop('disabled', totalItems === 0 || !$('#customer_id').val());
    }

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
            data: { barcode },
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
    });

    renderCart();
    focusScanInput();
});
</script>
@endsection
