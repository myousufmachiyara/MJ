@extends('layouts.app')

@section('title', 'Purchase | Edit Invoice #' . $purchaseInvoice->invoice_no)

@section('content')
<div class="row">
  <div class="col">
    <form id="main-form" action="{{ route('purchase_invoices.update', $purchaseInvoice->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if(session('printed_delete_warning'))
        <div class="alert alert-warning">
          <strong>Warning:</strong> The following items have already been printed and will be permanently deleted:
          <br><code>{{ session('printed_delete_warning') }}</code>
          <br><br>
          <form method="POST" action="{{ route('purchase_invoices.update', $purchaseInvoice->id) }}" enctype="multipart/form-data" id="confirm-delete-form">
            @csrf @method('PUT')
            <input type="hidden" name="confirm_delete_printed" value="1">
            <button type="button" class="btn btn-danger" onclick="resubmitWithConfirm()">
              Delete anyway and update invoice
            </button>
            <a href="{{ route('purchase_invoices.edit', $purchaseInvoice->id) }}" class="btn btn-secondary ms-2">
              Go back and keep items
            </a>
          </form>
        </div>
      @endif

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Invoice <span class="text-primary">#{{ $purchaseInvoice->invoice_no }}</span></h2>
          <span class="badge bg-{{ $purchaseInvoice->is_taxable ? 'success' : 'secondary' }} fs-6">
            {{ $purchaseInvoice->is_taxable ? 'Taxable (PUR-TAX)' : 'Non-Taxable (PUR)' }}
          </span>
        </header>

        <div class="card-body">

          {{-- ===================== HEADER ===================== --}}
          <div class="row mb-5">

            <div class="col-md-2">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control"
                value="{{ \Carbon\Carbon::parse($purchaseInvoice->invoice_date)->format('Y-m-d') }}">
            </div>

            <div class="col-md-2">
              <label class="fw-bold">Invoice Type</label>
              <select name="is_taxable" id="is_taxable" class="form-control border-primary" required>
                <option value="1" {{ $purchaseInvoice->is_taxable ? 'selected' : '' }}>Taxable (PUR-TAX)</option>
                <option value="0" {{ !$purchaseInvoice->is_taxable ? 'selected' : '' }}>Non-Taxable (PUR)</option>
              </select>
              <small class="text-muted">Invoice number is locked after creation</small>
            </div>

            <div class="col-md-2">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" {{ $purchaseInvoice->vendor_id == $vendor->id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Gold Rates --}}
            <div class="col-12 col-md-2">
              <label>Gold Rate (USD / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_usd" name="gold_rate_usd"
                class="form-control" value="{{ $purchaseInvoice->gold_rate_usd ?? 0 }}">
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (AED / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_aed_ounce" name="gold_rate_aed_ounce"
                class="form-control" value="{{ round($goldAedOunce, 2) }}">
            </div>

            <div class="col-12 col-md-3">
              <label class="text-primary">Gold Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="gold_rate_aed" name="gold_rate_aed"
                class="form-control" value="{{ $purchaseInvoice->gold_rate_aed ?? 0 }}" readonly>
              <small class="text-danger fw-bold">Used for calculations</small>
            </div>

            {{-- Diamond Rates — Ct. only, no gram/ounce conversion --}}
            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (USD) / Ct.</label>
              <input type="number" step="any" id="diamond_rate_usd" name="diamond_rate_usd"
                class="form-control" value="{{ $purchaseInvoice->diamond_rate_usd ?? 0 }}">
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (AED) / Ct.</label>
              <input type="number" step="any" id="diamond_rate_aed" name="diamond_rate_aed"
                class="form-control" value="{{ $diamondAedCt ?? 0 }}">
              <small class="text-danger fw-bold">Used for calculations</small>
            </div>

            <div class="col-md-4 mt-2">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control">{{ $purchaseInvoice->remarks }}</textarea>
            </div>

            <div class="col-md-4 mt-2">
              <label>Add Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
              @if($purchaseInvoice->attachments->count())
                <small class="text-muted">
                  {{ $purchaseInvoice->attachments->count() }} existing attachment(s) — uploading new files adds to them.
                </small>
              @endif
            </div>

          </div>{{-- end header row --}}

          {{-- ===================== ITEMS TABLE ===================== --}}
          <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
              <h2 class="card-title">Invoice Items</h2>
              <div>
                <input type="file" id="excel_import" class="d-none" accept=".xlsx,.xls,.csv">
                <button type="button" class="btn btn-success" onclick="document.getElementById('excel_import').click()">
                  <i class="fas fa-file-excel"></i> Import Excel
                </button>
                <a href="{{ route('purchase.download_template') }}" class="btn btn-danger">
                  <i class="fas fa-download"></i> Download Template
                </a>
              </div>
            </header>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th width="12%" rowspan="2">Item Name</th>
                    <th width="12%" rowspan="2">Description</th>
                    <th width="8%"  rowspan="2">Purity</th>
                    <th rowspan="2">Net Wt</th>
                    <th rowspan="2">Gross Wt</th>
                    <th rowspan="2">Purity Wt</th>
                    <th rowspan="2">995</th>
                    <th colspan="2" class="text-center">Making</th>
                    <th width="6%" rowspan="2">Material</th>
                    <th rowspan="2">Material Val</th>
                    <th rowspan="2">Taxable</th>
                    <th rowspan="2">VAT %</th>
                    <th rowspan="2">VAT Amt</th>
                    <th rowspan="2">Gross Total</th>
                    <th width="6%" rowspan="2">Action</th>
                  </tr>
                  <tr>
                    <th>Rate</th>
                    <th>Value</th>
                  </tr>
                </thead>
                <tbody id="PurchaseTable">
                  {{-- Rendered by JS --}}
                </tbody>
              </table>
              <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">Add Item</button>
            </div>
          </section>

          {{-- ===================== SUMMARY ===================== --}}
          <div class="row mt-5 mb-5">
            <div class="col-md-2">
              <label>Gold Gross Wt</label>
              <input type="text" id="sum_gold_gross_weight" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Diamond CTS</label>
              <input type="text" id="sum_diamond_cts" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Stone Qty</label>
              <input type="text" id="sum_stone_qty" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Purity Wt</label>
              <input type="text" id="sum_purity_weight" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total 995</label>
              <input type="text" id="sum_995" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Making (Incl. VAT)</label>
              <input type="text" id="sum_making_value" class="form-control" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Total Material Val.</label>
              <input type="text" id="sum_material_value" class="form-control" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Diamond Parts Val.</label>
              <input type="text" id="sum_diamond_value" class="form-control" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Stone Parts Val.</label>
              <input type="text" id="sum_stone_value" class="form-control" readonly>
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
          </div>

          {{-- ===================== PAYMENT METHOD ===================== --}}
          <div class="row mb-3">
            <div class="col-md-2">
              <label class="fw-bold">Payment Method</label>
              <select name="payment_method" id="payment_method" class="form-control" required>
                <option value="">Select Payment Method</option>
                @foreach(['credit','cash','bank_transfer','cheque','material+making cost'] as $pm)
                  <option value="{{ $pm }}" {{ $purchaseInvoice->payment_method === $pm ? 'selected' : '' }}>
                    {{ ucwords(str_replace(['_','+'], [' ',' + '], $pm)) }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Payment Term</label>
              <input type="text" name="payment_term" class="form-control" value="{{ $purchaseInvoice->payment_term }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="received_by_box">
            <div class="col-md-2">
              <label>Received By</label>
              <input type="text" name="received_by" class="form-control" value="{{ $purchaseInvoice->received_by }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="cheque_fields">
            <div class="col-md-2">
              <label>Bank Name</label>
              <select name="bank_name" class="form-control select2-js">
                <option value="">Select Bank</option>
                @foreach ($banks as $bank)
                  <option value="{{ $bank->id }}" {{ $purchaseInvoice->bank_name == $bank->id ? 'selected' : '' }}>
                    {{ $bank->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Cheque No</label>
              <input type="text" name="cheque_no" class="form-control" value="{{ $purchaseInvoice->cheque_no }}">
            </div>
            <div class="col-md-2">
              <label>Cheque Date</label>
              <input type="date" name="cheque_date" class="form-control" value="{{ $purchaseInvoice->cheque_date }}">
            </div>
            <div class="col-md-2">
              <label>Cheque Amount</label>
              <input type="number" step="any" name="cheque_amount" class="form-control" value="{{ $purchaseInvoice->cheque_amount }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="material_fields">
            <div class="col-md-2">
              <label>Raw Material Weight Given</label>
              <input type="number" step="any" name="material_weight" class="form-control" value="{{ $purchaseInvoice->material_weight }}">
            </div>
            <div class="col-md-2">
              <label>Raw Material Purity</label>
              <input type="number" step="any" name="material_purity" class="form-control" value="{{ $purchaseInvoice->material_purity }}">
            </div>
            <div class="col-md-2">
              <label>Material Adjustment Value</label>
              <input type="number" step="any" name="material_value" class="form-control" value="{{ $purchaseInvoice->material_value }}">
            </div>
            <div class="col-md-2">
              <label>Making Charges Payable</label>
              <input type="number" step="any" name="making_charges" class="form-control" value="{{ $purchaseInvoice->making_charges }}">
            </div>
            <div class="col-md-2">
              <label>Material Given By</label>
              <input type="text" name="material_given_by" class="form-control" value="{{ $purchaseInvoice->material_given_by }}">
            </div>
            <div class="col-md-2">
              <label>Material Received By</label>
              <input type="text" name="material_received_by" class="form-control" value="{{ $purchaseInvoice->material_received_by }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="bank_transfer_fields">
            <div class="col-md-2">
              <label>Transfer From Bank</label>
              <select name="transfer_from_bank" class="form-control select2-js">
                <option value="">Select Bank</option>
                @foreach ($banks as $bank)
                  <option value="{{ $bank->id }}" {{ $purchaseInvoice->transfer_from_bank == $bank->id ? 'selected' : '' }}>
                    {{ $bank->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Vendor Bank Name</label>
              <input type="text" name="transfer_to_bank" class="form-control" value="{{ $purchaseInvoice->transfer_to_bank }}" placeholder="e.g. Emirates NBD">
            </div>
            <div class="col-md-2">
              <label>Account Title</label>
              <input type="text" name="account_title" class="form-control" value="{{ $purchaseInvoice->account_title }}">
            </div>
            <div class="col-md-2">
              <label>Account Number</label>
              <input type="text" name="account_no" class="form-control" value="{{ $purchaseInvoice->account_no }}">
            </div>
            <div class="col-md-2">
              <label>Transaction Ref No</label>
              <input type="text" name="transaction_id" class="form-control" value="{{ $purchaseInvoice->transaction_id }}">
            </div>
            <div class="col-md-2">
              <label>Transfer Date</label>
              <input type="date" name="transfer_date" class="form-control"
                value="{{ $purchaseInvoice->transfer_date ? \Carbon\Carbon::parse($purchaseInvoice->transfer_date)->format('Y-m-d') : '' }}">
            </div>
            <div class="col-md-2 mt-2">
              <label>Transfer Amount</label>
              <input type="number" step="any" name="transfer_amount" class="form-control" value="{{ $purchaseInvoice->transfer_amount }}">
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
                    <option value="AED" {{ $purchaseInvoice->currency === 'AED' ? 'selected' : '' }}>AED / Dirhams</option>
                    <option value="USD" {{ $purchaseInvoice->currency === 'USD' ? 'selected' : '' }}>USD Dollars</option>
                  </select>
                </div>
                <div class="col-md-2" id="exchangeRateBox" style="display:none;">
                  <label class="form-label">USD → AED Rate <span class="text-danger">*</span></label>
                  <input type="number" step="0.000001" name="exchange_rate" id="exchange_rate"
                    class="form-control" value="{{ $purchaseInvoice->exchange_rate }}" placeholder="3.6725">
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
          <a href="{{ route('purchase_invoices.index') }}" class="btn btn-secondary me-2">
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

    const products      = @json($products);
    const existingItems = @json($itemsData);
    const TROY_OUNCE_TO_GRAM = 31.1035;

    // ===== PARTS TOGGLE =====
    $(document).on('click', '.toggle-parts', function() {
        $(this).closest('tr').next('.parts-row').fadeToggle(200);
    });

    // ===== CURRENCY BOX =====
    function initCurrencyBox() {
        const isUSD = $('#currency').val() === 'USD';
        if (isUSD) { $('#exchangeRateBox').show(); $('#exchange_rate').attr('required', true); }
        else        { $('#exchangeRateBox').hide(); $('#exchange_rate').removeAttr('required'); }
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
            $('#exchange_rate').removeAttr('required');
            $('#exchange_rate').val('');
        }
        calculateTotals();
    });

    $('#exchange_rate').on('input', function() { calculateTotals(); });

    $('.select2-js').select2({ width: '100%' });

    // ===== ROW INDEX MANAGEMENT =====
    function updateRowIndexes() {
        $('#PurchaseTable tr.item-row').each(function(i) {
            const itemRow = $(this);
            itemRow.attr('data-item-index', i);
            itemRow.find('input, select').each(function() {
                const name = $(this).attr('name');
                if (name) $(this).attr('name', name.replace(/items\[\d+\]/, `items[${i}]`));
            });
            const partsRow = itemRow.next('.parts-row');
            partsRow.find('.part-item-row').each(function(j) {
                $(this).attr('data-part-index', j);
                $(this).find('input, select').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name',
                            name.replace(/items\[\d+\]/, `items[${i}]`)
                                .replace(/parts\[\d+\]/, `parts[${j}]`)
                        );
                    }
                });
            });
        });
    }

    // ===== BUILD ITEM ROW HTML =====
    function buildItemRowHtml(index, data = {}) {
        const name    = data.item_name        || '';
        const desc    = data.item_description || '';
        const purity  = data.purity           || '0.92';
        const netWt   = data.net_weight       || 0;
        const grossWt = data.gross_weight     || 0;
        const mkRate  = data.making_rate      || 0;
        const matType = data.material_type    || 'gold';
        const vatPct  = data.vat_percent      || 0;

        const purityOptions = `@foreach($purities as $p)<option value="{{ $p->value }}" ${purity == {{ $p->value }} ? 'selected' : ''}>{{ $p->label }}</option>@endforeach`;

        return `
        <tr class="item-row" data-item-index="${index}">
            <td>
                <div class="product-wrapper">
                    <input type="text" name="items[${index}][item_name]" class="form-control item-name-input" placeholder="Product Name" value="${name}">
                    <input type="hidden" name="items[${index}][barcode_number]" value="${data.barcode_number || ''}">
                    <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
                </div>
            </td>
            <td><input type="text" name="items[${index}][item_description]" class="form-control" value="${desc}" required></td>
            <td>
                <select name="items[${index}][purity]" class="form-control purity">
                    ${purityOptions}
                </select>
            </td>
            <td><input type="number" name="items[${index}][net_weight]" step="any" value="${netWt}" class="form-control net-weight"></td>
            <td><input type="number" name="items[${index}][gross_weight]" step="any" value="${grossWt}" class="form-control gross-weight bg-light text-primary fw-bold" readonly></td>
            <td><input type="number" name="items[${index}][purity_weight]" step="any" value="${data.purity_weight || 0}" class="form-control purity-weight" readonly></td>
            <td><input type="number" name="items[${index}][col_995]" step="any" value="${data.col_995 || 0}" class="form-control col-995" readonly></td>
            <td><input type="number" name="items[${index}][making_rate]" step="any" value="${mkRate}" class="form-control making-rate"></td>
            <td><input type="number" name="items[${index}][making_value]" step="any" value="${data.making_value || 0}" class="form-control making-value" readonly></td>
            <td>
                <select name="items[${index}][material_type]" class="form-control material-type">
                    <option value="gold"    ${matType === 'gold'    ? 'selected' : ''}>Gold</option>
                    <option value="diamond" ${matType === 'diamond' ? 'selected' : ''}>Diamond</option>
                </select>
            </td>
            <td><input type="number" name="items[${index}][material_value]" step="any" value="${data.material_value || 0}" class="form-control material-value" readonly></td>
            <td><input type="number" name="items[${index}][taxable_amount]" step="any" value="${data.taxable_amount || 0}" class="form-control taxable-amount" readonly></td>
            <td><input type="number" name="items[${index}][vat_percent]" class="form-control vat-percent" step="any" value="${vatPct}"></td>
            <td><input type="number" name="items[${index}][vat_amount]" step="any" value="${data.vat_amount || 0}" class="form-control vat-amount" readonly></td>
            <td><input type="number" name="items[${index}][item_total]" step="any" value="${data.item_total || 0}" class="form-control item-total" readonly></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                <button type="button" class="btn btn-sm btn-primary toggle-parts"><i class="fas fa-wrench"></i></button>
            </td>
        </tr>
        <tr class="parts-row" style="display:none;background:#efefef">
            <td colspan="16">
                <div class="parts-wrapper">
                    <table class="table table-sm table-bordered parts-table">
                        <thead>
                            <tr>
                                <th>Part</th><th>Description</th><th>Diamond Ct.</th><th>Rate</th>
                                <th>Stone Ct.</th><th>Stone Rate</th><th>Total</th><th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary add-part">+ Add Part</button>
                </div>
            </td>
        </tr>`;
    }

    // ===== BUILD PART ROW HTML =====
    function buildPartRowHtml(itemIndex, partIndex, data = {}) {
        return `
        <tr class="part-item-row" data-part-index="${partIndex}">
            <td>
                <div class="product-wrapper">
                    <input type="text" name="items[${itemIndex}][parts][${partIndex}][item_name]" class="form-control item-name-input" placeholder="Part Name" value="${data.item_name || ''}">
                    <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
                </div>
            </td>
            <td><input type="text" name="items[${itemIndex}][parts][${partIndex}][part_description]" class="form-control" value="${data.part_description || ''}"></td>
            <td>
                <div class="input-group">
                    <input type="number" name="items[${itemIndex}][parts][${partIndex}][qty]" step="any" value="${data.qty || 0}" class="form-control part-qty">
                    <span class="input-group-text">Ct.</span>
                </div>
            </td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][rate]" step="any" value="${data.rate || 0}" class="form-control part-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_qty]" step="any" value="${data.stone_qty || 0}" class="form-control part-stone-qty"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_rate]" step="any" value="${data.stone_rate || 0}" class="form-control part-stone-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][total]" step="any" value="${data.total || 0}" class="form-control part-total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-part"><i class="fas fa-times"></i></button></td>
        </tr>`;
    }

    // ===== LOAD EXISTING ITEMS =====
    existingItems.forEach(function(itemData, i) {
        $('#PurchaseTable').append(buildItemRowHtml(i, itemData));

        const itemRow  = $('#PurchaseTable tr.item-row').last();
        const partsRow = itemRow.next('.parts-row');

        if (itemData.parts && itemData.parts.length > 0) {
            partsRow.show();
            itemData.parts.forEach(function(partData, j) {
                partsRow.find('.parts-table tbody').append(buildPartRowHtml(i, j, partData));
            });
        }

        // Always recalc after load to keep gross_weight and all derived values in sync
        recalcItemGrossWeight(itemRow);
    });

    calculateTotals();

    // ===== PAYMENT METHOD — show correct section on load =====
    function initPaymentFields() {
        const val = $('#payment_method').val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields').addClass('d-none');
        if (val === 'cheque')                    $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')                 $('#received_by_box').removeClass('d-none');
        else if (val === 'bank_transfer')        $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost') $('#material_fields').removeClass('d-none');
    }
    initPaymentFields();

    // ===== ADD NEW ROW =====
    window.addNewRow = function() {
        const nextIndex = $('#PurchaseTable tr.item-row').length;
        $('#PurchaseTable').append(buildItemRowHtml(nextIndex, {}));
        updateRowIndexes();
    };

    // ===== REMOVE ROW =====
    window.removeRow = function(btn) {
        const row      = $(btn).closest('tr');
        const partsRow = row.next('.parts-row');
        if ($('#PurchaseTable tr.item-row').length > 1) {
            partsRow.remove();
            row.remove();
            updateRowIndexes();
            calculateTotals();
        }
    };

    // ===== ADD PART =====
    $(document).on('click', '.add-part', function() {
        const partsBody = $(this).closest('.parts-wrapper').find('.parts-table tbody');
        const itemRow   = $(this).closest('.parts-row').prev('.item-row');
        const itemIndex = itemRow.data('item-index');
        const partIndex = partsBody.find('tr').length;
        partsBody.append(buildPartRowHtml(itemIndex, partIndex, {}));
    });

    // ===== REMOVE PART =====
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

        let namePath = isPart
            ? `items[${itemIdx}][parts][${wrapper.closest('.part-item-row').data('part-index')}]`
            : `items[${itemIdx}]`;

        if (isReverting) {
            wrapper.html(`
                <input type="text" name="${namePath}[item_name]" class="form-control item-name-input" placeholder="Name">
                <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
            `);
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

    // ===== PRODUCT SELECT =====
    $(document).on('change', '.product-select', function() {
        const productId       = $(this).val();
        const row             = $(this).closest('tr');
        const variationSelect = row.find('.variation-select');

        variationSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        if (!productId) {
            variationSelect.html('<option value="">Select Variation</option>').prop('disabled', false);
            return;
        }

        fetch(`/product/${productId}/variations`)
            .then(res => res.json())
            .then(data => {
                variationSelect.prop('disabled', false);
                let opts = '<option value="">No variation</option>';
                if (data.success && data.variation.length > 0) {
                    opts = '<option value="">Select Variation</option>';
                    data.variation.forEach(v => { opts += `<option value="${v.id}">${v.sku}</option>`; });
                }
                variationSelect.html(opts);
            });
    });

    // ================= CALCULATIONS =================

    // User types Net Wt — recalc gross + row
    $(document).on('input', '.net-weight', function() {
        recalcItemGrossWeight($(this).closest('tr.item-row'));
    });

    // Purity/rate/type changes — recalc row
    $(document).on('input change', '.purity, .making-rate, .vat-percent, .material-type, #gold_rate_aed, #diamond_rate_aed', function() {
        const row = $(this).closest('tr.item-row');
        if (row.length) recalcItemGrossWeight(row);
        calculateTotals();
    });

    /**
     * Gross Wt = Net Wt + (diamondCTS / 5) + (stoneCTS / 5)
     * gross_weight column is readonly — computed here, never typed by user.
     */
    function recalcItemGrossWeight(itemRow) {
        if (!itemRow || !itemRow.length) return;

        const netWt = parseFloat(itemRow.find('.net-weight').val()) || 0;

        let totalDiamondCTS = 0;
        let totalStoneCTS   = 0;
        itemRow.next('.parts-row').find('.part-item-row').each(function() {
            totalDiamondCTS += parseFloat($(this).find('.part-qty').val())       || 0;
            totalStoneCTS   += parseFloat($(this).find('.part-stone-qty').val()) || 0;
        });

        const newGross = netWt + (totalDiamondCTS / 5) + (totalStoneCTS / 5);
        itemRow.find('.gross-weight').val(newGross.toFixed(3));

        calculateRow(itemRow);
        calculateTotals();
    }

    /**
     * Row calculations:
     *   purity_weight  = net_weight × purity
     *   col_995        = purity_weight / 0.995
     *   making_value   = net_weight × making_rate
     *   material_value = rate × purity_weight
     *   taxable        = making_value + parts_total
     *   vat_amount     = taxable × vat% / 100
     *   item_total     = material_value + taxable + vat_amount
     */
    function calculateRow(row) {
        const netWt        = parseFloat(row.find('.net-weight').val())    || 0;
        const purity       = parseFloat(row.find('.purity').val())        || 0;
        const makingRate   = parseFloat(row.find('.making-rate').val())   || 0;
        const vatPercent   = parseFloat(row.find('.vat-percent').val())   || 0;
        const materialType = row.find('.material-type').val();

        let rate = (materialType === 'gold')
            ? parseFloat($('#gold_rate_aed').val())
            : parseFloat($('#diamond_rate_aed').val());
        rate = rate || 0;

        const purityWeight  = netWt * purity;
        const col995        = purityWeight / 0.995;
        const makingValue   = netWt * makingRate;
        const materialValue = rate * purityWeight;

        let partsTotal = 0;
        row.next('.parts-row').find('.part-item-row').each(function() {
            partsTotal += parseFloat($(this).find('.part-total').val()) || 0;
        });

        const taxableAmount = makingValue + partsTotal;
        const vatAmount     = taxableAmount * vatPercent / 100;
        const itemTotal     = materialValue + taxableAmount + vatAmount;

        row.find('.purity-weight').val(purityWeight.toFixed(3));
        row.find('.col-995').val(col995.toFixed(3));
        row.find('.making-value').val(makingValue.toFixed(2));
        row.find('.material-value').val(materialValue.toFixed(2));
        row.find('.taxable-amount').val(taxableAmount.toFixed(2));
        row.find('.vat-amount').val(vatAmount.toFixed(2));
        row.find('.item-total').val(itemTotal.toFixed(2));
    }

    function calculateTotals() {
        let sumNetWt         = 0;
        let sum995           = 0;
        let sumMakingTaxable = 0;
        let sumMaterial      = 0;
        let sumVAT           = 0;
        let sumGoldGross     = 0;
        let totalDiamondCTS  = 0;
        let totalStoneQty    = 0;
        let totalDiamondVal  = 0;
        let totalStoneVal    = 0;

        $('#PurchaseTable tr.item-row').each(function() {
            const itemRow      = $(this);
            const materialType = itemRow.find('.material-type').val();
            const grossVal     = parseFloat(itemRow.find('.gross-weight').val())  || 0;

            sumNetWt         += parseFloat(itemRow.find('.purity-weight').val())  || 0;
            sum995           += parseFloat(itemRow.find('.col-995').val())         || 0;
            sumMakingTaxable += parseFloat(itemRow.find('.taxable-amount').val())  || 0;
            sumMaterial      += parseFloat(itemRow.find('.material-value').val())  || 0;
            sumVAT           += parseFloat(itemRow.find('.vat-amount').val())      || 0;

            if (materialType === 'gold') sumGoldGross += grossVal;

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

        const makingTotalWithVat = sumMakingTaxable + sumVAT;
        // Net Amount = Material Val + Diamond Parts Val + Stone Parts Val + Making (incl VAT)
        const netTotal = sumMaterial + totalDiamondVal + totalStoneVal + makingTotalWithVat;

        $('#sum_gold_gross_weight').val(sumGoldGross.toFixed(3));
        $('#sum_diamond_cts').val(totalDiamondCTS.toFixed(3));
        $('#sum_stone_qty').val(totalStoneQty.toFixed(2));
        $('#sum_purity_weight').val(sumNetWt.toFixed(3));
        $('#sum_995').val(sum995.toFixed(3));
        $('#sum_making_value').val(makingTotalWithVat.toFixed(2));
        $('#sum_material_value').val(sumMaterial.toFixed(2));
        $('#sum_diamond_value').val(totalDiamondVal.toFixed(2));
        $('#sum_stone_value').val(totalStoneVal.toFixed(2));
        $('#sum_vat_amount').val(sumVAT.toFixed(2));
        $('#net_amount_display').val(netTotal.toFixed(2));
        $('#net_amount').val(netTotal.toFixed(2));

        const currency = $('#currency').val();
        const exRate   = parseFloat($('#exchange_rate').val()) || 1;
        $('#converted_total').val(currency === 'USD' ? (netTotal * exRate).toFixed(2) : netTotal.toFixed(2));

        if ($('#payment_method').val() === 'material+making cost') {
            $('input[name="material_weight"]').val(sum995.toFixed(3));
            $('input[name="material_purity"]').val(sumNetWt.toFixed(3));
            $('input[name="material_value"]').val(sumMaterial.toFixed(2));
            $('input[name="making_charges"]').val(makingTotalWithVat.toFixed(2));
        }
    }

    // ================= RATE CONVERSION =================
    $(document).on('input', '#gold_rate_usd, #gold_rate_aed_ounce, #diamond_rate_usd, #diamond_rate_aed, #exchange_rate', function() {
        const id     = $(this).attr('id');
        const exRate = parseFloat($('#exchange_rate').val()) || 3.6725;

        if (id === 'gold_rate_usd' || id === 'exchange_rate') {
            const goldUsd = parseFloat($('#gold_rate_usd').val()) || 0;
            $('#gold_rate_aed_ounce').val((goldUsd * exRate).toFixed(2));
        }
        $('#gold_rate_aed').val(((parseFloat($('#gold_rate_aed_ounce').val()) || 0) / TROY_OUNCE_TO_GRAM).toFixed(4));

        // Diamond: AED/Ct — USD converts via exchange rate only, no gram division
        if (id === 'diamond_rate_usd' || id === 'exchange_rate') {
            const diaUsd = parseFloat($('#diamond_rate_usd').val()) || 0;
            $('#diamond_rate_aed').val((diaUsd * exRate).toFixed(4));
        }

        $('#PurchaseTable tr.item-row').each(function() { calculateRow($(this)); });
        calculateTotals();
    });

    // ================= PAYMENT METHOD TOGGLE =================
    $('#payment_method').on('change', function() {
        const val = $(this).val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields').addClass('d-none');
        if (val === 'cheque')                    $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')                 $('#received_by_box').removeClass('d-none');
        else if (val === 'bank_transfer')        $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost') $('#material_fields').removeClass('d-none');
        calculateTotals();
    });

    // ================= PARTS CALCULATION =================
    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate', function() {
        const row       = $(this).closest('tr');
        const qty       = parseFloat(row.find('.part-qty').val())        || 0;
        const rate      = parseFloat(row.find('.part-rate').val())       || 0;
        const stoneQty  = parseFloat(row.find('.part-stone-qty').val())  || 0;
        const stoneRate = parseFloat(row.find('.part-stone-rate').val()) || 0;

        row.find('.part-total').val(((qty * rate) + (stoneQty * stoneRate)).toFixed(2));

        const itemRow = row.closest('.parts-row').prev('.item-row');
        recalcItemGrossWeight(itemRow);
    });

    // ================= EXCEL IMPORT =================
    $('#excel_import').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const data     = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const jsonData = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            if (jsonData.length === 0) return;

            let firstRow = $('#PurchaseTable tr.item-row').first();
            if ($('#PurchaseTable tr.item-row').length === 1 && !firstRow.find('.item-name-input').val()) {
                firstRow.next('.parts-row').remove();
                firstRow.remove();
            }

            let currentItemRow = null;
            jsonData.forEach(row => {
                if (row['Item Name'] && row['Item Name'].trim() !== '') {
                    addNewRow();
                    currentItemRow = $('#PurchaseTable tr.item-row').last();
                    currentItemRow.find('.item-name-input').val(row['Item Name']);
                    currentItemRow.find('input[name*="[item_description]"]').val(row['Description'] || '');
                    currentItemRow.find('.purity').val(row['Purity'] || '0.92');
                    currentItemRow.find('.net-weight').val(parseFloat(row['Gross Wt']) || 0);
                    currentItemRow.find('.making-rate').val(row['Making Rate'] || 0);
                    currentItemRow.find('.material-type').val((row['Material'] || 'gold').toLowerCase());
                    currentItemRow.find('.vat-percent').val(row['VAT %'] || 0);
                    recalcItemGrossWeight(currentItemRow);
                }
                if (row['Part Name'] && row['Part Name'].trim() !== '' && currentItemRow) {
                    const partsRow = currentItemRow.next('.parts-row');
                    partsRow.show();
                    partsRow.find('.add-part').click();
                    const currentPartRow = partsRow.find('.part-item-row').last();
                    currentPartRow.find('.item-name-input').val(row['Part Name']);
                    currentPartRow.find('input[name*="[part_description]"]').val(row['Part Desc'] || '');
                    currentPartRow.find('.part-qty').val(row['Part Qty'] || 0);
                    currentPartRow.find('.part-rate').val(row['Part Rate'] || 0);
                    currentPartRow.find('.part-stone-qty').val(row['Stone Qty'] || 0);
                    currentPartRow.find('.part-stone-rate').val(row['Stone Rate'] || 0);
                    currentPartRow.find('.part-qty').trigger('input');
                }
            });

            calculateTotals();
            alert('Items Imported Successfully!');
            $('#excel_import').val('');
        };
        reader.readAsArrayBuffer(file);
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