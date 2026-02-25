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
          <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif

      @if(session('printed_delete_warning'))
        <div class="alert alert-warning">
            <strong>Warning:</strong> The following items have already been printed and will be permanently deleted:
            <br><code>{{ session('printed_delete_warning') }}</code><br><br>
            <form method="POST" action="{{ route('sale_invoices.update', $saleInvoice->id) }}" enctype="multipart/form-data" id="confirm-delete-form">
                @csrf @method('PUT')
                <input type="hidden" name="confirm_delete_printed" value="1">
                <button type="button" class="btn btn-danger" onclick="resubmitWithConfirm()">Delete anyway and update invoice</button>
                <a href="{{ route('sale_invoices.edit', $saleInvoice->id) }}" class="btn btn-secondary ms-2">Go back and keep items</a>
            </form>
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

          {{-- HEADER --}}
          <div class="row mb-5">
            <div class="col-md-2">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ \Carbon\Carbon::parse($saleInvoice->invoice_date)->format('Y-m-d') }}">
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
                  <option value="{{ $customer->id }}" {{ $saleInvoice->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12 col-md-2">
              <label>Gold Rate (USD / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_usd" name="gold_rate_usd" class="form-control" value="{{ $saleInvoice->gold_rate_usd ?? 0 }}">
            </div>
            <div class="col-12 col-md-2">
              <label>Gold Rate (AED / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_aed_ounce" name="gold_rate_aed_ounce" class="form-control" value="{{ round($goldAedOunce, 2) }}">
            </div>
            <div class="col-12 col-md-3">
              <label class="text-primary">Gold Converted Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="gold_rate_aed" name="gold_rate_aed" class="form-control" value="{{ $saleInvoice->gold_rate_aed ?? 0 }}" readonly>
              <small class="text-danger text-bold">Used for calculations</small>
            </div>
            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (USD) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_usd" name="diamond_rate_usd" class="form-control" value="{{ $saleInvoice->diamond_rate_usd ?? 0 }}">
            </div>
            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (AED) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_aed_ounce" name="diamond_rate_aed_ounce" class="form-control" value="{{ round($diamondAedOunce, 2) }}">
            </div>
            <div class="col-12 col-md-3">
              <label class="text-primary">Diamond Converted Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="diamond_rate_aed_gram" name="diamond_rate_aed" class="form-control" value="{{ $saleInvoice->diamond_rate_aed ?? 0 }}" readonly>
              <small class="text-danger text-bold">Used for calculations</small>
            </div>
            <div class="col-12 col-md-2 mt-2">
              <label class="text-success fw-bold">Purchase Gold Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="purchase_gold_rate_aed" name="purchase_gold_rate_aed" class="form-control border-success" value="{{ $saleInvoice->purchase_gold_rate_aed ?? 0 }}">
              <small class="text-muted">For profit % calc</small>
            </div>
            <div class="col-12 col-md-2 mt-2">
              <label class="text-success fw-bold">Purchase Making Rate (AED / Gram)</label>
              <input type="number" step="any" id="purchase_making_rate_aed" name="purchase_making_rate_aed" class="form-control border-success" value="{{ $saleInvoice->purchase_making_rate_aed ?? 0 }}">
              <small class="text-muted">For profit % calc</small>
            </div>
            <div class="col-md-4 mt-2">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control">{{ $saleInvoice->remarks }}</textarea>
            </div>
            <div class="col-md-4 mt-2">
              <label>Add Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
              @if($saleInvoice->attachments->count())
                <small class="text-muted">{{ $saleInvoice->attachments->count() }} existing attachment(s) — uploading new files adds to them.</small>
              @endif
            </div>
          </div>

          {{-- =================== BARCODE SCANNER =================== --}}
          <div class="card mb-3 border-warning shadow-sm">
            <div class="card-body py-2 bg-warning bg-opacity-10">
              <div class="row align-items-end g-2">
                <div class="col-auto d-flex align-items-center">
                  <i class="fas fa-barcode fa-2x text-warning me-2"></i>
                  <strong class="text-warning">Barcode Scanner</strong>
                </div>
                <div class="col-md-4">
                  <div class="input-group">
                    <input type="text"
                           id="barcode_scan_input"
                           class="form-control form-control-lg fw-bold"
                           placeholder="Scan barcode or type &amp; press Enter…"
                           autocomplete="off">
                    <button type="button" class="btn btn-warning fw-bold" id="barcode_scan_btn">
                      <i class="fas fa-search"></i> Lookup
                    </button>
                  </div>
                  <small class="text-muted">Works with USB/Bluetooth barcode scanners. Scanned item is added as a new row.</small>
                </div>
                <div class="col-md-5">
                  <div id="barcode_scan_result" class="alert mb-0 py-2 px-3 d-none" role="alert" style="font-size:.9rem;"></div>
                </div>
              </div>
            </div>
          </div>
          {{-- ================= END BARCODE SCANNER ================= --}}

          {{-- ITEMS TABLE --}}
          <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
              <h2 class="card-title">Invoice Items</h2>
              <div>
                <input type="file" id="excel_import" class="d-none" accept=".xlsx, .xls, .csv">
                <button type="button" class="btn btn-success" onclick="document.getElementById('excel_import').click()">
                  <i class="fas fa-file-excel"></i> Import Excel
                </button>
              </div>
            </header>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th width="12%" rowspan="2">Item Name</th>
                    <th width="13%" rowspan="2">Item Description</th>
                    <th width="9%"  rowspan="2">Purity</th>
                    <th rowspan="2">Gross Wt</th>
                    <th rowspan="2">Purity Wt</th>
                    <th rowspan="2">995</th>
                    <th colspan="2" class="text-center">Making</th>
                    <th width="6%" rowspan="2">Material</th>
                    <th rowspan="2">Material Val</th>
                    <th rowspan="2">Taxable (MC)</th>
                    <th rowspan="2">VAT %</th>
                    <th rowspan="2">VAT Amt</th>
                    <th rowspan="2">Gross Total</th>
                    <th rowspan="2" class="text-success fw-bold" title="(Sale - Cost) / Cost × 100">Profit %</th>
                    <th width="6%" rowspan="2">Action</th>
                  </tr>
                  <tr><th>Rate</th><th>Value</th></tr>
                </thead>
                <tbody id="SaleTable">{{-- rendered by JS --}}</tbody>
              </table>
              <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">Add Item</button>
            </div>
          </section>

          {{-- SUMMARY --}}
          <div class="row mt-5 mb-5">
            <div class="col-md-2"><label>Total Gross Wt</label><input type="text" id="sum_gross_weight" class="form-control" readonly></div>
            <div class="col-md-2"><label>Total Purity Wt</label><input type="text" id="sum_purity_weight" class="form-control" readonly></div>
            <div class="col-md-2"><label>Total 995</label><input type="text" id="sum_995" class="form-control" readonly></div>
            <div class="col-md-2"><label>Total Making (Incl. VAT)</label><input type="text" id="sum_making_value" class="form-control" readonly></div>
            <div class="col-md-2"><label>Total Material Val.</label><input type="text" id="sum_material_value" class="form-control" readonly></div>
            <div class="col-md-2"><label>Total VAT</label><input type="text" id="sum_vat_amount" class="form-control" readonly></div>
            <div class="col-md-2 mt-3">
              <label>Net Amount</label>
              <input type="text" id="net_amount_display" class="form-control text-danger fw-bold" readonly>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
            <div class="col-md-2 mt-3">
              <label class="text-success fw-bold">Overall Invoice Profit %</label>
              <input type="text" id="overall_profit_pct" class="form-control fw-bold border-success text-center" readonly style="font-size:1.1rem;">
            </div>
          </div>

          {{-- PAYMENT --}}
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
            <div class="col-md-2"><label>Received By</label><input type="text" name="received_by" class="form-control" value="{{ $saleInvoice->received_by }}"></div>
          </div>

          <div class="row mb-3 d-none" id="cheque_fields">
            <div class="col-md-2">
              <label>Bank Name</label>
              <select name="bank_name" class="form-control select2-js">
                <option value="">Select Bank</option>
                @foreach ($banks as $bank)
                  <option value="{{ $bank->id }}" {{ $saleInvoice->bank_name == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2"><label>Cheque No</label><input type="text" name="cheque_no" class="form-control" value="{{ $saleInvoice->cheque_no }}"></div>
            <div class="col-md-2"><label>Cheque Date</label><input type="date" name="cheque_date" class="form-control" value="{{ $saleInvoice->cheque_date }}"></div>
            <div class="col-md-2"><label>Cheque Amount</label><input type="number" step="any" name="cheque_amount" class="form-control" value="{{ $saleInvoice->cheque_amount }}"></div>
          </div>

          <div class="row mb-3 d-none" id="material_fields">
            <div class="col-md-2"><label>Total Item Wt. Received</label><input type="text" id="total_wt_received" class="form-control"></div>
            <div class="col-md-2"><label>Raw Material Weight Given</label><input type="number" step="any" name="material_weight" class="form-control" value="{{ $saleInvoice->material_weight }}"></div>
            <div class="col-md-2"><label>Raw Material Purity</label><input type="number" step="any" name="material_purity" class="form-control" value="{{ $saleInvoice->material_purity }}"></div>
            <div class="col-md-2"><label>Material Adjustment Value</label><input type="number" step="any" name="material_value" class="form-control" value="{{ $saleInvoice->material_value }}"></div>
            <div class="col-md-2"><label>Making Charges Payable</label><input type="number" step="any" name="making_charges" class="form-control" value="{{ $saleInvoice->making_charges }}"></div>
            <div class="col-md-2"><label>Gold Used (Invoice)</label><input type="text" id="gold_used" class="form-control"></div>
            <div class="col-md-2 mt-3"><label>Gold Balance</label><input type="text" id="gold_balance" class="form-control fw-bold" readonly></div>
            <div class="col-md-2 mt-3"><label>Gold Balance Value (AED)</label><input type="text" id="gold_balance_value" class="form-control text-danger fw-bold" readonly></div>
            <div class="col-md-2 mt-3"><label>Material Given By</label><input type="text" name="material_given_by" class="form-control" value="{{ $saleInvoice->material_given_by }}"></div>
            <div class="col-md-2 mt-3"><label>Material Received By</label><input type="text" name="material_received_by" class="form-control" value="{{ $saleInvoice->material_received_by }}"></div>
          </div>

          <div class="row mb-3 d-none" id="bank_transfer_fields">
            <div class="col-md-2">
              <label>Transfer From Bank</label>
              <select name="transfer_from_bank" class="form-control select2-js">
                <option value="">Select Bank</option>
                @foreach ($banks as $bank)
                  <option value="{{ $bank->id }}" {{ $saleInvoice->transfer_from_bank == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2"><label>Customer Bank Name</label><input type="text" name="transfer_to_bank" class="form-control" value="{{ $saleInvoice->transfer_to_bank }}" placeholder="e.g. Emirates NBD"></div>
            <div class="col-md-2"><label>Account Title</label><input type="text" name="account_title" class="form-control" value="{{ $saleInvoice->account_title }}"></div>
            <div class="col-md-2"><label>Account Number</label><input type="text" name="account_no" class="form-control" value="{{ $saleInvoice->account_no }}"></div>
            <div class="col-md-2"><label>Transaction Ref No</label><input type="text" name="transaction_id" class="form-control" value="{{ $saleInvoice->transaction_id }}"></div>
            <div class="col-md-2"><label>Transfer Date</label><input type="date" name="transfer_date" class="form-control" value="{{ $saleInvoice->transfer_date ? \Carbon\Carbon::parse($saleInvoice->transfer_date)->format('Y-m-d') : '' }}"></div>
            <div class="col-md-2 mt-2"><label>Transfer Amount</label><input type="number" step="any" name="transfer_amount" class="form-control" value="{{ $saleInvoice->transfer_amount }}"></div>
          </div>

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
                  <label class="form-label">USD to AED Rate <span class="text-danger">*</span></label>
                  <input type="number" step="0.000001" name="exchange_rate" id="exchange_rate" class="form-control" value="{{ $saleInvoice->exchange_rate }}" placeholder="3.6725">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Converted Total (AED)</label>
                  <input type="text" id="converted_total" class="form-control" readonly>
                </div>
              </div>
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary me-2"><i class="fas fa-times"></i> Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Invoice</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () {
    const products           = @json($products);
    const existingItems      = @json($itemsData);
    const TROY_OUNCE_TO_GRAM = 31.1034768;
    const BARCODE_SCAN_URL   = '{{ route("sale.scan_barcode") }}';

    $(document).on('click', '.toggle-parts', function() {
        $(this).closest('tr').next('.parts-row').fadeToggle(200);
    });

    function initCurrencyBox() {
        const isUSD = $('#currency').val() === 'USD';
        if (isUSD) { $('#exchangeRateBox').show(); $('#exchange_rate').attr('required', true); }
        else        { $('#exchangeRateBox').hide(); $('#exchange_rate').removeAttr('required'); }
    }
    initCurrencyBox();

    $('#currency').on('change', function() {
        const isUSD = $(this).val() === 'USD';
        if (isUSD) { $('#exchangeRateBox').show(); $('#exchange_rate').attr('required', true); if (!$('#exchange_rate').val()) $('#exchange_rate').val('3.6725'); }
        else        { $('#exchangeRateBox').hide(); $('#exchange_rate').removeAttr('required').val(''); }
        calculateTotals();
    });
    $('#exchange_rate').on('input', calculateTotals);
    $('.select2-js').select2({ width: '100%' });

    // ======================== BARCODE SCANNER ========================

    function showScanResult(msg, type) {
        const el = $('#barcode_scan_result');
        el.removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-' + type).html(msg);
        clearTimeout(window._scanTimer);
        window._scanTimer = setTimeout(() => el.addClass('d-none'), 4000);
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

                // Duplicate check
                let duplicate = false;
                $('#SaleTable tr.item-row').each(function() {
                    if ($(this).find('input[name*="[barcode_number]"]').val() === barcode) {
                        duplicate = true; return false;
                    }
                });
                if (duplicate) {
                    showScanResult('<i class="fas fa-exclamation-triangle"></i> <strong>' + barcode + '</strong> is already on this invoice.', 'warning');
                    return;
                }

                addNewRow();
                const newRow = $('#SaleTable tr.item-row').last();

                newRow.find('.item-name-input').val(data.item_name);
                newRow.find('input[name*="[barcode_number]"]').val(data.barcode_number);
                newRow.find('input[name*="[item_description]"]').val(data.item_description);

                const pur     = parseFloat(data.purity);
                const nearest = [0.92, 0.88, 0.75, 0.60].reduce((a, b) => Math.abs(b - pur) < Math.abs(a - pur) ? b : a);
                newRow.find('.purity').val(nearest);

                newRow.find('.gross-weight').val(parseFloat(data.gross_weight).toFixed(3))
                                           .data('base-gross', parseFloat(data.gross_weight));
                newRow.find('.making-rate').val(data.making_rate);
                newRow.find('.material-type').val(data.material_type || 'gold');
                newRow.find('.vat-percent').val(data.vat_percent);

                if (data.parts && data.parts.length > 0) {
                    const partsRow  = newRow.next('.parts-row');
                    const partsBody = partsRow.find('.parts-table tbody');
                    const itemIndex = newRow.data('item-index');
                    partsRow.show();
                    data.parts.forEach((part, j) => partsBody.append(buildPartRowHtml(itemIndex, j, part)));
                    recalcItemGrossWeight(newRow);
                } else {
                    calculateRow(newRow);
                }

                calculateTotals();

                const src = data.source === 'purchase' ? ' <span class="badge bg-info">from Purchase</span>' : '';
                showScanResult('<i class="fas fa-check-circle"></i> Added: <strong>' + data.item_name + '</strong>' + src, 'success');
                newRow.addClass('table-warning');
                setTimeout(() => newRow.removeClass('table-warning'), 2000);
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Lookup failed. Check barcode and try again.';
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

    // ======================== ROW MANAGEMENT ========================

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
                    if (n) $(this).attr('name', n.replace(/items\[\d+\]/, `items[${i}]`).replace(/parts\[\d+\]/, `parts[${j}]`));
                });
            });
        });
    }

    function buildItemRowHtml(index, data) {
        data = data || {};
        const name    = data.item_name        || '';
        const desc    = data.item_description || '';
        const purity  = data.purity           || '0.92';
        const gross   = data.gross_weight     || 0;
        const mkRate  = data.making_rate      || 0;
        const matType = data.material_type    || 'gold';
        const vatPct  = data.vat_percent      || 0;

        return `
        <tr class="item-row" data-item-index="${index}">
            <td><div class="product-wrapper">
                <input type="text" name="items[${index}][item_name]" class="form-control item-name-input" placeholder="Product Name" value="${name}">
                <input type="hidden" name="items[${index}][barcode_number]" value="${data.barcode_number || ''}">
                <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
            </div></td>
            <td><input type="text" name="items[${index}][item_description]" class="form-control" value="${desc}" required></td>
            <td><select name="items[${index}][purity]" class="form-control purity">
                <option value="0.92" ${purity == 0.92 ? 'selected' : ''}>22K (92%)</option>
                <option value="0.88" ${purity == 0.88 ? 'selected' : ''}>21K (88%)</option>
                <option value="0.75" ${purity == 0.75 ? 'selected' : ''}>18K (75%)</option>
                <option value="0.60" ${purity == 0.60 ? 'selected' : ''}>14K (60%)</option>
            </select></td>
            <td><input type="number" name="items[${index}][gross_weight]" step="any" value="${gross}" class="form-control gross-weight"></td>
            <td><input type="number" name="items[${index}][purity_weight]" step="any" value="0" class="form-control purity-weight" readonly></td>
            <td><input type="number" name="items[${index}][995]" step="any" value="0" class="form-control col-995" readonly></td>
            <td><input type="number" name="items[${index}][making_rate]" step="any" value="${mkRate}" class="form-control making-rate"></td>
            <td><input type="number" name="items[${index}][making_value]" step="any" class="form-control making-value" readonly></td>
            <td><select name="items[${index}][material_type]" class="form-control material-type">
                <option value="gold"    ${matType === 'gold'    ? 'selected' : ''}>Gold</option>
                <option value="diamond" ${matType === 'diamond' ? 'selected' : ''}>Diamond</option>
            </select></td>
            <td><input type="number" name="items[${index}][metal_value]" step="any" value="0" class="form-control material-value" readonly></td>
            <td><input type="number" name="items[${index}][taxable_amount]" step="any" value="0" class="form-control taxable-amount" readonly></td>
            <td><input type="number" name="items[${index}][vat_percent]" class="form-control vat-percent" step="any" value="${vatPct}"></td>
            <td><input type="number" step="any" class="form-control vat-amount" readonly></td>
            <td><input type="number" class="form-control item-total" readonly></td>
            <td><input type="text" class="form-control item-profit-pct fw-bold text-center" readonly style="min-width:75px;"></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                <button type="button" class="btn btn-sm btn-primary toggle-parts"><i class="fas fa-wrench"></i></button>
            </td>
        </tr>
        <tr class="parts-row" style="display:none;background:#efefef">
            <td colspan="16"><div class="parts-wrapper">
                <table class="table table-sm table-bordered parts-table">
                    <thead><tr><th>Part</th><th>Description</th><th>Carat (CTS)</th><th>Rate</th><th>Stone Ct.</th><th>Stone Rate</th><th>Total</th><th></th></tr></thead>
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
                <input type="text" name="items[${itemIndex}][parts][${partIndex}][item_name]" class="form-control item-name-input" placeholder="Part Name" value="${data.item_name||''}">
                <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
            </div></td>
            <td><input type="text" name="items[${itemIndex}][parts][${partIndex}][part_description]" class="form-control" value="${data.part_description||''}"></td>
            <td><div class="input-group">
                <input type="number" name="items[${itemIndex}][parts][${partIndex}][qty]" step="any" value="${data.qty||0}" class="form-control part-qty">
                <input type="text" class="form-control part-unit-name" style="width:70px;flex:none;" readonly placeholder="CTS">
            </div></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][rate]" step="any" value="${data.rate||0}" class="form-control part-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_qty]" step="any" value="${data.stone_qty||0}" class="form-control part-stone-qty"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_rate]" step="any" value="${data.stone_rate||0}" class="form-control part-stone-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][total]" step="any" value="${data.total||0}" class="form-control part-total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-part"><i class="fas fa-times"></i></button></td>
        </tr>`;
    }

    // ======================== LOAD EXISTING ITEMS ========================
    existingItems.forEach(function(itemData, i) {
        $('#SaleTable').append(buildItemRowHtml(i, itemData));
        const itemRow  = $('#SaleTable tr.item-row').last();
        const partsRow = itemRow.next('.parts-row');
        itemRow.find('.gross-weight').data('base-gross', parseFloat(itemData.gross_weight) || 0);
        if (itemData.parts && itemData.parts.length > 0) {
            partsRow.show();
            itemData.parts.forEach((p, j) => partsRow.find('.parts-table tbody').append(buildPartRowHtml(i, j, p)));
            recalcItemGrossWeight(itemRow);
        } else {
            calculateRow(itemRow);
        }
    });
    calculateTotals();

    // Init payment fields
    (function() {
        const val = $('#payment_method').val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields').addClass('d-none');
        if (val === 'cheque')                    $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')                 $('#received_by_box').removeClass('d-none');
        else if (val === 'bank_transfer')        $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost') $('#material_fields').removeClass('d-none');
    })();

    window.addNewRow = function() {
        const idx = $('#SaleTable tr.item-row').length;
        $('#SaleTable').append(buildItemRowHtml(idx, {}));
        updateRowIndexes();
    };

    window.removeRow = function(btn) {
        const row = $(btn).closest('tr');
        if ($('#SaleTable tr.item-row').length > 1) {
            row.next('.parts-row').remove(); row.remove();
            updateRowIndexes(); calculateTotals();
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
        recalcItemGrossWeight(itemRow); calculateTotals();
    });

    // ======================== PRODUCT TOGGLE ========================
    $(document).on('click', '.toggle-product, .revert-to-name', function () {
        const isReverting = $(this).hasClass('revert-to-name');
        const wrapper     = $(this).closest('.product-wrapper');
        const isPart      = wrapper.closest('tr').hasClass('part-item-row');
        const itemIdx     = isPart ? wrapper.closest('.parts-row').prev('.item-row').data('item-index') : wrapper.closest('.item-row').data('item-index');
        const namePath    = isPart ? `items[${itemIdx}][parts][${wrapper.closest('.part-item-row').data('part-index')}]` : `items[${itemIdx}]`;

        if (isReverting) {
            wrapper.html(`<input type="text" name="${namePath}[item_name]" class="form-control item-name-input" placeholder="Name">
                          <input type="hidden" name="${namePath}[barcode_number]" value="">
                          <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>`);
        } else {
            wrapper.html(`
                <select name="${namePath}[product_id]" class="form-control select2-js product-select mb-2">
                    <option value="">Select Product</option>
                    ${products.map(p => `<option value="${p.id}" data-unit="${p.measurement_unit ? p.measurement_unit.name : ''}">${p.name}</option>`).join('')}
                </select>
                <select name="${namePath}[variation_id]" class="form-control select2-js variation-select">
                    <option value="">Select Variation</option>
                </select>
                <button type="button" class="btn btn-link p-0 revert-to-name mt-1">Write Name</button>
            `).find('.select2-js').select2({ width: '100%' });
        }
    });

    $(document).on('change', '.product-select', function() {
        const productId = $(this).val();
        const row = $(this).closest('tr');
        const variationSelect = row.find('.variation-select');
        const unitInput = row.find('.part-unit-name');
        if (unitInput.length) unitInput.val($(this).find(':selected').data('unit') || '');
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

    // ======================== PROFIT % ========================
    function calcProfitPct(sale, cost) {
        if (!cost || cost === 0) return { pct: null, label: 'N/A' };
        const pct = ((sale - cost) / cost) * 100;
        return { pct, label: pct.toFixed(2) + '%' };
    }

    function colourProfitInput(el, pct) {
        el.css('color', pct === null ? '#6c757d' : pct >= 0 ? '#198754' : '#dc3545');
    }

    // ======================== CALCULATIONS ========================
    $(document).on('input', '.gross-weight', function() {
        $(this).data('base-gross', parseFloat($(this).val()) || 0);
        recalcItemGrossWeight($(this).closest('tr.item-row'));
    });

    $(document).on('input change',
        '.purity, .making-rate, .vat-percent, .material-type, #gold_rate_aed, #diamond_rate_aed_gram, #purchase_gold_rate_aed, #purchase_making_rate_aed',
    function() {
        const row = $(this).closest('tr.item-row');
        if (row.length) calculateRow(row);
        calculateTotals();
    });

    function recalcItemGrossWeight(itemRow) {
        if (!itemRow || !itemRow.length) return;
        const grossInput = itemRow.find('.gross-weight');
        let baseGross = parseFloat(grossInput.data('base-gross'));
        if (isNaN(baseGross)) { baseGross = parseFloat(grossInput.val()) || 0; grossInput.data('base-gross', baseGross); }
        let contrib = 0;
        itemRow.next('.parts-row').find('.part-item-row').each(function() {
            contrib += (parseFloat($(this).find('.part-qty').val()) || 0) / 5;
        });
        grossInput.val((baseGross + contrib).toFixed(3));
        calculateRow(itemRow); calculateTotals();
    }

    function calculateRow(row) {
        const purity   = parseFloat(row.find('.purity').val())      || 0;
        const gross    = parseFloat(row.find('.gross-weight').val()) || 0;
        const mkRate   = parseFloat(row.find('.making-rate').val())  || 0;
        const vatPct   = parseFloat(row.find('.vat-percent').val())  || 0;
        const matType  = row.find('.material-type').val();
        const saleRate = (matType === 'gold' ? parseFloat($('#gold_rate_aed').val()) : parseFloat($('#diamond_rate_aed_gram').val())) || 0;
        const purGoldR = parseFloat($('#purchase_gold_rate_aed').val())  || 0;
        const purMkR   = parseFloat($('#purchase_making_rate_aed').val()) || 0;

        const purityWt  = gross * purity;
        const col995    = purityWt / 0.995;
        const mkVal     = gross * mkRate;
        const matVal    = saleRate * purityWt;
        const taxable   = mkVal;
        const vatAmt    = taxable * vatPct / 100;
        const itemTotal = taxable + matVal + vatAmt;
        const costTotal = (purGoldR * purityWt) + (gross * purMkR);

        row.find('.purity-weight').val(purityWt.toFixed(3));
        row.find('.col-995').val(col995.toFixed(3));
        row.find('.making-value').val(mkVal.toFixed(2));
        row.find('.material-value').val(matVal.toFixed(2));
        row.find('.taxable-amount').val(taxable.toFixed(2));
        row.find('.vat-amount').val(vatAmt.toFixed(2));
        row.find('.item-total').val(itemTotal.toFixed(2));

        const profitInput = row.find('.item-profit-pct');
        const { pct, label } = calcProfitPct(itemTotal, costTotal);
        profitInput.val(label); colourProfitInput(profitInput, pct);
    }

    function calculateTotals() {
        let sG=0, sP=0, s9=0, sMT=0, sMat=0, sV=0, net=0, cost=0;
        const purGoldR = parseFloat($('#purchase_gold_rate_aed').val())  || 0;
        const purMkR   = parseFloat($('#purchase_making_rate_aed').val()) || 0;
        $('#SaleTable tr.item-row').each(function() {
            const gross = parseFloat($(this).find('.gross-weight').val())  || 0;
            const purWt = parseFloat($(this).find('.purity-weight').val()) || 0;
            sG   += gross; sP += purWt;
            s9   += parseFloat($(this).find('.col-995').val())        || 0;
            sMT  += parseFloat($(this).find('.taxable-amount').val()) || 0;
            sMat += parseFloat($(this).find('.material-value').val()) || 0;
            sV   += parseFloat($(this).find('.vat-amount').val())     || 0;
            net  += parseFloat($(this).find('.item-total').val())     || 0;
            cost += (purGoldR * purWt) + (gross * purMkR);
        });
        const mkWithVat = sMT + sV;
        $('#sum_gross_weight').val(sG.toFixed(3));
        $('#sum_purity_weight').val(sP.toFixed(3));
        $('#sum_995').val(s9.toFixed(3));
        $('#sum_making_value').val(mkWithVat.toFixed(2));
        $('#sum_material_value').val(sMat.toFixed(2));
        $('#sum_vat_amount').val(sV.toFixed(2));
        $('#net_amount_display').val(net.toFixed(2));
        $('#net_amount').val(net.toFixed(2));
        const exRate = parseFloat($('#exchange_rate').val()) || 1;
        $('#converted_total').val($('#currency').val() === 'USD' ? (net * exRate).toFixed(2) : net.toFixed(2));
        const oi = $('#overall_profit_pct');
        const { pct, label } = calcProfitPct(net, cost);
        oi.val(label); colourProfitInput(oi, pct);
        if ($('#payment_method').val() === 'material+making cost') {
            $('input[name="material_weight"]').val(s9.toFixed(3));
            $('input[name="material_purity"]').val(sP.toFixed(3));
            $('input[name="material_value"]').val(sMat.toFixed(2));
            $('input[name="making_charges"]').val(mkWithVat.toFixed(2));
        }
    }

    $(document).on('input', '#gold_rate_usd, #gold_rate_aed_ounce, #diamond_rate_usd, #diamond_rate_aed_ounce, #exchange_rate', function() {
        const id = $(this).attr('id');
        const ex = parseFloat($('#exchange_rate').val()) || 3.6725;
        if (id === 'gold_rate_usd' || id === 'exchange_rate')
            $('#gold_rate_aed_ounce').val(((parseFloat($('#gold_rate_usd').val())||0)*ex).toFixed(2));
        $('#gold_rate_aed').val(((parseFloat($('#gold_rate_aed_ounce').val())||0)/TROY_OUNCE_TO_GRAM).toFixed(4));
        if (id === 'diamond_rate_usd' || id === 'exchange_rate')
            $('#diamond_rate_aed_ounce').val(((parseFloat($('#diamond_rate_usd').val())||0)*ex).toFixed(2));
        $('#diamond_rate_aed_gram').val(((parseFloat($('#diamond_rate_aed_ounce').val())||0)/TROY_OUNCE_TO_GRAM).toFixed(4));
        $('#SaleTable tr.item-row').each(function() { calculateRow($(this)); });
        calculateTotals();
    });

    $('#payment_method').on('change', function() {
        const val = $(this).val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields').addClass('d-none');
        if (val === 'cheque')                    $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')                 $('#received_by_box').removeClass('d-none');
        else if (val === 'bank_transfer')        $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost') $('#material_fields').removeClass('d-none');
        calculateTotals();
    });

    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate', function() {
      const row = $(this).closest('tr');
      row.find('.part-total').val(((parseFloat(row.find('.part-qty').val())||0)*(parseFloat(row.find('.part-rate').val())||0)
          + (parseFloat(row.find('.part-stone-qty').val())||0)*(parseFloat(row.find('.part-stone-rate').val())||0)).toFixed(2));
      recalcItemGrossWeight(row.closest('.parts-row').prev('.item-row'));
    });

    $('#excel_import').on('change', function(e) {
      const file = e.target.files[0]; if (!file) return;
      const reader = new FileReader();
      reader.onload = function(e) {
          const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
          const rows = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);
          if (!rows.length) return;
          let cur = null;
          rows.forEach(r => {
              if (r['Item Name'] && r['Item Name'].trim()) {
                  addNewRow(); cur = $('#SaleTable tr.item-row').last();
                  const bg = parseFloat(r['Gross Wt'])||0;
                  cur.find('.gross-weight').data('base-gross', bg).val(bg);
                  cur.find('.item-name-input').val(r['Item Name']);
                  cur.find('input[name*="[item_description]"]').val(r['Description']||'');
                  cur.find('.purity').val(r['Purity']||'0.92');
                  cur.find('.making-rate').val(r['Making Rate']||0);
                  cur.find('.material-type').val((r['Material']||'gold').toLowerCase());
                  cur.find('.vat-percent').val(r['VAT %']||0);
                  calculateRow(cur);
              }
              if (r['Part Name'] && r['Part Name'].trim() && cur) {
                  const pr = cur.next('.parts-row'); pr.show(); pr.find('.add-part').click();
                  const cp = pr.find('.part-item-row').last();
                  cp.find('.item-name-input').val(r['Part Name']);
                  cp.find('input[name*="[part_description]"]').val(r['Part Desc']||'');
                  cp.find('.part-qty').val(r['Part Qty']||0).trigger('input');
                  cp.find('.part-rate').val(r['Part Rate']||0);
                  cp.find('.part-stone-qty').val(r['Stone Qty']||0);
                  cp.find('.part-stone-rate').val(r['Stone Rate']||0);
              }
          });
          calculateTotals(); alert('Items Imported Successfully!'); $('#excel_import').val('');
      };
      reader.readAsArrayBuffer(file);
    });
  });

  function resubmitWithConfirm() {
    const form  = document.querySelector('form[action*="update"]');
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