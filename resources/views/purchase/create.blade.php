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

            <div class="col-12 col-md-2">
              <label class="text-primary">Converted Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="gold_rate_aed" name="gold_rate_aed" class="form-control" value="0" readonly>
              <small class="text-danger text-bold">Used for calculations</small>
            </div>

            <div class="col-12 col-md-2">
              <label>Diamond Rate (USD)  / gram</label>
              <input type="number" step="any" id="diamond_rate_usd" name="diamond_rate_usd" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (AED) / gram</label>
              <input type="number" step="any" id="diamond_rate_aed" name="diamond_rate_aed" class="form-control" value="0">
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
                      <option value="0.916">22K (92%)</option>
                      <option value="0.875">21K (88%)</option>
                      <option value="0.750">18K (75%)</option>
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
                            <th>Stone</th>
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

          <div class="row mb-3 d-none" id="material_fields">
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
                <label>Fine Gold Received</label>
                <input type="text" id="material_fine_gold" class="form-control">
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
      const TROY_OUNCE_TO_GRAM = 31.1034768;

      $('#currency').on('change', function() {
        const isUSD = $(this).val() === 'USD';
        
        if (isUSD) {
            $('#exchangeRateBox').show();
            $('#exchange_rate').attr('required', true);
            // Set a default if empty to prevent validation errors (optional)
            if(!$('#exchange_rate').val()) $('#exchange_rate').val('3.6725');
        } else {
            $('#exchangeRateBox').hide();
            $('#exchange_rate').removeAttr('required');
            $('#exchange_rate').val('');
        }
        
        // Trigger calculation updates
        $('#gold_rate_usd').trigger('input');
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
                              <tr><th>Part</th><th>Variation</th><th>Description</th><th>Qty/Unit</th><th>Rate</th><th>Stone</th><th>Total</th><th></th></tr>
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
                      ${products.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                  </select>
              </td>
              <td><select name="items[${itemIndex}][parts][${partIndex}][variation_id]" class="form-control select2-js part-variation-select"><option value="">Select Variation</option></select></td>
              <td><input type="text" name="items[${itemIndex}][parts][${partIndex}][part_description]" class="form-control"></td>
              <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][qty]" step="any" value="0" class="form-control part-qty"></td>
              <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][rate]" step="any" value="0" class="form-control part-rate"></td>
              <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone]" step="any" value="0" class="form-control part-stone"></td>
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
          
          let rate = (materialType === 'gold') ? parseFloat($('#gold_rate_aed').val()) : parseFloat($('#diamond_rate_aed').val());
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
          let sumGross = 0, sumPurity = 0, sum995 = 0, sumMaking = 0, sumMaterial = 0, sumVAT = 0, netTotal = 0;
          
          $('#PurchaseTable tr.item-row').each(function () {
              sumGross    += parseFloat($(this).find('.gross-weight').val()) || 0;
              sumPurity   += parseFloat($(this).find('.purity-weight').val()) || 0;
              sum995      += parseFloat($(this).find('.col-995').val()) || 0;
              sumMaking   += parseFloat($(this).find('.making-value').val()) || 0;
              sumMaterial += parseFloat($(this).find('.material-value').val()) || 0;
              sumVAT      += parseFloat($(this).find('.vat-amount').val()) || 0;
              netTotal    += parseFloat($(this).find('.item-total').val()) || 0;
          });

          $('#sum_gross_weight').val(sumGross.toFixed(3));
          $('#sum_purity_weight').val(sumPurity.toFixed(3));
          $('#sum_995').val(sum995.toFixed(3));
          $('#sum_making_value').val(sumMaking.toFixed(2));
          $('#sum_material_value').val(sumMaterial.toFixed(2));
          $('#sum_vat_amount').val(sumVAT.toFixed(2));
          $('#net_amount_display').val(netTotal.toFixed(2));
          $('#net_amount').val(netTotal.toFixed(2));

          if ($('#payment_method').val() === 'material+making cost') {
              $('input[name="material_weight"]').val(sum995.toFixed(3));
              $('input[name="material_purity"]').val(sumPurity.toFixed(3));
              // Value of gold used
              const goldVal = sumPurity * (parseFloat($('#gold_rate_aed').val()) || 0);
              $('input[name="material_value"]').val(goldVal.toFixed(2));
              // Making + VAT
              $('input[name="making_charges"]').val((sumMaking + sumVAT).toFixed(2));
          }
      }

      // Ounce to Gram Calculation
      $(document).on('input', '#gold_rate_usd, #exchange_rate, #gold_rate_aed_ounce', function() {
          const id = $(this).attr('id');
          const exRate = parseFloat($('#exchange_rate').val()) || 3.6725;
          
          if(id === 'gold_rate_usd') {
              const usd = parseFloat($(this).val()) || 0;
              const aedOunce = usd * exRate;
              $('#gold_rate_aed_ounce').val(aedOunce.toFixed(2));
              $('#gold_rate_aed').val((aedOunce / TROY_OUNCE_TO_GRAM).toFixed(4));
          } else if (id === 'gold_rate_aed_ounce') {
              const aedOunce = parseFloat($(this).val()) || 0;
              $('#gold_rate_aed').val((aedOunce / TROY_OUNCE_TO_GRAM).toFixed(4));
          }
          $('.gross-weight').trigger('input');
      });

      $('#payment_method').on('change', function() {
          const val = $(this).val();
          $('#cheque_fields, #material_fields, #received_by_box').addClass('d-none');
          if(val === 'cheque') $('#cheque_fields, #received_by_box').removeClass('d-none');
          else if(val === 'cash') $('#received_by_box').removeClass('d-none');
          else if(val === 'material+making cost') $('#material_fields').removeClass('d-none');
          calculateTotals();
      });
  });
</script>
@endsection