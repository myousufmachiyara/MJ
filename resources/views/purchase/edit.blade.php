@extends('layouts.app')

@section('title', 'Purchase | Edit Invoice #' . $purchaseInvoice->invoice_no)

@section('content')

{{-- ═══════════════════════════════════════════════════════════════════════
     EXCEL IMPORT — MODE CHOICE MODAL
     Shown only when the item table already has data at the moment a file
     is picked. Lets the user choose to keep existing rows and append the
     Excel rows, or wipe the table and replace it entirely with the Excel
     rows. Self-contained (no Bootstrap JS dependency) so it works
     regardless of which Bootstrap version layouts.app loads.
     Mirrors the same feature on the Create Purchase Invoice page.
     ═══════════════════════════════════════════════════════════════════════ --}}
<div id="excelImportModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:8px; max-width:480px; width:92%; padding:24px; box-shadow:0 10px 40px rgba(0,0,0,0.25);">
    <h5 class="mb-2"><i class="fas fa-file-excel text-success"></i> Import Excel — Existing Items Found</h5>
    <p class="text-muted mb-3">
      This invoice already has item rows. How should the imported Excel data be applied?
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

<div class="row">
  <div class="col">
    <form id="main-form" action="{{ route('purchase_invoices.update', $purchaseInvoice->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      {{-- FIX (multipart body parts limit): on submit, collectItemsJsonForSubmit()
           (defined below) packs every items[N][...] / items[N][parts][M][...]
           field into this single JSON field and disables the individual inputs
           so they aren't also sent as one multipart part per cell. A large
           invoice (hundreds of items) was generating more multipart parts than
           PHP's max_multipart_body_parts ini limit allows, causing PHP to
           silently drop fields that appear later in the HTML than the cutoff —
           this hidden field sits at the very top of the form so it is always
           parsed regardless. Item image files still travel as real
           items[N][image] file parts; see decodeItemsJsonPayload() in
           PurchaseInvoiceController. --}}
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

      @if(session('printed_delete_warning'))
        <div class="alert alert-warning">
          <strong>Warning:</strong> The following items have already been printed and will be permanently deleted:
          <br><code>{{ session('printed_delete_warning') }}</code>
          <br><br>
          {{-- FIX: this used to be its own <form id="confirm-delete-form">...</form>
               nested INSIDE #main-form. Browsers don't allow nested forms — the
               inner <form> open tag gets silently dropped by the HTML parser, but
               its closing </form> tag is still honored and ends up closing the
               OUTER #main-form early (right here). Everything below this point in
               the page — the items table, Currency card, Payment Method, and the
               hidden net_amount input — ended up outside the real <form> in the
               DOM, so none of it was submitted, causing "currency/net amount/
               payment method field is required" errors whenever this warning was
               shown. The button below never actually needed its own <form>:
               resubmitWithConfirm() already submits #main-form directly and
               appends confirm_delete_printed itself, so the wrapper is removed
               entirely rather than nested. --}}
          <button type="button" class="btn btn-danger" onclick="resubmitWithConfirm()">
            Delete anyway and update invoice
          </button>
          <a href="{{ route('purchase_invoices.edit', $purchaseInvoice->id) }}" class="btn btn-secondary ms-2">
            Go back and keep items
          </a>
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
              <small class="text-muted">Changing type re-assigns the invoice number to the correct series (PUR- / PUR-TAX-)</small>
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

          </div>

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
                    <th width="8%"  rowspan="2">Cert. No.<br><small class="text-muted">(for label)</small></th>
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
                    <th width="7%" rowspan="2">Selling Price<br><small class="text-muted">(Optional — used by POS)</small></th>
                    <th rowspan="2" width="4%">Img</th>
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
              <label>Total Making</label>
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
                @foreach(['credit','cash','bank_transfer','cheque','material+making cost','material'] as $pm)
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

          <div class="row mb-3 d-none" id="cash_fields">
            <div class="col-md-2">
              <label>Amount Paid (Cash)</label>
              <input type="number" step="any" name="cash_amount_paid"
                     class="form-control" value="{{ $purchaseInvoice->cash_amount_paid }}"
                     placeholder="Leave blank = full payment">
              <small class="text-muted">Remaining goes to vendor payable</small>
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
              <small class="text-muted">Leave blank = full invoice amount. Remaining goes to vendor payable.</small>
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
              <label>Making Charges (Calculated)</label>
              <input type="number" step="any" name="making_charges" id="making_charges_display"
                     class="form-control" value="{{ $purchaseInvoice->making_charges }}" readonly>
            </div>
            <div class="col-md-2 making-collection-field">
              <label>Making Charges Paid Now</label>
              <input type="number" step="any" name="making_amount_paid" id="making_amount_paid"
                     class="form-control" value="0">
              <small class="text-muted">0 = fully payable to vendor</small>
            </div>
            <div class="col-md-2 making-collection-field">
              <label>Cash/Bank Account (for payment)</label>
              <select name="making_payment_account" class="form-control select2-js">
                  <option value="">None (fully payable)</option>
                  @foreach ($banks as $account)
                      <option value="bank_{{ $account->id }}"
                          {{ old('making_payment_account', $purchaseInvoice->making_payment_account ?? '') == 'bank_'.$account->id ? 'selected' : '' }}>
                          {{ $account->name }}
                      </option>
                  @endforeach
              </select>
            </div>
            <div class="col-md-2 mt-3">
              <label>Material Given By</label>
              <input type="text" name="material_given_by" class="form-control text-danger fw-bold"
                     value="{{ $purchaseInvoice->material_given_by }}">
            </div>
            <div class="col-md-2 mt-3">
              <label>Material Received By</label>
              <input type="text" name="material_received_by" class="form-control text-danger fw-bold"
                     value="{{ $purchaseInvoice->material_received_by }}">
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
              <input type="text" name="transfer_to_bank" class="form-control"
                     value="{{ $purchaseInvoice->transfer_to_bank }}" placeholder="e.g. Emirates NBD">
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
              <small class="text-muted">Leave blank = full invoice amount. Remaining goes to vendor payable.</small>
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

        </div>

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
    const categories    = @json($categories);
    // Preloaded in full (not just fetched per-category via AJAX) so Excel
    // import can resolve a row's Category Code / Subcategory Code to real
    // IDs entirely client-side — see applyCategorySubcategoryFromCodes().
    const subcategories = @json($subcategories);
    const existingItems = @json($itemsData);
    const TROY_OUNCE_TO_GRAM = 31.1035;

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

    function findCategoryByCode(code) {
        const norm = normalizeCode(code).toLowerCase();
        if (!norm) return null;
        return categories.find(c => normalizeCode(c.code).toLowerCase() === norm) || null;
    }

    function findSubcategoryByCode(code) {
        const norm = normalizeCode(code).toLowerCase();
        if (!norm) return null;
        return subcategories.find(sc => normalizeCode(sc.code).toLowerCase() === norm) || null;
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
     * Resolves an imported row's Category Code / Subcategory Code text to
     * real category_id/subcategory_id selections on that item row, entirely
     * from the preloaded `categories`/`subcategories` arrays (no AJAX, so a
     * bulk import of many rows doesn't fire dozens of concurrent requests).
     * Subcategory Code takes priority for deriving the category — a code
     * alone is enough since subcategory codes are unique — falling back to
     * Category Code alone when no Subcategory Code is given or matched.
     * Anything that doesn't match a known code is pushed onto `warnings`
     * (by row label) instead of failing the import.
     */
    function applyCategorySubcategoryFromCodes(itemRow, categoryCodeRaw, subcategoryCodeRaw, itemLabel, warnings) {
        const categoryCode    = normalizeCode(categoryCodeRaw);
        const subcategoryCode = normalizeCode(subcategoryCodeRaw);
        if (!categoryCode && !subcategoryCode) return;

        const matchedSubcategory = subcategoryCode ? findSubcategoryByCode(subcategoryCode) : null;
        let   matchedCategory    = categoryCode ? findCategoryByCode(categoryCode) : null;

        if (subcategoryCode && !matchedSubcategory) {
            warnings.push(`${itemLabel}: Subcategory Code "${subcategoryCode}" not found`);
        }
        if (categoryCode && !matchedCategory) {
            warnings.push(`${itemLabel}: Category Code "${categoryCode}" not found`);
        }

        if (matchedSubcategory) {
            matchedCategory = categories.find(c => c.id == matchedSubcategory.category_id) || matchedCategory;
        }
        if (!matchedCategory) return;

        itemRow.find('.category-select').val(matchedCategory.id);
        itemRow.find('.subcategory-select')
            .prop('disabled', false)
            .html(subcategoryOptionsHtmlForCategory(matchedCategory.id, matchedSubcategory ? matchedSubcategory.id : null));
    }

    // ===== PARTS TOGGLE =====
    $(document).on('click', '.toggle-parts', function() {
        $(this).closest('tr').next('.parts-row').fadeToggle(200);
    });

    // ===== QUICK SELLING PRICE SAVE =====
    // FEATURE (quick Selling Price save): PUTs just this one item's
    // selling_price — see routes/web.php (purchase_invoice_items.update_selling_price)
    // and PurchaseInvoiceController::updateSellingPrice() for why this exists
    // instead of resubmitting the entire "Update Invoice" form.
    const SELLING_PRICE_SAVE_URL_BASE = '{{ url("/purchase-invoice-items") }}';
    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

    $(document).on('click', '.selling-price-save-btn', function() {
        const btn      = $(this);
        const itemId   = btn.data('item-id');
        const row      = btn.closest('tr.item-row');
        const input    = row.find('input[name*="[selling_price]"]');
        const statusEl = row.find('.selling-price-save-status');
        const rawVal   = input.val();

        const originalIcon = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        statusEl.removeClass('text-success text-danger').text('');

        $.ajax({
            url: SELLING_PRICE_SAVE_URL_BASE + '/' + itemId + '/selling-price',
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            data: { selling_price: rawVal === '' ? null : rawVal },
            success: function(res) {
                input.val(res.selling_price === null ? '' : res.selling_price);
                statusEl.addClass('text-success')
                    .html('<i class="fas fa-check-circle"></i> Saved');
                clearTimeout(row.data('_sp_status_timer'));
                row.data('_sp_status_timer', setTimeout(() => statusEl.text(''), 3000));
            },
            error: function(xhr) {
                const msg = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0])))
                    || 'Save failed.';
                statusEl.addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + msg);
            },
            complete: function() {
                btn.prop('disabled', false).html(originalIcon);
            }
        });
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
    // Must run before ANY submission of #main-form, including the
    // resubmitWithConfirm() path which calls form.submit() directly and so
    // bypasses the form's 'submit' event listener entirely.
    // DIAGNOSTIC INSTRUMENTATION: every field is now collected inside its own
    // try/catch so ONE bad/unexpected field can't silently abort the whole
    // collapse (previously: a single thrown error partway through the
    // forEach meant items_json stayed empty AND none of the fields after the
    // failure point got disabled, while fields processed before it still did
    // — an all-or-nothing failure that was invisible unless you happened to
    // be watching the console at the exact moment of submit, since a normal
    // form submit navigates away and wipes the console log). The
    // console.log/console.error calls below are intentional and should stay
    // even after this is confirmed working — with DevTools "Preserve log"
    // enabled they give a permanent, unambiguous record of whether this
    // function ran and what it did on any given submit.
    //
    // ROOT CAUSE FIX: this used to be a plain `function collectItemsJsonForSubmit() {`
    // declaration, which scopes it to THIS $(document).ready(...) closure only.
    // resubmitWithConfirm() and the addEventListener('submit', ...) handler
    // below both call this function from OUTSIDE this closure (they always
    // have, since before this file was ever touched for this fix) — so every
    // call was throwing "ReferenceError: collectItemsJsonForSubmit is not
    // defined" at actual submit time, on every single invoice, silently
    // aborting before items_json was ever populated or any items[] field was
    // disabled. Small invoices never needed the collapse to survive PHP's
    // multipart parsing, so this broke invisibly until a large invoice hit
    // the real limit. Assigning to `window.` (same pattern already used by
    // addNewRow/removeRow below, for the same cross-scope-visibility reason)
    // makes it reachable from anywhere on the page, regardless of where it's
    // declared relative to $(document).ready(...).
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
        const certNo  = data.certificate_no   || '';
        const trayNo  = data.tray_no          || '';
        // FIX (POS Selling Price): kept as a plain empty string (not 0) when
        // unset, same treatment as certNo/trayNo above — 0 is a real,
        // different value (a free/zero-priced item) from "not set yet",
        // and posScan() on the POS side specifically distinguishes those
        // two cases (null => "selling price not set" error).
        const sellingPrice = (data.selling_price ?? '') === null ? '' : (data.selling_price ?? '');
        // FEATURE (quick Selling Price save): only an already-persisted item
        // (loaded from existingItems, which now carries its own id — see
        // PurchaseInvoiceController::edit()) has a row to PATCH. A row just
        // added in this edit session has no id yet — it doesn't exist in the
        // database until this whole form is submitted once — so no
        // quick-save button is rendered for it (see below).
        const itemId = data.id || null;

        const purityOptions = `@foreach($purities as $p)<option value="{{ $p->value }}" ${purity == {{ $p->value }} ? 'selected' : ''}>{{ $p->label }}</option>@endforeach`;

        return `
        <tr class="item-row" data-item-index="${index}" data-item-id="${itemId || ''}">
            <td>
                <div class="product-wrapper">
                    <input type="text" name="items[${index}][item_name]" class="form-control item-name-input" placeholder="Product Name" value="${name}" required>
                    <input type="hidden" name="items[${index}][barcode_number]" value="${data.barcode_number || ''}">
                    <select name="items[${index}][category_id]" class="form-control form-control-sm category-select mt-1">
                        ${categoryOptionsHtml(data.category_id)}
                    </select>
                    <select name="items[${index}][subcategory_id]" class="form-control form-control-sm subcategory-select mt-1">
                        <option value="">Select Subcategory</option>
                    </select>
                    <input type="text" name="items[${index}][tray_no]" class="form-control form-control-sm tray-no-input mt-1" value="${trayNo}" placeholder="Tray No">
                    <input type="file" name="items[${index}][image]" class="form-control form-control-sm item-image-input mt-1" accept="image/*">
                </div>
            </td>
            <td><input type="text" name="items[${index}][item_description]" class="form-control" value="${desc}" required></td>
            <td><input type="text" name="items[${index}][certificate_no]" class="form-control" value="${certNo}" placeholder="e.g. GIA 123456"></td>
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
            {{--
                FIX (POS Selling Price): independent of every costing column
                to its left — never computed from purity/making/material rate
                and doesn't feed into any of them either. See
                PurchaseInvoiceItem::selling_price and
                PurchaseInvoiceController::createItems(). Optional: leave
                blank to decide the price later, from this same screen.

                FEATURE (quick Selling Price save): the Save icon next to it
                PUTs just this one field to purchase_invoice_items.{id}/
                selling-price (PurchaseInvoiceController::updateSellingPrice())
                instead of requiring the big "Update Invoice" submit below,
                which would otherwise re-save the whole invoice — master
                info, every item/part, totals, and accounting — just to
                change a price. Only shown for an already-saved item (it has
                an id to target); a row just added in this session has to be
                saved once via the normal form first.
            --}}
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" name="items[${index}][selling_price]" step="any" min="0" value="${sellingPrice}" class="form-control selling-price" placeholder="Optional">
                    ${itemId ? `
                    <button type="button" class="btn btn-outline-success selling-price-save-btn" data-item-id="${itemId}"
                            title="Save Selling Price only — does not resubmit the whole invoice">
                        <i class="fas fa-save"></i>
                    </button>` : ''}
                </div>
                ${itemId
                    ? `<small class="text-muted selling-price-save-status d-block" style="min-height:1em;"></small>`
                    : `<small class="text-muted d-block">Save invoice once to enable quick-save.</small>`
                }
            </td>
            <td class="item-img-cell" style="text-align:center;vertical-align:middle;padding:4px;">${
                data.image_url
                    ? `<img src="${data.image_url}" alt="${name || ''}" title="${name || ''}"
                            style="width:42px;height:42px;object-fit:cover;border-radius:6px;
                                  cursor:pointer;border:1px solid #dee2e6;"
                            onclick="window.open('${data.image_url}','_blank')">`
                    : ''
            }</td>
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
                                <th>Part</th><th>Description</th><th>Diamond Ct.</th><th>Rate</th>
                                <th>Stone Ct.</th><th>Stone Rate</th><th>Cert. Charges</th><th>Total</th><th></th>
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
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][certification_charges]" step="any" value="${data.certification_charges || 0}" class="form-control part-cert-charges"></td>
            <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][total]" step="any" value="${data.total || 0}" class="form-control part-total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-part"><i class="fas fa-times"></i></button></td>
        </tr>`;
    }

    // ===== LOAD EXISTING ITEMS =====
    // DIAGNOSTIC / RESILIENCE: each row is built inside its own try/catch.
    // Previously, one bad item (unexpected/missing field in itemData) could
    // throw and abort the ENTIRE forEach loop partway through — silently
    // leaving the rest of the invoice's rows missing from the table (and,
    // since $(document).ready() callbacks aren't guaranteed to keep the rest
    // of this script block from continuing, this was a plausible source of
    // the "everything downstream looks fine in the page source but the form
    // behaves as if this never ran" symptom). Now a single bad row is
    // logged and skipped instead of taking the other ~199 rows down with it.
    let rowsBuilt = 0;
    let rowsFailed = 0;
    existingItems.forEach(function(itemData, i) {
        try {
            $('#PurchaseTable').append(buildItemRowHtml(i, itemData));

            const itemRow  = $('#PurchaseTable tr.item-row').last();
            const partsRow = itemRow.next('.parts-row');

            if (itemData.parts && itemData.parts.length > 0) {
                partsRow.show();
                itemData.parts.forEach(function(partData, j) {
                    partsRow.find('.parts-table tbody').append(buildPartRowHtml(i, j, partData));
                });
            }

            recalcItemGrossWeight(itemRow);

            // Load product image if this item was linked to a product
            if (!itemData.image_url && itemData.product_id) {
                fetchAndShowImage(itemRow, itemData.product_id);
            }

            // Restore the subcategory dropdown (category is rendered pre-selected
            // already; subcategory needs an AJAX fetch scoped to that category).
            if (itemData.category_id) {
                loadSubcategoryOptions(itemRow.find('.subcategory-select'), itemData.category_id, itemData.subcategory_id);
            }

            rowsBuilt++;
        } catch (err) {
            rowsFailed++;
            console.error('[existingItems] failed to build row for item index ' + i + ' (id=' + (itemData && itemData.id) + '):', err, itemData);
        }
    });
    console.log('[existingItems] built ' + rowsBuilt + ' of ' + existingItems.length + ' item row(s)' + (rowsFailed ? (', ' + rowsFailed + ' FAILED — see errors above') : '') + '.');

    calculateTotals();

    // ===== PAYMENT METHOD — show correct section on load =====
    function initPaymentFields() {
        const val = $('#payment_method').val();
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
    }
    initPaymentFields();

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

    // ===== PRODUCT TOGGLE (PARTS ONLY) =====
    // NOTE: this toggle used to also exist on the main Item Name field, but
    // main invoice items are never linked to the Product catalog — Item Name
    // is always free-text input there (Category/Subcategory dropdowns handle
    // classification instead). It's kept here only for parts rows (diamond /
    // stone components), where linking to a catalog Product is still useful
    // (e.g. to pull the part's measurement unit).
    $(document).on('click', '.toggle-product, .revert-to-name', function() {
        const isReverting = $(this).hasClass('revert-to-name');
        const wrapper     = $(this).closest('.product-wrapper');
        const isPart      = wrapper.closest('tr').hasClass('part-item-row');
        if (!isPart) return;

        const itemIdx  = wrapper.closest('.parts-row').prev('.item-row').data('item-index');
        const partIdx  = wrapper.closest('.part-item-row').data('part-index');
        const namePath = `items[${itemIdx}][parts][${partIdx}]`;

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

        // Load product image — only for main item rows, not part rows
        const itemRow = row.closest('tr.item-row');
        if (itemRow.length) {
            fetchAndShowImage(itemRow, productId);
        }
    });

    // ================= CALCULATIONS =================

    $(document).on('input', '.net-weight', function() {
        recalcItemGrossWeight($(this).closest('tr.item-row'));
    });

    $(document).on('input change', '.purity, .making-rate, .vat-percent, .material-type, #gold_rate_aed, #diamond_rate_aed', function() {
        const row = $(this).closest('tr.item-row');
        if (row.length) recalcItemGrossWeight(row);
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

        const netWt = parseFloat(itemRow.find('.net-weight').val()) || 0;

        let totalDiamondCTS = 0;
        let totalStoneCTS   = 0;
        itemRow.next('.parts-row').find('.part-item-row').each(function() {
            totalDiamondCTS += parseFloat($(this).find('.part-qty').val())       || 0;
            totalStoneCTS   += parseFloat($(this).find('.part-stone-qty').val()) || 0;
        });

        const newGross = netWt + (totalDiamondCTS / 5) + (totalStoneCTS / 5);
        itemRow.find('.gross-weight').val(newGross.toFixed(4));

        calculateRow(itemRow);
        calculateTotals();
    }

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

    function calculateTotals() {
        let sumNetWt        = 0;
        let sum995          = 0;
        let sumMakingValue  = 0;
        let sumMaterial     = 0;
        let sumVAT          = 0;
        let sumGoldGross    = 0;
        let totalDiamondCTS = 0;
        let totalStoneQty   = 0;
        let totalDiamondVal = 0;
        let totalStoneVal   = 0;
        let sumItemTotal    = 0;

        $('#PurchaseTable tr.item-row').each(function() {
            const itemRow      = $(this);
            const materialType = itemRow.find('.material-type').val();
            const grossVal     = parseFloat(itemRow.find('.gross-weight').val())  || 0;

            sumNetWt       += parseFloat(itemRow.find('.purity-weight').val())  || 0;
            sum995         += parseFloat(itemRow.find('.col-995').val())         || 0;
            sumMakingValue += parseFloat(itemRow.find('.making-value').val())   || 0;
            sumMaterial    += parseFloat(itemRow.find('.material-value').val())  || 0;
            sumVAT         += parseFloat(itemRow.find('.vat-amount').val())      || 0;
            sumItemTotal   += parseFloat(itemRow.find('.item-total').val())      || 0;

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

        const netTotal = sumItemTotal;

        $('#sum_gold_gross_weight').val(sumGoldGross.toFixed(4));
        $('#sum_diamond_cts').val(totalDiamondCTS.toFixed(4));
        $('#sum_stone_qty').val(totalStoneQty.toFixed(4));
        $('#sum_purity_weight').val(sumNetWt.toFixed(4));
        $('#sum_995').val(sum995.toFixed(4));
        $('#sum_making_value').val(sumMakingValue.toFixed(4));
        $('#sum_material_value').val(sumMaterial.toFixed(4));
        $('#sum_diamond_value').val(totalDiamondVal.toFixed(4));
        $('#sum_stone_value').val(totalStoneVal.toFixed(4));
        $('#sum_vat_amount').val(sumVAT.toFixed(4));
        $('#net_amount_display').val(netTotal.toFixed(4));
        $('#net_amount').val(netTotal.toFixed(4));

        const currency = $('#currency').val();
        const exRate   = parseFloat($('#exchange_rate').val()) || 1;
        $('#converted_total').val(currency === 'USD' ? (netTotal * exRate).toFixed(4) : netTotal.toFixed(4));

        const pm = $('#payment_method').val();
        if (pm === 'material+making cost' || pm === 'material') {
            $('input[name="material_weight"]').val(sum995.toFixed(4));
            $('input[name="material_purity"]').val(sumNetWt.toFixed(4));
            $('input[name="material_value"]').val(sumMaterial.toFixed(4));
            $('#making_charges_display').val(sumMakingValue.toFixed(4));
        }
    }

    // ================= RATE CONVERSION =================
    $(document).on('input', '#gold_rate_usd, #gold_rate_aed_ounce, #diamond_rate_usd, #diamond_rate_aed, #exchange_rate', function() {
        const id     = $(this).attr('id');
        const exRate = parseFloat($('#exchange_rate').val()) || 3.6725;

        if (id === 'gold_rate_usd' || id === 'exchange_rate') {
            const goldUsd = parseFloat($('#gold_rate_usd').val()) || 0;
            $('#gold_rate_aed_ounce').val((goldUsd * exRate).toFixed(4));
        }
        $('#gold_rate_aed').val(((parseFloat($('#gold_rate_aed_ounce').val()) || 0) / TROY_OUNCE_TO_GRAM).toFixed(4));

        if (id === 'diamond_rate_usd' || id === 'exchange_rate') {
            const diaUsd = parseFloat($('#diamond_rate_usd').val()) || 0;
            $('#diamond_rate_aed').val((diaUsd * exRate).toFixed(4));
        }

        $('#PurchaseTable tr.item-row').each(function() { calculateRow($(this)); });
        calculateTotals();
    });

    // ================= PARTS CALCULATION =================
    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate, .part-cert-charges', function() {
        const row       = $(this).closest('tr');
        const qty       = parseFloat(row.find('.part-qty').val())             || 0;
        const rate      = parseFloat(row.find('.part-rate').val())            || 0;
        const stoneQty  = parseFloat(row.find('.part-stone-qty').val())       || 0;
        const stoneRate = parseFloat(row.find('.part-stone-rate').val())      || 0;
        const certChg   = parseFloat(row.find('.part-cert-charges').val())    || 0;

        row.find('.part-total').val(((qty * rate) + (stoneQty * stoneRate) + certChg).toFixed(4));

        const itemRow = row.closest('.parts-row').prev('.item-row');
        recalcItemGrossWeight(itemRow);
    });

    // ================= EXCEL IMPORT =================
    // Same "keep existing items vs. replace" mode-choice modal as the Create
    // Purchase Invoice page. If the item table already has real data (either
    // rows loaded from this invoice, or rows the user typed/added since),
    // ask whether the Excel data should be appended to it or should replace
    // it entirely. If the table is empty, skip the prompt and import
    // straight in "append" mode, same as Create does.
    //
    // NOTE: none of the row-parsing / field-mapping / calculation logic
    // below was changed — only wrapped so it can run in "append" or
    // "replace" mode.
    let pendingExcelFile = null;

    function tableHasRealData() {
        return $('#PurchaseTable tr.item-row').toArray().some(function (row) {
            const $row = $(row);
            const name = $row.find('.item-name-input').val();
            const productSelected = $row.find('.product-select').val();
            return (name && name.trim() !== '') || (productSelected && productSelected !== '');
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

    function runExcelImport(file, mode) {
        const unmatchedPurityRows = [];
        const categorySubWarnings = [];

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
            jsonData.forEach(row => {
                if (row['Item Name'] && row['Item Name'].toString().trim() !== '') {
                    addNewRow();
                    currentItemRow = $('#PurchaseTable tr.item-row').last();
                    currentItemRow.find('.item-name-input').val(row['Item Name']);
                    currentItemRow.find('input[name*="[item_description]"]').val(row['Description'] || '');
                    currentItemRow.find('input[name*="[certificate_no]"]').val(row['Certificate No'] || '');
                    currentItemRow.find('.tray-no-input').val(row['Tray No'] || '');
                    applyCategorySubcategoryFromCodes(
                        currentItemRow, row['Category Code'], row['Subcategory Code'],
                        row['Item Name'], categorySubWarnings
                    );

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
                if (row['Part Name'] && row['Part Name'].toString().trim() !== '' && currentItemRow) {
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

            const warningBlocks = [];
            if (unmatchedPurityRows.length > 0) {
                warningBlocks.push(
                    'Purity value with no matching dropdown option:\n' +
                    unmatchedPurityRows.map(r => `- ${r.item}: ${r.purity}`).join('\n')
                );
            }
            if (categorySubWarnings.length > 0) {
                warningBlocks.push(
                    'Category/Subcategory Code not found:\n' +
                    categorySubWarnings.map(w => `- ${w}`).join('\n')
                );
            }

            if (warningBlocks.length > 0) {
                alert(
                    'Items imported, but please check the following manually:\n\n' +
                    warningBlocks.join('\n\n')
                );
            } else {
                alert(
                    mode === 'replace'
                        ? 'Existing items removed. Items imported successfully!'
                        : 'Items imported successfully and added to the existing list!'
                );
            }

            $('#excel_import').val('');
            pendingExcelFile = null;
        };
        reader.readAsArrayBuffer(file);
    }

});

function resubmitWithConfirm() {
    const form  = document.getElementById('main-form');
    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'confirm_delete_printed';
    input.value = '1';
    form.appendChild(input);
    collectItemsJsonForSubmit(); // form.submit() below bypasses the 'submit' event listener, so this must run explicitly first
    form.submit();
}

// DIAGNOSTIC: this block sits OUTSIDE the $(document).ready(...) callback
// above, at the top level of this <script> tag, so it runs the instant the
// browser parses this line — it does NOT wait on, and is not blocked by,
// anything inside $(document).ready(...) (including the loop that builds
// the ~200 item rows). The console.log at the end is intentional and should
// stay: if it is MISSING from the console on a fresh hard-refresh of this
// page, something earlier in this same <script> tag threw before reaching
// this line (check the console for the actual error, it will be the real
// root cause) and the submit handler below was never attached at all.
try {
    document.getElementById('main-form').addEventListener('submit', function() {
        collectItemsJsonForSubmit();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    });
    console.log('[items_json] submit listener attached successfully on page load.');
} catch (err) {
    console.error('[items_json] FAILED to attach submit listener on page load:', err);
}
</script>
@endsection
