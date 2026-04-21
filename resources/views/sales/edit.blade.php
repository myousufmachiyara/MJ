@extends('layouts.app')

@section('title', 'Sale | Edit Invoice #' . $saleInvoice->invoice_no)

@section('content')
<div class="row">
  <div class="col">
    <form id="main-form" action="{{ route('sale_invoices.update', $saleInvoice->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
          </ul>
        </div>
      @endif

      @if(session('printed_delete_warning'))
        <div class="alert alert-warning">
          <strong>Warning:</strong> The following items have already been printed and will be permanently deleted:
          <br><code>{{ session('printed_delete_warning') }}</code><br><br>
          <button type="button" class="btn btn-danger" onclick="resubmitWithConfirm()">
            Delete anyway and update invoice
          </button>
          <a href="{{ route('sale_invoices.edit', $saleInvoice->id) }}" class="btn btn-secondary ms-2">
            Go back and keep items
          </a>
        </div>
      @endif

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Invoice <span class="text-primary">#{{ $saleInvoice->invoice_no }}</span></h2>
          <span class="badge bg-{{ $saleInvoice->is_taxable ? 'success' : 'secondary' }} fs-6">
            {{ $saleInvoice->is_taxable ? 'Taxable (SAL-TAX)' : 'Non-Taxable (SAL)' }}
          </span>
        </header>

        <div class="card-body">

          {{-- ===================== HEADER ===================== --}}
          <div class="row mb-5">

            <div class="col-md-2">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control"
                value="{{ \Carbon\Carbon::parse($saleInvoice->invoice_date)->format('Y-m-d') }}">
            </div>

            <div class="col-md-2">
              <label class="fw-bold">Invoice Type</label>
              <select name="is_taxable" id="is_taxable" class="form-control border-primary" required>
                <option value="1" {{ $saleInvoice->is_taxable ? 'selected' : '' }}>Taxable (SAL-TAX)</option>
                <option value="0" {{ !$saleInvoice->is_taxable ? 'selected' : '' }}>Non-Taxable (SAL)</option>
              </select>
              <small class="text-muted">Invoice number is locked after creation</small>
            </div>

            <div class="col-md-2">
              <label>Customer</label>
              <select name="customer_id" class="form-control select2-js" required>
                <option value="">Select Customer</option>
                @foreach ($customers as $customer)
                  <option value="{{ $customer->id }}" {{ $saleInvoice->customer_id == $customer->id ? 'selected' : '' }}>
                    {{ $customer->name }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Gold Rates --}}
            <div class="col-12 col-md-2">
              <label>Gold Rate (USD / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_usd" name="gold_rate_usd"
                class="form-control" value="{{ $saleInvoice->gold_rate_usd ?? 0 }}">
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (AED / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_aed_ounce" name="gold_rate_aed_ounce"
                class="form-control" value="{{ round($goldAedOunce, 4) }}">
            </div>

            <div class="col-12 col-md-3 mt-2">
              <label class="text-primary">Gold Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="gold_rate_aed" name="gold_rate_aed"
                class="form-control" value="{{ $saleInvoice->gold_rate_aed ?? 0 }}" readonly>
              <small class="text-danger fw-bold">Used for calculations</small>
            </div>

            {{-- Diamond Rates --}}
            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (USD) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_usd" name="diamond_rate_usd"
                class="form-control" value="{{ $saleInvoice->diamond_rate_usd ?? 0 }}">
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (AED) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_aed_ounce" name="diamond_rate_aed_ounce"
                class="form-control" value="{{ round($diamondAedOunce, 4) }}">
            </div>

            <div class="col-12 col-md-3 mt-2">
              <label class="text-primary">Diamond Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="diamond_rate_aed_gram" name="diamond_rate_aed"
                class="form-control" value="{{ $saleInvoice->diamond_rate_aed ?? 0 }}" readonly>
              <small class="text-danger fw-bold">Used for calculations</small>
            </div>

            {{-- Purchase rates --}}
            <div class="col-12 col-md-2 mt-2">
              <label class="text-success fw-bold">Purchase Gold Rate (AED / Gram)</label>
              <input type="number" step="any" id="purchase_gold_rate_aed" name="purchase_gold_rate_aed"
                class="form-control border-success" value="{{ $saleInvoice->purchase_gold_rate_aed ?? 0 }}">
              <small class="text-muted">For profit % calculation</small>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label class="text-success fw-bold">Purchase Making Rate (AED / Gram)</label>
              <input type="number" step="any" id="purchase_making_rate_aed" name="purchase_making_rate_aed"
                class="form-control border-success" value="{{ $saleInvoice->purchase_making_rate_aed ?? 0 }}">
              <small class="text-muted">For profit % calculation</small>
            </div>

            <div class="col-md-4 mt-2">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control">{{ $saleInvoice->remarks }}</textarea>
            </div>

            <div class="col-md-4 mt-2">
              <label>Add Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
              @if($saleInvoice->attachments->count())
                <small class="text-muted">{{ $saleInvoice->attachments->count() }} existing attachment(s) — new uploads add to them.</small>
              @endif
            </div>

          </div>{{-- end header row --}}

          {{-- =================== BARCODE SCANNER =================== --}}
          <div class="card mb-3 border-primary shadow-sm">
            <div class="card-body py-2 bg-primary bg-opacity-10">
              <div class="row align-items-end g-2">
                <div class="col-auto d-flex align-items-center">
                  <i class="fas fa-barcode fa-2x text-light me-2"></i>
                  <strong class="text-light">Barcode Scanner</strong>
                </div>
                <div class="col-md-5">
                  <div class="input-group">
                    <input type="text" id="barcode_scan_input" class="form-control" placeholder="Scan barcode or type &amp; press Enter…" autocomplete="off">
                    <button type="button" class="btn btn-primary fw-bold" id="barcode_scan_btn">
                      <i class="fas fa-search"></i> Search
                    </button>
                  </div>
                  <small class="text-light">USB/Bluetooth scanners supported. Scanned item is auto-added as a new row.</small>
                </div>
                <div class="col-md-5">
                  <div id="barcode_scan_result" class="alert mb-0 py-2 px-3 d-none" role="alert" style="font-size:.9rem;"></div>
                </div>
              </div>
            </div>
          </div>
          {{-- ================= END BARCODE SCANNER ================= --}}

          {{-- ===================== ITEMS TABLE ===================== --}}
          <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
              <h2 class="card-title">Invoice Items</h2>
            </header>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th width="10%" rowspan="2">Item Name</th>
                    <th width="10%" rowspan="2">Description</th>
                    <th width="6%"  rowspan="2">Purity</th>
                    <th rowspan="2">Base Gross Wt</th>
                    <th rowspan="2">Gold Gross Wt<br><small class="text-muted">(Calc.)</small></th>
                    <th rowspan="2">Purity Wt</th>
                    <th rowspan="2">995</th>
                    <th colspan="2" class="text-center">Making</th>
                    <th width="6%" rowspan="2">Material</th>
                    <th rowspan="2">Material Val</th>
                    <th rowspan="2">MC</th>
                    <th rowspan="2">VAT %</th>
                    <th rowspan="2">VAT Amt</th>
                    <th rowspan="2">Gross Total</th>
                    <th rowspan="2" class="text-success fw-bold">Profit %</th>
                    <th width="5%" rowspan="2">Action</th>
                  </tr>
                  <tr><th>Rate</th><th>Value</th></tr>
                </thead>
                <tbody id="SaleTable">
                  {{-- Rendered by JS from existingItems --}}
                </tbody>
              </table>
              <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">Add Item</button>
            </div>
          </section>

          {{-- ===================== SUMMARY ===================== --}}
          <div class="row mt-5 mb-5">
            <div class="col-md-2">
              <label>Gold Gross Wt</label>
              <input type="text" id="sum_gold_gross_weight" class="form-control text-primary fw-bold" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Purity Wt</label>
              <input type="text" id="sum_purity_weight" class="form-control text-success fw-bold" readonly>
            </div>
            <div class="col-md-2">
              <label>Diamond CTS</label>
              <input type="text" id="sum_diamond_cts" class="form-control text-warning fw-bold" readonly>
            </div>
            <div class="col-md-2">
              <label>Stone Qty</label>
              <input type="text" id="sum_stone_qty" class="form-control text-info fw-bold" readonly>
            </div>
            <div class="col-md-2">
              <label>Total 995</label>
              <input type="text" id="sum_995" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Making</label>
              <input type="text" id="sum_making_value" class="form-control" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Total Material Val.</label>
              <input type="text" id="sum_material_value" class="form-control" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Diamond Parts Val.</label>
              <input type="text" id="sum_diamond_value" class="form-control text-warning fw-bold" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Stone Parts Val.</label>
              <input type="text" id="sum_stone_value" class="form-control text-info fw-bold" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Total VAT</label>
              <input type="text" id="sum_vat_amount" class="form-control" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Net Amount</label>
              <input type="text" id="net_amount_display" class="form-control text-danger fw-bold" readonly>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
            <div class="col-md-2 mt-3">
              <label class="text-success fw-bold">Overall Profit %</label>
              <input type="text" id="overall_profit_pct" class="form-control fw-bold border-success text-center" readonly style="font-size:1.1rem;">
            </div>
          </div>

          {{-- ===================== PAYMENT METHOD ===================== --}}
          <div class="row mb-3">
            <div class="col-md-2">
              <label class="fw-bold">Payment Method</label>
              <select name="payment_method" id="payment_method" class="form-control" required>
                <option value="">Select Payment Method</option>
                @foreach(['credit','cash','bank_transfer','cheque','material+making cost'] as $pm)
                  <option value="{{ $pm }}" {{ $saleInvoice->payment_method === $pm ? 'selected' : '' }}>
                    {{ ucwords(str_replace(['_','+'], [' ',' + '], $pm)) }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Payment Term</label>
              <input type="text" name="payment_term" class="form-control" value="{{ $saleInvoice->payment_term }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="received_by_box">
            <div class="col-md-2">
              <label>Received By</label>
              <input type="text" name="received_by" class="form-control" value="{{ $saleInvoice->received_by }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="cheque_fields">
            <div class="col-md-2">
              <label>Bank Name</label>
              <select name="bank_name" class="form-control select2-js">
                <option value="">Select Bank</option>
                @foreach ($banks as $bank)
                  <option value="{{ $bank->id }}" {{ $saleInvoice->bank_name == $bank->id ? 'selected' : '' }}>
                    {{ $bank->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Cheque No</label>
              <input type="text" name="cheque_no" class="form-control" value="{{ $saleInvoice->cheque_no }}">
            </div>
            <div class="col-md-2">
              <label>Cheque Date</label>
              <input type="date" name="cheque_date" class="form-control" value="{{ $saleInvoice->cheque_date }}">
            </div>
            <div class="col-md-2">
              <label>Cheque Amount</label>
              <input type="number" step="any" name="cheque_amount" class="form-control" value="{{ $saleInvoice->cheque_amount }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="material_fields">
            <div class="col-md-2">
              <label>Raw Material Weight Given</label>
              <input type="number" step="any" name="material_weight" class="form-control" value="{{ $saleInvoice->material_weight }}">
            </div>
            <div class="col-md-2">
              <label>Raw Material Purity</label>
              <input type="number" step="any" name="material_purity" class="form-control" value="{{ $saleInvoice->material_purity }}">
            </div>
            <div class="col-md-2">
              <label>Material Adjustment Value</label>
              <input type="number" step="any" name="material_value_input" class="form-control" value="{{ $saleInvoice->material_value }}">
            </div>
            <div class="col-md-2">
              <label>Making Charges Payable</label>
              <input type="number" step="any" name="making_charges" class="form-control" value="{{ $saleInvoice->making_charges }}">
            </div>
            <div class="col-md-2">
              <label>Material Given By</label>
              <input type="text" name="material_given_by" class="form-control" value="{{ $saleInvoice->material_given_by }}">
            </div>
            <div class="col-md-2">
              <label>Material Received By</label>
              <input type="text" name="material_received_by" class="form-control" value="{{ $saleInvoice->material_received_by }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="bank_transfer_fields">
            <div class="col-md-2">
              <label>Transfer From Bank</label>
              <select name="transfer_from_bank" class="form-control select2-js">
                <option value="">Select Bank</option>
                @foreach ($banks as $bank)
                  <option value="{{ $bank->id }}" {{ $saleInvoice->transfer_from_bank == $bank->id ? 'selected' : '' }}>
                    {{ $bank->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Customer Bank Name</label>
              <input type="text" name="transfer_to_bank" class="form-control" value="{{ $saleInvoice->transfer_to_bank }}" placeholder="e.g. Emirates NBD">
            </div>
            <div class="col-md-2">
              <label>Account Title</label>
              <input type="text" name="account_title" class="form-control" value="{{ $saleInvoice->account_title }}">
            </div>
            <div class="col-md-2">
              <label>Account Number</label>
              <input type="text" name="account_no" class="form-control" value="{{ $saleInvoice->account_no }}">
            </div>
            <div class="col-md-2">
              <label>Transaction Ref No</label>
              <input type="text" name="transaction_id" class="form-control" value="{{ $saleInvoice->transaction_id }}">
            </div>
            <div class="col-md-2">
              <label>Transfer Date</label>
              <input type="date" name="transfer_date" class="form-control"
                value="{{ $saleInvoice->transfer_date ? \Carbon\Carbon::parse($saleInvoice->transfer_date)->format('Y-m-d') : '' }}">
            </div>
            <div class="col-md-2 mt-2">
              <label>Transfer Amount</label>
              <input type="number" step="any" name="transfer_amount" class="form-control" value="{{ $saleInvoice->transfer_amount }}">
            </div>
          </div>

          {{-- ===================== CURRENCY ===================== --}}
          <div class="card mt-3">
            <div class="card-header"><h2 class="card-title">Currency</h2></div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-2">
                  <label class="form-label">Invoice Currency</label>
                  <select name="currency" id="currency" class="form-control">
                    <option value="AED" {{ $saleInvoice->currency === 'AED' ? 'selected' : '' }}>AED / Dirhams</option>
                    <option value="USD" {{ $saleInvoice->currency === 'USD' ? 'selected' : '' }}>USD Dollars</option>
                  </select>
                </div>
                <div class="col-md-2" id="exchangeRateBox" style="display:none;">
                  <label class="form-label">USD → AED Rate <span class="text-danger">*</span></label>
                  <input type="number" step="0.000001" name="exchange_rate" id="exchange_rate"
                    class="form-control" value="{{ $saleInvoice->exchange_rate }}" placeholder="3.6725">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Converted Total (AED)</label>
                  <input type="text" id="converted_total" class="form-control" readonly>
                </div>
              </div>
            </div>
          </div>

        </div>{{-- end card-body --}}

        <footer class="card-footer text-end">
          <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary me-2">
            <i class="fas fa-times"></i> Cancel
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Invoice
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
$(document).ready(function () {
    const products           = @json($products);
    const existingItems      = @json($itemsData);
    const TROY_OUNCE_TO_GRAM = 31.1035;
    const BARCODE_SCAN_URL   = '{{ route("sale.scan_barcode") }}';

    // ===== PARTS TOGGLE =====
    $(document).on('click', '.toggle-parts', function() {
        $(this).closest('tr').next('.parts-row').fadeToggle(200);
    });

    // ===== CURRENCY =====
    function initCurrencyBox() {
        const isUSD = $('#currency').val() === 'USD';
        $('#exchangeRateBox').toggle(isUSD);
        if (isUSD) $('#exchange_rate').attr('required', true);
        else       $('#exchange_rate').removeAttr('required');
    }
    initCurrencyBox();

    $('#currency').on('change', function() {
        const isUSD = $(this).val() === 'USD';
        if (isUSD) {
            $('#exchangeRateBox').show();
            $('#exchange_rate').attr('required', true);
            if (!$('#exchange_rate').val()) $('#exchange_rate').val('3.6725');
        } else {
            $('#exchangeRateBox').hide();
            $('#exchange_rate').removeAttr('required').val('');
        }
        calculateTotals();
    });
    $('#exchange_rate').on('input', calculateTotals);

    $('.select2-js').select2({ width: '100%' });

    // ===== BARCODE SCANNER =====
    function showScanResult(msg, type) {
        const el = $('#barcode_scan_result');
        el.removeClass('d-none alert-success alert-danger alert-warning')
          .addClass('alert-' + type).html(msg);
        clearTimeout(window._scanTimer);
        window._scanTimer = setTimeout(() => el.addClass('d-none'), 5000);
    }

    function handleBarcodeScan() {
        const barcode = $('#barcode_scan_input').val().trim();
        if (!barcode) { $('#barcode_scan_input').focus(); return; }

        $('#barcode_scan_btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: BARCODE_SCAN_URL,
            method: 'GET',
            data: { barcode },
            success: function(data) {
                if (!data.success) {
                    showScanResult('<i class="fas fa-times-circle"></i> ' + data.message, 'danger');
                    return;
                }

                let duplicate = false;
                $('#SaleTable tr.item-row').each(function() {
                    if ($(this).find('input[name*="[barcode_number]"]').val() === barcode) {
                        duplicate = true; return false;
                    }
                });
                if (duplicate) {
                    showScanResult('<i class="fas fa-exclamation-triangle"></i> <strong>' + barcode + '</strong> already on this invoice.', 'warning');
                    return;
                }

                addNewRow();
                const newRow = $('#SaleTable tr.item-row').last();

                newRow.find('.item-name-input').val(data.item_name);
                newRow.find('input[name*="[barcode_number]"]').val(data.barcode_number);
                newRow.find('input[name*="[item_description]"]').val(data.item_description);

                const pur = parseFloat(data.purity);
                let nearestOpt = null; let minDiff = Infinity;
                newRow.find('.purity option').each(function() {
                    const diff = Math.abs(parseFloat($(this).val()) - pur);
                    if (diff < minDiff) { minDiff = diff; nearestOpt = $(this).val(); }
                });
                if (nearestOpt) newRow.find('.purity').val(nearestOpt);

                const baseGross = parseFloat(data.gross_weight) || 0;
                newRow.find('.base-gross-weight').val(baseGross.toFixed(3));
                newRow.find('.making-rate').val(data.making_rate || 0);
                newRow.find('.material-type').val(data.material_type || 'gold');
                newRow.find('.vat-percent').val(data.vat_percent || 0);

                if (data.parts && data.parts.length > 0) {
                    const partsRow  = newRow.next('.parts-row');
                    const partsBody = partsRow.find('.parts-table tbody');
                    const itemIndex = newRow.data('item-index');
                    partsRow.show();
                    data.parts.forEach((part, j) => partsBody.append(buildPartRowHtml(itemIndex, j, part)));
                }

                recalcItemGrossWeight(newRow);
                calculateTotals();

                const src = data.source === 'purchase'
                    ? ' <span class="badge bg-info">from Purchase</span>'
                    : ' <span class="badge bg-success">from Sale</span>';
                showScanResult('<i class="fas fa-check-circle"></i> Added: <strong>' + data.item_name + '</strong>' + src, 'success');
                newRow.addClass('table-warning');
                setTimeout(() => newRow.removeClass('table-warning'), 2000);
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Lookup failed.';
                showScanResult('<i class="fas fa-times-circle"></i> ' + msg, 'danger');
            },
            complete: function() {
                $('#barcode_scan_btn').prop('disabled', false).html('<i class="fas fa-search"></i> Lookup');
                $('#barcode_scan_input').val('').focus();
            }
        });
    }

    $('#barcode_scan_input').on('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); handleBarcodeScan(); }
    });
    $('#barcode_scan_btn').on('click', handleBarcodeScan);

    // ===== ROW MANAGEMENT =====
    function updateRowIndexes() {
        $('#SaleTable tr.item-row').each(function(i) {
            $(this).attr('data-item-index', i);
            $(this).find('input, select').each(function() {
                const n = $(this).attr('name');
                if (n) $(this).attr('name', n.replace(/items\[\d+\]/, `items[${i}]`));
            });
            $(this).next('.parts-row').find('.part-item-row').each(function(j) {
                $(this).attr('data-part-index', j);
                $(this).find('input, select').each(function() {
                    const n = $(this).attr('name');
                    if (n) $(this).attr('name',
                        n.replace(/items\[\d+\]/, `items[${i}]`).replace(/parts\[\d+\]/, `parts[${j}]`)
                    );
                });
            });
        });
    }

    function buildItemRowHtml(index, data) {
        data = data || {};
        const name      = data.item_name        || '';
        const desc      = data.item_description || '';
        const purity    = data.purity           || '0.92';
        const baseGross = data.gross_weight     || 0;  // stored gross_weight IS the base for sale items
        const mkRate    = data.making_rate      || 0;
        const matType   = data.material_type    || 'gold';
        const vatPct    = data.vat_percent      || 0;

        const purityOptions = `@foreach($purities as $p)<option value="{{ $p->value }}" ${purity == {{ $p->value }} ? 'selected' : ''}>{{ $p->label }}</option>@endforeach`;

        return `
        <tr class="item-row" data-item-index="${index}">
            <td><div class="product-wrapper">
                <input type="text" name="items[${index}][item_name]" class="form-control item-name-input" placeholder="Product Name" value="${name}">
                <input type="hidden" name="items[${index}][barcode_number]" value="${data.barcode_number || ''}">
                <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
            </div></td>
            <td><input type="text" name="items[${index}][item_description]" class="form-control" value="${desc}" required></td>
            <td><select name="items[${index}][purity]" class="form-control purity">${purityOptions}</select></td>
            <td><input type="number" name="items[${index}][base_gross_weight]" step="any" value="${baseGross}" class="form-control base-gross-weight"></td>
            <td><input type="number" name="items[${index}][gross_weight]" step="any" value="${data.gross_weight || 0}" class="form-control gross-weight bg-light text-primary fw-bold" readonly></td>
            <td><input type="number" name="items[${index}][purity_weight]" step="any" value="${data.purity_weight || 0}" class="form-control purity-weight" readonly></td>
            <td><input type="number" name="items[${index}][col_995]" step="any" value="${data.col_995 || 0}" class="form-control col-995" readonly></td>
            <td><input type="number" name="items[${index}][making_rate]" step="any" value="${mkRate}" class="form-control making-rate"></td>
            <td><input type="number" name="items[${index}][making_value]" step="any" value="${data.making_value || 0}" class="form-control making-value" readonly></td>
            <td><select name="items[${index}][material_type]" class="form-control material-type">
                <option value="gold"    ${matType === 'gold'    ? 'selected' : ''}>Gold</option>
                <option value="diamond" ${matType === 'diamond' ? 'selected' : ''}>Diamond</option>
            </select></td>
            <td><input type="number" name="items[${index}][material_value]" step="any" value="${data.material_value || 0}" class="form-control material-value" readonly></td>
            <td><input type="number" name="items[${index}][taxable_amount]" step="any" value="${data.taxable_amount || 0}" class="form-control taxable-amount" readonly></td>
            <td><input type="number" name="items[${index}][vat_percent]" class="form-control vat-percent" step="any" value="${vatPct}"></td>
            <td><input type="number" name="items[${index}][vat_amount]" step="any" value="${data.vat_amount || 0}" class="form-control vat-amount" readonly></td>
            <td><input type="number" name="items[${index}][item_total]" step="any" value="${data.item_total || 0}" class="form-control item-total" readonly></td>
            <td><input type="text" class="form-control item-profit-pct fw-bold text-center" readonly style="min-width:80px;font-size:.9rem;"></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                <button type="button" class="btn btn-sm btn-primary toggle-parts"><i class="fas fa-wrench"></i></button>
            </td>
        </tr>
        <tr class="parts-row" style="display:none;background:#efefef">
            <td colspan="17"><div class="parts-wrapper">
                <table class="table table-sm table-bordered parts-table">
                    <thead><tr>
                        <th>Part</th><th>Description</th><th>Diamond Ct.</th><th>Rate</th>
                        <th>Stone Ct.</th><th>Stone Rate</th><th>Total</th><th></th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary add-part">+ Add Part</button>
            </div></td>
        </tr>`;
    }

    function buildPartRowHtml(itemIndex, partIndex, data) {
        data = data || {};
        return `
        <tr class="part-item-row" data-part-index="${partIndex}">
            <td><div class="product-wrapper">
                <input type="text" name="items[${itemIndex}][parts][${partIndex}][item_name]" class="form-control item-name-input" placeholder="Part Name" value="${data.item_name || ''}">
                <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
            </div></td>
            <td><input type="text" name="items[${itemIndex}][parts][${partIndex}][part_description]" class="form-control" value="${data.part_description || ''}"></td>
            <td><div class="input-group">
                <input type="number" name="items[${itemIndex}][parts][${partIndex}][qty]" step="any" value="${data.qty || 0}" class="form-control part-qty">
                <span class="input-group-text">Ct.</span>
            </div></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][rate]" step="any" value="${data.rate || 0}" class="form-control part-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_qty]" step="any" value="${data.stone_qty || 0}" class="form-control part-stone-qty"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_rate]" step="any" value="${data.stone_rate || 0}" class="form-control part-stone-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][total]" step="any" value="${data.total || 0}" class="form-control part-total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-part"><i class="fas fa-times"></i></button></td>
        </tr>`;
    }

    // ===== LOAD EXISTING ITEMS =====
    existingItems.forEach(function(itemData, i) {
        $('#SaleTable').append(buildItemRowHtml(i, itemData));
        const itemRow  = $('#SaleTable tr.item-row').last();
        const partsRow = itemRow.next('.parts-row');

        // Set base-gross = stored gross_weight (parts contribution already included)
        // We recalc to ensure gross-weight column is consistent
        itemRow.find('.base-gross-weight').val(parseFloat(itemData.gross_weight) || 0);

        if (itemData.parts && itemData.parts.length > 0) {
            partsRow.show();
            itemData.parts.forEach((p, j) => {
                partsRow.find('.parts-table tbody').append(buildPartRowHtml(i, j, p));
            });
        }

        recalcItemGrossWeight(itemRow);
    });

    calculateTotals();

    // ===== PAYMENT METHOD — init on load =====
    (function() {
        const val = $('#payment_method').val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields').addClass('d-none');
        if (val === 'cheque')                    $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')                 $('#received_by_box').removeClass('d-none');
        else if (val === 'bank_transfer')        $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost') $('#material_fields').removeClass('d-none');
    })();

    // ===== ADD / REMOVE ROWS =====
    window.addNewRow = function() {
        const nextIndex = $('#SaleTable tr.item-row').length;
        $('#SaleTable').append(buildItemRowHtml(nextIndex, {}));
        updateRowIndexes();
    };

    window.removeRow = function(btn) {
        const row = $(btn).closest('tr');
        if ($('#SaleTable tr.item-row').length > 1) {
            row.next('.parts-row').remove();
            row.remove();
            updateRowIndexes();
            calculateTotals();
        }
    };

    $(document).on('click', '.add-part', function() {
        const partsBody = $(this).closest('.parts-wrapper').find('.parts-table tbody');
        const itemRow   = $(this).closest('.parts-row').prev('.item-row');
        partsBody.append(buildPartRowHtml(itemRow.data('item-index'), partsBody.find('tr').length, {}));
    });

    $(document).on('click', '.remove-part', function() {
        const itemRow = $(this).closest('.parts-row').prev('.item-row');
        $(this).closest('tr').remove();
        recalcItemGrossWeight(itemRow);
        calculateTotals();
    });

    // ===== PRODUCT TOGGLE =====
    $(document).on('click', '.toggle-product, .revert-to-name', function() {
        const isReverting = $(this).hasClass('revert-to-name');
        const wrapper     = $(this).closest('.product-wrapper');
        const isPart      = wrapper.closest('tr').hasClass('part-item-row');
        const itemIdx     = isPart
            ? wrapper.closest('.parts-row').prev('.item-row').data('item-index')
            : wrapper.closest('.item-row').data('item-index');
        const namePath = isPart
            ? `items[${itemIdx}][parts][${wrapper.closest('.part-item-row').data('part-index')}]`
            : `items[${itemIdx}]`;

        if (isReverting) {
            wrapper.html(`
                <input type="text" name="${namePath}[item_name]" class="form-control item-name-input" placeholder="Name">
                <input type="hidden" name="${namePath}[barcode_number]" value="">
                <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
            `);
        } else {
            wrapper.html(`
                <select name="${namePath}[product_id]" class="form-control select2-js product-select mb-2">
                    <option value="">Select Product</option>
                    ${products.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                </select>
                <select name="${namePath}[variation_id]" class="form-control select2-js variation-select">
                    <option value="">Select Variation</option>
                </select>
                <button type="button" class="btn btn-link p-0 revert-to-name mt-1">Write Name</button>
            `).find('.select2-js').select2({ width: '100%' });
        }
    });

    $(document).on('change', '.product-select', function() {
        const productId       = $(this).val();
        const variationSelect = $(this).closest('tr').find('.variation-select');
        variationSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        if (!productId) { variationSelect.html('<option value="">Select Variation</option>').prop('disabled', false); return; }
        fetch(`/product/${productId}/variations`).then(r => r.json()).then(data => {
            variationSelect.prop('disabled', false);
            let opts = '<option value="">No variation</option>';
            if (data.success && data.variation.length) {
                opts = '<option value="">Select Variation</option>';
                data.variation.forEach(v => { opts += `<option value="${v.id}">${v.sku}</option>`; });
            }
            variationSelect.html(opts);
        });
    });

    // ===== PROFIT % HELPERS =====
    function calcProfitPct(sale, cost) {
        if (!cost || cost === 0) return { pct: null, label: 'N/A' };
        const pct = ((sale - cost) / cost) * 100;
        return { pct, label: pct.toFixed(2) + '%' };
    }
    function colourProfitInput(el, pct) {
        el.css('color', pct === null ? '#6c757d' : pct >= 0 ? '#198754' : '#dc3545');
    }

    // ===== CALCULATIONS =====
    $(document).on('input', '.base-gross-weight', function() {
        recalcItemGrossWeight($(this).closest('tr.item-row'));
    });

    $(document).on('input change',
        '.purity, .making-rate, .vat-percent, .material-type, #gold_rate_aed, #diamond_rate_aed_gram, #purchase_gold_rate_aed, #purchase_making_rate_aed',
    function() {
        const row = $(this).closest('tr.item-row');
        if (row.length) recalcItemGrossWeight(row);
        calculateTotals();
    });

    function recalcItemGrossWeight(itemRow) {
        if (!itemRow || !itemRow.length) return;
        const baseGross = parseFloat(itemRow.find('.base-gross-weight').val()) || 0;
        let totalDiamondCTS = 0, totalStoneCTS = 0;
        itemRow.next('.parts-row').find('.part-item-row').each(function() {
            totalDiamondCTS += parseFloat($(this).find('.part-qty').val())       || 0;
            totalStoneCTS   += parseFloat($(this).find('.part-stone-qty').val()) || 0;
        });
        const newGross = baseGross + (totalDiamondCTS / 5) + (totalStoneCTS / 5);
        itemRow.find('.gross-weight').val(newGross.toFixed(4));
        calculateRow(itemRow);
        calculateTotals();
    }

    function calculateRow(row) {
        const gross      = parseFloat(row.find('.gross-weight').val())      || 0;
        const baseGross  = parseFloat(row.find('.base-gross-weight').val()) || 0;
        const purity     = parseFloat(row.find('.purity').val())            || 0;
        const makingRate = parseFloat(row.find('.making-rate').val())       || 0;
        const vatPercent = parseFloat(row.find('.vat-percent').val())       || 0;
        const matType    = row.find('.material-type').val();
        const saleRate   = (matType === 'gold')
            ? (parseFloat($('#gold_rate_aed').val())         || 0)
            : (parseFloat($('#diamond_rate_aed_gram').val()) || 0);
        const purGoldR   = parseFloat($('#purchase_gold_rate_aed').val())   || 0;
        const purMkR     = parseFloat($('#purchase_making_rate_aed').val()) || 0;

        const purityWeight  = gross * purity;
        const col995        = purityWeight > 0 ? purityWeight / 0.995 : 0;
        const makingValue   = gross * makingRate;
        const materialValue = saleRate * purityWeight;

        let partsTotal = 0;
        row.next('.parts-row').find('.part-item-row').each(function() {
            partsTotal += parseFloat($(this).find('.part-total').val()) || 0;
        });

        const taxableAmount = makingValue;
        const vatAmount     = taxableAmount * vatPercent / 100;
        const itemTotal     = materialValue + makingValue + partsTotal + vatAmount;
        const costTotal     = (purGoldR * purityWeight) + (baseGross * purMkR);

        row.find('.purity-weight').val(purityWeight.toFixed(4));
        row.find('.col-995').val(col995.toFixed(4));
        row.find('.making-value').val(makingValue.toFixed(4));
        row.find('.material-value').val(materialValue.toFixed(4));
        row.find('.taxable-amount').val(taxableAmount.toFixed(4));
        row.find('.vat-amount').val(vatAmount.toFixed(4));
        row.find('.item-total').val(itemTotal.toFixed(4));

        const profitInput = row.find('.item-profit-pct');
        const { pct, label } = calcProfitPct(itemTotal, costTotal);
        profitInput.val(label);
        colourProfitInput(profitInput, pct);
    }

    function calculateTotals() {
        let sumGoldGross = 0, sumPurityWeight = 0, sum995 = 0;
        let sumMaking = 0, sumMaterial = 0, sumVAT = 0, sumItemTotal = 0;
        let totalDiamondCTS = 0, totalStoneQty = 0, totalDiamondVal = 0, totalStoneVal = 0;
        let totalCost = 0;

        const purGoldR = parseFloat($('#purchase_gold_rate_aed').val())   || 0;
        const purMkR   = parseFloat($('#purchase_making_rate_aed').val()) || 0;

        $('#SaleTable tr.item-row').each(function() {
            const itemRow   = $(this);
            const matType   = itemRow.find('.material-type').val();
            const grossVal  = parseFloat(itemRow.find('.gross-weight').val())      || 0;
            const baseGross = parseFloat(itemRow.find('.base-gross-weight').val()) || 0;
            const purWt     = parseFloat(itemRow.find('.purity-weight').val())     || 0;

            sumPurityWeight += purWt;
            sum995          += parseFloat(itemRow.find('.col-995').val())          || 0;
            sumMaking       += parseFloat(itemRow.find('.making-value').val())     || 0;
            sumMaterial     += parseFloat(itemRow.find('.material-value').val())   || 0;
            sumVAT          += parseFloat(itemRow.find('.vat-amount').val())       || 0;
            sumItemTotal    += parseFloat(itemRow.find('.item-total').val())        || 0;
            totalCost       += (purGoldR * purWt) + (baseGross * purMkR);

            if (matType === 'gold') sumGoldGross += grossVal;

            itemRow.next('.parts-row').find('.part-item-row').each(function() {
                const diaQty    = parseFloat($(this).find('.part-qty').val())        || 0;
                const diaRate   = parseFloat($(this).find('.part-rate').val())       || 0;
                const stoneQty  = parseFloat($(this).find('.part-stone-qty').val())  || 0;
                const stoneRate = parseFloat($(this).find('.part-stone-rate').val()) || 0;
                totalDiamondCTS += diaQty;
                totalStoneQty   += stoneQty;
                totalDiamondVal += diaQty   * diaRate;
                totalStoneVal   += stoneQty * stoneRate;
            });
        });

        $('#sum_gold_gross_weight').val(sumGoldGross.toFixed(4));
        $('#sum_purity_weight').val(sumPurityWeight.toFixed(4));
        $('#sum_diamond_cts').val(totalDiamondCTS.toFixed(4));
        $('#sum_stone_qty').val(totalStoneQty.toFixed(4));
        $('#sum_995').val(sum995.toFixed(4));
        $('#sum_making_value').val(sumMaking.toFixed(4));
        $('#sum_material_value').val(sumMaterial.toFixed(4));
        $('#sum_vat_amount').val(sumVAT.toFixed(4));
        $('#sum_diamond_value').val(totalDiamondVal.toFixed(4));
        $('#sum_stone_value').val(totalStoneVal.toFixed(4));
        $('#net_amount_display').val(sumItemTotal.toFixed(4));
        $('#net_amount').val(sumItemTotal.toFixed(4));

        const currency = $('#currency').val();
        const exRate   = parseFloat($('#exchange_rate').val()) || 1;
        $('#converted_total').val(
            currency === 'USD' ? (sumItemTotal * exRate).toFixed(4) : sumItemTotal.toFixed(4)
        );

        const oi = $('#overall_profit_pct');
        const { pct, label } = calcProfitPct(sumItemTotal, totalCost);
        oi.val(label);
        colourProfitInput(oi, pct);

        if ($('#payment_method').val() === 'material+making cost') {
            $('input[name="material_weight"]').val(sum995.toFixed(4));
            $('input[name="material_purity"]').val(sumPurityWeight.toFixed(4));
            $('input[name="material_value_input"]').val(sumMaterial.toFixed(4));
            $('input[name="making_charges"]').val(sumMaking.toFixed(4));
        }
    }

    // ===== RATE CONVERSION =====
    $(document).on('input', '#gold_rate_usd, #gold_rate_aed_ounce, #diamond_rate_usd, #diamond_rate_aed_ounce, #exchange_rate', function() {
        const id     = $(this).attr('id');
        const exRate = parseFloat($('#exchange_rate').val()) || 3.6725;

        if (id === 'gold_rate_usd' || id === 'exchange_rate') {
            const goldUsd = parseFloat($('#gold_rate_usd').val()) || 0;
            $('#gold_rate_aed_ounce').val((goldUsd * exRate).toFixed(4));
        }
        $('#gold_rate_aed').val(((parseFloat($('#gold_rate_aed_ounce').val()) || 0) / TROY_OUNCE_TO_GRAM).toFixed(4));

        if (id === 'diamond_rate_usd' || id === 'exchange_rate') {
            const diaUsd = parseFloat($('#diamond_rate_usd').val()) || 0;
            $('#diamond_rate_aed_ounce').val((diaUsd * exRate).toFixed(4));
        }
        $('#diamond_rate_aed_gram').val(((parseFloat($('#diamond_rate_aed_ounce').val()) || 0) / TROY_OUNCE_TO_GRAM).toFixed(4));

        $('#SaleTable tr.item-row').each(function() { calculateRow($(this)); });
        calculateTotals();
    });

    // ===== PAYMENT METHOD =====
    $('#payment_method').on('change', function() {
        const val = $(this).val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields').addClass('d-none');
        if (val === 'cheque')                    $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')                 $('#received_by_box').removeClass('d-none');
        else if (val === 'bank_transfer')        $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost') $('#material_fields').removeClass('d-none');
        calculateTotals();
    });

    // ===== PARTS CALCULATION =====
    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate', function() {
        const row       = $(this).closest('tr');
        const qty       = parseFloat(row.find('.part-qty').val())       || 0;
        const rate      = parseFloat(row.find('.part-rate').val())      || 0;
        const stoneQty  = parseFloat(row.find('.part-stone-qty').val()) || 0;
        const stoneRate = parseFloat(row.find('.part-stone-rate').val())|| 0;
        row.find('.part-total').val(((qty * rate) + (stoneQty * stoneRate)).toFixed(4));
        recalcItemGrossWeight(row.closest('.parts-row').prev('.item-row'));
    });
});

function resubmitWithConfirm() {
    const form  = document.getElementById('main-form');
    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'confirm_delete_printed';
    input.value = '1';
    form.appendChild(input);
    form.submit();
}

document.getElementById('main-form').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
});
</script>
@endsection