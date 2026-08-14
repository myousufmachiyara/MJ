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
          <button type="button" class="btn btn-danger" onclick="resubmitWithConfirm()">Delete anyway and update invoice</button>
          <a href="{{ route('sale_invoices.edit', $saleInvoice->id) }}" class="btn btn-secondary ms-2">Go back and keep items</a>
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
              <small class="text-muted">Changing type re-assigns the invoice number to the correct series (SAL- / SAL-TAX-)</small>
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

            <div class="col-md-3 mt-2">
              <label>Linked Consignment <small class="text-muted">(outbound, optional)</small></label>
              <select name="consignment_id" id="consignment_id" class="form-control select2-js">
                <option value="">-- None --</option>
                @foreach($outboundConsignments as $csg)
                  <option value="{{ $csg->id }}" {{ $saleInvoice->consignment_id == $csg->id ? 'selected' : '' }}>
                    {{ $csg->consignment_no }} — {{ optional($csg->partner)->name }}
                  </option>
                @endforeach
              </select>
              <button type="button" id="filter_consignment_items_btn" class="btn btn-sm btn-outline-primary mt-1 d-none">
                <i class="fas fa-filter"></i> Select Sold Items
              </button>
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (USD / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_usd" name="gold_rate_usd" class="form-control" value="{{ $saleInvoice->gold_rate_usd ?? 0 }}">
            </div>

            <div class="col-12 col-md-2">
              <label>Gold Rate (AED / <b>Ounce</b>)</label>
              <input type="number" step="any" id="gold_rate_aed_ounce" name="gold_rate_aed_ounce" class="form-control" value="{{ round($goldAedOunce, 4) }}">
            </div>

            <div class="col-12 col-md-3 mt-2">
              <label class="text-primary">Gold Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="gold_rate_aed" name="gold_rate_aed" class="form-control" value="{{ $saleInvoice->gold_rate_aed ?? 0 }}" readonly>
              <small class="text-danger fw-bold">Used for calculations</small>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (USD) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_usd" name="diamond_rate_usd" class="form-control" value="{{ $saleInvoice->diamond_rate_usd ?? 0 }}">
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (AED) / Ounce</label>
              <input type="number" step="any" id="diamond_rate_aed_ounce" name="diamond_rate_aed_ounce" class="form-control" value="{{ round($diamondAedOunce, 4) }}">
            </div>

            <div class="col-12 col-md-3 mt-2">
              <label class="text-primary">Diamond Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="diamond_rate_aed_gram" name="diamond_rate_aed" class="form-control" value="{{ $saleInvoice->diamond_rate_aed ?? 0 }}" readonly>
              <small class="text-danger fw-bold">Used for calculations</small>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label class="text-success fw-bold">Purchase Gold Rate (AED / Gram)</label>
              <input type="number" step="any" id="purchase_gold_rate_aed" name="purchase_gold_rate_aed" class="form-control border-success" value="{{ $saleInvoice->purchase_gold_rate_aed ?? 0 }}">
              <small class="text-muted">For profit % calculation</small>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label class="text-success fw-bold">Purchase Making Rate (AED / Gram)</label>
              <input type="number" step="any" id="purchase_making_rate_aed" name="purchase_making_rate_aed" class="form-control border-success" value="{{ $saleInvoice->purchase_making_rate_aed ?? 0 }}">
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

          </div>

          {{-- =================== BARCODE / NAME SCANNER =================== --}}
          <div class="card mb-3 border-primary shadow-sm">
            <div class="card-body py-2 bg-primary bg-opacity-10">
              <div class="row align-items-end g-3">
                <div class="col-auto d-flex align-items-center">
                  <i class="fas fa-barcode fa-2x text-light me-2"></i>
                  <strong class="text-light">Scan / Search</strong>
                </div>

                <div class="col-md-4">
                  <label class="text-light small mb-1">Barcode</label>
                  <div class="input-group">
                    <input type="text" id="barcode_scan_input" class="form-control" placeholder="Scan barcode or type &amp; press Enter…" autocomplete="off">
                    <button type="button" class="btn btn-primary fw-bold" id="barcode_scan_btn">
                      <i class="fas fa-search"></i>
                    </button>
                  </div>
                  <small class="text-light">USB/Bluetooth scanners supported.</small>
                </div>

                {{-- NEW: dedicated "Search by Item Name" box — same as create blade --}}
                <div class="col-md-4" style="position:relative;">
                  <label class="text-light small mb-1">Search by Item Name</label>
                  <input type="text" id="name_search_input" class="form-control"
                        placeholder="Type item name…" autocomplete="off">
                  <div id="name_search_results"
                      style="display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;
                              background:#fff;border:1px solid #dee2e6;border-radius:6px;
                              box-shadow:0 4px 12px rgba(0,0,0,.15);max-height:320px;overflow-y:auto;">
                  </div>
                  <small class="text-light">Searches purchase, sale history &amp; consignment.</small>
                </div>

                <div class="col-md-3">
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
                  <tr><th>Rate</th><th>Value</th></tr>
                </thead>
                <tbody id="SaleTable"></tbody>
              </table>
              <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">Add Item</button>
            </div>
          </section>

          {{-- ===================== SUMMARY ===================== --}}
          <div class="row mt-5 mb-5">
            <div class="col-md-2"><label>Gold Gross Wt</label><input type="text" id="sum_gold_gross_weight" class="form-control text-primary fw-bold" readonly></div>
            <div class="col-md-2"><label>Total Purity Wt</label><input type="text" id="sum_purity_weight" class="form-control text-success fw-bold" readonly></div>
            <div class="col-md-2"><label>Diamond CTS</label><input type="text" id="sum_diamond_cts" class="form-control text-warning fw-bold" readonly></div>
            <div class="col-md-2"><label>Total Stone Qty</label><input type="text" id="sum_stone_qty" class="form-control text-info fw-bold" readonly></div>
            <div class="col-md-2"><label>Total 995</label><input type="text" id="sum_995" class="form-control" readonly></div>
            <div class="col-md-2"><label>Total Making</label><input type="text" id="sum_making_value" class="form-control" readonly></div>
            <div class="col-md-2 mt-3"><label>Total Material Val.</label><input type="text" id="sum_material_value" class="form-control" readonly></div>
            <div class="col-md-2 mt-3"><label>Diamond Parts Val.</label><input type="text" id="sum_diamond_value" class="form-control text-warning fw-bold" readonly></div>
            <div class="col-md-2 mt-3"><label>Stone Parts Val.</label><input type="text" id="sum_stone_value" class="form-control text-info fw-bold" readonly></div>
            <div class="col-md-2 mt-3"><label>Total VAT</label><input type="text" id="sum_vat_amount" class="form-control" readonly></div>
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
              </label>
              <input type="number" step="0.01" min="0" max="100" name="invoice_vat_percent" id="invoice_vat_percent"
                     class="form-control border-danger"
                     value="{{ old('invoice_vat_percent', $saleInvoice->invoice_vat_percent ?? 0) }}" placeholder="e.g. 5">
              <small class="text-muted d-block fw-normal" style="font-size:.75rem">B2C: on total | B2B: per-item</small>
            </div>
            <div class="col-md-2 mt-3">
              <label>Invoice VAT Amt (AED)</label>
              <input type="text" id="invoice_vat_amount_display" class="form-control bg-light fw-bold text-danger" readonly value="{{ number_format($saleInvoice->invoice_vat_amount ?? 0, 2) }}">
            </div>
            <div class="col-md-2 mt-3">
              <label class="fw-bold text-success">Grand Total (AED)</label>
              <input type="text" id="grand_total_display" class="form-control fw-bold text-success border-success" readonly
                     style="font-size:1.05rem;" value="{{ number_format($saleInvoice->grand_total ?? $saleInvoice->net_amount_aed, 2) }}">
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

          <div class="row mb-3 d-none" id="cash_fields">
            <div class="col-md-2">
              <label>Amount Received (Cash)</label>
              <input type="number" step="any" name="cash_amount_paid" class="form-control"
                     value="{{ $saleInvoice->cash_amount_paid }}" placeholder="Leave blank = full payment">
              <small class="text-muted">Remaining goes to customer receivable</small>
            </div>
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
              <small class="text-muted">Leave blank = full. Remaining goes to customer receivable.</small>
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
              <label>Making Charges (Calculated)</label>
              <input type="number" step="any" name="making_charges" id="making_charges_display" class="form-control" value="{{ $saleInvoice->making_charges }}" readonly>
            </div>
            <div class="col-md-2">
              <label>Making Charges Collected Now</label>
              <input type="number" step="any" name="making_amount_paid" id="making_amount_paid" class="form-control"
                     value="{{ old('making_amount_paid', $saleInvoice->making_amount_collected ?? 0) }}">
              <small class="text-muted">0 = fully receivable from customer</small>
            </div>
            <div class="col-md-2">
              <label>Cash/Bank Account (for collection)</label>
              <select name="making_payment_account" class="form-control select2-js">
                <option value="">None (fully receivable)</option>
                @foreach ($banks as $bank)
                  <option value="bank_{{ $bank->id }}">{{ $bank->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2 mt-3">
              <label>Material Given By</label>
              <input type="text" name="material_given_by" class="form-control text-danger fw-bold" value="{{ $saleInvoice->material_given_by }}">
            </div>
            <div class="col-md-2 mt-3">
              <label>Material Received By</label>
              <input type="text" name="material_received_by" class="form-control text-danger fw-bold" value="{{ $saleInvoice->material_received_by }}">
            </div>
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

        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary me-2"><i class="fas fa-times"></i> Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Invoice</button>
        </footer>
      </section>
    </form>
    {{-- ===================== CONSIGNMENT ITEMS MODAL ===================== --}}
    <div class="modal fade" id="consignmentItemsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              Select Sold Items <span id="modal_consignment_no" class="text-primary"></span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="consignment_items_loading" class="text-center text-muted py-4">
              <i class="fas fa-spinner fa-spin"></i> Loading items…
            </div>
            <div id="consignment_items_empty" class="text-center text-muted py-4 d-none">
              <i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>
              No pending (in-stock) items found for this consignment.
            </div>
            <table class="table table-sm table-bordered d-none" id="consignment_items_table">
              <thead class="table-light">
                <tr>
                  <th width="4%"><input type="checkbox" id="csg_select_all"></th>
                  <th>Barcode</th>
                  <th>Item Name</th>
                  <th>Description</th>
                  <th class="text-center">Purity</th>
                  <th class="text-center">Gross Wt</th>
                  <th class="text-center">Making Rate</th>
                  <th class="text-center">Material</th>
                  <th class="text-center">Agreed Val</th>
                </tr>
              </thead>
              <tbody id="consignment_items_tbody"></tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="csg_select_btn">
              <i class="fas fa-check"></i> Select &amp; Add to Invoice
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
    const products           = @json($products);
    const existingItems      = @json($itemsData);
    const TROY_OUNCE_TO_GRAM = 31.1035;
    const BARCODE_SCAN_URL   = '{{ route("sale.scan_barcode") }}';

    $(document).on('click', '.toggle-parts', function() {
        $(this).closest('tr').next('.parts-row').fadeToggle(200);
    });

    // ===== CURRENCY =====
    function initCurrencyBox() {
        const isUSD = $('#currency').val() === 'USD';
        $('#exchangeRateBox').toggle(isUSD);
        if (isUSD) $('#exchange_rate').attr('required', true);
        else        $('#exchange_rate').removeAttr('required');
    }
    initCurrencyBox();
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
            error: function(xhr) { showScanResult('<i class="fas fa-times-circle"></i> ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Lookup failed.'), 'danger'); },
            complete: function() { $('#barcode_scan_btn').prop('disabled', false).html('<i class="fas fa-search"></i> Search'); $('#barcode_scan_input').val('').focus(); }
        });
    }
    $('#barcode_scan_input').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); handleBarcodeScan(); } });
    $('#barcode_scan_btn').on('click', handleBarcodeScan);

    // ===== SEARCH BY ITEM NAME — inline autocomplete on the item name field =====
    const NAME_SEARCH_URL = '{{ route("sale.search_by_name") }}';
    let nameSearchTimer   = null;

    // ===== SEARCH BY ITEM NAME — dedicated search box (same as create blade) =====
    // This is separate from the inline per-row autocomplete below: it always
    // ADDS A NEW ROW from the picked result, matching create.blade.php's UX.
    function addRowFromResult(data) {
        addNewRow();
        const newRow = $('#SaleTable tr.item-row').last();

        newRow.find('.item-name-input').val(data.item_name || '');
        newRow.find('input[name*="[barcode_number]"]').val(data.barcode_number || '');
        newRow.find('input[name*="[item_description]"]').val(data.item_description || '');

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

    function renderDedicatedNameResults(results) {
      const box = $('#name_search_results');
      if (!results.length) {
          box.html('<div style="padding:10px 14px;font-size:.82rem;color:#6c757d;">No results found.</div>').show();
          return;
      }

      const sourceColors = { sale: '#0d6efd', purchase: '#198754', consignment: '#6f42c1' };
      const sourceLabels = { sale: 'Sale', purchase: 'Purchase', consignment: 'Consignment' };

      let html = '';
      results.forEach(function(r, i) {
          const color = sourceColors[r.source] || '#6c757d';
          const label = sourceLabels[r.source] || r.source;
          const wt    = r.gross_weight ? parseFloat(r.gross_weight).toFixed(3) + 'g' : '';
          const bc    = r.barcode_number
              ? `<span style="font-family:monospace;font-size:.75rem;color:#2563eb;">${r.barcode_number}</span>`
              : '';
          const csg   = r.consignment_no
              ? `<span style="font-size:.72rem;color:#6c757d;"> · ${r.consignment_no}</span>`
              : '';
          const purityBadge = r.material_type
              ? `<span style="margin-left:4px;font-size:.68rem;padding:1px 6px;border-radius:20px;background:#f1f3f5;color:#495057;">${r.material_type}${r.purity ? ' · ' + r.purity : ''}</span>`
              : '';
          const partyLine = (r.party_name || r.invoice_no || r.invoice_date)
              ? `<small class="text-muted d-block" style="font-size:.7rem;">${[r.party_name, r.invoice_no, r.invoice_date].filter(Boolean).join(' · ')}</small>`
              : '';

          html += `
          <div class="dedicated-name-result-row" data-idx="${i}"
              style="padding:9px 14px;border-bottom:1px solid #f1f3f5;font-size:.82rem;">
            <div class="dedicated-name-result-select text-dark" style="cursor:pointer;"
                  onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background=''">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <span style="font-weight:600;">${r.item_name || '—'}</span>
                  ${csg}
                  <span style="margin-left:6px;font-size:.7rem;padding:1px 7px;border-radius:20px;
                              background:${color}22;color:${color};font-weight:500;">${label}</span>
                  ${purityBadge}
                </div>
                <div style="text-align:right;flex-shrink:0;margin-left:8px;">
                  ${bc}
                  ${wt ? `<div style="font-size:.72rem;color:#6c757d;">${wt}</div>` : ''}
                </div>
              </div>
              ${r.item_description ? `<div style="font-size:.74rem;color:#6c757d;margin-top:2px;">${r.item_description}</div>` : ''}
              ${partyLine}
            </div>
            <div class="d-flex justify-content-end mt-1">
              <button type="button" class="btn btn-link btn-sm p-0 dedicated-expand-toggle" data-idx="${i}" style="font-size:.72rem;">Details ▾</button>
            </div>
            <div class="result-detail d-none mt-2 p-2 text-dark" id="dedicated_result_detail_${i}" style="background:#f8f9fa;border-radius:6px;font-size:.75rem;"></div>
          </div>`;
      });

      box.html(html).show();

      box.find('.dedicated-name-result-select').each(function(i) {
          $(this).on('click', function() {
              addRowFromResult(results[i]);
              $('#name_search_input').val('');
              box.hide();
          });
      });

      box.find('.dedicated-expand-toggle').on('click', function(e) {
          e.stopPropagation();
          const idx    = $(this).data('idx');
          const r      = results[idx];
          const $panel = $('#dedicated_result_detail_' + idx);

          if (!$panel.hasClass('d-none')) { $panel.addClass('d-none'); return; }

          if (!$panel.data('built')) {
              let partsHtml = '';
              if (r.parts && r.parts.length) {
                  partsHtml = '<div class="mt-1"><strong>Parts:</strong><ul class="mb-0 ps-3">' +
                      r.parts.map(p => `<li>${p.item_name || 'Part'} — Ct: ${p.qty || 0}, Rate: ${p.rate || 0}, Stone: ${p.stone_qty || 0}@${p.stone_rate || 0}, Total: ${p.total || 0}</li>`).join('') +
                      '</ul></div>';
              }
              $panel.html(`
                  <div><strong>Making Rate:</strong> ${parseFloat(r.making_rate || 0).toFixed(2)}</div>
                  <div><strong>Material Value:</strong> ${parseFloat(r.material_value || 0).toFixed(2)}</div>
                  <div><strong>VAT %:</strong> ${r.vat_percent || 0}</div>
                  ${partsHtml}
                  <div class="result-img mt-1"></div>
              `);
              $panel.data('built', true);

              if (r.product_id) {
                  $.ajax({
                      url: '{{ url("/product") }}/' + r.product_id + '/image',
                      method: 'GET',
                      success: function(data) {
                          if (data.image_url) {
                              $panel.find('.result-img').html(`<img src="${data.image_url}" style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">`);
                          }
                      }
                  });
              }
          }

          box.find('.result-detail').not($panel).addClass('d-none');
          $panel.removeClass('d-none');
      });
    }

    let dedicatedNameSearchTimer = null;
    $('#name_search_input').on('input', function() {
        const q = $(this).val().trim();
        clearTimeout(dedicatedNameSearchTimer);
        if (q.length < 2) { $('#name_search_results').hide(); return; }

        dedicatedNameSearchTimer = setTimeout(function() {
            $.ajax({
                url: NAME_SEARCH_URL, method: 'GET', data: { q },
                success: function(data) {
                    if (data.success) renderDedicatedNameResults(data.results);
                },
                error: function() { $('#name_search_results').hide(); }
            });
        }, 280);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#name_search_input, #name_search_results').length) {
            $('#name_search_results').hide();
        }
    });

    $('#name_search_input').on('keydown', function(e) {
        const rows = $('#name_search_results .dedicated-name-result-row');
        if (!rows.length) return;
        const active = rows.filter('.active');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!active.length) { rows.first().addClass('active').css('background','#f0f4ff'); }
            else {
                active.removeClass('active').css('background','');
                const next = active.next('.dedicated-name-result-row');
                (next.length ? next : rows.first()).addClass('active').css('background','#f0f4ff');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!active.length) { rows.last().addClass('active').css('background','#f0f4ff'); }
            else {
                active.removeClass('active').css('background','');
                const prev = active.prev('.dedicated-name-result-row');
                (prev.length ? prev : rows.last()).addClass('active').css('background','#f0f4ff');
            }
        } else if (e.key === 'Enter' && active.length) {
            e.preventDefault();
            active.trigger('click');
        } else if (e.key === 'Escape') {
            $('#name_search_results').hide();
        }
    });
    
    function renderNameDropdown(inputEl, results) {
        $('.name-search-dropdown').remove();
        if (!results.length) return;

        const sourceColors = { sale: '#0d6efd', purchase: '#198754', consignment: '#6f42c1' };
        const sourceLabels = { sale: 'Sale', purchase: 'Purchase', consignment: 'Consignment' };

        let html = '<div class="name-search-dropdown" style="position:absolute;z-index:9999;'
                + 'background:#fff;border:1px solid #dee2e6;border-radius:6px;'
                + 'box-shadow:0 4px 12px rgba(0,0,0,.15);max-height:280px;overflow-y:auto;'
                + 'min-width:320px;">';

        results.forEach(function(r, i) {
            const color = sourceColors[r.source] || '#6c757d';
            const label = sourceLabels[r.source] || r.source;
            const wt    = r.gross_weight ? parseFloat(r.gross_weight).toFixed(3) + 'g' : '';
            const bc    = r.barcode_number
                ? `<span style="font-family:monospace;font-size:.72rem;color:#2563eb;">${r.barcode_number}</span> `
                : '';

            html += `<div class="name-search-result-item" data-idx="${i}"
                          style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f3f5;font-size:.82rem;"
                          onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background=''">
                      <div class="d-flex justify-content-between align-items-center">
                        <span style="font-weight:600;">${r.item_name || '—'}</span>
                        <span style="margin-left:8px;font-size:.7rem;padding:1px 7px;border-radius:20px;
                                      background:${color}22;color:${color};font-weight:500;white-space:nowrap;">${label}</span>
                      </div>
                      <div style="font-size:.74rem;color:#6c757d;margin-top:2px;">
                        ${bc}${r.item_description || ''}${wt ? ' · ' + wt : ''}
                      </div>
                    </div>`;
        });

        html += '</div>';

        const $dropdown = $(html);
        inputEl.closest('.product-wrapper').css('position', 'relative').append($dropdown);

        $dropdown.find('.name-search-result-item').each(function(i) {
            $(this).on('click', function() {
                const r   = results[i];
                const row = inputEl.closest('tr.item-row');

                inputEl.val(r.item_name || '');
                row.find('input[name*="[barcode_number]"]').val(r.barcode_number || '');
                row.find('input[name*="[item_description]"]').val(r.item_description || '');

                const pur = parseFloat(r.purity);
                let nearestOpt = null, minDiff = Infinity;
                row.find('.purity option').each(function() {
                    const diff = Math.abs(parseFloat($(this).val()) - pur);
                    if (diff < minDiff) { minDiff = diff; nearestOpt = $(this).val(); }
                });
                if (nearestOpt) row.find('.purity').val(nearestOpt);

                row.find('.base-gross-weight').val((parseFloat(r.gross_weight) || 0).toFixed(3));
                row.find('.making-rate').val(r.making_rate || 0);
                row.find('.material-type').val(r.material_type || 'gold');
                row.find('.vat-percent').val(r.vat_percent || 0);

                if (r.parts && r.parts.length > 0) {
                    const partsRow  = row.next('.parts-row');
                    const partsBody = partsRow.find('.parts-table tbody');
                    partsBody.empty();
                    partsRow.show();
                    r.parts.forEach((part, j) => {
                        partsBody.append(buildPartRowHtml(row.data('item-index'), j, part));
                    });
                }

                recalcItemGrossWeight(row);
                $dropdown.remove();
            });
        });
    }

    $(document).on('input', '.item-name-input', function() {
        const inputEl = $(this);
        const q       = inputEl.val().trim();
        clearTimeout(nameSearchTimer);
        $('.name-search-dropdown').remove();
        if (q.length < 2) return;
        nameSearchTimer = setTimeout(function() {
            $.ajax({
                url: NAME_SEARCH_URL, method: 'GET', data: { q },
                success: function(data) {
                    if (data.success && data.results.length) {
                        renderNameDropdown(inputEl, data.results);
                    }
                }
            });
        }, 280);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.product-wrapper').length) {
            $('.name-search-dropdown').remove();
        }
    });

    $(document).on('keydown', '.item-name-input', function(e) {
        const dropdown = $(this).closest('.product-wrapper').find('.name-search-dropdown');
        if (!dropdown.length) return;
        const items  = dropdown.find('.name-search-result-item');
        const active = items.filter('.kbd-active');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!active.length) { items.first().addClass('kbd-active').css('background','#f0f4ff'); }
            else { active.removeClass('kbd-active').css('background',''); (active.next('.name-search-result-item').length ? active.next('.name-search-result-item') : items.first()).addClass('kbd-active').css('background','#f0f4ff'); }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!active.length) { items.last().addClass('kbd-active').css('background','#f0f4ff'); }
            else { active.removeClass('kbd-active').css('background',''); (active.prev('.name-search-result-item').length ? active.prev('.name-search-result-item') : items.last()).addClass('kbd-active').css('background','#f0f4ff'); }
        } else if (e.key === 'Enter' && active.length) {
            e.preventDefault(); active.trigger('click');
        } else if (e.key === 'Escape') {
            dropdown.remove();
        }
    });
    // ===== CONSIGNMENT ITEMS MODAL (outbound → sale settlement) =====
    const CONSIGNMENT_ITEMS_URL_BASE = '{{ url("/consignments") }}';
    let csgModalItems = [];

    function showModal(id) {
        const el = document.getElementById(id);
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        } else if ($.fn.modal) {
            $(el).modal('show');
        }
    }
    function hideModal(id) {
        const el = document.getElementById(id);
        if (window.bootstrap && bootstrap.Modal) {
            const inst = bootstrap.Modal.getInstance(el);
            if (inst) inst.hide();
        } else if ($.fn.modal) {
            $(el).modal('hide');
        }
    }

    function toggleFilterBtn() {
        const val = $('#consignment_id').val();
        $('#filter_consignment_items_btn').toggleClass('d-none', !val);
    }
    $('#consignment_id').on('change', toggleFilterBtn);
    toggleFilterBtn(); // show button immediately if a consignment is already linked (edit mode)

    $('#filter_consignment_items_btn').on('click', function() {
        const consignmentId = $('#consignment_id').val();
        if (!consignmentId) return;

        $('#consignment_items_table').addClass('d-none');
        $('#consignment_items_empty').addClass('d-none');
        $('#consignment_items_loading').removeClass('d-none');
        $('#consignment_items_tbody').empty();
        $('#modal_consignment_no').text('');
        csgModalItems = [];

        showModal('consignmentItemsModal');

        $.ajax({
            url: CONSIGNMENT_ITEMS_URL_BASE + '/' + consignmentId + '/items-for-sale',
            method: 'GET',
            success: function(data) {
                $('#consignment_items_loading').addClass('d-none');

                if (!data.success) {
                    alert(data.message || 'Failed to load consignment items.');
                    hideModal('consignmentItemsModal');
                    return;
                }

                $('#modal_consignment_no').text(data.consignment_no ? '(' + data.consignment_no + ')' : '');

                if (!data.items.length) {
                    $('#consignment_items_empty').removeClass('d-none');
                    return;
                }

                csgModalItems = data.items;
                const tbody = $('#consignment_items_tbody');

                data.items.forEach(function(item, idx) {
                    tbody.append(`
                        <tr>
                            <td><input type="checkbox" class="csg-item-check" data-idx="${idx}"></td>
                            <td><code style="font-size:.75rem">${item.source_barcode || '-'}</code></td>
                            <td>${item.item_name || '-'}</td>
                            <td class="small text-muted">${item.item_description || '-'}</td>
                            <td class="text-center">${parseFloat(item.purity || 0).toFixed(3)}</td>
                            <td class="text-center">${parseFloat(item.gross_weight || 0).toFixed(3)}g</td>
                            <td class="text-center">${parseFloat(item.making_rate || 0).toFixed(2)}</td>
                            <td class="text-center">${(item.material_type || '').toUpperCase()}</td>
                            <td class="text-center fw-bold">${parseFloat(item.agreed_value || 0).toFixed(2)}</td>
                        </tr>
                    `);
                });

                $('#consignment_items_table').removeClass('d-none');
            },
            error: function(xhr) {
                $('#consignment_items_loading').addClass('d-none');
                alert(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to load consignment items.');
                hideModal('consignmentItemsModal');
            }
        });
    });

    $('#csg_select_all').on('change', function() {
        $('.csg-item-check').prop('checked', $(this).is(':checked'));
    });

    $('#csg_select_btn').on('click', function() {
        const selectedIdx = [];
        $('.csg-item-check:checked').each(function() {
            selectedIdx.push(parseInt($(this).data('idx'), 10));
        });

        if (!selectedIdx.length) {
            alert('Please select at least one item.');
            return;
        }

        selectedIdx.forEach(function(idx) {
            const item = csgModalItems[idx];
            if (item) addItemFromConsignment(item);
        });

        hideModal('consignmentItemsModal');
    });

    // NOTE: this edit page defines addNewRow() (defined later, below) which appends
    // a blank row via buildItemRowHtml(). We call it first, then fill the fields —
    // identical pattern to how the barcode scanner adds rows on this same page.
    function addItemFromConsignment(item) {
        addNewRow();
        const newRow = $('#SaleTable tr.item-row').last();

        newRow.find('.item-name-input').val(item.item_name || '');

        // IMPORTANT: source_barcode is the original MJ-/MJT- barcode. This is what
        // ConsignmentController::settleItems() matches against to flip this
        // consignment item to 'sold' once the invoice is saved/updated.
        newRow.find('input[name*="[barcode_number]"]').val(item.source_barcode || '');

        newRow.find('input[name*="[item_description]"]').val(item.item_description || '');

        const pur = parseFloat(item.purity);
        let nearestOpt = null, minDiff = Infinity;
        newRow.find('.purity option').each(function() {
            const diff = Math.abs(parseFloat($(this).val()) - pur);
            if (diff < minDiff) { minDiff = diff; nearestOpt = $(this).val(); }
        });
        if (nearestOpt) newRow.find('.purity').val(nearestOpt);

        newRow.find('.base-gross-weight').val((parseFloat(item.gross_weight) || 0).toFixed(3));
        newRow.find('.making-rate').val(item.making_rate || 0);
        newRow.find('.material-type').val(item.material_type || 'gold');
        newRow.find('.vat-percent').val(item.vat_percent || 0);

        if (item.parts && item.parts.length > 0) {
            const partsRow = newRow.next('.parts-row');
            partsRow.show();
            item.parts.forEach((part, j) => {
                partsRow.find('.parts-table tbody').append(
                    buildPartRowHtml(newRow.data('item-index'), j, part)
                );
            });
        }

        recalcItemGrossWeight(newRow);

        newRow.addClass('table-warning');
        setTimeout(() => newRow.removeClass('table-warning'), 2000);
    }
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

    function buildItemRowHtml(index, data) {
        data = data || {};
        const purity  = data.purity        || '0.92';
        const matType = data.material_type || 'gold';
        const purityOptions = `@foreach($purities as $p)<option value="{{ $p->value }}" ${purity == {{ $p->value }} ? 'selected' : ''}>{{ $p->label }}</option>@endforeach`;

        return `
        <tr class="item-row" data-item-index="${index}">
            <td><div class="product-wrapper">
                <input type="text" name="items[${index}][item_name]" class="form-control item-name-input" placeholder="Product Name" value="${data.item_name || ''}">
                <input type="hidden" name="items[${index}][barcode_number]" value="${data.barcode_number || ''}">
                <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
            </div></td>
            <td><input type="text" name="items[${index}][item_description]" class="form-control" value="${data.item_description || ''}" required></td>
            <td><select name="items[${index}][purity]" class="form-control purity">${purityOptions}</select></td>
            <td><input type="number" name="items[${index}][base_gross_weight]" step="any" value="${data.gross_weight || 0}" class="form-control base-gross-weight"></td>
            <td><input type="number" name="items[${index}][gross_weight]" step="any" value="${data.gross_weight || 0}" class="form-control gross-weight bg-light text-primary fw-bold" readonly></td>
            <td><input type="number" name="items[${index}][purity_weight]" step="any" value="${data.purity_weight || 0}" class="form-control purity-weight" readonly></td>
            <td><input type="number" name="items[${index}][col_995]" step="any" value="${data.col_995 || 0}" class="form-control col-995" readonly></td>
            <td><input type="number" name="items[${index}][making_rate]" step="any" value="${data.making_rate || 0}" class="form-control making-rate"></td>
            <td><input type="number" name="items[${index}][making_value]" step="any" value="${data.making_value || 0}" class="form-control making-value" readonly></td>
            <td><select name="items[${index}][material_type]" class="form-control material-type">
                <option value="gold"    ${matType === 'gold'    ? 'selected' : ''}>Gold</option>
                <option value="diamond" ${matType === 'diamond' ? 'selected' : ''}>Diamond</option>
            </select></td>
            <td><input type="number" name="items[${index}][material_value]" step="any" value="${data.material_value || 0}" class="form-control material-value" readonly></td>
            <td><input type="number" name="items[${index}][taxable_amount]" step="any" value="${data.taxable_amount || 0}" class="form-control taxable-amount" readonly></td>
            <td><input type="number" name="items[${index}][vat_percent]" class="form-control vat-percent" step="any" value="${data.vat_percent || 0}"></td>
            <td><input type="number" name="items[${index}][vat_amount]" step="any" value="${data.vat_amount || 0}" class="form-control vat-amount" readonly></td>
            <td><input type="number" name="items[${index}][item_total]" step="any" value="${data.item_total || 0}" class="form-control item-total" readonly></td>
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
            <td colspan="18"><div class="parts-wrapper">
                <table class="table table-sm table-bordered parts-table">
                    <thead><tr><th>Part</th><th>Description</th><th>Diamond Ct.</th><th>Rate</th><th>Stone Ct.</th><th>Stone Rate</th><th>Total</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary add-part">+ Add Part</button>
            </div></td>
        </tr>`;
    }

    // ===== LOAD EXISTING ITEMS =====
    existingItems.forEach(function(itemData, i) {
        $('#SaleTable').append(buildItemRowHtml(i, itemData));
        const itemRow  = $('#SaleTable tr.item-row').last();
        const partsRow = itemRow.next('.parts-row');
        itemRow.find('.base-gross-weight').val(parseFloat(itemData.gross_weight) || 0);
        if (itemData.parts && itemData.parts.length > 0) {
            partsRow.show();
            itemData.parts.forEach((p, j) => partsRow.find('.parts-table tbody').append(buildPartRowHtml(i, j, p)));
        }
        recalcItemGrossWeight(itemRow);
    });

    calculateTotals();

    // ===== PAYMENT METHOD — init on load =====
    function initPaymentFields() {
        const val = $('#payment_method').val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields, #cash_fields').addClass('d-none');
        if (val === 'cheque')                    $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')                 $('#received_by_box, #cash_fields').removeClass('d-none');
        else if (val === 'bank_transfer')        $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost') $('#material_fields').removeClass('d-none');
    }
    initPaymentFields();

    $('#payment_method').on('change', function() {
        const val = $(this).val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields, #cash_fields').addClass('d-none');
        if (val === 'cheque')                    $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')                 $('#received_by_box, #cash_fields').removeClass('d-none');
        else if (val === 'bank_transfer')        $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost') $('#material_fields').removeClass('d-none');
        calculateTotals();
    });

    // ===== ADD / REMOVE ROWS =====
    window.addNewRow = function() {
        $('#SaleTable').append(buildItemRowHtml($('#SaleTable tr.item-row').length, {}));
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

    // ===== CALCULATIONS (ALL EXISTING FORMULAS UNCHANGED) =====
    $(document).on('input', '.base-gross-weight', function() {
        recalcItemGrossWeight($(this).closest('tr.item-row'));
    });

    // making-rate typed directly → recalc row then reflect actual profit % back into the field
    $(document).on('input', '.making-rate', function() {
        const row = $(this).closest('tr.item-row');
        calculateRow(row);
        calculateTotals();
        updateProfitDisplay(row);
    });

    $(document).on('input change', '.purity, .vat-percent, .material-type, #gold_rate_aed, #diamond_rate_aed_gram, #purchase_gold_rate_aed, #purchase_making_rate_aed', function() {
        $('#SaleTable tr.item-row').each(function() { calculateRow($(this)); updateProfitDisplay($(this)); });
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
            ? (parseFloat($('#gold_rate_aed').val())         || 0)
            : (parseFloat($('#diamond_rate_aed_gram').val()) || 0);

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

    function updateProfitDisplay(row) {
        const itemTotal = parseFloat(row.find('.item-total').val())        || 0;
        const baseGross = parseFloat(row.find('.base-gross-weight').val()) || 0;
        const purWt     = parseFloat(row.find('.purity-weight').val())     || 0;
        const purGoldR  = parseFloat($('#purchase_gold_rate_aed').val())   || 0;
        const purMkR    = parseFloat($('#purchase_making_rate_aed').val()) || 0;
        const costTotal = (purGoldR * purWt) + (baseGross * purMkR);
        const el        = row.find('.target-profit-pct');
        const { pct }   = calcProfitPct(itemTotal, costTotal);
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
            sum995          += parseFloat(row.find('.col-995').val())         || 0;
            sumMaking       += parseFloat(row.find('.making-value').val())    || 0;
            sumMaterial     += parseFloat(row.find('.material-value').val())  || 0;
            sumVAT          += parseFloat(row.find('.vat-amount').val())      || 0;
            sumItemTotal    += parseFloat(row.find('.item-total').val())       || 0;
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

        const oi = $('#overall_profit_pct');
        const { pct, label } = calcProfitPct(sumItemTotal, totalCost);
        oi.val(label); colourProfitInput(oi, pct);

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

    // ===== PARTS CALCULATION =====
    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate', function() {
        const row = $(this).closest('tr');
        row.find('.part-total').val((
            (parseFloat(row.find('.part-qty').val())        || 0) * (parseFloat(row.find('.part-rate').val())       || 0) +
            (parseFloat(row.find('.part-stone-qty').val())  || 0) * (parseFloat(row.find('.part-stone-rate').val()) || 0)
        ).toFixed(4));
        recalcItemGrossWeight(row.closest('.parts-row').prev('.item-row'));
    });

}); // end $(document).ready

function resubmitWithConfirm() {
    const form = document.getElementById('main-form');
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'confirm_delete_printed'; input.value = '1';
    form.appendChild(input); form.submit();
}

document.getElementById('main-form').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
});
</script>
@endsection