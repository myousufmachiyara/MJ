@extends('layouts.app')

@section('title', 'Purchase | New Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.store') }}" method="POST" enctype="multipart/form-data">
      @csrf

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
          <h2 class="card-title">New Purchase Invoice</h2>
        </header>

        <div class="card-body">

          {{-- HEADER --}}
          <div class="row mb-5">
            <div class="col-md-2">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>

            <div class="col-md-2">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (AED)</label>
              <input type="number" step="any" id="gold_rate_aed" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (USD)</label>
              <input type="number" step="any" id="gold_rate_usd" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-2">
              <label>Metal Rate (AED)</label>
              <input type="number" step="any" id="metal_rate_aed" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-2">
              <label>Metal Rate (USD)</label>
              <input type="number" step="any" id="metal_rate_usd" class="form-control" value="0">
            </div>

            <div class="col-md-4 mt-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control"></textarea>
            </div>

            <div class="col-md-4 mt-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>
          </div>

          {{-- TABLE --}}
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th width="12%" rowspan="2">Item Name</th>
                  <th width="13%" rowspan="2">Item Description</th>
                  <th width="9%" rowspan="2">Purity</th>
                  <th rowspan="2">Gross Wt</th>
                  <th rowspan="2">Purity Wt</th>
                  <th rowspan="2">995</th>
                  <th colspan="2" class="text-center">Making</th>
                  <th width="7%" rowspan="2">Metal</th>
                  <th rowspan="2">Metal Val</th>
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
                <tr>
                  <td>
                    <div class="product-wrapper">
                      {{-- Manual input by default --}}
                      <input type="text" name="items[0][item_name]" class="form-control item-name-input" placeholder="Product Name">

                      {{-- Toggle button --}}
                      <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>
                    </div>
                  </td>

                  <td><input type="text" name="items[0][item_description]" class="form-control" required></td>
                  <td>
                    <select name="items[0][purity]" class="form-control purity">
                      <option value="0.916">22K (92%)</option>
                      <option value="0.875">21K (88%)</option>
                      <option value="0.750">18K (75%)</option>
                    </select>
                  </td>
                  <td><input type="number" name="items[0][gross_weight]" step="any" value="0" class="form-control gross-weight"></td>
                  <td><input type="number" name="items[0][purity_weight]" step="any" value="0" class="form-control purity-weight"></td>
                  <td><input type="number" name="items[0][995]" step="any" value="0" class="form-control col-995"></td>

                  <td><input type="number" name="items[0][making_rate]"  step="any" value="0" class="form-control making-rate"></td>
                  <td><input type="number" name="items[0][making_value]" step="any" class="form-control making-value" readonly></td>

                  <td>
                    <select name="items[0][metal_type]" class="form-control metal-type">
                      <option value="gold">Gold</option>
                      <option value="metal">Metal</option>
                    </select>
                  </td>

                  <td><input type="number" name="items[0][metal_value]" step="any" value="0" class="form-control metal-value"></td>
                  <td><input type="number" name="items[0][taxable_amount]" step="any" value="0" class="form-control taxable-amount"></td>

                  <td><input type="number" name="items[0][vat_percent]" class="form-control vat-percent" step="any" value="0"></td>
                  <td><input type="number" step="any" class="form-control vat-amount" readonly></td>

                  <td><input type="number" class="form-control item-total" readonly></td>

                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                      <i class="fas fa-times"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary toggle-parts"> <i class="fas fa-wrench"></i> </button>
                  </td>
                </tr>
                <tr class="parts-row" style="display:none;background:#efefef">
                  <td colspan="15">
                    <div class="parts-wrapper">
                      <table class="table table-sm table-bordered parts-table">
                        <thead>
                          <tr>
                            <th>Part</th>
                            <th>Variation</th>
                            <th>Qty</th>
                            <th>Rate</th>
                            <th>Wastage</th>
                            <th>Total</th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                      </table>

                      <button type="button" class="btn btn-sm btn-outline-primary add-part"> + Add Part </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">Add Item</button>
          </div>

          {{-- SUMMARY --}}
          <div class="row mt-5 mb-5">
            <div class="col-md-2">
              <label>Total Gross Wt</label>
              <input type="text" id="sum_gross_weight" class="form-control" readonly>
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
              <label>Total Making</label>
              <input type="text" id="sum_making_value" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Metal Val.</label>
              <input type="text" id="sum_metal_value" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total VAT</label>
              <input type="text" id="sum_vat_amount" class="form-control" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Net Amount</label>
              <input type="text" id="net_amount_display" class="form-control text-danger fw-bold" readonly>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>

          {{-- PAYMENT METHOD --}}
          <div class="row mb-3">
            <div class="col-md-2">
              <label class="fw-bold">Payment Method</label>
              <select name="payment_method" id="payment_method" class="form-control" required>
                <option value="">Select Payment Method</option>
                <option value="credit">Credit</option>
                <option value="cash">Cash</option>
                <option value="cheque">Cheque</option>
                <option value="material+making cost">Material + Making Cost</option>
              </select>
            </div>

            <div class="col-md-2">
              <label>Payment Term</label>
              <input type="text" name="payment_term" class="form-control">
            </div>
          </div>

          {{-- RECEIVED BY (common for Cash & Cheque) --}}
          <div class="row mb-3 d-none" id="received_by_box">
            <div class="col-md-2">
              <label>Received By</label>
              <input type="text" name="received_by" class="form-control">
            </div>
          </div>

          {{-- CHEQUE DETAILS --}}
          <div class="row mb-3 d-none" id="cheque_fields">
            <div class="col-md-2">
              <label>Bank Name</label>
              <input type="text" name="bank_name" class="form-control">
            </div>
            <div class="col-md-2">
              <label>Cheque No</label>
              <input type="text" name="cheque_no" class="form-control">
            </div>
            <div class="col-md-2">
              <label>Cheque Date</label>
              <input type="date" name="cheque_date" class="form-control">
            </div>
            <div class="col-md-2">
              <label>Cheque Amount</label>
              <input type="number" step="any" name="cheque_amount" class="form-control">
            </div>
          </div>

          {{-- MATERIAL + MAKING COST --}}
          <div class="row mb-3 d-none" id="material_fields">
            <div class="col-md-2">
              <label>Raw Metal Weight Given</label>
              <input type="number" step="any" name="material_weight" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Raw Metal Purity</label>
              <input type="number" step="any" name="material_purity" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Metal Adjustment Value</label>
              <input type="number" step="any" name="material_value" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Making Charges Payable</label>
              <input type="number" step="any" name="making_charges" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Fine Gold Received</label>
              <input type="text" id="material_fine_gold" class="form-control" readonly>
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
          </div>

          {{-- CURRENCY --}}
          <div class="card mt-3">
            <div class="card-header">
              <h2 class="card-title">Currency</h2>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-2">
                  <label class="form-label">Invoice Currency</label>
                  <select name="currency" id="currency" class="form-control">
                    <option value="AED" selected>AED / Dirhams</option>
                    <option value="USD">USD Dollars</option>
                  </select>
                </div>
                <div class="col-md-2" id="exchangeRateBox" style="display:none;">
                  <label class="form-label">USD → AED Rate</label>
                  <input type="number" step="0.000001" name="exchange_rate" id="exchange_rate" class="form-control" placeholder="e.g. 3.6725">
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
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save"></i> Save Invoice
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
$(document).ready(function () {
    const products = @json($products); // Now JS knows the products

    // Initialize select2 for existing selects
    $('.select2-js').select2({ width: '100%' });

    const currencySelect = $('#currency');
    const rateBox = $('#exchangeRateBox');
    const rateInput = $('#exchange_rate');
    const convertedTotal = $('#converted_total');

    // ================= ROW MANAGEMENT =================
    function updateRowIndexes() {
        $('#PurchaseTable tr.item-row').each(function(i) {
            $(this).find('input, select').each(function() {
                const name = $(this).attr('name');
                if(name) {
                    const newName = name.replace(/items\[\d+\]/, `items[${i}]`);
                    $(this).attr('name', newName);
                }
            });
        });
    }

    window.addNewRow = function() {
        const nextIndex = $('#PurchaseTable tr.item-row').length;

        const rowHtml = `
        <tr class="item-row" data-item-index="${nextIndex}">
            <td>
                <div class="product-wrapper">
                    <input type="text" name="items[${nextIndex}][item_name]" class="form-control item-name-input" placeholder="Enter product name">
                    <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>
                </div>
            </td>
            <td><input type="text" name="items[${nextIndex}][item_description]" class="form-control"></td>
            <td>
                <select name="items[${nextIndex}][purity]" class="form-control purity">
                    <option value="0.916">22K (92%)</option>
                    <option value="0.875">21K (88%)</option>
                    <option value="0.750">18K (75%)</option>
                </select>
            </td>
            <td><input type="number" name="items[${nextIndex}][gross_weight]" step="any" value="0" class="form-control gross-weight"></td>
            <td><input type="number" name="items[${nextIndex}][purity_weight]" step="any" value="0" class="form-control purity-weight" readonly></td>
            <td><input type="number" name="items[${nextIndex}][995]" step="any" value="0" class="form-control col-995" readonly></td>
            <td><input type="number" name="items[${nextIndex}][making_rate]" step="any" value="0" class="form-control making-rate"></td>
            <td><input type="number" name="items[${nextIndex}][making_value]" step="any" class="form-control making-value" readonly></td>
            <td>
                <select name="items[${nextIndex}][metal_type]" class="form-control metal-type">
                    <option value="gold">Gold</option>
                    <option value="metal">Metal</option>
                </select>
            </td>
            <td><input type="number" name="items[${nextIndex}][metal_value]" step="any" value="0" class="form-control metal-value" readonly></td>
            <td><input type="number" name="items[${nextIndex}][taxable_amount]" step="any" value="0" class="form-control taxable-amount" readonly></td>
            <td><input type="number" name="items[${nextIndex}][vat_percent]" class="form-control vat-percent" step="any" value="0"></td>
            <td><input type="number" step="any" class="form-control vat-amount" readonly></td>
            <td><input type="number" class="form-control item-total" readonly></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                <button type="button" class="btn btn-sm btn-primary toggle-parts"><i class="fas fa-wrench"></i></button>
            </td>
        </tr>`;

        const partsRowHtml = `
        <tr class="parts-row" style="display:none;background:#efefef">
            <td colspan="15">
                <table class="table table-sm table-bordered parts-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Part</th>
                            <th>Variation</th>
                            <th>Qty</th>
                            <th>Rate</th>
                            <th>Wastage</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary add-part">+ Add Part</button>
            </td>
        </tr>`;

        $('#PurchaseTable').append(rowHtml).append(partsRowHtml);
    };

    window.removeRow = function(btn) {
        if ($('#PurchaseTable tr.item-row').length > 1) {
            const partsRow = $(btn).closest('tr').next('.parts-row');
            partsRow.remove();
            $(btn).closest('tr').remove();
            updateRowIndexes();
            calculateTotals();
        }
    };

    // ================= PART MANAGEMENT =================
    function addPartRow(partsTable, itemIndex) {
        let partIndex = partsTable.find('tr').length;

        let row = `
        <tr style="background:#efefef">
            <td>
                <select name="items[${itemIndex}][parts][${partIndex}][product_id]" class="form-control part-product-select">
                    <option value="">Select Part</option>
                    ${products.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                </select>
            </td>
            <td>
                <select name="items[${itemIndex}][parts][${partIndex}][variation_id]" class="form-control part-variation-select" disabled>
                    <option value="">Select Variation</option>
                </select>
            </td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][qty]" class="form-control part-qty"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][rate]" class="form-control part-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][wastage]" class="form-control part-wastage"></td>
            <td><input type="number" class="form-control part-total" disabled></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-part">×</button></td>
        </tr>`;

        partsTable.append(row);
        partsTable.find('select').select2({ width: '100%' });
    }

    $(document).on('click', '.toggle-parts', function() {
        $(this).closest('tr').next('.parts-row').toggle();
    });

    $(document).on('click', '.add-part', function() {
        const partsTable = $(this).siblings('table').find('tbody');
        const itemIndex = $(this).closest('.parts-row').prev('.item-row').data('item-index');
        addPartRow(partsTable, itemIndex);
    });

    $(document).on('click', '.remove-part', function() {
        $(this).closest('tr').remove();
    });

    $(document).on('input', '.part-qty, .part-rate, .part-wastage', function() {
        const row = $(this).closest('tr');
        const qty = parseFloat(row.find('.part-qty').val()) || 0;
        const rate = parseFloat(row.find('.part-rate').val()) || 0;
        const wastage = parseFloat(row.find('.part-wastage').val()) || 0;
        row.find('.part-total').val(((qty + wastage) * rate).toFixed(2));
    });

    // ================= PRODUCT → VARIATION =================
    $(document).on('click', '.toggle-product', function () {
        const wrapper = $(this).closest('.product-wrapper');
        const row = wrapper.closest('tr');
        const rowIndex = row.data('item-index');

        // Remove text field
        wrapper.find('.item-name-input').remove();

        // Inject product + variation + revert button
        const dropdownHtml = `
            <select name="items[${rowIndex}][product_id]" class="form-control select2-js product-select mb-2">
                <option value="">Select Product</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>

            <select name="items[${rowIndex}][variation_id]" class="form-control select2-js mt-2 variation-select" style="display:none;">
                <option value="">Select Variation</option>
            </select>

            <button type="button" class="btn btn-link p-0 revert-to-name mt-1"> Write Name </button>
        `;

        wrapper.prepend(dropdownHtml);

        wrapper.find('.select2-js').select2({ width: '100%' });

        $(this).remove(); // remove "Select Product" button
    });

    $(document).on('click', '.revert-to-name', function () {
      const wrapper = $(this).closest('.product-wrapper');
      const row = wrapper.closest('tr');
      const rowIndex = row.data('item-index');

      // Remove product & variation selects
      wrapper.find('.product-select').select2('destroy').remove();
      wrapper.find('.variation-select').select2('destroy').remove();
      $(this).remove(); // remove "Write Name"

      // Restore manual input
      const input = `<input type="text" name="items[${rowIndex}][item_name]" class="form-control item-name-input" placeholder="Product Name">`;
      const toggleBtn = `<button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>`;

      wrapper.prepend(input);
      wrapper.append(toggleBtn);
    }); 

    $(document).on('change', '.product-select', function() {
        const wrapper = $(this).closest('.product-wrapper');
        const variationSelect = wrapper.find('.variation-select');
        const productId = $(this).val();

        if(productId) {
            variationSelect.show().html('<option value="">Loading...</option>');
            $.get(`/product/${productId}/variations`, function(data) {
                let options = '<option value="">Select Variation</option>';
                (data.variation || []).forEach(v => options += `<option value="${v.id}">${v.sku}</option>`);
                variationSelect.html(options).select2({ width: '100%' });
            });
        } else {
            variationSelect.hide().html('<option value="">Select Variation</option>');
        }
    });

    // ================= CALCULATIONS =================
    $(document).on('input change', '.gross-weight,.purity,.making-rate,.vat-percent,.metal-type,#gold_rate_aed,#metal_rate_aed,#gold_rate_usd,#metal_rate_usd', function() {
        calculateRow($(this).closest('tr'));
        calculateTotals();
    });

    function calculateRow(row) {
        const purity = parseFloat(row.find('.purity').val()) || 0;
        const gross = parseFloat(row.find('.gross-weight').val()) || 0;
        const makingRate = parseFloat(row.find('.making-rate').val()) || 0;
        const vatPercent = parseFloat(row.find('.vat-percent').val()) || 0;
        const rate = getMetalRate(row);

        const purityWt = gross * purity;
        row.find('.purity-weight').val(purityWt.toFixed(3));

        const col995 = purityWt / 0.995;
        row.find('.col-995').val(col995.toFixed(3));

        const makingValue = makingRate * gross;
        row.find('.making-value').val(makingValue.toFixed(2));

        const metalValue = purityWt * rate;
        row.find('.metal-value').val(metalValue.toFixed(2));

        const taxableAmount = makingValue + metalValue;
        row.find('.taxable-amount').val(taxableAmount.toFixed(2));

        const vatAmount = taxableAmount * vatPercent / 100;
        row.find('.vat-amount').val(vatAmount.toFixed(2));

        const itemTotal = taxableAmount + vatAmount;
        row.find('.item-total').val(itemTotal.toFixed(2));
    }

    function calculateTotals() {
        let sumGross = 0, sumPurity = 0, sum995 = 0, sumMaking = 0, sumMetal = 0, sumVAT = 0, netAmount = 0;
        $('#PurchaseTable tr.item-row').each(function () {
            sumGross  += parseFloat($(this).find('.gross-weight').val()) || 0;
            sumPurity  += parseFloat($(this).find('.purity-weight').val()) || 0;
            sum995     += parseFloat($(this).find('.col-995').val()) || 0;
            sumMaking  += parseFloat($(this).find('.making-value').val()) || 0;
            sumMetal   += parseFloat($(this).find('.metal-value').val()) || 0;
            sumVAT     += parseFloat($(this).find('.vat-amount').val()) || 0;
            netAmount  += parseFloat($(this).find('.item-total').val()) || 0;
        });
        $('#sum_gross_weight').val(sumGross.toFixed(3));
        $('#sum_purity_weight').val(sumPurity.toFixed(3));
        $('#sum_995').val(sum995.toFixed(3));
        $('#sum_making_value').val(sumMaking.toFixed(2));
        $('#sum_metal_value').val(sumMetal.toFixed(2));
        $('#sum_vat_amount').val(sumVAT.toFixed(2));
        $('#net_amount_display').val(netAmount.toFixed(2));
        $('#net_amount').val(netAmount.toFixed(2));

        updateConversion();
        updateGoldBalance();
    }

    function getMetalRate(row) {
        const metalType = row.find('.metal-type').val();
        const currency = currencySelect.val();
        if(metalType === 'gold') return parseFloat(currency === 'USD' ? $('#gold_rate_usd').val() : $('#gold_rate_aed').val()) || 0;
        else return parseFloat(currency === 'USD' ? $('#metal_rate_usd').val() : $('#metal_rate_aed').val()) || 0;
    }

    // ================= GOLD BALANCE =================
    function updateGoldBalance() {
        const fineGoldReceived = parseFloat($('#material_fine_gold').val()) || 0;
        const goldUsed = parseFloat($('#gold_used').val()) || 0;
        const balance = fineGoldReceived - goldUsed;
        $('#gold_balance').val(balance.toFixed(3));
        const rate = parseFloat($('#gold_rate_aed').val()) || 0;
        $('#gold_balance_value').val((balance * rate).toFixed(2));
    }

    $('#material_fine_gold,#gold_used,#gold_rate_aed').on('input change', updateGoldBalance);

    // ================= CURRENCY =================
    function updateConversion() {
        const netTotal = parseFloat($('#net_amount').val() || 0);
        const rate = parseFloat(rateInput.val() || 0);
        if(currencySelect.val() === 'USD' && rate > 0) convertedTotal.val((netTotal * rate).toFixed(2));
        else convertedTotal.val(netTotal.toFixed(2));
    }

    currencySelect.on('change', function() {
        if(this.value === 'USD') rateBox.show();
        else { rateBox.hide(); rateInput.val(''); }
        updateConversion();
    });
    rateInput.on('input', updateConversion);

    // ================= PAYMENT METHOD =================
    function togglePaymentFields() {
        const val = $('#payment_method').val();
        $('#cheque_fields, #material_fields, #received_by_box').addClass('d-none');
        if(val === 'cheque') $('#cheque_fields,#received_by_box').removeClass('d-none');
        else if(val === 'cash') $('#received_by_box').removeClass('d-none');
        else if(val === 'material+making cost') $('#material_fields').removeClass('d-none');
    }

    $('#payment_method').on('change', togglePaymentFields);
    togglePaymentFields(); // trigger on load

});
</script>

@endsection
