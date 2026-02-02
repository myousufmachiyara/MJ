@extends('layouts.app')

@section('title', 'Purchase | Edit Invoice #' . $invoice->invoice_no)

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.update', $invoice->id) }}" method="POST" enctype="multipart/form-data">
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

      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Edit Purchase Invoice: {{ $invoice->invoice_no }}</h2>
        </header>

        <div class="card-body">
          {{-- HEADER --}}
          <div class="row mb-5">

            <div class="col-md-2">
                <label class="fw-bold">Invoice #</label>
                <input type="text" class="form-control" value="{{ $invoice->invoice_no }}" readonly>
            </div>

            <div class="col-md-2">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ $invoice->invoice_date }}">
            </div>

            <div class="col-md-2">
              <label class="fw-bold">Invoice Type</label>
              <select name="is_taxable" id="is_taxable" class="form-control border-primary" required>
                  <option value="1" {{ (isset($invoice) && $invoice->is_taxable) ? 'selected' : '' }}>Taxable (PUR-TAX)</option>
                  <option value="0" {{ (isset($invoice) && !$invoice->is_taxable) ? 'selected' : '' }}>Non-Taxable (PUR)</option>
              </select>
              <small class="text-muted">Determines the sequence number</small>
            </div>

            <div class="col-md-2">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" {{ $invoice->vendor_id == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (USD / Ounce)</label>
              <input type="number" step="any" id="gold_rate_usd" name="gold_rate_usd" class="form-control" value="{{ $invoice->gold_rate_usd }}">
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (AED / Ounce)</label>
              <input type="number" step="any" id="gold_rate_aed_ounce" name="gold_rate_aed_ounce" class="form-control" value="{{ $invoice->gold_rate_aed * 31.1034768 }}">
            </div>

            <div class="col-12 col-md-3">
              <label class="text-primary">Gold Converted Rate (AED / Gram)</label>
              <input type="number" step="any" id="gold_rate_aed" name="gold_rate_aed" class="form-control" value="{{ $invoice->gold_rate_aed }}" readonly>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (USD) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_usd" name="diamond_rate_usd" class="form-control" value="{{ $invoice->diamond_rate_usd }}">
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (AED) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_aed_ounce" name="diamond_rate_aed_ounce" class="form-control" value="{{ $invoice->diamond_rate_aed * 31.1034768 }}">
            </div>

            <div class="col-12 col-md-3">
              <label class="text-primary">Diamond Converted Rate (AED / Gram)</label>
              <input type="number" step="any" id="dia_rate_aed" name="diamond_rate_aed" class="form-control" value="{{ $invoice->diamond_rate_aed }}" readonly>
            </div>

            <div class="col-md-4 mt-2">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control">{{ $invoice->remarks }}</textarea>
            </div>

            <div class="col-md-4 mt-2">
              <label>Attachments (Add New)</label>
              <input type="file" name="attachments[]" class="form-control" multiple>
              <small class="text-muted">Existing files are preserved.</small>
            </div>
          </div>

          {{-- TABLE --}}
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th width="12%" rowspan="2">Item Name</th>
                  <th width="13%" rowspan="2">Description</th>
                  <th width="9%" rowspan="2">Purity</th>
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
                  <th width="6%" rowspan="2">Action</th>
                </tr>
                <tr>
                  <th>Rate</th>
                  <th>Value</th>
                </tr>
              </thead>

              <tbody id="PurchaseTable">
                @foreach ($invoice->items as $idx => $item)
                <tr class="item-row" data-item-index="{{ $idx }}">
                  <td>
                    <div class="product-wrapper">
                      @if($item->product_id)
                        <select name="items[{{ $idx }}][product_id]" class="form-control select2-js product-select mb-2">
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ $item->product_id == $p->id ? 'selected' : '' }} data-unit="{{ $p->measurementUnit->name ?? '' }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-link p-0 revert-to-name mt-1"> Write Name </button>
                      @else
                        <input type="text" name="items[{{ $idx }}][item_name]" value="{{ $item->item_name }}" class="form-control item-name-input">
                        <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>
                      @endif
                    </div>
                  </td>
                  <td><input type="text" name="items[{{ $idx }}][item_description]" value="{{ $item->item_description }}" class="form-control" required></td>
                  <td>
                    <select name="items[{{ $idx }}][purity]" class="form-control purity">
                      <option value="0.92" {{ $item->purity == 0.92 ? 'selected' : '' }}>22K (92%)</option>
                      <option value="0.88" {{ $item->purity == 0.88 ? 'selected' : '' }}>21K (88%)</option>
                      <option value="0.75" {{ $item->purity == 0.75 ? 'selected' : '' }}>18K (75%)</option>
                    </select>
                  </td>
                  <td><input type="number" name="items[{{ $idx }}][gross_weight]" step="any" value="{{ $item->gross_weight }}" class="form-control gross-weight"></td>
                  <td><input type="number" name="items[{{ $idx }}][purity_weight]" step="any" value="{{ $item->purity_weight }}" class="form-control purity-weight" readonly></td>
                  <td><input type="number" name="items[{{ $idx }}][995]" step="any" value="{{ $item->col_995 }}" class="form-control col-995" readonly></td>
                  <td><input type="number" name="items[{{ $idx }}][making_rate]" step="any" value="{{ $item->making_rate }}" class="form-control making-rate"></td>
                  <td><input type="number" name="items[{{ $idx }}][making_value]" step="any" value="{{ $item->making_value }}" class="form-control making-value" readonly></td>
                  <td>
                    <select name="items[{{ $idx }}][material_type]" class="form-control material-type">
                      <option value="gold" {{ $item->material_type == 'gold' ? 'selected' : '' }}>Gold</option>
                      <option value="diamond" {{ $item->material_type == 'diamond' ? 'selected' : '' }}>Diamond</option>
                    </select>
                  </td>
                  <td><input type="number" name="items[{{ $idx }}][metal_value]" step="any" value="{{ $item->material_value }}" class="form-control material-value" readonly></td>
                  <td><input type="number" name="items[{{ $idx }}][taxable_amount]" step="any" value="{{ $item->taxable_amount }}" class="form-control taxable-amount" readonly></td>
                  <td><input type="number" name="items[{{ $idx }}][vat_percent]" value="{{ $item->vat_percent }}" class="form-control vat-percent" step="any"></td>
                  <td><input type="number" step="any" value="{{ $item->vat_amount }}" class="form-control vat-amount" readonly></td>
                  <td><input type="number" value="{{ $item->item_total }}" class="form-control item-total" readonly></td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                    <button type="button" class="btn btn-sm btn-primary toggle-parts"> <i class="fas fa-wrench"></i> </button>
                  </td>
                </tr>
                <tr class="parts-row" style="{{ $item->parts->count() > 0 ? '' : 'display:none;' }} background:#efefef">
                  <td colspan="15">
                    <div class="parts-wrapper">
                      <table class="table table-sm table-bordered parts-table">
                        <thead>
                          <tr><th>Part</th><th>Description</th><th>Carat</th><th>Rate</th><th>Stone Ct.</th><th>Stone Rate</th><th>Total</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($item->parts as $pIdx => $part)
                            <tr class="part-item-row" data-part-index="{{ $pIdx }}">
                                <td><input type="text" name="items[{{ $idx }}][parts][{{ $pIdx }}][item_name]" value="{{ $part->item_name }}" class="form-control"></td>
                                <td><input type="text" name="items[{{ $idx }}][parts][{{ $pIdx }}][part_description]" value="{{ $part->part_description }}" class="form-control"></td>
                                <td><input type="number" name="items[{{ $idx }}][parts][{{ $pIdx }}][qty]" step="any" value="{{ $part->qty }}" class="form-control part-qty"></td>
                                <td><input type="number" name="items[{{ $idx }}][parts][{{ $pIdx }}][rate]" step="any" value="{{ $part->rate }}" class="form-control part-rate"></td>
                                <td><input type="number" name="items[{{ $idx }}][parts][{{ $pIdx }}][stone_qty]" step="any" value="{{ $part->stone_qty }}" class="form-control part-stone-qty"></td>
                                <td><input type="number" name="items[{{ $idx }}][parts][{{ $pIdx }}][stone_rate]" step="any" value="{{ $part->stone_rate }}" class="form-control part-stone-rate"></td>
                                <td><input type="number" name="items[{{ $idx }}][parts][{{ $pIdx }}][total]" step="any" value="{{ $part->total }}" class="form-control part-total" readonly></td>
                                <td><button type="button" class="btn btn-sm btn-danger remove-part"><i class="fas fa-times"></i></button></td>
                            </tr>
                            @endforeach
                        </tbody>
                      </table>
                      <button type="button" class="btn btn-sm btn-outline-primary add-part"> + Add Part </button>
                    </div>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">Add Item</button>
          </div>

          {{-- SUMMARY (Keep existing calculation fields) --}}
          <div class="row mt-5 mb-5">
            <div class="col-md-2">
              <label>Net Amount</label>
              <input type="text" id="net_amount_display" class="form-control text-danger fw-bold" readonly value="{{ $invoice->net_amount }}">
              <input type="hidden" name="net_amount" id="net_amount" value="{{ $invoice->net_amount }}">
            </div>
          </div>

          {{-- PAYMENT METHOD --}}
          <div class="row mb-3">
            <div class="col-md-2">
              <label class="fw-bold">Payment Method</label>
              <select name="payment_method" id="payment_method" class="form-control" required>
                <option value="">Select Payment Method</option>
                <option value="credit" {{ $invoice->payment_method == 'credit' ? 'selected' : '' }}>Credit</option>
                <option value="cash" {{ $invoice->payment_method == 'cash' ? 'selected' : '' }}>Cash</option>
                <option value="cheque" {{ $invoice->payment_method == 'cheque' ? 'selected' : '' }}>Cheque</option>
                <option value="material+making cost" {{ $invoice->payment_method == 'material+making cost' ? 'selected' : '' }}>Material + Making Cost</option>
              </select>
            </div>
            <div class="col-md-2">
              <label>Payment Term</label>
              <input type="text" name="payment_term" class="form-control" value="{{ $invoice->payment_term }}">
            </div>
          </div>

          {{-- ADDITIONAL FIELDS (Hidden/Shown via JS) --}}
          <div class="row mb-3 d-none" id="received_by_box">
            <div class="col-md-2">
              <label>Received By</label>
              <input type="text" name="received_by" class="form-control" value="{{ $invoice->received_by }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="cheque_fields">
            <div class="col-md-2">
              <label>Bank Name</label>
              <select name="bank_name" class="form-control select2-js">
                <option value="">Select Bank</option>
                @foreach ($banks as $bank)
                  <option value="{{ $bank->id }}" {{ $invoice->bank_name == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Cheque No</label>
              <input type="text" name="cheque_no" class="form-control" value="{{ $invoice->cheque_no }}">
            </div>
            <div class="col-md-2">
              <label>Cheque Date</label>
              <input type="date" name="cheque_date" class="form-control" value="{{ $invoice->cheque_date }}">
            </div>
            <div class="col-md-2">
              <label>Cheque Amount</label>
              <input type="number" step="any" name="cheque_amount" class="form-control" value="{{ $invoice->cheque_amount }}">
            </div>
          </div>

          <div class="row mb-3 d-none" id="material_fields">
            <div class="col-md-2">
              <label>Total Item Wt. Received</label>
              <input type="text" id="total_wt_received" class="form-control" readonly>
            </div>
            <div class="col-md-2">
                <label>Raw Material Weight Given</label>
                <input type="number" step="any" name="material_weight" class="form-control" value="{{ $invoice->material_weight }}">
              </div>
              <div class="col-md-2">
                <label>Raw Material Purity</label>
                <input type="number" step="any" name="material_purity" class="form-control" value="{{ $invoice->material_purity }}">
              </div>
              <div class="col-md-2">
                <label>Material Adjustment Value</label>
                <input type="number" step="any" name="material_value" class="form-control" value="{{ $invoice->material_value }}">
              </div>
              <div class="col-md-2">
                <label>Making Charges Payable</label>
                <input type="number" step="any" name="making_charges" class="form-control" value="{{ $invoice->making_charges }}">
              </div>
              <div class="col-md-2">
                <label>Gold Used (Invoice)</label>
                <input type="text" id="gold_used" class="form-control" readonly>
              </div>
              <div class="col-md-2 mt-3">
                <label>Gold Balance</label>
                <input type="text" id="gold_balance" class="form-control fw-bold" readonly>
              </div>
              <div class="col-md-2 mt-3">
                <label>Gold Balance Value (AED)</label>
                <input type="text" id="gold_balance_value" class="form-control text-danger fw-bold" readonly>
              </div>
              <div class="col-md-2 mt-3">
                <label>Material Given By</label>
                <input type="text" id="material_given_by" name="material_given_by" class="form-control text-danger fw-bold" value="{{ $invoice->material_given_by }}">
              </div>
              <div class="col-md-2 mt-3">
                <label>Material Received By</label>
                <input type="text" id="material_received_by" name="material_received_by" class="form-control text-danger fw-bold" value="{{ $invoice->material_received_by }}">
              </div>
          </div>

          {{-- CURRENCY & EXCHANGE RATE --}}
          <div class="card mt-3">
            <div class="card-body">
              <div class="row">
                <div class="col-md-2">
                  <label>Invoice Currency</label>
                  <select name="currency" id="currency" class="form-control">
                    <option value="AED" {{ $invoice->currency == 'AED' ? 'selected' : '' }}>AED</option>
                    <option value="USD" {{ $invoice->currency == 'USD' ? 'selected' : '' }}>USD</option>
                  </select>
                </div>
                <div class="col-md-2" id="exchangeRateBox" style="{{ $invoice->currency == 'USD' ? '' : 'display:none' }}">
                    <label>Exchange Rate</label>
                    <input type="number" step="any" name="exchange_rate" id="exchange_rate" class="form-control" value="{{ $invoice->exchange_rate ?? '3.6725' }}">
                </div>
              </div>
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success">Update Invoice</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () {
    const products = @json($products);
    const TROY_OUNCE_TO_GRAM = 31.1034768;
    handlePaymentMethodFields(); // This will read the current value of #payment_method and show the correct divs
    // ================= 1. UI & PAYMENT DISPLAY LOGIC =================

    function handlePaymentMethodFields() {
        const val = $('#payment_method').val();
        
        // Hide all conditional boxes
        $('#cheque_fields, #material_fields, #received_by_box').addClass('d-none');
        
        // Show based on selection
        if (val === 'cheque') {
            $('#cheque_fields, #received_by_box').removeClass('d-none');
        } else if (val === 'cash') {
            $('#received_by_box').removeClass('d-none');
        } else if (val === 'material+making cost') {
            $('#material_fields').removeClass('d-none');
        }
        
        calculateTotals();
    }

    // Initialize Select2 on page load
    $('.select2-js').select2({ width: '100%' });

    // Payment method change event
    $('#payment_method').on('change', function() {
        handlePaymentMethodFields();
    });

    // Currency Change Logic
    $('#currency').on('change', function() {
        const isUSD = $(this).val() === 'USD';
        if (isUSD) {
            $('#exchangeRateBox').show();
            $('#exchange_rate').attr('required', true);
            if(!$('#exchange_rate').val()) $('#exchange_rate').val('3.6725');
        } else {
            $('#exchangeRateBox').hide();
            $('#exchange_rate').removeAttr('required').val('');
        }
        calculateTotals(); 
    });

    // ================= 2. CORE CALCULATIONS =================

    function calculateRow(row) {
        const purity = parseFloat(row.find('.purity').val()) || 0;
        const gross = parseFloat(row.find('.gross-weight').val()) || 0;
        const makingRate = parseFloat(row.find('.making-rate').val()) || 0;
        const vatPercent = parseFloat(row.find('.vat-percent').val()) || 0;
        const materialType = row.find('.material-type').val();
        
        let rate = (materialType === 'gold') ? parseFloat($('#gold_rate_aed').val()) : parseFloat($('#dia_rate_aed').val());
        rate = rate || 0;

        const purityWt = gross * purity;
        const col995 = purityWt / 0.995;
        const makingValue = gross * makingRate;
        const materialValue = rate * purityWt;
        const taxableAmount = makingValue; 
        const vatAmount = taxableAmount * vatPercent / 100;
        const itemTotal = taxableAmount + materialValue + vatAmount;

        row.find('.purity-weight').val(purityWt.toFixed(3));
        row.find('.col-995').val(col995.toFixed(3));
        row.find('.making-value').val(makingValue.toFixed(2));
        row.find('.material-value').val(materialValue.toFixed(2)); 
        row.find('.taxable-amount').val(taxableAmount.toFixed(2));
        row.find('.vat-amount').val(vatAmount.toFixed(2));
        row.find('.item-total').val(itemTotal.toFixed(2));
    }

    function calculateTotals() {
        let sumGross = 0, sumPurity = 0, sum995 = 0, sumMakingTaxable = 0, sumMaterial = 0, sumVAT = 0, netTotal = 0;
        
        $('#PurchaseTable tr.item-row').each(function () {
            sumGross         += parseFloat($(this).find('.gross-weight').val()) || 0;
            sumPurity        += parseFloat($(this).find('.purity-weight').val()) || 0;
            sum995           += parseFloat($(this).find('.col-995').val()) || 0;
            sumMakingTaxable += parseFloat($(this).find('.taxable-amount').val()) || 0;
            sumMaterial      += parseFloat($(this).find('.material-value').val()) || 0;
            sumVAT           += parseFloat($(this).find('.vat-amount').val()) || 0;
            netTotal         += parseFloat($(this).find('.item-total').val()) || 0;
        });

        const makingTotalWithVat = sumMakingTaxable + sumVAT;

        $('#sum_gross_weight').val(sumGross.toFixed(3));
        $('#sum_purity_weight').val(sumPurity.toFixed(3));
        $('#sum_995').val(sum995.toFixed(3));
        $('#sum_making_value').val(makingTotalWithVat.toFixed(2));
        $('#sum_material_value').val(sumMaterial.toFixed(2));
        $('#sum_vat_amount').val(sumVAT.toFixed(2));
        $('#net_amount_display').val(netTotal.toFixed(2));
        $('#net_amount').val(netTotal.toFixed(2));

        const currency = $('#currency').val();
        const exRate = parseFloat($('#exchange_rate').val()) || 1;
        $('#converted_total').val((currency === 'USD' ? netTotal * exRate : netTotal).toFixed(2));

        // Update Payment Method specific hidden/visible inputs
        if ($('#payment_method').val() === 'material+making cost') {
            $('input[name="material_weight"]').val(sum995.toFixed(3));
            $('input[name="material_purity"]').val(sumPurity.toFixed(3));
            $('input[name="material_value"]').val(sumMaterial.toFixed(2));
            $('input[name="making_charges"]').val(makingTotalWithVat.toFixed(2));
        }
    }

    // Trigger calculation on input
    $(document).on('input change', '.gross-weight, .purity, .making-rate, .vat-percent, .material-type, #gold_rate_aed, #dia_rate_aed, #exchange_rate', function() {
        const row = $(this).closest('tr.item-row');
        if(row.length) calculateRow(row);
        calculateTotals();
    });

    // Troy Ounce & Rate Conversions
    $(document).on('input', '#gold_rate_usd, #gold_rate_aed_ounce, #diamond_rate_usd, #diamond_rate_aed', function() {
        const id = $(this).attr('id');
        const exRate = parseFloat($('#exchange_rate').val()) || 3.6725;

        if (id === 'gold_rate_usd') {
            $('#gold_rate_aed_ounce').val((parseFloat($(this).val()) * exRate).toFixed(2));
        }
        const goldAedOunceFinal = parseFloat($('#gold_rate_aed_ounce').val()) || 0;
        $('#gold_rate_aed').val((goldAedOunceFinal / TROY_OUNCE_TO_GRAM).toFixed(4));

        if (id === 'diamond_rate_usd') {
            $('#diamond_rate_aed').val((parseFloat($(this).val()) * exRate).toFixed(2));
        }
        const diaAedOunceFinal = parseFloat($('#diamond_rate_aed').val()) || 0;
        $('#dia_rate_aed').val((diaAedOunceFinal / TROY_OUNCE_TO_GRAM).toFixed(4));

        $('#PurchaseTable tr.item-row').each(function() { calculateRow($(this)); });
        calculateTotals();
    });

    // ================= 3. ROW & PARTS MANAGEMENT =================

    function updateRowIndexes() {
        $('#PurchaseTable tr.item-row').each(function(i) {
            const itemRow = $(this);
            itemRow.attr('data-item-index', i);
            itemRow.find('input, select').each(function() {
                const name = $(this).attr('name');
                if(name) $(this).attr('name', name.replace(/items\[\d+\]/, `items[${i}]`));
            });

            const partsRow = itemRow.next('.parts-row');
            partsRow.find('.part-item-row').each(function(j) {
                $(this).attr('data-part-index', j);
                $(this).find('input, select').each(function() {
                    const name = $(this).attr('name');
                    if(name) {
                        const newName = name.replace(/items\[\d+\]/, `items[${i}]`).replace(/parts\[\d+\]/, `parts[${j}]`);
                        $(this).attr('name', newName);
                    }
                });
            });
        });
    }

    window.addNewRow = function() {
        const nextIndex = $('#PurchaseTable tr.item-row').length;
        const rowHtml = `
        <tr class="item-row" data-item-index="${nextIndex}">
            <td>
                <div class="product-wrapper">
                    <input type="text" name="items[${nextIndex}][item_name]" class="form-control item-name-input" placeholder="Product Name">
                    <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>
                </div>
            </td>
            <td><input type="text" name="items[${nextIndex}][item_description]" class="form-control" required></td>
            <td>
                <select name="items[${nextIndex}][purity]" class="form-control purity">
                    <option value="0.92">22K (92%)</option>
                    <option value="0.88">21K (88%)</option>
                    <option value="0.75">18K (75%)</option>
                </select>
            </td>
            <td><input type="number" name="items[${nextIndex}][gross_weight]" step="any" value="0" class="form-control gross-weight"></td>
            <td><input type="number" name="items[${nextIndex}][purity_weight]" step="any" value="0" class="form-control purity-weight" readonly></td>
            <td><input type="number" name="items[${nextIndex}][995]" step="any" value="0" class="form-control col-995" readonly></td>
            <td><input type="number" name="items[${nextIndex}][making_rate]" step="any" value="0" class="form-control making-rate"></td>
            <td><input type="number" name="items[${nextIndex}][making_value]" step="any" class="form-control making-value" readonly></td>
            <td>
                <select name="items[${nextIndex}][material_type]" class="form-control material-type">
                    <option value="gold">Gold</option>
                    <option value="diamond">Diamond</option>
                </select>
            </td>
            <td><input type="number" name="items[${nextIndex}][metal_value]" step="any" value="0" class="form-control material-value" readonly></td>
            <td><input type="number" name="items[${nextIndex}][taxable_amount]" step="any" value="0" class="form-control taxable-amount" readonly></td>
            <td><input type="number" name="items[${nextIndex}][vat_percent]" class="form-control vat-percent" step="any" value="0"></td>
            <td><input type="number" step="any" class="form-control vat-amount" readonly></td>
            <td><input type="number" class="form-control item-total" readonly></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                <button type="button" class="btn btn-sm btn-primary toggle-parts"><i class="fas fa-wrench"></i></button>
            </td>
        </tr>
        <tr class="parts-row" style="display:none;background:#efefef">
            <td colspan="15">
                <div class="parts-wrapper">
                    <table class="table table-sm table-bordered parts-table">
                        <thead>
                            <tr><th>Part</th><th>Description</th><th>Carat</th><th>Rate</th><th>Stone Ct.</th><th>Stone Rate</th><th>Total</th><th></th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary add-part">+ Add Part</button>
                </div>
            </td>
        </tr>`;
        $('#PurchaseTable').append(rowHtml);
        updateRowIndexes();
    };

    window.removeRow = function(btn) {
        if ($('#PurchaseTable tr.item-row').length > 1) {
            const row = $(btn).closest('tr');
            row.next('.parts-row').remove();
            row.remove();
            updateRowIndexes();
            calculateTotals();
        }
    };

    // Parts row toggle
    $(document).on('click', '.toggle-parts', function() {
        $(this).closest('tr').next('.parts-row').fadeToggle(200);
    });

    $(document).on('click', '.add-part', function() {
        const partsTableBody = $(this).closest('.parts-wrapper').find('.parts-table tbody');
        const itemIdx = $(this).closest('.parts-row').prev('.item-row').data('item-index');
        const partIdx = partsTableBody.find('tr').length;

        const rowHtml = `
        <tr class="part-item-row" data-part-index="${partIdx}">
            <td>
                <div class="product-wrapper">
                    <input type="text" name="items[${itemIdx}][parts][${partIdx}][item_name]" class="form-control item-name-input" placeholder="Part Name">
                    <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>
                </div>
            </td>
            <td><input type="text" name="items[${itemIdx}][parts][${partIdx}][part_description]" class="form-control"></td>
            <td>
                <div class="input-group">
                    <input type="number" name="items[${itemIdx}][parts][${partIdx}][qty]" step="any" value="0" class="form-control part-qty">
                    <input type="text" class="form-control part-unit-name" style="width:70px; flex:none;" readonly placeholder="Unit">
                </div>
            </td>
            <td><input type="number" name="items[${itemIdx}][parts][${partIdx}][rate]" step="any" value="0" class="form-control part-rate"></td>
            <td><input type="number" name="items[${itemIdx}][parts][${partIdx}][stone_qty]" step="any" value="0" class="form-control part-stone-qty"></td>
            <td><input type="number" name="items[${itemIdx}][parts][${partIdx}][stone_rate]" step="any" value="0" class="form-control part-stone-rate"></td>
            <td><input type="number" name="items[${itemIdx}][parts][${partIdx}][total]" step="any" value="0" class="form-control part-total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-part"><i class="fas fa-times"></i></button></td>
        </tr>`;
        partsTableBody.append(rowHtml);
    });

    $(document).on('click', '.remove-part', function() {
        $(this).closest('tr').remove();
        calculateTotals();
    });

    // ================= 4. PRODUCT SELECTION LOGIC =================

    $(document).on('click', '.toggle-product, .revert-to-name', function () {
        const isReverting = $(this).hasClass('revert-to-name');
        const wrapper = $(this).closest('.product-wrapper');
        const row = wrapper.closest('tr');
        const isPart = row.hasClass('part-item-row');
        
        let itemIdx = isPart ? row.closest('.parts-row').prev('.item-row').data('item-index') : row.data('item-index');
        let namePath = isPart ? `items[${itemIdx}][parts][${row.data('part-index')}]` : `items[${itemIdx}]`;

        if (isReverting) {
            wrapper.html(`<input type="text" name="${namePath}[item_name]" class="form-control item-name-input" placeholder="Name">
                          <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>`);
        } else {
            wrapper.html(`<select name="${namePath}[product_id]" class="form-control select2-js product-select mb-2">
                            <option value="">Select Product</option>
                            ${products.map(p => `<option value="${p.id}" data-unit="${p.measurement_unit ? p.measurement_unit.name : ''}">${p.name}</option>`).join('')}
                          </select>
                          <select name="${namePath}[variation_id]" class="form-control select2-js variation-select">
                            <option value="">Select Variation</option>
                          </select>
                          <button type="button" class="btn btn-link p-0 revert-to-name mt-1"> Write Name </button>`)
                   .find('.select2-js').select2({ width: '100%' });
        }
    });

    $(document).on('change', '.product-select', function() {
        const productId = $(this).val();
        const row = $(this).closest('tr');
        const variationSelect = row.find('.variation-select');
        const unitInput = row.find('.part-unit-name');

        if(unitInput.length) unitInput.val($(this).find(':selected').data('unit') || '');

        variationSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        if (!productId) return variationSelect.html('<option value="">Select Variation</option>').prop('disabled', false);

        fetch(`/product/${productId}/variations`)
            .then(res => res.json())
            .then(data => {
                variationSelect.prop('disabled', false);
                let options = (data.success && data.variation.length > 0) ? '<option value="">Select Variation</option>' : '<option value="">No variation</option>';
                if(data.success) data.variation.forEach(v => options += `<option value="${v.id}">${v.sku}</option>`);
                variationSelect.html(options).trigger('change');
            });
    });

    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate', function() {
        const row = $(this).closest('tr');
        const total = ((parseFloat(row.find('.part-qty').val()) || 0) * (parseFloat(row.find('.part-rate').val()) || 0)) +
                      ((parseFloat(row.find('.part-stone-qty').val()) || 0) * (parseFloat(row.find('.part-stone-rate').val()) || 0));
        row.find('.part-total').val(total.toFixed(2));
    });

    // ================= 5. FINAL INITIALIZATION (RUN ON LOAD) =================
    
    // 1. Trigger calculation for existing rows from DB
    $('#PurchaseTable tr.item-row').each(function() {
        calculateRow($(this));
    });

    // 2. Set the correct UI state for the payment method saved in the DB
    handlePaymentMethodFields();

    // 3. Final Total Calculation
    calculateTotals();

  });
</script>
@endsection