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
              <label>Gold Rate (USD / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_usd" name="gold_rate_usd" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (AED / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_aed_ounce" name="gold_rate_aed_ounce" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-3">
              <label class="text-primary">Gold Converted Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="gold_rate_aed" name="gold_rate_aed" class="form-control" value="0" readonly>
              <small class="text-danger text-bold">Used for calculations</small>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (USD)  / Ounce</label>
              <input type="number" step="any" id="diamond_rate_usd" name="diamond_rate_usd" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (AED) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_aed" name="diamond_rate_aed" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-3">
              <label class="text-primary">Diamond Converted Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="dia_rate_aed" name="dia_rate_aed" class="form-control" value="0" readonly>
              <small class="text-danger text-bold">Used for calculations</small>
            </div>

            <div class="col-md-4 mt-2">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control"></textarea>
            </div>

            <div class="col-md-4 mt-2">
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
                <tr class="item-row" data-item-index="0">
                  <td>
                    <div class="product-wrapper">
                      <input type="text" name="items[0][item_name]" class="form-control item-name-input" placeholder="Product Name">
                      <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>
                    </div>
                  </td>
                  <td><input type="text" name="items[0][item_description]" class="form-control" required></td>
                  <td>
                    <select name="items[0][purity]" class="form-control purity">
                      <option value="0.92">22K (92%)</option>
                      <option value="0.88">21K (88%)</option>
                      <option value="0.75">18K (75%)</option>
                    </select>
                  </td>
                  <td><input type="number" name="items[0][gross_weight]" step="any" value="0" class="form-control gross-weight"></td>
                  <td><input type="number" name="items[0][purity_weight]" step="any" value="0" class="form-control purity-weight" readonly></td>
                  <td><input type="number" name="items[0][995]" step="any" value="0" class="form-control col-995" readonly></td>
                  <td><input type="number" name="items[0][making_rate]" step="any" value="0" class="form-control making-rate"></td>
                  <td><input type="number" name="items[0][making_value]" step="any" class="form-control making-value" readonly></td>
                  <td>
                    <select name="items[0][material_type]" class="form-control material-type">
                      <option value="gold">Gold</option>
                      <option value="diamond">Diamond</option>
                    </select>
                  </td>
                  <td><input type="number" name="items[0][metal_value]" step="any" value="0" class="form-control material-value" readonly></td>
                  <td><input type="number" name="items[0][taxable_amount]" step="any" value="0" class="form-control taxable-amount" readonly></td>
                  <td><input type="number" name="items[0][vat_percent]" class="form-control vat-percent" step="any" value="0"></td>
                  <td><input type="number" step="any" class="form-control vat-amount" readonly></td>
                  <td><input type="number" class="form-control item-total" readonly></td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
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
                            <th>Description</th>
                            <th>Qty/Unit</th>
                            <th>Rate</th>
                            <th>Stone Qty</th>
                            <th>Stone Rate</th>
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
              <label>Total Making (Incl. VAT)</label>
              <input type="text" id="sum_making_value" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Material Val.</label>
              <input type="text" id="sum_material_value" class="form-control" readonly>
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

          {{-- ADDITIONAL FIELDS (Hidden/Shown via JS) --}}
          <div class="row mb-3 d-none" id="received_by_box">
            <div class="col-md-2">
              <label>Received By</label>
              <input type="text" name="received_by" class="form-control">
            </div>
          </div>

          <div class="row mb-3 d-none" id="cheque_fields">
            <div class="col-md-2">
              <label>Bank Name</label>
              <select name="bank_name" class="form-control select2-js">
                <option value="">Select Bank</option>
                @foreach ($banks as $bank)
                  <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                @endforeach
              </select>
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

          <div class="row mb-3 d-none" id="material_fields">
            <div class="col-md-2">
              <label>Total Item Wt. Received</label>
              <input type="text" id="total_wt_received" class="form-control">
            </div>
            <div class="col-md-2">
                <label>Raw Material Weight Given</label>
                <input type="number" step="any" name="material_weight" class="form-control">
              </div>
              <div class="col-md-2">
                <label>Raw Material Purity</label>
                <input type="number" step="any" name="material_purity" class="form-control">
              </div>
              <div class="col-md-2">
                <label>Material Adjustment Value</label>
                <input type="number" step="any" name="material_value" class="form-control">
              </div>
              <div class="col-md-2">
                <label>Making Charges Payable</label>
                <input type="number" step="any" name="making_charges" class="form-control">
              </div>
              <div class="col-md-2">
                <label>Gold Used (Invoice)</label>
                <input type="text" id="gold_used" class="form-control">
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
                <input type="text" id="material_given_by" name="material_given_by" class="form-control text-danger fw-bold">
              </div>
              <div class="col-md-2 mt-3">
                <label>Material Received By</label>
                <input type="text" id="material_received_by" name="material_received_by" class="form-control text-danger fw-bold">
              </div>
          </div>

          <div class="card mt-3">
            <div class="card-header"><h2 class="card-title">Currency</h2></div>
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
                    <label class="form-label">USD → AED Rate <span class="text-danger">*</span></label>
                    <input type="number" step="0.000001" name="exchange_rate" id="exchange_rate" class="form-control" placeholder="3.6725">
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
    const products = @json($products);

    // Map the unit name specifically so it's easy to access in the loop
    const productsWithUnits = products.map(p => {
        return {
            id: p.id,
            name: p.name,
            // Access the name column from the measurement_unit table
            unit_name: p.measurement_unit ? p.measurement_unit.name : '' 
        };
    });

    const TROY_OUNCE_TO_GRAM = 31.1034768;

    $('#currency').on('change', function() {
        const isUSD = $(this).val() === 'USD';
        
        if (isUSD) {
            $('#exchangeRateBox').show();
            $('#exchange_rate').attr('required', true);
            if(!$('#exchange_rate').val()) $('#exchange_rate').val('3.6725');
        } else {
            $('#exchangeRateBox').hide();
            $('#exchange_rate').removeAttr('required');
            $('#exchange_rate').val('');
        }
        
        // Recalculate everything when currency changes
        calculateTotals(); 
    });

    // Also trigger calculation when the exchange rate itself is typed
    $('#exchange_rate').on('input', function() {
        calculateTotals();
    });

    $('.select2-js').select2({ width: '100%' });
    const currencySelect = $('#currency');
    const rateInput = $('#exchange_rate');

    // ================= ROW MANAGEMENT =================
    function updateRowIndexes() {
        $('#PurchaseTable tr.item-row').each(function(i) {
            $(this).attr('data-item-index', i);
            $(this).find('input, select').each(function() {
                const name = $(this).attr('name');
                if(name) {
                    const newName = name.replace(/items\[\d+\]/, `items[${i}]`);
                    $(this).attr('name', newName);
                }
            });
            // Update parts in the following parts-row
            const partsRow = $(this).next('.parts-row');
            partsRow.find('input, select').each(function() {
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
                            <tr><th>Part</th><th>Variation</th><th>Description</th><th>Qty/Unit</th><th>Rate</th><th>Stone Qty</th><th>Stone Rate</th><th>Total</th><th></th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary add-part">+ Add Part</button>
                </div>
            </td>
        </tr>`;
        $('#PurchaseTable').append(rowHtml);
    };

    window.removeRow = function(btn) {
        const row = $(btn).closest('tr');
        const partsRow = row.next('.parts-row');
        if ($('#PurchaseTable tr.item-row').length > 1) {
            partsRow.remove();
            row.remove();
            updateRowIndexes();
            calculateTotals();
        }
    };

    // ================= PARTS & PRODUCT LOGIC =================
    $(document).on('click', '.toggle-parts', function() {
      $(this).closest('tr').next('.parts-row').toggle();
    });

    $(document).on('click', '.add-part', function() {
        const partsTable = $(this).siblings('table').find('tbody');
        const itemIndex = $(this).closest('.parts-row').prev('.item-row').data('item-index');
        let partIndex = partsTable.find('tr').length;

        let row = `
        <tr>
            <td>
              <select name="items[${itemIndex}][parts][${partIndex}][product_id]" class="form-control select2-js part-product-select">
                <option value="">Select Part</option>
                ${products.map(p => `
                  <option value="${p.id}" data-unit="${p.measurement_unit?.shortcode || ''}">
                    ${p.name}
                  </option>
                `).join('')}
              </select>
            </td>
            <td><select name="items[${itemIndex}][parts][${partIndex}][variation_id]" class="form-control select2-js part-variation-select"><option value="">Select Variation</option></select></td>
            <td><input type="text" name="items[${itemIndex}][parts][${partIndex}][part_description]" class="form-control"></td>
            <td>
              <div class="input-group">
                <input type="number" name="items[${itemIndex}][parts][${partIndex}][qty]" step="any" value="0" class="form-control part-qty">
                <input type="text" class="form-control part-unit-name" style="width:60px; flex:none;" readonly placeholder="Unit">
              </div>
            </td>          
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][rate]" step="any" value="0" class="form-control part-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_qty]" step="any" value="0" class="form-control part-stone-qty"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_rate]" step="any" value="0" class="form-control part-stone-rate"></td>
            <td><input type="number" class="form-control part-total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-part">x</button></td>
        </tr>`;
        partsTable.append(row);
        partsTable.find('.select2-js').select2({ width: '100%' });
    });

    $(document).on('click', '.toggle-product', function () {
        const wrapper = $(this).closest('.product-wrapper');
        const rowIndex = wrapper.closest('tr').data('item-index');
        wrapper.empty().append(`
            <select name="items[${rowIndex}][product_id]" class="form-control select2-js product-select mb-2">
                <option value="">Select Product</option>
                ${products.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
            </select>
            <select name="items[${rowIndex}][variation_id]" class="form-control select2-js variation-select" style="display:none;"><option value="">Select Variation</option></select>
            <button type="button" class="btn btn-link p-0 revert-to-name mt-1"> Write Name </button>
        `).find('.select2-js').select2({ width: '100%' });
    });

    $(document).on('click', '.revert-to-name', function () {
        const wrapper = $(this).closest('.product-wrapper');
        const rowIndex = wrapper.closest('tr').data('item-index');
        wrapper.empty().append(`
            <input type="text" name="items[${rowIndex}][item_name]" class="form-control item-name-input" placeholder="Product Name">
            <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>
        `);
    });

    // ================= CALCULATIONS (Integrated your 6 Rules) =================
    $(document).on('input change', '.gross-weight, .purity, .making-rate, .vat-percent, .material-type, #gold_rate_aed, #diamond_rate_aed', function() {
        const row = $(this).closest('tr.item-row');
        if(row.length) calculateRow(row);
        calculateTotals();
    });

    function calculateRow(row) {
        const purity = parseFloat(row.find('.purity').val()) || 0;
        const gross = parseFloat(row.find('.gross-weight').val()) || 0;
        const makingRate = parseFloat(row.find('.making-rate').val()) || 0;
        const vatPercent = parseFloat(row.find('.vat-percent').val()) || 0;
        const materialType = row.find('.material-type').val();
        
        // Inside calculateRow(row)...
        let rate = (materialType === 'gold') ? parseFloat($('#gold_rate_aed').val()) : parseFloat($('#dia_rate_aed').val()); // Use the Gram rate field here
        rate = rate || 0;

        // 1. gross wt. * purity = purity wt.
        const purityWt = gross * purity;

        // 2. purity wt. / 0.995 = 995 val
        const col995 = purityWt / 0.995;

        // 3. makingValue = making rate * gross wt. / 0.995 = 995 val
        const makingValue = gross * makingRate ;

        // 4. making rate * purity wt. = material value (As per your rule)
        const materialValue = rate * purityWt;

        // 5. making value = Taxable (Your logic maps rule 3 to rule 4)
        const taxableAmount = makingValue; 

        // 6. taxable * VAT% = VAT Amount
        const vatAmount = taxableAmount * vatPercent / 100;

        // 5. Taxable + Material Val (Metal) + VAT Amount = gross amount
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

        // --- NEW LOGIC FOR CONVERTED TOTAL ---
        const currency = $('#currency').val();
        const exRate = parseFloat($('#exchange_rate').val()) || 1;
        
        if (currency === 'USD') {
            // If invoice is in USD, converted total (AED) = USD Amount * Rate
            $('#converted_total').val((netTotal * exRate).toFixed(2));
        } else {
            // If already in AED, converted total is just the net total
            $('#converted_total').val(netTotal.toFixed(2));
        }
        // -------------------------------------

        if ($('#payment_method').val() === 'material+making cost') {
            $('input[name="material_weight"]').val(sum995.toFixed(3));
            $('input[name="material_purity"]').val(sumPurity.toFixed(3));
            $('input[name="material_value"]').val(sumMaterial.toFixed(2));
            $('input[name="making_charges"]').val(makingTotalWithVat.toFixed(2));
        }
    }

    // ================= OUNCE TO GRAM & CURRENCY CONVERSION =================
    $(document).on('input', '#gold_rate_usd, #gold_rate_aed_ounce, #diamond_rate_usd, #diamond_rate_aed, #exchange_rate', function() {
        const id = $(this).attr('id');
        const exRate = parseFloat($('#exchange_rate').val()) || 3.6725;
        const TROY_OUNCE_TO_GRAM = 31.1034768;

        // --- GOLD LOGIC ---
        if (id === 'gold_rate_usd' || id === 'exchange_rate') {
            const goldUsd = parseFloat($('#gold_rate_usd').val()) || 0;
            $('#gold_rate_aed_ounce').val((goldUsd * exRate).toFixed(2));
        }
        // Update Gold Gram Rate
        const goldAedOunceFinal = parseFloat($('#gold_rate_aed_ounce').val()) || 0;
        $('#gold_rate_aed').val((goldAedOunceFinal / TROY_OUNCE_TO_GRAM).toFixed(4));

        // --- DIAMOND LOGIC (FIXED) ---
        if (id === 'diamond_rate_usd' || id === 'exchange_rate') {
            const diaUsd = parseFloat($('#diamond_rate_usd').val()) || 0;
            // Calculate AED/Ounce based on USD input
            $('#diamond_rate_aed').val((diaUsd * exRate).toFixed(2));
        }

        // Always update the Converted Gram Rate (dia_rate_aed) based on the Ounce Rate (diamond_rate_aed)
        const diaAedOunceFinal = parseFloat($('#diamond_rate_aed').val()) || 0;
        $('#dia_rate_aed').val((diaAedOunceFinal / TROY_OUNCE_TO_GRAM).toFixed(4));

        // Trigger row recalculations to update the table
        $('.gross-weight').first().trigger('input'); 
    });

    $('#payment_method').on('change', function() {
        const val = $(this).val();
        $('#cheque_fields, #material_fields, #received_by_box').addClass('d-none');
        if(val === 'cheque') $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if(val === 'cash') $('#received_by_box').removeClass('d-none');
        else if(val === 'material+making cost') $('#material_fields').removeClass('d-none');
        calculateTotals();
    });

    $(document).on('change', '.part-product-select, .product-select', function() {
        const productId = $(this).val();
        const row = $(this).closest('tr');
        
        // Fix: Use .add() or check length to find the correct variation select
        let variationSelect = row.find('.part-variation-select');
        if (variationSelect.length === 0) {
            variationSelect = row.find('.variation-select');
        }

        const selectedOption = $(this).find(':selected');
        const unitName = selectedOption.data('unit') || '';
        row.find('.part-unit-name').val(unitName);

        variationSelect.html('<option value="">Loading...</option>').prop('disabled', true);

        if (!productId) {
            variationSelect.html('<option value="">Select Variation</option>').prop('disabled', false);
            return;
        }

        fetch(`/product/${productId}/variations`)
            .then(res => res.json())
            .then(data => {
                variationSelect.prop('disabled', false);
                let options = '<option value="">No variation</option>';

                if (data.success && data.variation.length > 0) {
                    options = '<option value="">Select Variation</option>';
                    data.variation.forEach(v => {
                        options += `<option value="${v.id}">${v.sku}</option>`;
                    });
                }
                variationSelect.html(options).trigger('change');
            })
            .catch(err => {
                console.error('Error:', err);
                variationSelect.html('<option value="">Error loading</option>').prop('disabled', false);
            });
    });

    // ================= REMOVE PART LOGIC =================
    $(document).on('click', '.remove-part', function() {
        const row = $(this).closest('tr');
        const tableBody = row.closest('tbody');
        
        row.remove();
    });

    // ================= PARTS ROW CALCULATION =================
    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate', function() {
        const row = $(this).closest('tr');
        
        const qty = parseFloat(row.find('.part-qty').val()) || 0;
        const rate = parseFloat(row.find('.part-rate').val()) || 0;
        const stoneQty = parseFloat(row.find('.part-stone-qty').val()) || 0;
        const stoneRate = parseFloat(row.find('.part-stone-rate').val()) || 0;

        // Rule: (part qty * part rate) + (stone qty * stone rate)
        const total = (qty * rate) + (stoneQty * stoneRate);
        
        row.find('.part-total').val(total.toFixed(2));
        
        // Optional: If you want part totals to affect the main Item Total, 
        // you would add logic here to sum parts and add to taxable-amount.
    });

    // ================= UPDATED REMOVE PART (WITH RECALC) =================
    $(document).on('click', '.remove-part', function() {
        const tableBody = $(this).closest('tbody');
        $(this).closest('tr').remove();
        
        // Update indexes for remaining parts in this specific table
        tableBody.find('tr').each(function(partIdx) {
            const itemRow = $(this).closest('.parts-row').prev('.item-row');
            const itemIdx = itemRow.data('item-index');
            
            $(this).find('input, select').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    // regex to update the part index [parts][index]
                    const newName = name.replace(/\[parts\]\[\d+\]/, `[parts][${partIdx}]`);
                    $(this).attr('name', newName);
                }
            });
        });
    });
  });
</script>
@endsection