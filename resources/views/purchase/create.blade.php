@extends('layouts.app')

@section('title', 'Purchase | New Invoice')

@section('content')

{{-- ═══════════════════════════════════════════════════════════════════════
     EXCEL IMPORT — MODE CHOICE MODAL
     Shown only when the item table already has data at the moment a file
     is picked. Lets the user choose to keep existing rows and append the
     Excel rows, or wipe the table and replace it entirely with the Excel
     rows. Self-contained (no Bootstrap JS dependency) so it works
     regardless of which Bootstrap version layouts.app loads.
     Mirrors the same feature on the Edit Purchase Invoice page.
     ═══════════════════════════════════════════════════════════════════════ --}}
<div id="excelImportModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:8px; max-width:480px; width:92%; padding:24px; box-shadow:0 10px 40px rgba(0,0,0,0.25);">
    <h5 class="mb-2"><i class="fas fa-file-excel text-success"></i> Import Excel — Existing Items Found</h5>
    <p class="text-muted mb-3">
      The item table already has data. How should the imported Excel data be applied?
    </p>
    <div class="d-grid gap-2">
      <button type="button" id="excelImportReplace" class="btn btn-danger w-100 mb-2">
        <i class="fas fa-trash-alt"></i> Remove existing items &amp; replace with Excel
      </button>
      <button type="button" id="excelImportAppend" class="btn btn-success w-100 mb-2">
        <i class="fas fa-plus"></i> Keep existing items &amp; add Excel items
      </button>
      <button type="button" id="excelImportCancel" class="btn btn-secondary w-100">
        Cancel
      </button>
    </div>
  </div>
</div>

{{-- IMPORT REPORT MODAL (result of Import Excel / Import Pictures) --}}
<div id="importReportModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2100; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:8px; max-width:860px; width:94%; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 10px 40px rgba(0,0,0,0.25);">
    <div style="padding:16px 22px; border-bottom:1px solid #dee2e6;">
      <h5 class="mb-0" id="importReportTitle"></h5>
    </div>
    <div id="importReportBody" style="padding:16px 22px; overflow:auto; font-size:.9rem;"></div>
    <div style="padding:12px 22px; border-top:1px solid #dee2e6; text-align:right;">
      <button type="button" id="importReportClose" class="btn btn-primary">OK</button>
    </div>
  </div>
</div>

<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.store') }}" method="POST" enctype="multipart/form-data">
      @csrf

      {{-- FIX (multipart body parts limit): see edit.blade.php for the full
           explanation. collectItemsJsonForSubmit() (defined below) packs every
           items[N][...] field into this JSON field and disables the individual
           inputs so a large invoice doesn't generate more multipart parts than
           PHP's max_multipart_body_parts ini limit allows. --}}
      <input type="hidden" name="items_json" id="items_json">

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

            <div class="col-12 col-md-3 mt-2">
              <label class="text-primary">Gold Converted Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="gold_rate_aed" name="gold_rate_aed" class="form-control" value="0" readonly>
              <small class="text-danger text-bold">Used for calculations</small>
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (USD) / Ct.</label>
              <input type="number" step="any" id="diamond_rate_usd" name="diamond_rate_usd" class="form-control" value="0">
            </div>

            <div class="col-12 col-md-2 mt-2">
              <label>Diamond Rate (AED) / Ct.</label>
              <input type="number" step="any" id="diamond_rate_aed" name="diamond_rate_aed" class="form-control" value="0">
            </div>

            <div class="col-md-4 mt-2">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-4 mt-2">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control"></textarea>
            </div>
          </div>

          {{-- TABLE --}}
          <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
              <h2 class="card-title">Invoice Items</h2>
              <div class="">
                <input type="file" id="excel_import" class="d-none" accept=".xlsx, .xls, .csv">
                <button type="button" class="btn btn-success" onclick="document.getElementById('excel_import').click()">
                    <i class="fas fa-file-excel"></i> Import Excel
                </button>
                {{-- FEATURE: bulk pictures — matched to item rows by file name (Excel "Image File" column, else Cert. No, else Item Name) --}}
                <input type="file" id="bulk_images" class="d-none" accept="image/*" multiple>
                <button type="button" class="btn btn-warning" onclick="document.getElementById('bulk_images').click()"
                        title="Select many pictures at once. They are matched to items by file name (Excel column 'Image File', else Cert. No., else Item Name).">
                    <i class="fas fa-images"></i> Import Pictures
                </button>
                <span id="bulkImagesStatus" class="small text-muted ms-1"></span>
                <a href="{{ route('purchase.download_template') }}" class="btn btn-danger"><i class="fas fa-download"></i> Download Template</a>
              </div>
            </header>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th width="10%" rowspan="2">Item Name</th>
                    <th width="10%" rowspan="2">Item Description</th>
                    <th width="7%" rowspan="2">Cert. No.<br><small class="text-muted">(for label)</small></th>
                    <th width="6%" rowspan="2">Purity</th>
                    <th rowspan="2">Net Wt<br><small class="text-muted">(User Input)</small></th>
                    <th rowspan="2">Gold Gross Wt<br><small class="text-muted">(Calculated)</small></th>
                    <th rowspan="2">Purity Wt</th>
                    <th rowspan="2">995</th>
                    <th colspan="2" class="text-center">Making</th>
                    <th width="6%" rowspan="2">Material</th>
                    <th rowspan="2">Material Val</th>
                    <th rowspan="2">MC</th>
                    <th rowspan="2">VAT %</th>
                    <th rowspan="2">VAT Amt</th>
                    <th rowspan="2">Gross Total</th>
                    <th width="7%" rowspan="2">Selling Price<br><small class="text-muted">(Optional — used by POS)</small></th>
                    <th rowspan="2" width="4%">Img</th>
                    <th width="5%" rowspan="2">Action</th>
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
                        <input type="text" name="items[0][item_name]" class="form-control item-name-input" placeholder="Product Name" required>
                        <select name="items[0][category_id]" class="form-control form-control-sm category-select mt-1">
                            <option value="">Select Category</option>
                            @foreach($categories as $cat)
                              <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <select name="items[0][subcategory_id]" class="form-control form-control-sm subcategory-select mt-1">
                            <option value="">Select Subcategory</option>
                        </select>
                        <input type="text" name="items[0][tray_no]" class="form-control form-control-sm tray-no-input mt-1" placeholder="Tray No">
                        <input type="file" name="items[0][image]" class="form-control form-control-sm item-image-input mt-1" accept="image/*">
                      </div>
                    </td>
                    <td><input type="text" name="items[0][item_description]" class="form-control" required></td>
                    <td><input type="text" name="items[0][certificate_no]" class="form-control" placeholder="e.g. GIA 123456"></td>
                    <td>
                      <select name="items[0][purity]" class="form-control purity">
                        @foreach($purities as $p)
                          <option value="{{ $p->value }}">{{ $p->label }}</option>
                        @endforeach
                      </select>
                    </td>
                    <td><input type="number" name="items[0][net_weight]" step="any" value="0" class="form-control net-weight"></td>
                    <td><input type="number" name="items[0][gross_weight]" step="any" value="0" class="form-control gross-weight bg-light text-primary fw-bold" readonly></td>
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
                    {{--
                        FIX (POS Selling Price): a purchased item IS the sellable
                        product in this app (there is no separate Product Master
                        the POS pulls from) — see PurchaseInvoiceItem::selling_price
                        and PurchaseInvoiceController::createItems(). This is
                        completely independent of the costing columns to its
                        left: it is never computed from purity/making/material
                        rate, and none of those values feed into it. Optional —
                        leave blank if the selling price isn't decided yet; it
                        can be set/edited later from this same invoice's Edit
                        screen, and the POS simply won't sell an item that has
                        no selling price set.
                    --}}
                    <td><input type="number" name="items[0][selling_price]" step="any" min="0" class="form-control selling-price" placeholder="Optional"></td>
                    <td class="item-img-cell" style="text-align:center;vertical-align:middle;padding:4px;"></td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                      <button type="button" class="btn btn-sm btn-primary toggle-parts"> <i class="fas fa-wrench"></i> </button>
                    </td>
                  </tr>
                  <tr class="parts-row" style="display:none;background:#efefef">
                    <td colspan="19">
                      <div class="parts-wrapper">
                        <table class="table table-sm table-bordered parts-table">
                          <thead>
                            <tr>
                              <th>Part</th>
                              <th>Description</th>
                              <th>Carat (CTS)</th>
                              <th>Rate</th>
                              <th>Stone Ct.</th>
                              <th>Stone Rate</th>
                              <th>Cert. Charges</th>
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
          </section>

          {{-- SUMMARY --}}
          <div class="row mt-5 mb-5">
            <div class="col-md-2">
                <label>Gold Gross Wt <small class="text-muted">(Net Wt + CTS/5)</small></label>
                <input type="text" id="sum_gold_gross_weight" class="form-control text-primary fw-bold" readonly>
            </div>
            <div class="col-md-2">
                <label>Total Purity Wt</label>
                <input type="text" id="sum_purity_weight" class="form-control text-success fw-bold" readonly>
            </div>
            <div class="col-md-2">
                <label>Diamond CTS <small class="text-muted">(Total Parts)</small></label>
                <input type="text" id="sum_diamond_cts" class="form-control text-warning fw-bold" readonly>
            </div>
            <div class="col-md-2">
                <label>Total Stone Qty <small class="text-muted">(All Parts)</small></label>
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
              <label>Total VAT</label>
              <input type="text" id="sum_vat_amount" class="form-control" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Total Diamond Val. <small class="text-muted">(Parts)</small></label>
              <input type="text" id="sum_diamond_value" class="form-control text-warning fw-bold" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Total Stone Val. <small class="text-muted">(Parts)</small></label>
              <input type="text" id="sum_stone_value" class="form-control text-info fw-bold" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Net Amount <small class="text-muted">(Mat + Dia + Stone)</small></label>
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
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="material+making cost">Material + Making Cost</option>
                <option value="material">Material</option>
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
                  <label>Amount Paid (Cash)</label>
                  <input type="number" step="any" name="cash_amount_paid"
                        class="form-control" placeholder="Leave blank = full payment">
                  <small class="text-muted">Remaining goes to vendor payable</small>
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
              <small class="text-muted">Leave blank = full invoice amount. Remaining goes to vendor payable.</small>
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
                  <label>Making Charges (Calculated)</label>
                  <input type="number" step="any" name="making_charges" id="making_charges_display" class="form-control" readonly>
              </div>
              <div class="col-md-2 making-collection-field">
                  <label>Making Charges Paid Now</label>
                  <input type="number" step="any" name="making_amount_paid" id="making_amount_paid" class="form-control" value="0">
                  <small class="text-muted">0 = fully payable to vendor</small>
              </div>
              <div class="col-md-2 making-collection-field">
                  <label>Cash/Bank Account (for payment)</label>
                  <select name="making_payment_account" class="form-control select2-js">
                    <option value="">None (fully payable)</option>
                    @foreach ($banks as $account)
                        <option value="bank_{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                  </select>
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
                  <label>Vendor Bank Name</label>
                  <input type="text" name="transfer_to_bank" class="form-control" placeholder="e.g. Emirates NBD">
              </div>
              <div class="col-md-2">
                  <label>Account Title</label>
                  <input type="text" name="account_title" class="form-control" placeholder="Account Holder Name">
              </div>
              <div class="col-md-2">
                  <label>Account Number</label>
                  <input type="text" name="account_no" class="form-control" placeholder="XXXX XXXX XXXX">
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
                  <small class="text-muted">Leave blank = full invoice amount. Remaining goes to vendor payable.</small>
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
                    <input type="number" step="0.000001" name="exchange_rate" id="exchange_rate" class="form-control" placeholder="3.674">
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
    const products      = @json($products);
    const categories    = @json($categories);
    // Preloaded in full (not just fetched per-category via AJAX) so Excel
    // import can resolve a row's Category Code / Subcategory Code to real
    // IDs entirely client-side — see applyCategorySubcategoryFromCodes().
    const subcategories = @json($subcategories);

    // ================= CATEGORY / SUBCATEGORY =================
    function categoryOptionsHtml(selectedId) {
        let html = '<option value="">Select Category</option>';
        categories.forEach(c => {
            const sel = (selectedId && selectedId == c.id) ? 'selected' : '';
            html += `<option value="${c.id}" ${sel}>${c.name}</option>`;
        });
        return html;
    }

    const SUBCATEGORY_BASE = '{{ url("/get-subcategories") }}';

    function loadSubcategoryOptions(subSelect, categoryId, selectedSubcategoryId) {
        subSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        if (!categoryId) {
            subSelect.html('<option value="">Select Subcategory</option>').prop('disabled', false);
            return;
        }
        fetch(`${SUBCATEGORY_BASE}/${categoryId}`)
            .then(res => res.json())
            .then(data => {
                subSelect.prop('disabled', false);
                let options = '<option value="">Select Subcategory</option>';
                (Array.isArray(data) ? data : []).forEach(sc => {
                    const label = sc.code ? `${sc.name} (${sc.code})` : sc.name;
                    const sel   = (selectedSubcategoryId && selectedSubcategoryId == sc.id) ? 'selected' : '';
                    options += `<option value="${sc.id}" ${sel}>${label}</option>`;
                });
                subSelect.html(options);
            })
            .catch(() => {
                subSelect.html('<option value="">Select Subcategory</option>').prop('disabled', false);
            });
    }

    $(document).on('change', '.category-select', function() {
        const row = $(this).closest('tr.item-row');
        loadSubcategoryOptions(row.find('.subcategory-select'), $(this).val());
    });

    // ── Excel import: resolve Category Code / Subcategory Code text to IDs ──
    function normalizeCode(v) {
        return (v === undefined || v === null) ? '' : v.toString().trim();
    }

    // Header names are matched ignoring case, spaces and punctuation, so
    // "Category Code", "category code", "CATEGORY_CODE" and "Category  Code "
    // are all the same column. (Before, only the exact text "Category Code"
    // was recognised — any other spelling made the Category Code read as
    // EMPTY, and the category was then guessed from the Subcategory Code
    // alone, which is how a "T" (Twin) row could silently end up as "R" (Ring).)
    function normHeader(h) {
        return (h === undefined || h === null) ? '' : h.toString().toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function getCell(row, aliases) {
        const wanted = aliases.map(normHeader);
        const keys   = Object.keys(row);
        for (let i = 0; i < wanted.length; i++) {
            const hit = keys.find(k => normHeader(k) === wanted[i]);
            if (hit !== undefined && row[hit] !== undefined && row[hit] !== null && row[hit].toString().trim() !== '') {
                return row[hit];
            }
        }
        return '';
    }

    const CATEGORY_HEADERS    = ['Category Code', 'CategoryCode', 'Category', 'Cat Code'];
    const SUBCATEGORY_HEADERS = ['Subcategory Code', 'SubcategoryCode', 'Sub Category Code', 'Subcategory', 'Sub Category', 'Sub Cat Code'];
    const IMAGE_HEADERS       = ['Image File', 'Image', 'Picture', 'Picture File', 'Photo', 'Image Name'];

    // Category lookup. An exact (same-case) code wins; otherwise the code is
    // matched ignoring case; otherwise the category NAME ("Twin") is accepted.
    // Returns EVERY match so the caller can refuse to guess when it is not 1.
    function findCategoryMatches(raw) {
        const v = normalizeCode(raw);
        if (!v) return [];
        const lower = v.toLowerCase();
        let m = categories.filter(c => normalizeCode(c.code) === v);
        if (!m.length) m = categories.filter(c => normalizeCode(c.code).toLowerCase() === lower);
        if (!m.length) m = categories.filter(c => normalizeCode(c.name).toLowerCase() === lower);
        return m;
    }

    // Subcategory lookup, optionally limited to ONE category. A Subcategory
    // Code is only unique INSIDE its category (the same code can exist under
    // several categories), so when the row names a category the subcategory
    // must belong to it — same-coded rows of other categories are ignored.
    function findSubcategoryMatches(raw, categoryId) {
        const v = normalizeCode(raw);
        if (!v) return [];
        const lower = v.toLowerCase();
        const pool  = categoryId ? subcategories.filter(sc => sc.category_id == categoryId) : subcategories;
        let m = pool.filter(sc => normalizeCode(sc.code) === v);
        if (!m.length) m = pool.filter(sc => normalizeCode(sc.code).toLowerCase() === lower);
        if (!m.length) m = pool.filter(sc => normalizeCode(sc.name).toLowerCase() === lower);
        return m;
    }

    function describeCategoryOwners(subcategoryCandidates) {
        return subcategoryCandidates
            .map(sc => categories.find(c => c.id == sc.category_id))
            .filter(Boolean)
            .map(c => `${c.name} (${c.code})`)
            .join(', ');
    }

    function validCategoryCodesText() {
        return categories.map(c => `${normalizeCode(c.code) || '(no code)'} = ${c.name}`).join(', ');
    }

    function subcategoryOptionsHtmlForCategory(categoryId, selectedId) {
        let html = '<option value="">Select Subcategory</option>';
        subcategories
            .filter(sc => sc.category_id == categoryId)
            .forEach(sc => {
                const label = sc.code ? `${sc.name} (${sc.code})` : sc.name;
                const sel   = (selectedId && selectedId == sc.id) ? 'selected' : '';
                html += `<option value="${sc.id}" ${sel}>${label}</option>`;
            });
        return html;
    }

    /**
     * Resolves an imported row's Category Code / Subcategory Code to a real
     * category + subcategory selection on that item row, from the preloaded
     * `categories`/`subcategories` arrays (no AJAX).
     *
     * Rules (strict — it never guesses):
     *   1. A Category Code that is given MUST match exactly one category. If
     *      it matches none (or several), the row is left unselected and a
     *      warning lists the valid codes. It is NEVER replaced by a category
     *      worked out from the Subcategory Code (that silent fallback is what
     *      used to turn a "T" row into an "R" row).
     *   2. With a valid category, the Subcategory Code must belong to THAT
     *      category. Otherwise the subcategory is left empty and the warning
     *      says which categories do have that code.
     *   3. With NO Category Code on the row, the Subcategory Code is accepted
     *      only if exactly one category owns it; if several do, nothing is
     *      guessed and a warning asks for a Category Code.
     *
     * Returns a result object used for the post-import report:
     *   { category, subcategory, problems: [..text..] }
     */
    function applyCategorySubcategoryFromCodes(itemRow, categoryCodeRaw, subcategoryCodeRaw, itemLabel, warnings) {
        const categoryCode    = normalizeCode(categoryCodeRaw);
        const subcategoryCode = normalizeCode(subcategoryCodeRaw);
        const result = { category: null, subcategory: null, problems: [] };
        if (!categoryCode && !subcategoryCode) return result;

        const problem = function (msg) { result.problems.push(msg); warnings.push(`${itemLabel}: ${msg}`); };

        let matchedCategory = null;
        let matchedSubcategory = null;

        if (categoryCode) {
            const cats = findCategoryMatches(categoryCode);
            if (cats.length === 1) {
                matchedCategory = cats[0];
            } else if (cats.length === 0) {
                problem(`Category Code "${categoryCode}" not found — category and subcategory left empty. Valid codes: ${validCategoryCodesText()}`);
                return result;
            } else {
                problem(`Category Code "${categoryCode}" matches more than one category (${cats.map(c => c.name).join(', ')}) — category and subcategory left empty, please pick manually`);
                return result;
            }
        }

        if (subcategoryCode) {
            if (matchedCategory) {
                const subs = findSubcategoryMatches(subcategoryCode, matchedCategory.id);
                if (subs.length === 1) {
                    matchedSubcategory = subs[0];
                } else if (subs.length > 1) {
                    problem(`Subcategory Code "${subcategoryCode}" is defined more than once under "${matchedCategory.name}" — subcategory left empty, please pick manually`);
                } else {
                    const elsewhere = findSubcategoryMatches(subcategoryCode, null);
                    problem(
                        `Subcategory Code "${subcategoryCode}" is not defined under "${matchedCategory.name}" (${matchedCategory.code})` +
                        (elsewhere.length ? ` — that code exists under: ${describeCategoryOwners(elsewhere)}` : ' — no subcategory has that code') +
                        `. Category kept; please pick the Subcategory manually`
                    );
                }
            } else {
                const subs = findSubcategoryMatches(subcategoryCode, null);
                if (subs.length === 1) {
                    matchedSubcategory = subs[0];
                    matchedCategory    = categories.find(c => c.id == matchedSubcategory.category_id) || null;
                } else if (subs.length === 0) {
                    problem(`Subcategory Code "${subcategoryCode}" not found`);
                } else {
                    problem(
                        `Subcategory Code "${subcategoryCode}" is used by more than one category (${describeCategoryOwners(subs)}) and no Category Code was given ` +
                        `on this row, so it can't be resolved automatically — add a Category Code and re-import, or pick both manually`
                    );
                }
            }
        }

        if (!matchedCategory) return result;

        itemRow.find('.category-select').val(matchedCategory.id);
        itemRow.find('.subcategory-select')
            .prop('disabled', false)
            .html(subcategoryOptionsHtmlForCategory(matchedCategory.id, matchedSubcategory ? matchedSubcategory.id : null));

        result.category    = matchedCategory;
        result.subcategory = matchedSubcategory;
        return result;
    }

    $(document).on('click', '.toggle-parts', function() {
        const partsRow = $(this).closest('tr').next('.parts-row');
        partsRow.fadeToggle(200);
    });

    const TROY_OUNCE_TO_GRAM = 31.1035;

    $('#currency').on('change', function() {
        const isUSD = $(this).val() === 'USD';
        if (isUSD) {
            $('#exchangeRateBox').show();
            $('#exchange_rate').attr('required', true);
            if(!$('#exchange_rate').val()) $('#exchange_rate').val('3.674');
        } else {
            $('#exchangeRateBox').hide();
            $('#exchange_rate').removeAttr('required');
            $('#exchange_rate').val('');
        }
        calculateTotals();
    });

    $('#exchange_rate').on('input', function() {
        calculateTotals();
    });

    $('.select2-js').select2({ width: '100%' });

    // ================= PRODUCT IMAGE =================
    const PRODUCT_IMG_BASE = '{{ url("/product") }}';

    function showItemImage(row, imageUrl, productName) {
        const cell = row.find('.item-img-cell');
        if (!imageUrl) {
            cell.html('');
            return;
        }
        cell.html(
            `<img src="${imageUrl}" alt="${productName || ''}" title="${productName || ''}"
                  style="width:42px;height:42px;object-fit:cover;border-radius:6px;
                         cursor:pointer;border:1px solid #dee2e6;"
                  onclick="window.open('${imageUrl}','_blank')">`
        );
    }

    function fetchAndShowImage(row, productId) {
        if (!productId) { row.find('.item-img-cell').html(''); return; }
        $.ajax({
            url: PRODUCT_IMG_BASE + '/' + productId + '/image',
            method: 'GET',
            success: function(data) { showItemImage(row, data.image_url || null, data.name || ''); },
            error:   function()     { row.find('.item-img-cell').html(''); }
        });
    }

    // ===== ITEMS JSON SERIALIZATION (multipart body parts limit fix) =====
    // Packs every items[N][field] / items[N][parts][M][field] input under
    // #PurchaseTable into one JSON blob (#items_json) and disables those
    // inputs so the browser doesn't ALSO send them as individual multipart
    // parts. File inputs (item images) are left alone — a file can't be put
    // inside JSON, so items[N][image] still travels as a real multipart part.
    //
    // DIAGNOSTIC INSTRUMENTATION (mirrors edit.blade.php): each field is
    // collected inside its own try/catch so one bad/unexpected field can't
    // silently abort the whole collapse, and the console.log/console.error
    // calls below give a permanent, unambiguous record — with DevTools
    // "Preserve log" enabled — of whether this ran and what it did.
    //
    // ROOT CAUSE FIX (found on edit.blade.php, applied here too): a plain
    // `function collectItemsJsonForSubmit() {` declaration scopes it to THIS
    // $(document).ready(...) closure only. The addEventListener('submit', ...)
    // handler was moved OUTSIDE this closure (top-level script, for
    // resilience against earlier errors in this callback) — from outside,
    // that inner declaration isn't visible at all, so the call was throwing
    // "ReferenceError: collectItemsJsonForSubmit is not defined" at every
    // submit. Assigning to `window.` (same pattern this file already uses
    // for addNewRow/removeRow, for the identical reason) makes it reachable
    // from anywhere on the page regardless of where it's declared.
    window.collectItemsJsonForSubmit = function collectItemsJsonForSubmit() {
        const itemsObj = {};
        let collected = 0;
        let failed = 0;
        document.querySelectorAll('#PurchaseTable [name^="items["]').forEach(function (el) {
            try {
                if (el.type === 'file') return;
                const matches = el.name.match(/\[([^\]]*)\]/g);
                if (!matches) return;
                const path = matches.map(function (p) { return p.slice(1, -1); });
                let cur = itemsObj;
                for (let i = 0; i < path.length; i++) {
                    const key = path[i];
                    if (i === path.length - 1) {
                        cur[key] = el.value;
                    } else {
                        if (typeof cur[key] !== 'object' || cur[key] === null) cur[key] = {};
                        cur = cur[key];
                    }
                }
                el.disabled = true;
                collected++;
            } catch (err) {
                failed++;
                console.error('[items_json] could not collapse field "' + (el && el.name) + '" — leaving it as a normal (undisabled) field so it still submits on its own:', err);
            }
        });
        try {
            document.getElementById('items_json').value = JSON.stringify(itemsObj);
            console.log('[items_json] collapse complete: ' + collected + ' field(s) collapsed into items_json, ' + failed + ' field(s) skipped/left as individual fields.');
        } catch (err) {
            console.error('[items_json] FAILED to write the items_json hidden field — items_json will submit empty:', err);
        }
    };

    // ================= ROW MANAGEMENT =================
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
                        $(this).attr('name',
                            name.replace(/items\[\d+\]/, `items[${i}]`)
                                .replace(/parts\[\d+\]/, `parts[${j}]`)
                        );
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
                    <input type="text" name="items[${nextIndex}][item_name]" class="form-control item-name-input" placeholder="Product Name" required>
                    <select name="items[${nextIndex}][category_id]" class="form-control form-control-sm category-select mt-1">
                        ${categoryOptionsHtml()}
                    </select>
                    <select name="items[${nextIndex}][subcategory_id]" class="form-control form-control-sm subcategory-select mt-1">
                        <option value="">Select Subcategory</option>
                    </select>
                    <input type="text" name="items[${nextIndex}][tray_no]" class="form-control form-control-sm tray-no-input mt-1" placeholder="Tray No">
                    <input type="file" name="items[${nextIndex}][image]" class="form-control form-control-sm item-image-input mt-1" accept="image/*">
                </div>
            </td>
            <td><input type="text" name="items[${nextIndex}][item_description]" class="form-control" required></td>
            <td><input type="text" name="items[${nextIndex}][certificate_no]" class="form-control" placeholder="e.g. GIA 123456"></td>
            <td>
                <select name="items[${nextIndex}][purity]" class="form-control purity">
                  @foreach($purities as $p)
                      <option value="{{ $p->value }}">{{ $p->label }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" name="items[${nextIndex}][net_weight]" step="any" value="0" class="form-control net-weight"></td>
            <td><input type="number" name="items[${nextIndex}][gross_weight]" step="any" value="0" class="form-control gross-weight bg-light text-primary fw-bold" readonly></td>
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
            <td><input type="number" name="items[${nextIndex}][selling_price]" step="any" min="0" class="form-control selling-price" placeholder="Optional"></td>
            <td class="item-img-cell" style="text-align:center;vertical-align:middle;padding:4px;"></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                <button type="button" class="btn btn-sm btn-primary toggle-parts"><i class="fas fa-wrench"></i></button>
            </td>
        </tr>
        <tr class="parts-row" style="display:none;background:#efefef">
            <td colspan="19">
                <div class="parts-wrapper">
                    <table class="table table-sm table-bordered parts-table">
                        <thead>
                            <tr>
                              <th>Part</th><th>Description</th><th>Carat (CTS)</th><th>Rate</th><th>Stone Ct.</th><th>Stone Rate</th><th>Cert. Charges</th><th>Total</th><th></th>
                            </tr>
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
        const row = $(btn).closest('tr');
        const partsRow = row.next('.parts-row');
        if ($('#PurchaseTable tr.item-row').length > 1) {
            partsRow.remove();
            row.remove();
            updateRowIndexes();
            calculateTotals();
        }
    };

    $(document).on('click', '.add-part', function() {
        const partsWrapper = $(this).closest('.parts-wrapper');
        const partsTableBody = partsWrapper.find('.parts-table tbody');
        const itemRow = $(this).closest('.parts-row').prev('.item-row');
        const itemIndex = itemRow.data('item-index');
        const partIndex = partsTableBody.find('tr').length;

        const rowHtml = `
        <tr class="part-item-row" data-part-index="${partIndex}">
            <td>
                <div class="product-wrapper">
                    <input type="text" name="items[${itemIndex}][parts][${partIndex}][item_name]" class="form-control item-name-input" placeholder="Part Name">
                    <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>
                </div>
            </td>
            <td><input type="text" name="items[${itemIndex}][parts][${partIndex}][part_description]" class="form-control"></td>
            <td>
                <div class="input-group">
                    <input type="number" name="items[${itemIndex}][parts][${partIndex}][qty]" step="any" value="0" class="form-control part-qty">
                    <input type="text" class="form-control part-unit-name" style="width:70px; flex:none;" readonly placeholder="CTS">
                </div>
            </td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][rate]" step="any" value="0" class="form-control part-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_qty]" step="any" value="0" class="form-control part-stone-qty"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][stone_rate]" step="any" value="0" class="form-control part-stone-rate"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][certification_charges]" step="any" value="0" class="form-control part-cert-charges"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][total]" step="any" value="0" class="form-control part-total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-part"><i class="fas fa-times"></i></button></td>
        </tr>`;

        partsTableBody.append(rowHtml);
    });

    $(document).on('click', '.remove-part', function() {
        const itemRow = $(this).closest('.parts-row').prev('.item-row');
        $(this).closest('tr').remove();
        recalcItemGrossWeight(itemRow);
        calculateTotals();
    });

    // ================= PRODUCT TOGGLE (PARTS ONLY) =================
    // NOTE: this toggle used to also exist on the main Item Name field, but
    // main invoice items are never linked to the Product catalog — Item Name
    // is always free-text input there (Category/Subcategory dropdowns handle
    // classification instead). It's kept here only for parts rows (diamond /
    // stone components), where linking to a catalog Product is still useful
    // (e.g. to pull the part's measurement unit).
    $(document).on('click', '.toggle-product, .revert-to-name', function () {
        const isReverting = $(this).hasClass('revert-to-name');
        const wrapper = $(this).closest('.product-wrapper');
        const isPart = wrapper.closest('tr').hasClass('part-item-row');
        if (!isPart) return;

        const itemIdx = wrapper.closest('.parts-row').prev('.item-row').data('item-index');
        const partIdx = wrapper.closest('.part-item-row').data('part-index');
        const namePath = `items[${itemIdx}][parts][${partIdx}]`;

        if (isReverting) {
            wrapper.html(`
                <input type="text" name="${namePath}[item_name]" class="form-control item-name-input" placeholder="Name">
                <button type="button" class="btn btn-link p-0 toggle-product"> Select Product </button>
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
                <button type="button" class="btn btn-link p-0 revert-to-name mt-1"> Write Name </button>
            `).find('.select2-js').select2({ width: '100%' });
        }
    });

    $(document).on('change', '.product-select', function() {
        const productId = $(this).val();
        const row = $(this).closest('tr');
        const variationSelect = row.find('.variation-select');
        const unitInput = row.find('.part-unit-name');

        const selectedOption = $(this).find(':selected');
        if(unitInput.length > 0) {
            unitInput.val(selectedOption.data('unit') || '');
        }

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
            });

        // Load product image for this item row (only main item rows, not part rows)
        const itemRow = row.closest('tr.item-row');
        if (itemRow.length) {
            fetchAndShowImage(itemRow, productId);
        }
    });

    // ================= CALCULATIONS =================

    $(document).on('input', '.net-weight', function() {
        const itemRow = $(this).closest('tr.item-row');
        recalcItemGrossWeight(itemRow);
    });

    $(document).on('input change', '.purity, .making-rate, .vat-percent, .material-type, #gold_rate_aed, #diamond_rate_aed', function() {
        const row = $(this).closest('tr.item-row');
        if (row.length) {
            recalcItemGrossWeight(row);
        }
        calculateTotals();
    });

    function setPurityDropdown(selectEl, rawValue) {
        const target = parseFloat(rawValue);
        if (isNaN(target)) return false;

        let matched = false;
        selectEl.find('option').each(function () {
            const optVal = parseFloat($(this).val());
            if (!isNaN(optVal) && Math.abs(optVal - target) < 0.0005) {
                selectEl.val($(this).val());
                matched = true;
                return false;
            }
        });

        return matched;
    }

    function recalcItemGrossWeight(itemRow) {
        if (!itemRow || !itemRow.length) return;

        const purity    = parseFloat(itemRow.find('.purity').val())          || 0;
        const baseGross = parseFloat(itemRow.find('.net-weight').val()) || 0;
        const netWt     = baseGross * purity;

        const partsRow = itemRow.next('.parts-row');
        let totalDiamondCTS = 0;
        let totalStoneCTS   = 0;
        partsRow.find('.part-item-row').each(function() {
            totalDiamondCTS += parseFloat($(this).find('.part-qty').val())       || 0;
            totalStoneCTS   += parseFloat($(this).find('.part-stone-qty').val()) || 0;
        });

        let newGross = baseGross + (totalDiamondCTS / 5) + (totalStoneCTS / 5);
        itemRow.find('.gross-weight').val(newGross.toFixed(4));

        calculateRow(itemRow);
        calculateTotals();
    }

    function calculateRow(row) {
        const purity       = parseFloat(row.find('.purity').val())       || 0;
        const gross        = parseFloat(row.find('.gross-weight').val())  || 0;
        const userNetWt    = parseFloat(row.find('.net-weight').val())    || 0;
        const makingRate   = parseFloat(row.find('.making-rate').val())   || 0;
        const vatPercent   = parseFloat(row.find('.vat-percent').val())   || 0;
        const materialType = row.find('.material-type').val();

        let rate = (materialType === 'gold')
            ? parseFloat($('#gold_rate_aed').val())
            : parseFloat($('#diamond_rate_aed').val());
        rate = rate || 0;

        const netWt  = userNetWt * purity;
        const col995 = netWt / 0.995;
        const makingValue = userNetWt * makingRate;
        const materialValue = rate * netWt;

        let partsTotal = 0;
        row.next('.parts-row').find('.part-item-row').each(function () {
            partsTotal += parseFloat($(this).find('.part-total').val()) || 0;
        });

        const taxableAmount = makingValue;
        const vatAmount     = taxableAmount * vatPercent / 100;
        const itemTotal     = materialValue + makingValue + partsTotal + vatAmount;

        row.find('.taxable-amount').val(taxableAmount.toFixed(4));
        row.find('.vat-amount').val(vatAmount.toFixed(4));
        row.find('.item-total').val(itemTotal.toFixed(4));
        row.find('.purity-weight').val(netWt.toFixed(4));
        row.find('.col-995').val(col995.toFixed(4));
        row.find('.making-value').val(makingValue.toFixed(4));
        row.find('.material-value').val(materialValue.toFixed(4));
    }

    function calculateTotals() {
        let sumNetWt        = 0;
        let sum995          = 0;
        let sumMakingValue  = 0;
        let sumMaterial     = 0;
        let sumVAT          = 0;
        let totalStoneQty   = 0;
        let sumGoldGross    = 0;
        let totalDiamondCTS = 0;
        let totalDiamondVal = 0;
        let totalStoneVal   = 0;
        let sumItemTotal    = 0;

        $('#PurchaseTable tr.item-row').each(function () {
            const itemRow      = $(this);
            const materialType = itemRow.find('.material-type').val();
            const grossVal     = parseFloat(itemRow.find('.gross-weight').val())  || 0;
            const netWtVal     = parseFloat(itemRow.find('.purity-weight').val()) || 0;

            sumNetWt       += netWtVal;
            sum995         += parseFloat(itemRow.find('.col-995').val())        || 0;
            sumMakingValue += parseFloat(itemRow.find('.making-value').val())   || 0;
            sumMaterial    += parseFloat(itemRow.find('.material-value').val()) || 0;
            sumVAT         += parseFloat(itemRow.find('.vat-amount').val())     || 0;
            sumItemTotal   += parseFloat(itemRow.find('.item-total').val())     || 0;

            itemRow.next('.parts-row').find('.part-item-row').each(function () {
                const diaQty    = parseFloat($(this).find('.part-qty').val())        || 0;
                const diaRate   = parseFloat($(this).find('.part-rate').val())       || 0;
                const stoneQty  = parseFloat($(this).find('.part-stone-qty').val())  || 0;
                const stoneRate = parseFloat($(this).find('.part-stone-rate').val()) || 0;

                totalDiamondCTS += diaQty;
                totalStoneQty   += stoneQty;
                totalDiamondVal += diaQty   * diaRate;
                totalStoneVal   += stoneQty * stoneRate;
            });

            if (materialType === 'gold') {
                sumGoldGross += grossVal;
            }
        });

        const netTotal = sumItemTotal;

        $('#sum_gold_gross_weight').val(sumGoldGross.toFixed(4));
        $('#sum_diamond_cts').val(totalDiamondCTS.toFixed(4));
        $('#sum_stone_qty').val(totalStoneQty.toFixed(4));
        $('#sum_purity_weight').val(sumNetWt.toFixed(4));
        $('#sum_995').val(sum995.toFixed(4));
        $('#sum_making_value').val(sumMakingValue.toFixed(4));
        $('#sum_material_value').val(sumMaterial.toFixed(4));
        $('#sum_vat_amount').val(sumVAT.toFixed(4));
        $('#sum_diamond_value').val(totalDiamondVal.toFixed(4));
        $('#sum_stone_value').val(totalStoneVal.toFixed(4));
        $('#net_amount_display').val(netTotal.toFixed(4));
        $('#net_amount').val(netTotal.toFixed(4));

        const currency = $('#currency').val();
        const exRate   = parseFloat($('#exchange_rate').val()) || 1;
        $('#converted_total').val(
            currency === 'USD' ? (netTotal * exRate).toFixed(4) : netTotal.toFixed(4)
        );

        const pm = $('#payment_method').val();
        if (pm === 'material+making cost' || pm === 'material') {
            $('input[name="material_weight"]').val(sum995.toFixed(4));
            $('input[name="material_purity"]').val(sumNetWt.toFixed(4));
            $('input[name="material_value"]').val(sumMaterial.toFixed(4));
            $('input[name="making_charges"]').val(sumMakingValue.toFixed(4));
        }
    }

    // ================= OUNCE TO GRAM & CURRENCY CONVERSION =================
    $(document).on('input', '#gold_rate_usd, #gold_rate_aed_ounce, #diamond_rate_usd, #diamond_rate_aed, #exchange_rate', function() {
        const id     = $(this).attr('id');
        const exRate = parseFloat($('#exchange_rate').val()) || 3.674;

        if (id === 'gold_rate_usd' || id === 'exchange_rate') {
            const goldUsd = parseFloat($('#gold_rate_usd').val()) || 0;
            $('#gold_rate_aed_ounce').val((goldUsd * exRate).toFixed(4));
        }
        const goldAedOunceFinal = parseFloat($('#gold_rate_aed_ounce').val()) || 0;
        $('#gold_rate_aed').val((goldAedOunceFinal / TROY_OUNCE_TO_GRAM).toFixed(4));

        if (id === 'diamond_rate_usd' || id === 'exchange_rate') {
            const diaUsd = parseFloat($('#diamond_rate_usd').val()) || 0;
            $('#diamond_rate_aed').val((diaUsd * exRate).toFixed(4));
        }

        $('#PurchaseTable tr.item-row').each(function() {
            calculateRow($(this));
        });
        calculateTotals();
    });

    $('#payment_method').on('change', function() {
        const val = $(this).val();
        $('#cheque_fields, #material_fields, #received_by_box, #bank_transfer_fields, #cash_fields').addClass('d-none');
        if (val === 'cheque')             $('#cheque_fields, #received_by_box').removeClass('d-none');
        else if (val === 'cash')          $('#received_by_box, #cash_fields').removeClass('d-none');
        else if (val === 'bank_transfer') $('#bank_transfer_fields').removeClass('d-none');
        else if (val === 'material+making cost' || val === 'material') {
            $('#material_fields').removeClass('d-none');
            if (val === 'material') {
                $('.making-collection-field').addClass('d-none');
                $('#making_amount_paid').val(0);
                $('select[name="making_payment_account"]').val('').trigger('change');
            } else {
                $('.making-collection-field').removeClass('d-none');
            }
        }
        calculateTotals();
    });

    // ================= PARTS ROW CALCULATION =================
    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate, .part-cert-charges', function() {
        const row       = $(this).closest('tr');
        const qty       = parseFloat(row.find('.part-qty').val())            || 0;
        const rate      = parseFloat(row.find('.part-rate').val())            || 0;
        const stoneQty  = parseFloat(row.find('.part-stone-qty').val())      || 0;
        const stoneRate = parseFloat(row.find('.part-stone-rate').val())      || 0;
        const certChg   = parseFloat(row.find('.part-cert-charges').val())   || 0;

        const total = (qty * rate) + (stoneQty * stoneRate) + certChg;
        row.find('.part-total').val(total.toFixed(4));

        const itemRow = row.closest('.parts-row').prev('.item-row');
        recalcItemGrossWeight(itemRow);
    });

    // ================= EXCEL IMPORT =================
    // "Keep existing items vs. replace" mode-choice modal, mirroring the
    // Edit Purchase Invoice page: if the item table already has real data
    // (rows the user typed/added, or rows from a previous Excel import),
    // ask whether the newly-picked Excel data should be appended to it or
    // should replace it entirely. If the table is still just the empty
    // starter row, skip the prompt and import straight in "append" mode.
    let pendingExcelFile = null;

    function tableHasRealData() {
        return $('#PurchaseTable tr.item-row').toArray().some(function (row) {
            const name = $(row).find('.item-name-input').val();
            return name && name.trim() !== '';
        });
    }

    function showExcelImportModal() {
        document.getElementById('excelImportModal').style.display = 'flex';
    }
    function hideExcelImportModal() {
        document.getElementById('excelImportModal').style.display = 'none';
    }

    $('#excel_import').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        pendingExcelFile = file;

        if (tableHasRealData()) {
            showExcelImportModal();
        } else {
            runExcelImport(pendingExcelFile, 'append');
        }
    });

    $('#excelImportReplace').on('click', function() {
        hideExcelImportModal();
        if (pendingExcelFile) runExcelImport(pendingExcelFile, 'replace');
    });

    $('#excelImportAppend').on('click', function() {
        hideExcelImportModal();
        if (pendingExcelFile) runExcelImport(pendingExcelFile, 'append');
    });

    $('#excelImportCancel').on('click', function() {
        hideExcelImportModal();
        pendingExcelFile = null;
        $('#excel_import').val('');
    });

    // ================= BULK PICTURE IMPORT =================
    // FEATURE: a second attachment next to "Import Excel" — pick many pictures
    // at once; each is matched to an item row by FILE NAME and put into that
    // row's own "Img" file input (items[N][image]), exactly as if it had been
    // chosen by hand, so saving the invoice stores it the normal way
    // (image_path) and the Sale Invoice then shows it.
    //
    // A row's picture is found, in this order, by:
    //   1. the "Image File" column of the Excel row (e.g. R-001.jpg — with or
    //      without the extension). When that column is filled, ONLY it is used.
    //   2. otherwise the row's Certificate No (picture named like the cert no)
    //   3. otherwise the row's Item Name
    // Names are compared ignoring case, spaces and punctuation.
    //
    // Pictures can be chosen before OR after the Excel file (and again at any
    // time) — they are applied to whatever rows are on the page at that moment.
    let bulkPictures = [];   // [{ key, name, file }]

    function normalizeRefKey(s) {
        return (s === undefined || s === null) ? '' :
            s.toString().trim().replace(/\.(jpe?g|png|gif|webp|bmp|heic|heif)$/i, '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    // Shrinks big phone/camera pictures before upload (a 200-row invoice with
    // full-size photos would exceed the server's POST size limit). Falls back
    // to the original file whenever anything goes wrong.
    function compressImage(file, maxDim, quality) {
        maxDim = maxDim || 1600; quality = quality || 0.85;
        return new Promise(function (resolve) {
            if (!/^image\//.test(file.type)) return resolve(file);
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = function () {
                const w = img.naturalWidth, h = img.naturalHeight;
                const scale = Math.min(1, maxDim / Math.max(w, h));
                if (scale === 1 && file.size <= 600 * 1024) { URL.revokeObjectURL(url); return resolve(file); }
                const c = document.createElement('canvas');
                c.width = Math.max(1, Math.round(w * scale)); c.height = Math.max(1, Math.round(h * scale));
                const ctx = c.getContext('2d');
                ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, c.width, c.height);
                ctx.drawImage(img, 0, 0, c.width, c.height);
                c.toBlob(function (blob) {
                    URL.revokeObjectURL(url);
                    if (!blob || (scale === 1 && blob.size >= file.size)) return resolve(file);
                    resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', quality);
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    }

    function showRowThumb(row, file) {
        const wrapper = row.find('.product-wrapper').first();
        wrapper.find('.row-img-thumb').remove();
        if (!file) return;
        const src = URL.createObjectURL(file);
        $('<img class="row-img-thumb mt-1">')
            .attr({ src: src, title: file.name })
            .css({ height: '48px', width: '48px', objectFit: 'cover', borderRadius: '6px', border: '1px solid #dee2e6', display: 'block' })
            .appendTo(wrapper);
    }

    // PHP only accepts this many uploaded files per request (ini max_file_uploads);
    // extra pictures are dropped by PHP without any message, so they are limited here.
    const SERVER_MAX_FILE_UPLOADS = {{ (int) (ini_get('max_file_uploads') ?: 20) }};

    function picturesAttachedCount(exceptRow) {
        let n = 0;
        $('#PurchaseTable .item-image-input').each(function () {
            if (exceptRow && exceptRow.length && $(this).closest('tr.item-row')[0] === exceptRow[0]) return;
            if (this.files && this.files.length) n++;
        });
        return n;
    }

    // picture chosen by hand: shrunk first (big phone photos are otherwise rejected
    // by the server's upload size limit), then shown as a thumbnail
    $(document).on('change', '.item-image-input', async function () {
        const input = this;
        const row   = $(input).closest('tr.item-row');
        row.removeData('bulkImg');
        const file  = input.files && input.files[0] ? input.files[0] : null;
        if (!file) { showRowThumb(row, null); return; }

        const small = await compressImage(file);
        if (small !== file && typeof DataTransfer !== 'undefined') {
            const dt = new DataTransfer();
            dt.items.add(small);
            input.files = dt.files;
        }
        if (picturesAttachedCount(row) >= SERVER_MAX_FILE_UPLOADS) {
            input.value = '';
            showRowThumb(row, null);
            alert('The server accepts only ' + SERVER_MAX_FILE_UPLOADS + ' pictures per save (PHP max_file_uploads). ' +
                  'Save this invoice first, then open Edit to add the remaining pictures.');
            return;
        }
        showRowThumb(row, input.files[0]);
    });

    function setRowPicture(row, file) {
        const input = row.find('.item-image-input')[0];
        if (!input || typeof DataTransfer === 'undefined') return false;
        if (picturesAttachedCount(row) >= SERVER_MAX_FILE_UPLOADS) return false;
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        row.data('bulkImg', true);
        showRowThumb(row, file);
        return true;
    }

    // Applies the loaded pictures to the rows now on the page.
    // Returns a summary used by the report.
    function applyBulkPictures() {
        const summary = { matched: 0, overflow: [], rowsMissing: [], unusedPictures: [] };
        if (!bulkPictures.length) return summary;

        const byKey  = {};
        bulkPictures.forEach(p => { if (p.key && !byKey[p.key]) byKey[p.key] = p; });
        const usedKeys = {};

        $('#PurchaseTable tr.item-row').each(function () {
            const row     = $(this);
            const name    = row.find('.item-name-input').val();
            if (!name || !name.toString().trim()) return;

            // a picture the user picked by hand on this row is never overwritten
            const input = row.find('.item-image-input')[0];
            if (input && input.files && input.files.length && !row.data('bulkImg')) return;

            const sheetRef = row.data('imageRef');
            const refs = sheetRef
                ? [sheetRef]
                : [row.find('input[name*="[certificate_no]"]').val(), name];

            let pic = null;
            for (let i = 0; i < refs.length; i++) {
                const k = normalizeRefKey(refs[i]);
                if (k && byKey[k]) { pic = byKey[k]; break; }
            }

            if (pic) {
                if (setRowPicture(row, pic.file)) { summary.matched++; usedKeys[pic.key] = true; }
                else { summary.overflow.push(name.toString()); usedKeys[pic.key] = true; }
            } else if (sheetRef) {
                summary.rowsMissing.push(`${name} → "${sheetRef}"`);
            }
        });

        bulkPictures.forEach(p => { if (!usedKeys[p.key]) summary.unusedPictures.push(p.name); });
        return summary;
    }

    function escHtml(s) {
        return (s === undefined || s === null ? '' : s.toString())
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function pictureSummaryHtml(sum) {
        let html = `<div class="mb-2"><strong>${sum.matched}</strong> picture(s) attached to item rows.</div>`;
        if (sum.rowsMissing.length) {
            html += `<div class="text-danger mb-2"><strong>Image File not found among the selected pictures (${sum.rowsMissing.length}):</strong><br>` +
                    sum.rowsMissing.slice(0, 40).map(escHtml).join('<br>') +
                    (sum.rowsMissing.length > 40 ? '<br>…' : '') + '</div>';
        }
        if (sum.unusedPictures.length) {
            html += `<div class="text-warning-emphasis mb-2"><strong>Pictures that matched no item (${sum.unusedPictures.length}):</strong><br>` +
                    sum.unusedPictures.slice(0, 40).map(escHtml).join('<br>') +
                    (sum.unusedPictures.length > 40 ? '<br>…' : '') + '</div>';
        }
        if (sum.overflow && sum.overflow.length) {
            html += `<div class="alert alert-danger py-2 mb-2" style="font-size:.85rem;">
                <strong>${sum.overflow.length} picture(s) NOT attached:</strong> the server accepts only ${SERVER_MAX_FILE_UPLOADS} pictures per save
                (PHP <code>max_file_uploads</code>). Save the invoice, then use <strong>Edit → Import Pictures</strong> for the rest
                (pictures there are saved directly, with no limit), or ask your host to raise <code>max_file_uploads</code>.<br>` +
                sum.overflow.slice(0, 40).map(escHtml).join('<br>') + (sum.overflow.length > 40 ? '<br>…' : '') + `</div>`;
        }
        return html;
    }

    function openImportReport(title, bodyHtml) {
        document.getElementById('importReportTitle').innerHTML = title;
        document.getElementById('importReportBody').innerHTML  = bodyHtml;
        document.getElementById('importReportModal').style.display = 'flex';
    }
    $('#importReportClose').on('click', function () {
        document.getElementById('importReportModal').style.display = 'none';
    });

    $('#bulk_images').on('change', async function () {
        const files = Array.from(this.files || []).filter(f => /^image\//.test(f.type) || /\.(jpe?g|png|gif|webp|bmp)$/i.test(f.name));
        $(this).val('');
        if (!files.length) return;

        const status = $('#bulkImagesStatus');
        const prepared = [];
        for (let i = 0; i < files.length; i++) {
            status.text(`Preparing pictures ${i + 1} / ${files.length}…`);
            const f = await compressImage(files[i]);
            prepared.push({ key: normalizeRefKey(files[i].name), name: files[i].name, file: f });
        }

        // newly chosen pictures replace earlier ones with the same name
        const incoming = {};
        prepared.forEach(p => { incoming[p.key] = true; });
        bulkPictures = bulkPictures.filter(p => !incoming[p.key]).concat(prepared);

        const sum = applyBulkPictures();
        status.text(`${bulkPictures.length} picture(s) loaded`);
        openImportReport('<i class="fas fa-images text-warning"></i> Pictures', pictureSummaryHtml(sum));
    });

    function runExcelImport(file, mode) {
        const unmatchedPurityRows   = [];
        const categorySubWarnings   = [];
        const importRows            = [];   // for the on-screen report

        const reader = new FileReader();
        reader.onload = function(e) {
            const data     = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const jsonData = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);

            if (jsonData.length === 0) {
                alert('The selected file has no data rows.');
                $('#excel_import').val('');
                pendingExcelFile = null;
                return;
            }

            if (mode === 'replace') {
                // Wipe every existing item + its parts row before importing.
                $('#PurchaseTable tr.item-row, #PurchaseTable tr.parts-row').remove();
            } else {
                // append mode: only clean up a single default blank starter
                // row, exactly like before — never touches rows with data.
                let firstRow = $('#PurchaseTable tr.item-row').first();
                if ($('#PurchaseTable tr.item-row').length === 1 && !firstRow.find('.item-name-input').val()) {
                    firstRow.next('.parts-row').remove();
                    firstRow.remove();
                }
            }

            let currentItemRow = null;

            jsonData.forEach((row) => {
                if (row['Item Name'] && row['Item Name'].toString().trim() !== "") {
                    addNewRow();
                    currentItemRow = $('#PurchaseTable tr.item-row').last();

                    currentItemRow.find('.item-name-input').val(row['Item Name']);
                    currentItemRow.find('input[name*="[item_description]"]').val(row['Description'] || '');
                    currentItemRow.find('input[name*="[certificate_no]"]').val(row['Certificate No'] || '');
                    currentItemRow.find('.tray-no-input').val(row['Tray No'] || '');

                    const catRaw = getCell(row, CATEGORY_HEADERS);
                    const subRaw = getCell(row, SUBCATEGORY_HEADERS);
                    const resolved = applyCategorySubcategoryFromCodes(
                        currentItemRow, catRaw, subRaw, row['Item Name'], categorySubWarnings
                    );

                    // picture file name for this item (optional "Image File" column)
                    const imgRef = normalizeCode(getCell(row, IMAGE_HEADERS));
                    if (imgRef) currentItemRow.data('imageRef', imgRef);

                    importRows.push({
                        item: row['Item Name'], catRaw: normalizeCode(catRaw), subRaw: normalizeCode(subRaw),
                        category: resolved.category, subcategory: resolved.subcategory, problems: resolved.problems
                    });

                    const purityRaw = row['Purity'] !== undefined && row['Purity'] !== ''
                        ? row['Purity']
                        : 0.92;
                    const purityMatched = setPurityDropdown(currentItemRow.find('.purity'), purityRaw);
                    if (!purityMatched) {
                        unmatchedPurityRows.push({ item: row['Item Name'], purity: purityRaw });
                    }

                    currentItemRow.find('.net-weight').val(parseFloat(row['Gross Wt']) || 0);
                    currentItemRow.find('.making-rate').val(row['Making Rate'] || 0);
                    currentItemRow.find('.material-type').val((row['Material'] || 'gold').toLowerCase());
                    currentItemRow.find('.vat-percent').val(row['VAT %'] || 0);

                    recalcItemGrossWeight(currentItemRow);
                }

                if (row['Part Name'] && row['Part Name'].toString().trim() !== "" && currentItemRow) {
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
                    currentPartRow.find('.part-cert-charges').val(row['Cert. Charges'] || 0);

                    currentPartRow.find('.part-qty').trigger('input');
                }
            });

            calculateTotals();

            // pictures already loaded (chosen before the Excel file) are attached now
            const picSummary = bulkPictures.length ? applyBulkPictures() : null;

            // ── on-screen import report (replaces the old plain alert) ──
            let html = '';
            if (unmatchedPurityRows.length > 0) {
                html += '<div class="text-danger mb-2"><strong>Purity value with no matching dropdown option:</strong><br>' +
                        unmatchedPurityRows.map(r => `${escHtml(r.item)}: ${escHtml(r.purity)}`).join('<br>') + '</div>';
            }
            if (categorySubWarnings.length > 0) {
                html += '<div class="text-danger mb-2"><strong>Category / Subcategory — please check these manually:</strong><br>' +
                        categorySubWarnings.map(w => '• ' + escHtml(w)).join('<br>') + '</div>';
            }
            if (picSummary) html += pictureSummaryHtml(picSummary);
            else if ($('#PurchaseTable tr.item-row').filter(function () { return $(this).data('imageRef'); }).length) {
                html += '<div class="text-muted mb-2">The sheet has an "Image File" column — now click <strong>Import Pictures</strong> and select the picture files.</div>';
            }

            // what the import actually selected for every row, so a wrong match is visible immediately
            html += '<div class="fw-bold mt-2 mb-1">What was selected for each imported item</div>' +
                '<div style="max-height:40vh; overflow:auto;"><table class="table table-sm table-bordered mb-0" style="font-size:.8rem;">' +
                '<thead class="table-light"><tr><th>#</th><th>Item</th><th>Sheet Category</th><th>→ Category</th><th>Sheet Subcat.</th><th>→ Subcategory</th></tr></thead><tbody>' +
                importRows.map((r, i) => {
                    const bad = r.problems.length > 0;
                    return `<tr class="${bad ? 'table-danger' : ''}"><td>${i + 1}</td><td>${escHtml(r.item)}</td>` +
                        `<td>${escHtml(r.catRaw) || '<span class="text-muted">—</span>'}</td>` +
                        `<td>${r.category ? escHtml(r.category.name) + ' (' + escHtml(r.category.code) + ')' : '<span class="text-muted">—</span>'}</td>` +
                        `<td>${escHtml(r.subRaw) || '<span class="text-muted">—</span>'}</td>` +
                        `<td>${r.subcategory ? escHtml(r.subcategory.name) + ' (' + escHtml(r.subcategory.code) + ')' : '<span class="text-muted">—</span>'}</td></tr>`;
                }).join('') + '</tbody></table></div>';

            const hasWarnings = unmatchedPurityRows.length > 0 || categorySubWarnings.length > 0;
            openImportReport(
                hasWarnings
                    ? '<i class="fas fa-exclamation-triangle text-warning"></i> Items imported — please check'
                    : '<i class="fas fa-check-circle text-success"></i> ' + (mode === 'replace' ? 'Existing items removed. Items imported successfully' : 'Items imported successfully and added to the existing list'),
                html
            );

            $('#excel_import').val('');
            pendingExcelFile = null;
        };
        reader.readAsArrayBuffer(file);
    }

  });

  // DIAGNOSTIC (mirrors edit.blade.php): this submit-listener registration
  // sits OUTSIDE the $(document).ready(...) callback above, at the top level
  // of this <script> tag, so it runs the instant the browser parses this
  // line — it does NOT wait on, and is not blocked by, anything inside
  // $(document).ready(...). The console.log at the end is intentional and
  // should stay: if it is MISSING from the console on a fresh hard-refresh
  // of this page, something earlier in this same <script> tag threw before
  // reaching this line (check the console for the actual error) and the
  // submit handler below was never attached at all.
  try {
      document.querySelector('form').addEventListener('submit', function() {
          collectItemsJsonForSubmit();
          const btn = this.querySelector('button[type="submit"]');
          btn.disabled = true;
          btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      });
      console.log('[items_json] submit listener attached successfully on page load.');
  } catch (err) {
      console.error('[items_json] FAILED to attach submit listener on page load:', err);
  }
</script>
@endsection