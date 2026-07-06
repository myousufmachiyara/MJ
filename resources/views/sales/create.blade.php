@extends('layouts.app')

@section('title', 'Sale | New Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('sale_invoices.store') }}" method="POST" enctype="multipart/form-data">
      @csrf

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
          </ul>
        </div>
      @endif

      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Sale Invoice</h2>
        </header>

        <div class="card-body">

          {{-- ===================== HEADER ===================== --}}
          <div class="row mb-5">

            <div class="col-md-2">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>

            <div class="col-md-2">
              <label class="fw-bold">Invoice Type</label>
              <select name="is_taxable" id="is_taxable" class="form-control border-primary" required>
                <option value="1">Taxable (SAL-TAX)</option>
                <option value="0">Non-Taxable (SAL)</option>
              </select>
              <small class="text-muted">Determines the sequence number</small>
            </div>

            <div class="col-md-2">
              <label>Customer</label>
              <select name="customer_id" class="form-control select2-js" required>
                <option value="">Select Customer</option>
                @foreach ($customers as $customer)
                  <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mt-2">
              <label>Linked Consignment <small class="text-muted">(outbound, optional)</small></label>
              <select name="consignment_id" class="form-control select2-js">
                <option value="">-- None --</option>
                @foreach($outboundConsignments as $csg)
                  <option value="{{ $csg->id }}">{{ $csg->consignment_no }} — {{ optional($csg->partner)->name }}</option>
                @endforeach
              </select>
              <small class="text-muted">Link when settling an outbound consignment sale.</small>
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (USD / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_usd" name="gold_rate_usd" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (AED / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_aed_ounce" name="gold_rate_aed_ounce" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-3 mt-2">
              <label class="text-primary">Gold Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="gold_rate_aed" name="gold_rate_aed" class="form-control" value="0" readonly>
              <small class="text-danger fw-bold">Used for calculations</small>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (USD) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_usd" name="diamond_rate_usd" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (AED) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_aed_ounce" name="diamond_rate_aed_ounce" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-3 mt-2">
              <label class="text-primary">Diamond Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="diamond_rate_aed_gram" name="diamond_rate_aed" class="form-control" value="0" readonly>
              <small class="text-danger fw-bold">Used for calculations</small>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label class="text-success fw-bold">Purchase Gold Rate (AED / Gram)</label>
              <input type="number" step="any" id="purchase_gold_rate_aed" name="purchase_gold_rate_aed" class="form-control border-success" value="0">
              <small class="text-muted">For profit % calculation</small>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label class="text-success fw-bold">Purchase Making Rate (AED / Gram)</label>
              <input type="number" step="any" id="purchase_making_rate_aed" name="purchase_making_rate_aed" class="form-control border-success" value="0">
              <small class="text-muted">For profit % calculation</small>
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
                  <small class="text-light">USB/Bluetooth scanners supported. Auto-added as new row.</small>
                </div>
                <div class="col-md-5">
                  <div id="barcode_scan_result" class="alert mb-0 py-2 px-3 d-none" role="alert" style="font-size:.9rem;"></div>
                </div>
              </div>
            </div>
          </div>

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
                    <th width="5%"  rowspan="2">Purity</th>
                    <th rowspan="2">Base Gross Wt</th>
                    <th rowspan="2">Gross Wt<br><small class="text-muted fw-normal">(Calc.)</small></th>
                    <th rowspan="2">Purity Wt</th>
                    <th rowspan="2">995</th>
                    <th colspan="2" class="text-center">Making</th>
                    <th width="5%" rowspan="2">Material</th>
                    <th rowspan="2">Material Val</th>
                    <th rowspan="2">MC</th>
                    <th rowspan="2">VAT %</th>
                    <th rowspan="2">VAT Amt</th>
                    <th rowspan="2">Item Total</th>
                    <th rowspan="2" class="text-warning fw-bold" style="min-width:85px;">
                      Target<br>Profit %
                      <br><small class="fw-normal text-muted" style="font-size:.65rem;">type→sets MC</small>
                    </th>
                    <th width="5%" rowspan="2">Action</th>
                  </tr>
                  <tr>
                    <th>Rate</th>
                    <th>Value</th>
                  </tr>
                </thead>
                <tbody id="SaleTable">
                  <tr class="item-row" data-item-index="0">
                    <td>
                      <div class="product-wrapper">
                        <input type="text" name="items[0][item_name]" class="form-control item-name-input" placeholder="Product Name">
                        <input type="hidden" name="items[0][barcode_number]" value="">
                        <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
                      </div>
                    </td>
                    <td><input type="text" name="items[0][item_description]" class="form-control" required></td>
                    <td>
                      <select name="items[0][purity]" class="form-control purity">
                        @foreach($purities as $p)
                          <option value="{{ $p->value }}">{{ $p->label }}</option>
                        @endforeach
                      </select>
                    </td>
                    <td><input type="number" name="items[0][base_gross_weight]" step="any" value="0" class="form-control base-gross-weight"></td>
                    <td><input type="number" name="items[0][gross_weight]" step="any" value="0" class="form-control gross-weight bg-light text-primary fw-bold" readonly></td>
                    <td><input type="number" name="items[0][purity_weight]" step="any" value="0" class="form-control purity-weight" readonly></td>
                    <td><input type="number" name="items[0][col_995]" step="any" value="0" class="form-control col-995" readonly></td>
                    <td><input type="number" name="items[0][making_rate]" step="any" value="0" class="form-control making-rate"></td>
                    <td><input type="number" name="items[0][making_value]" step="any" value="0" class="form-control making-value" readonly></td>
                    <td>
                      <select name="items[0][material_type]" class="form-control material-type">
                        <option value="gold">Gold</option>
                        <option value="diamond">Diamond</option>
                      </select>
                    </td>
                    <td><input type="number" name="items[0][material_value]" step="any" value="0" class="form-control material-value" readonly></td>
                    <td><input type="number" name="items[0][taxable_amount]" step="any" value="0" class="form-control taxable-amount" readonly></td>
                    <td><input type="number" name="items[0][vat_percent]" class="form-control vat-percent" step="any" value="0"></td>
                    <td><input type="number" name="items[0][vat_amount]" step="any" value="0" class="form-control vat-amount" readonly></td>
                    <td><input type="number" name="items[0][item_total]" step="any" value="0" class="form-control item-total" readonly></td>
                    <td>
                      <input type="number" step="0.01" class="form-control target-profit-pct fw-bold text-center"
                             placeholder="%" title="Type target profit % → auto-sets making rate"
                             style="min-width:75px;font-size:.9rem;border-color:#ffc107;">
                    </td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                      <button type="button" class="btn btn-sm btn-primary toggle-parts"><i class="fas fa-wrench"></i></button>
                    </td>
                  </tr>
                  <tr class="parts-row" style="display:none;background:#efefef">
                    <td colspan="18">
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
                  </tr>
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
              <label>Total Stone Qty</label>
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

            {{-- ===== APPLY PROFIT TO ALL ===== --}}
            <div class="col-md-3 mt-3">
              <label class="fw-bold text-warning">Apply Profit % to All Items</label>
              <div class="input-group">
                <input type="number" step="0.01" id="bulk_profit_pct" class="form-control border-warning" placeholder="e.g. 25" min="0">
                <button type="button" class="btn btn-warning fw-bold" id="apply_profit_all">
                  <i class="fas fa-magic"></i> Apply to All
                </button>
              </div>
              <small class="text-muted">Sets making rate on every row to achieve this profit %</small>
            </div>

            <div class="col-md-2 mt-3">
              <label class="fw-bold text-danger">
                Invoice VAT %
                <small class="text-muted d-block fw-normal" style="font-size:.75rem">B2C: on total | B2B: per-item</small>
              </label>
              <input type="number" step="0.01" min="0" max="100" name="invoice_vat_percent" id="invoice_vat_percent"
                     class="form-control border-danger" value="0" placeholder="e.g. 5">
            </div>
            <div class="col-md-2 mt-3">
              <label>Invoice VAT Amt (AED)</label>
              <input type="text" id="invoice_vat_amount_display" class="form-control bg-light fw-bold text-danger" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label class="fw-bold text-success">Grand Total (AED)</label>
              <input type="text" id="grand_total_display" class="form-control fw-bold text-success border-success" readonly style="font-size:1.05rem;">
            </div>
          </div>

          {{-- ===================== PAYMENT METHOD ===================== --}}
          <div class="row mb-3">
            <div class="col-md-2">
              <label class="fw-bold">Payment Method</label>
              <select name="payment_method" id="payment_method" class="form-control" required>
                <option value="">Select Payment Method</option>
                <option value="credit">Credit</option>
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="material+making cost">Material + Making Cost</option>
              </select>
            </div>
            <div class="col-md-2">
              <label>Payment Term</label>
              <input type="text" name="payment_term" class="form-control">
            </div>
          </div>

          <div class="row mb-3 d-none" id="received_by_box">
            <div class="col-md-2">
              <label>Received By</label>
              <input type="text" name="received_by" class="form-control">
            </div>
          </div>

          <div class="row mb-3 d-none" id="cash_fields">
            <div class="col-md-2">
              <label>Amount Received (Cash)</label>
              <input type="number" step="any" name="cash_amount_paid" class="form-control" placeholder="Leave blank = full payment">
              <small class="text-muted">Remaining goes to customer receivable</small>
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
              <small class="text-muted">Leave blank = full. Remaining goes to customer receivable.</small>
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
              <input type="number" step="any" name="material_value_input" class="form-control">
            </div>
            <div class="col-md-2">
              <label>Making Charges (Calculated)</label>
              <input type="number" step="any" name="making_charges" id="making_charges_display" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Making Charges Collected Now</label>
              <input type="number" step="any" name="making_amount_paid" id="making_amount_paid" class="form-control" value="0">
              <small class="text-muted">0 = fully receivable from customer</small>
            </div>
            <div class="col-md-2">
              <label>Cash/Bank Account (for collection)</label>
              <select name="making_payment_account" class="form-control select2-js">
                <option value="">None (fully receivable)</option>
                <option value="cash">Cash in Hand</option>
                @foreach ($banks as $bank)
                  <option value="bank_{{ $bank->id }}">{{ $bank->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2 mt-3">
              <label>Material Given By</label>
              <input type="text" name="material_given_by" class="form-control text-danger fw-bold">
            </div>
            <div class="col-md-2 mt-3">
              <label>Material Received By</label>
              <input type="text" name="material_received_by" class="form-control text-danger fw-bold">
            </div>
          </div>

          <div class="row mb-3 d-none" id="bank_transfer_fields">
            <div class="col-md-2">
              <label>Transfer From Bank</label>
              <select name="transfer_from_bank" class="form-control select2-js">
                <option value="">Select Bank</option>
                @foreach ($banks as $bank)
                  <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Customer Bank Name</label>
              <input type="text" name="transfer_to_bank" class="form-control" placeholder="e.g. Emirates NBD">
            </div>
            <div class="col-md-2">
              <label>Account Title</label>
              <input type="text" name="account_title" class="form-control">
            </div>
            <div class="col-md-2">
              <label>Account Number</label>
              <input type="text" name="account_no" class="form-control">
            </div>
            <div class="col-md-2">
              <label>Transaction Ref No</label>
              <input type="text" name="transaction_id" class="form-control">
            </div>
            <div class="col-md-2">
              <label>Transfer Date</label>
              <input type="date" name="transfer_date" class="form-control">
            </div>
            <div class="col-md-2 mt-2">
              <label>Transfer Amount</label>
              <input type="number" step="any" name="transfer_amount" class="form-control">
              <small class="text-muted">Leave blank = full. Remaining goes to customer receivable.</small>
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
    const products           = @json($products);
    const TROY_OUNCE_TO_GRAM = 31.1035;
    const BARCODE_SCAN_URL   = '{{ route("sale.scan_barcode") }}';

    $(document).on('click', '.toggle-parts', function() {
        $(this).closest('tr').next('.parts-row').fadeToggle(200);
    });

    // ===== CURRENCY =====
    $('#currency').on('change', function() {
        const isUSD = $(this).val() === 'USD';
        if (isUSD) { $('#exchangeRateBox').show(); $('#exchange_rate').attr('required', true); if (!$('#exchange_rate').val()) $('#exchange_rate').val('3.6725'); }
        else        { $('#exchangeRateBox').hide(); $('#exchange_rate').removeAttr('required').val(''); }
        calculateTotals();
    });
    $('#exchange_rate').on('input', calculateTotals);
    $('#invoice_vat_percent').on('input', calculateTotals);
    $('.select2-js').select2({ width: '100%' });

    // ===== PROFIT HELPERS =====
    function calcProfitPct(sale, cost) {
        if (!cost || cost === 0) return { pct: null, label: 'N/A' };
        const pct = ((sale - cost) / cost) * 100;
        return { pct, label: pct.toFixed(2) + '%' };
    }
    function colourProfitInput(el, pct) {
        el.css('color', pct === null ? '#6c757d' : pct >= 0 ? '#198754' : '#dc3545');
    }

    // ===== APPLY TARGET PROFIT % → BACK-CALCULATES making_rate =====
    // Formula:
    //   cost          = (purchase_gold_rate × purity_weight) + (base_gross × purchase_making_rate)
    //   desired_total = cost × (1 + targetPct / 100)
    //   making_value  = (desired_total - material_value - parts_total) / (1 + vat% / 100)
    //   making_rate   = making_value / gross_weight
    function applyTargetProfit(row, targetPct) {
        const gross        = parseFloat(row.find('.gross-weight').val())      || 0;
        const baseGross    = parseFloat(row.find('.base-gross-weight').val()) || 0;
        const purityWeight = parseFloat(row.find('.purity-weight').val())     || 0;
        const vatPercent   = parseFloat(row.find('.vat-percent').val())       || 0;
        const materialVal  = parseFloat(row.find('.material-value').val())    || 0;
        const purGoldR     = parseFloat($('#purchase_gold_rate_aed').val())   || 0;
        const purMkR       = parseFloat($('#purchase_making_rate_aed').val()) || 0;

        let partsTotal = 0;
        row.next('.parts-row').find('.part-item-row').each(function() {
            partsTotal += parseFloat($(this).find('.part-total').val()) || 0;
        });

        const costTotal = (purGoldR * purityWeight) + (baseGross * purMkR);
        if (costTotal <= 0 || gross <= 0) return;

        const desiredTotal  = costTotal * (1 + targetPct / 100);
        const residual      = desiredTotal - materialVal - partsTotal;
        const makingValue   = residual / (1 + vatPercent / 100);
        const newMakingRate = Math.max(0, makingValue / gross);

        row.find('.making-rate').val(newMakingRate.toFixed(4));
        calculateRow(row);
        calculateTotals();
    }

    // ===== APPLY TO ALL BUTTON =====
    $('#apply_profit_all').on('click', function() {
        const targetPct = parseFloat($('#bulk_profit_pct').val());
        if (isNaN(targetPct)) { alert('Please enter a profit % first.'); return; }
        $('#SaleTable tr.item-row').each(function() {
            const row = $(this);
            row.find('.target-profit-pct').val(targetPct.toFixed(2));
            applyTargetProfit(row, targetPct);
        });
    });

    // ===== PER-ROW TARGET PROFIT INPUT =====
    $(document).on('input', '.target-profit-pct', function() {
        const row = $(this).closest('tr.item-row');
        const targetPct = parseFloat($(this).val());
        if (!isNaN(targetPct)) applyTargetProfit(row, targetPct);
    });

    // ===== BARCODE SCANNER =====
    function showScanResult(msg, type) {
        const el = $('#barcode_scan_result');
        el.removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-' + type).html(msg);
        clearTimeout(window._scanTimer);
        window._scanTimer = setTimeout(() => el.addClass('d-none'), 5000);
    }

    function handleBarcodeScan() {
        const barcode = $('#barcode_scan_input').val().trim();
        if (!barcode) { $('#barcode_scan_input').focus(); return; }
        $('#barcode_scan_btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.ajax({
            url: BARCODE_SCAN_URL, method: 'GET', data: { barcode },
            success: function(data) {
                if (!data.success) { showScanResult('<i class="fas fa-times-circle"></i> ' + data.message, 'danger'); return; }
                let duplicate = false;
                $('#SaleTable tr.item-row').each(function() { if ($(this).find('input[name*="[barcode_number]"]').val() === barcode) { duplicate = true; return false; } });
                if (duplicate) { showScanResult('<i class="fas fa-exclamation-triangle"></i> <strong>' + barcode + '</strong> already on this invoice.', 'warning'); return; }
                const firstRow = $('#SaleTable tr.item-row').first();
                if ($('#SaleTable tr.item-row').length === 1 && !firstRow.find('.item-name-input').val() && !firstRow.find('input[name*="[barcode_number]"]').val()) {
                    firstRow.next('.parts-row').remove(); firstRow.remove();
                }
                addNewRow();
                const newRow = $('#SaleTable tr.item-row').last();
                newRow.find('.item-name-input').val(data.item_name);
                newRow.find('input[name*="[barcode_number]"]').val(data.barcode_number);
                newRow.find('input[name*="[item_description]"]').val(data.item_description);
                const pur = parseFloat(data.purity);
                let nearestOpt = null, minDiff = Infinity;
                newRow.find('.purity option').each(function() { const diff = Math.abs(parseFloat($(this).val()) - pur); if (diff < minDiff) { minDiff = diff; nearestOpt = $(this).val(); } });
                if (nearestOpt) newRow.find('.purity').val(nearestOpt);
                newRow.find('.base-gross-weight').val((parseFloat(data.gross_weight) || 0).toFixed(3));
                newRow.find('.making-rate').val(data.making_rate || 0);
                newRow.find('.material-type').val(data.material_type || 'gold');
                newRow.find('.vat-percent').val(data.vat_percent || 0);
                if (data.parts && data.parts.length > 0) {
                    const partsRow = newRow.next('.parts-row');
                    partsRow.show();
                    data.parts.forEach((part, j) => partsRow.find('.parts-table tbody').append(buildPartRowHtml(newRow.data('item-index'), j, part)));
                }
                recalcItemGrossWeight(newRow);
                showScanResult('<i class="fas fa-check-circle"></i> Added: <strong>' + data.item_name + '</strong>', 'success');
                newRow.addClass('table-warning'); setTimeout(() => newRow.removeClass('table-warning'), 2000);
            },
            error: function(xhr) { showScanResult('<i class="fas fa-times-circle"></i> ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Search failed.'), 'danger'); },
            complete: function() { $('#barcode_scan_btn').prop('disabled', false).html('<i class="fas fa-search"></i> Search'); $('#barcode_scan_input').val('').focus(); }
        });
    }
    $('#barcode_scan_input').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); handleBarcodeScan(); } });
    $('#barcode_scan_btn').on('click', handleBarcodeScan);

    // ===== SEARCH BY NAME =====
    const NAME_SEARCH_URL = '{{ route("sale.search_by_name") }}';
    let nameSearchTimer   = null;

    function addRowFromResult(data) {
        const firstRow = $('#SaleTable tr.item-row').first();
        if ($('#SaleTable tr.item-row').length === 1
            && !firstRow.find('.item-name-input').val()
            && !firstRow.find('input[name*="[barcode_number]"]').val()) {
            firstRow.next('.parts-row').remove();
            firstRow.remove();
        }

        addNewRow();
        const newRow = $('#SaleTable tr.item-row').last();

        newRow.find('.item-name-input').val(data.item_name || '');
        newRow.find('input[name*="[barcode_number]"]').val(data.barcode_number || '');
        newRow.find('input[name*="[item_description]"]').val(data.item_description || '');

        // Match purity to nearest option
        const pur = parseFloat(data.purity);
        let nearestOpt = null, minDiff = Infinity;
        newRow.find('.purity option').each(function() {
            const diff = Math.abs(parseFloat($(this).val()) - pur);
            if (diff < minDiff) { minDiff = diff; nearestOpt = $(this).val(); }
        });
        if (nearestOpt) newRow.find('.purity').val(nearestOpt);

        newRow.find('.base-gross-weight').val((parseFloat(data.gross_weight) || 0).toFixed(3));
        newRow.find('.making-rate').val(data.making_rate || 0);
        newRow.find('.material-type').val(data.material_type || 'gold');
        newRow.find('.vat-percent').val(data.vat_percent || 0);

        if (data.parts && data.parts.length > 0) {
            const partsRow  = newRow.next('.parts-row');
            const partsBody = partsRow.find('.parts-table tbody');
            partsRow.show();
            data.parts.forEach((part, j) => {
                partsBody.append(buildPartRowHtml(newRow.data('item-index'), j, part));
            });
        }

        recalcItemGrossWeight(newRow);
        showScanResult('<i class="fas fa-check-circle"></i> Added: <strong>'
            + (data.item_name || 'Item') + '</strong>', 'success');
        newRow.addClass('table-warning');
        setTimeout(() => newRow.removeClass('table-warning'), 2000);
    }

    function renderNameResults(results) {
        const box = $('#name_search_results');
        if (!results.length) {
            box.html('<div style="padding:10px 14px;font-size:.82rem;color:#6c757d;">No results found.</div>').show();
            return;
        }

        const sourceColors = { sale: '#0d6efd', purchase: '#198754', consignment: '#6f42c1' };
        const sourceLabels = { sale: 'Sale', purchase: 'Purchase', consignment: 'Consignment' };

        let html = '';
        results.forEach(function(r) {
            const color = sourceColors[r.source] || '#6c757d';
            const label = sourceLabels[r.source] || r.source;
            const wt    = r.gross_weight ? parseFloat(r.gross_weight).toFixed(3) + 'g' : '';
            const bc    = r.barcode_number
                ? `<span style="font-family:monospace;font-size:.75rem;color:#2563eb;">${r.barcode_number}</span>`
                : '';
            const csg   = r.consignment_no
                ? `<span style="font-size:.72rem;color:#6c757d;"> · ${r.consignment_no}</span>`
                : '';

            html += `
            <div class="name-result-row"
                style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f1f3f5;font-size:.82rem;"
                onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background=''"
                data-idx="${html.length}">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <span style="font-weight:600;">${r.item_name || '—'}</span>
                  ${csg}
                  <span style="margin-left:6px;font-size:.7rem;padding:1px 7px;border-radius:20px;
                              background:${color}22;color:${color};font-weight:500;">${label}</span>
                </div>
                <div style="text-align:right;flex-shrink:0;margin-left:8px;">
                  ${bc}
                  ${wt ? `<div style="font-size:.72rem;color:#6c757d;">${wt}</div>` : ''}
                </div>
              </div>
              ${r.item_description ? `<div style="font-size:.74rem;color:#6c757d;margin-top:2px;">${r.item_description}</div>` : ''}
            </div>`;
        });

        box.html(html).show();

        // Attach click handlers after rendering
        box.find('.name-result-row').each(function(i) {
            $(this).on('click', function() {
                addRowFromResult(results[i]);
                $('#name_search_input').val('');
                box.hide();
            });
        });
    }

    $('#name_search_input').on('input', function() {
        const q = $(this).val().trim();
        clearTimeout(nameSearchTimer);
        if (q.length < 2) { $('#name_search_results').hide(); return; }

        nameSearchTimer = setTimeout(function() {
            $.ajax({
                url: NAME_SEARCH_URL, method: 'GET', data: { q },
                success: function(data) {
                    if (data.success) renderNameResults(data.results);
                },
                error: function() { $('#name_search_results').hide(); }
            });
        }, 280); // 280ms debounce
    });

    // Hide dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#name_search_input, #name_search_results').length) {
            $('#name_search_results').hide();
        }
    });

    // Keyboard navigation in dropdown
    $('#name_search_input').on('keydown', function(e) {
        const rows = $('#name_search_results .name-result-row');
        if (!rows.length) return;
        const active = rows.filter('.active');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!active.length) { rows.first().addClass('active').css('background','#f0f4ff'); }
            else {
                active.removeClass('active').css('background','');
                const next = active.next('.name-result-row');
                (next.length ? next : rows.first()).addClass('active').css('background','#f0f4ff');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!active.length) { rows.last().addClass('active').css('background','#f0f4ff'); }
            else {
                active.removeClass('active').css('background','');
                const prev = active.prev('.name-result-row');
                (prev.length ? prev : rows.last()).addClass('active').css('background','#f0f4ff');
            }
        } else if (e.key === 'Enter' && active.length) {
            e.preventDefault();
            active.trigger('click');
        } else if (e.key === 'Escape') {
            $('#name_search_results').hide();
        }
    });
    // ===== ROW INDEX MANAGEMENT =====
    function updateRowIndexes() {
        $('#SaleTable tr.item-row').each(function(i) {
            $(this).attr('data-item-index', i);
            $(this).find('input, select').each(function() { const n = $(this).attr('name'); if (n) $(this).attr('name', n.replace(/items\[\d+\]/, `items[${i}]`)); });
            $(this).next('.parts-row').find('.part-item-row').each(function(j) {
                $(this).attr('data-part-index', j);
                $(this).find('input, select').each(function() { const n = $(this).attr('name'); if (n) $(this).attr('name', n.replace(/items\[\d+\]/, `items[${i}]`).replace(/parts\[\d+\]/, `parts[${j}]`)); });
            });
        });
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

    window.addNewRow = function() {
        const nextIndex = $('#SaleTable tr.item-row').length;
        const purityOptions = `@foreach($purities as $p)<option value="{{ $p->value }}">{{ $p->label }}</option>@endforeach`;
        const rowHtml = `
        <tr class="item-row" data-item-index="${nextIndex}">
            <td><div class="product-wrapper">
                <input type="text" name="items[${nextIndex}][item_name]" class="form-control item-name-input" placeholder="Product Name">
                <input type="hidden" name="items[${nextIndex}][barcode_number]" value="">
                <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
            </div></td>
            <td><input type="text" name="items[${nextIndex}][item_description]" class="form-control" required></td>
            <td><select name="items[${nextIndex}][purity]" class="form-control purity">${purityOptions}</select></td>
            <td><input type="number" name="items[${nextIndex}][base_gross_weight]" step="any" value="0" class="form-control base-gross-weight"></td>
            <td><input type="number" name="items[${nextIndex}][gross_weight]" step="any" value="0" class="form-control gross-weight bg-light text-primary fw-bold" readonly></td>
            <td><input type="number" name="items[${nextIndex}][purity_weight]" step="any" value="0" class="form-control purity-weight" readonly></td>
            <td><input type="number" name="items[${nextIndex}][col_995]" step="any" value="0" class="form-control col-995" readonly></td>
            <td><input type="number" name="items[${nextIndex}][making_rate]" step="any" value="0" class="form-control making-rate"></td>
            <td><input type="number" name="items[${nextIndex}][making_value]" step="any" value="0" class="form-control making-value" readonly></td>
            <td><select name="items[${nextIndex}][material_type]" class="form-control material-type">
                <option value="gold">Gold</option><option value="diamond">Diamond</option>
            </select></td>
            <td><input type="number" name="items[${nextIndex}][material_value]" step="any" value="0" class="form-control material-value" readonly></td>
            <td><input type="number" name="items[${nextIndex}][taxable_amount]" step="any" value="0" class="form-control taxable-amount" readonly></td>
            <td><input type="number" name="items[${nextIndex}][vat_percent]" class="form-control vat-percent" step="any" value="0"></td>
            <td><input type="number" name="items[${nextIndex}][vat_amount]" step="any" value="0" class="form-control vat-amount" readonly></td>
            <td><input type="number" name="items[${nextIndex}][item_total]" step="any" value="0" class="form-control item-total" readonly></td>
            <td><input type="number" step="0.01" class="form-control target-profit-pct fw-bold text-center" placeholder="%" style="min-width:75px;font-size:.9rem;border-color:#ffc107;"></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                <button type="button" class="btn btn-sm btn-primary toggle-parts"><i class="fas fa-wrench"></i></button>
            </td>
        </tr>
        <tr class="parts-row" style="display:none;background:#efefef">
            <td colspan="18"><div class="parts-wrapper">
                <table class="table table-sm table-bordered parts-table">
                    <thead><tr><th>Part</th><th>Description</th><th>Diamond Ct.</th><th>Rate</th><th>Stone Ct.</th><th>Stone Rate</th><th>Total</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary add-part">+ Add Part</button>
            </div></td>
        </tr>`;
        $('#SaleTable').append(rowHtml);
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

    // ===== PRODUCT TOGGLE =====
    $(document).on('click', '.toggle-product, .revert-to-name', function() {
        const isReverting = $(this).hasClass('revert-to-name');
        const wrapper  = $(this).closest('.product-wrapper');
        const isPart   = wrapper.closest('tr').hasClass('part-item-row');
        const itemIdx  = isPart ? wrapper.closest('.parts-row').prev('.item-row').data('item-index') : wrapper.closest('.item-row').data('item-index');
        const namePath = isPart ? `items[${itemIdx}][parts][${wrapper.closest('.part-item-row').data('part-index')}]` : `items[${itemIdx}]`;
        if (isReverting) {
            wrapper.html(`<input type="text" name="${namePath}[item_name]" class="form-control item-name-input" placeholder="Name"><input type="hidden" name="${namePath}[barcode_number]" value=""><button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>`);
        } else {
            wrapper.html(`
                <select name="${namePath}[product_id]" class="form-control select2-js product-select mb-2">
                    <option value="">Select Product</option>
                    ${products.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                </select>
                <select name="${namePath}[variation_id]" class="form-control select2-js variation-select"><option value="">Select Variation</option></select>
                <button type="button" class="btn btn-link p-0 revert-to-name mt-1">Write Name</button>
            `).find('.select2-js').select2({ width: '100%' });
        }
    });
    $(document).on('change', '.product-select', function() {
        const variationSelect = $(this).closest('tr').find('.variation-select');
        variationSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        const productId = $(this).val();
        if (!productId) { variationSelect.html('<option value="">Select Variation</option>').prop('disabled', false); return; }
        fetch(`/product/${productId}/variations`).then(r => r.json()).then(data => {
            variationSelect.prop('disabled', false);
            let opts = '<option value="">No variation</option>';
            if (data.success && data.variation.length) { opts = '<option value="">Select Variation</option>'; data.variation.forEach(v => { opts += `<option value="${v.id}">${v.sku}</option>`; }); }
            variationSelect.html(opts);
        });
    });

    // ===== CALCULATIONS =====
    $(document).on('input', '.base-gross-weight', function() {
        recalcItemGrossWeight($(this).closest('tr.item-row'));
    });

    // When making_rate is typed directly → recalculate row then update profit display (reverse direction)
    $(document).on('input', '.making-rate', function() {
        const row = $(this).closest('tr.item-row');
        calculateRow(row);
        calculateTotals();
        updateProfitDisplay(row);
    });

    // When purity/vat/material-type/gold-rate changes → recalculate all rows + profit displays
    $(document).on('input change', '.purity, .vat-percent, .material-type, #gold_rate_aed, #diamond_rate_aed_gram, #purchase_gold_rate_aed, #purchase_making_rate_aed', function() {
        $('#SaleTable tr.item-row').each(function() {
            calculateRow($(this));
            updateProfitDisplay($(this));
        });
        calculateTotals();
    });

    function recalcItemGrossWeight(itemRow) {
        if (!itemRow || !itemRow.length) return;
        const baseGross = parseFloat(itemRow.find('.base-gross-weight').val()) || 0;
        let dCTS = 0, sCTS = 0;
        itemRow.next('.parts-row').find('.part-item-row').each(function() {
            dCTS += parseFloat($(this).find('.part-qty').val())       || 0;
            sCTS += parseFloat($(this).find('.part-stone-qty').val()) || 0;
        });
        itemRow.find('.gross-weight').val((baseGross + (dCTS / 5) + (sCTS / 5)).toFixed(4));
        calculateRow(itemRow);
        updateProfitDisplay(itemRow);
        calculateTotals();
    }

    function calculateRow(row) {
        const gross      = parseFloat(row.find('.gross-weight').val())    || 0;
        const purity     = parseFloat(row.find('.purity').val())          || 0;
        const makingRate = parseFloat(row.find('.making-rate').val())     || 0;
        const vatPercent = parseFloat(row.find('.vat-percent').val())     || 0;
        const matType    = row.find('.material-type').val();
        const saleRate   = matType === 'gold'
            ? (parseFloat($('#gold_rate_aed').val())          || 0)
            : (parseFloat($('#diamond_rate_aed_gram').val())  || 0);

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

        row.find('.purity-weight').val(purityWeight.toFixed(4));
        row.find('.col-995').val(col995.toFixed(4));
        row.find('.making-value').val(makingValue.toFixed(4));
        row.find('.material-value').val(materialValue.toFixed(4));
        row.find('.taxable-amount').val(taxableAmount.toFixed(4));
        row.find('.vat-amount').val(vatAmount.toFixed(4));
        row.find('.item-total').val(itemTotal.toFixed(4));
    }

    // Update the target-profit-pct field to show ACTUAL current profit %
    // (only when user is not actively typing in that field)
    function updateProfitDisplay(row) {
        const itemTotal  = parseFloat(row.find('.item-total').val())        || 0;
        const baseGross  = parseFloat(row.find('.base-gross-weight').val()) || 0;
        const purWt      = parseFloat(row.find('.purity-weight').val())     || 0;
        const purGoldR   = parseFloat($('#purchase_gold_rate_aed').val())   || 0;
        const purMkR     = parseFloat($('#purchase_making_rate_aed').val()) || 0;
        const costTotal  = (purGoldR * purWt) + (baseGross * purMkR);
        const el         = row.find('.target-profit-pct');
        const { pct }    = calcProfitPct(itemTotal, costTotal);
        if (document.activeElement !== el[0]) {
            el.val(pct !== null ? pct.toFixed(2) : '');
        }
        colourProfitInput(el, pct);
    }

    function calculateTotals() {
        let sumGoldGross = 0, sumPurityWeight = 0, sum995 = 0, sumMaking = 0;
        let sumMaterial = 0, sumVAT = 0, sumItemTotal = 0, totalCost = 0;
        let totalDiamondCTS = 0, totalStoneQty = 0, totalDiamondVal = 0, totalStoneVal = 0;
        const purGoldR = parseFloat($('#purchase_gold_rate_aed').val()) || 0;
        const purMkR   = parseFloat($('#purchase_making_rate_aed').val()) || 0;

        $('#SaleTable tr.item-row').each(function() {
            const row      = $(this);
            const matType  = row.find('.material-type').val();
            const grossVal = parseFloat(row.find('.gross-weight').val())      || 0;
            const baseGros = parseFloat(row.find('.base-gross-weight').val()) || 0;
            const purWt    = parseFloat(row.find('.purity-weight').val())     || 0;

            sumPurityWeight += purWt;
            sum995          += parseFloat(row.find('.col-995').val())          || 0;
            sumMaking       += parseFloat(row.find('.making-value').val())     || 0;
            sumMaterial     += parseFloat(row.find('.material-value').val())   || 0;
            sumVAT          += parseFloat(row.find('.vat-amount').val())       || 0;
            sumItemTotal    += parseFloat(row.find('.item-total').val())        || 0;
            totalCost       += (purGoldR * purWt) + (baseGros * purMkR);
            if (matType === 'gold') sumGoldGross += grossVal;

            row.next('.parts-row').find('.part-item-row').each(function() {
                const dQ = parseFloat($(this).find('.part-qty').val())        || 0;
                const dR = parseFloat($(this).find('.part-rate').val())       || 0;
                const sQ = parseFloat($(this).find('.part-stone-qty').val())  || 0;
                const sR = parseFloat($(this).find('.part-stone-rate').val()) || 0;
                totalDiamondCTS += dQ; totalStoneQty += sQ;
                totalDiamondVal += dQ * dR; totalStoneVal += sQ * sR;
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
        $('#converted_total').val(currency === 'USD' ? (sumItemTotal * exRate).toFixed(4) : sumItemTotal.toFixed(4));

        // Overall profit %
        const oi = $('#overall_profit_pct');
        const { pct, label } = calcProfitPct(sumItemTotal, totalCost);
        oi.val(label); colourProfitInput(oi, pct);

        // Invoice-level VAT
        const invVatPct = parseFloat($('#invoice_vat_percent').val()) || 0;
        const netAed    = currency === 'USD' ? (sumItemTotal * exRate) : sumItemTotal;
        const invVatAmt = Math.round(netAed * invVatPct / 100 * 100) / 100;
        $('#invoice_vat_amount_display').val(invVatAmt.toFixed(2));
        $('#grand_total_display').val((Math.round((netAed + invVatAmt) * 100) / 100).toFixed(2));

        if ($('#payment_method').val() === 'material+making cost') {
            $('input[name="material_weight"]').val(sum995.toFixed(4));
            $('input[name="material_purity"]').val(sumPurityWeight.toFixed(4));
            $('input[name="material_value_input"]').val(sumMaterial.toFixed(4));
            $('#making_charges_display').val(sumMaking.toFixed(4));
        }
    }

    // ===== RATE CONVERSION =====
    $(document).on('input', '#gold_rate_usd, #gold_rate_aed_ounce, #diamond_rate_usd, #diamond_rate_aed_ounce, #exchange_rate', function() {
        const id     = $(this).attr('id');
        const exRate = parseFloat($('#exchange_rate').val()) || 3.6725;
        if (id === 'gold_rate_usd' || id === 'exchange_rate')
            $('#gold_rate_aed_ounce').val(((parseFloat($('#gold_rate_usd').val()) || 0) * exRate).toFixed(4));
        $('#gold_rate_aed').val(((parseFloat($('#gold_rate_aed_ounce').val()) || 0) / TROY_OUNCE_TO_GRAM).toFixed(4));
        if (id === 'diamond_rate_usd' || id === 'exchange_rate')
            $('#diamond_rate_aed_ounce').val(((parseFloat($('#diamond_rate_usd').val()) || 0) * exRate).toFixed(4));
        $('#diamond_rate_aed_gram').val(((parseFloat($('#diamond_rate_aed_ounce').val()) || 0) / TROY_OUNCE_TO_GRAM).toFixed(4));
        $('#SaleTable tr.item-row').each(function() { calculateRow($(this)); updateProfitDisplay($(this)); });
        calculateTotals();
    });

    // ===== PAYMENT METHOD =====
    $('#payment_method').on('change', function() {
        const val = $(this).val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields, #cash_fields').addClass('d-none');
        if (val === 'cheque')                    $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')                 $('#received_by_box, #cash_fields').removeClass('d-none');
        else if (val === 'bank_transfer')        $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost') $('#material_fields').removeClass('d-none');
        calculateTotals();
    });

    // ===== PARTS CALCULATION =====
    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate', function() {
        const row = $(this).closest('tr');
        row.find('.part-total').val((
            (parseFloat(row.find('.part-qty').val())        || 0) * (parseFloat(row.find('.part-rate').val())       || 0) +
            (parseFloat(row.find('.part-stone-qty').val())  || 0) * (parseFloat(row.find('.part-stone-rate').val()) || 0)
        ).toFixed(4));
        recalcItemGrossWeight(row.closest('.parts-row').prev('.item-row'));
    });

    document.querySelector('form').addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    });
});
</script>
@endsection