@extends('layouts.app')
@section('title', isset($consignment) ? 'Edit — ' . $consignment->consignment_no : 'New Consignment')

@section('content')
@php
    $isEdit    = isset($consignment);
    $itemsJson = $itemsJson ?? '[]';
@endphp

<div class="row"><div class="col">
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">
        <i class="fas fa-handshake me-2 text-primary"></i>
        {{ $isEdit ? 'Edit — ' . $consignment->consignment_no : 'New Consignment' }}
      </h2>
      <a href="{{ $isEdit ? route('consignments.show', $consignment->id) : route('consignments.index') }}"
         class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
      </a>
    </header>

    @if($errors->any())
      <div class="alert alert-danger mx-3 mt-3">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger mx-3 mt-3">{{ session('error') }}</div>
    @endif

    <form method="POST"
          action="{{ $isEdit ? route('consignments.update', $consignment->id) : route('consignments.store') }}"
          id="consignment-form">
      @csrf
      @if($isEdit) @method('PUT') @endif

      <div class="card-body">

        {{-- ===================== HEADER ===================== --}}
        <div class="row mb-4">

          @if(!$isEdit)
          <div class="col-md-3">
            <label class="fw-bold">Direction <span class="text-danger">*</span></label>
            <select name="direction" id="direction" class="form-control border-primary" required>
              <option value="">-- Select Direction --</option>
              <option value="inbound"  {{ old('direction') === 'inbound'  ? 'selected' : '' }}>
                ↓ Inbound — we receive goods from partner
              </option>
              <option value="outbound" {{ old('direction') === 'outbound' ? 'selected' : '' }}>
                ↑ Outbound — we send goods to partner
              </option>
            </select>
            <small class="text-muted">Inbound = CSG barcodes generated. Outbound = no barcodes.</small>
          </div>
          @else
          <input type="hidden" name="direction" value="{{ $consignment->direction }}">
          <div class="col-md-3">
            <label class="fw-bold">Direction</label>
            <div class="form-control bg-light">
              @if($consignment->direction === 'inbound')
                <span class="badge bg-success fs-6"><i class="fas fa-arrow-down me-1"></i>Inbound</span>
              @else
                <span class="badge bg-primary fs-6"><i class="fas fa-arrow-up me-1"></i>Outbound</span>
              @endif
            </div>
          </div>
          @endif

          <div class="col-md-3">
            <label class="fw-bold">Partner (Customer / Vendor) <span class="text-danger">*</span></label>
            <select name="partner_id" class="form-control select2-js" required>
              <option value="">-- Select Partner --</option>
              @foreach($partners as $p)
                <option value="{{ $p->id }}"
                  {{ old('partner_id', $isEdit ? $consignment->partner_id : '') == $p->id ? 'selected' : '' }}>
                  {{ $p->name }} [{{ strtoupper($p->account_type) }}]
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-2">
            <label class="fw-bold">Start Date <span class="text-danger">*</span></label>
            <input type="date" name="start_date" class="form-control" required
                   value="{{ old('start_date', $isEdit ? $consignment->start_date->format('Y-m-d') : now()->format('Y-m-d')) }}">
          </div>

          <div class="col-md-2">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control"
                   value="{{ old('end_date', $isEdit && $consignment->end_date ? $consignment->end_date->format('Y-m-d') : '') }}">
            <small class="text-muted">Leave blank for open-ended.</small>
          </div>

          <div class="col-md-2">
            <label>Duration Label</label>
            <input type="text" name="duration_label" class="form-control" placeholder="e.g. 3 months"
                   value="{{ old('duration_label', $isEdit ? $consignment->duration_label : '') }}">
          </div>

          <div class="col-12 col-md-2 mt-2">
            <label>Gold Rate (AED / <b>Gram</b>)</label>
            <input type="number" step="any" name="gold_rate_aed" id="gold_rate_aed"
                   class="form-control" value="{{ old('gold_rate_aed', 0) }}">
            <small class="text-danger fw-bold">Used for material value calc</small>
          </div>

          <div class="col-12 col-md-2 mt-2">
            <label>Diamond Rate (AED / <b>Gram</b>)</label>
            <input type="number" step="any" name="diamond_rate_aed" id="diamond_rate_aed"
                   class="form-control" value="{{ old('diamond_rate_aed', 0) }}">
          </div>

          <div class="col-md-4 mt-2">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="2"
            >{{ old('remarks', $isEdit ? $consignment->remarks : '') }}</textarea>
          </div>

        </div>

        {{-- ===================== BARCODE SCANNER ===================== --}}
        <div class="card mb-3 border-primary shadow-sm">
          <div class="card-body py-2" style="background:rgba(13,110,253,.07)">
            <div class="row align-items-center g-2">
              <div class="col-auto d-flex align-items-center">
                <i class="fas fa-barcode fa-2x text-primary me-2"></i>
                <strong class="text-primary">Barcode Scanner</strong>
              </div>
              <div class="col-md-5">
                <div class="input-group">
                  <input type="text" id="barcode_scan_input" class="form-control"
                         placeholder="Scan MJ-/MJT- barcode or press Enter…"
                         autocomplete="off">
                  <button type="button" class="btn btn-primary fw-bold" id="barcode_scan_btn">
                    <i class="fas fa-search"></i> Search
                  </button>
                </div>
                <small class="text-muted">
                  Finds purchased items (MJ-/MJT-) and auto-fills a row.
                  For external supplier items, use <strong>Add Item</strong> manually.
                </small>
              </div>
              <div class="col-md-5">
                <div id="barcode_scan_result" class="alert mb-0 py-2 px-3 d-none"
                     role="alert" style="font-size:.9rem;"></div>
              </div>
            </div>
          </div>
        </div>

        {{-- ===================== ITEMS TABLE ===================== --}}
        <section class="card">
          <header class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title">Consignment Items</h2>
            <div class="d-flex gap-2">
              <input type="file" id="excel_import" class="d-none" accept=".xlsx,.xls,.csv">
              <button type="button" class="btn btn-success btn-sm"
                      onclick="document.getElementById('excel_import').click()">
                <i class="fas fa-file-excel me-1"></i> Import Excel
              </button>
              <a href="{{ route('consignments.download_template') }}" class="btn btn-danger btn-sm">
                <i class="fas fa-download me-1"></i> Template
              </a>
              <button type="button" class="btn btn-primary btn-sm" onclick="addNewRow()">
                <i class="fas fa-plus me-1"></i> Add Item
              </button>
            </div>
          </header>

          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th width="10%" rowspan="2">Item Name</th>
                  <th width="10%" rowspan="2">Description</th>
                  <th width="6%"  rowspan="2">Purity</th>
                  <th rowspan="2">Gross Wt<br><small class="text-muted">(User Input)</small></th>
                  <th rowspan="2">Purity Wt</th>
                  <th rowspan="2">995</th>
                  <th colspan="2" class="text-center">Making</th>
                  <th width="6%" rowspan="2">Material</th>
                  <th rowspan="2">Material Val</th>
                  <th rowspan="2">VAT %</th>
                  <th rowspan="2">Agreed Value<br><small class="text-muted">(Override)</small></th>
                  <th width="5%" rowspan="2">Action</th>
                </tr>
                <tr>
                  <th>Rate</th>
                  <th>Value</th>
                </tr>
              </thead>
              <tbody id="ConsignmentTable"></tbody>
            </table>
          </div>

          <div class="row mt-3 px-3 pb-3">
            <div class="col-md-2">
              <label>Total Gross Wt</label>
              <input type="text" id="sum_gross_weight" class="form-control text-primary fw-bold" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Purity Wt</label>
              <input type="text" id="sum_purity_weight" class="form-control text-success fw-bold" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Making Val</label>
              <input type="text" id="sum_making_value" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Material Val</label>
              <input type="text" id="sum_material_value" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Parts Val</label>
              <input type="text" id="sum_parts_value" class="form-control text-warning fw-bold" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Agreed Value</label>
              <input type="text" id="sum_agreed_value" class="form-control text-danger fw-bold" readonly>
            </div>
          </div>
        </section>

      </div>

      <footer class="card-footer text-end">
        <a href="{{ $isEdit ? route('consignments.show', $consignment->id) : route('consignments.index') }}"
           class="btn btn-secondary me-2">Cancel</a>
        <button type="submit" class="btn btn-success" id="submit_btn">
          <i class="fas fa-save me-1"></i>
          {{ $isEdit ? 'Update Consignment' : 'Save Consignment' }}
        </button>
      </footer>
    </form>
  </section>
</div></div>

<script>
$(document).ready(function () {
    const products         = @json($products);
    const INIT_ITEMS       = {!! $itemsJson !!};
    const IS_EDIT          = {{ $isEdit ? 'true' : 'false' }};
    const BARCODE_SCAN_URL = '{{ route("consignments.scan_barcode_form") }}';

    $('.select2-js').select2({ width: '100%' });

    // =========================================================================
    // PURITY SNAP
    // Snaps a decimal purity value (e.g. 0.75) to the nearest <select> option
    // =========================================================================
    function snapPurity(selectEl, purityVal) {
        const pur = parseFloat(purityVal) || 0;
        let nearestOpt = null;
        let minDiff    = Infinity;
        selectEl.find('option').each(function () {
            const diff = Math.abs(parseFloat($(this).val()) - pur);
            if (diff < minDiff) { minDiff = diff; nearestOpt = $(this).val(); }
        });
        if (nearestOpt !== null) selectEl.val(nearestOpt);
    }

    // =========================================================================
    // BARCODE SCANNER
    // =========================================================================

    function showScanResult(msg, type) {
        const el = $('#barcode_scan_result');
        el.removeClass('d-none alert-success alert-danger alert-warning')
          .addClass('alert-' + type).html(msg);
        clearTimeout(window._scanTimer);
        window._scanTimer = setTimeout(() => el.addClass('d-none'), 6000);
    }

    function handleBarcodeScan() {
        const barcode = $('#barcode_scan_input').val().trim();
        if (!barcode) { $('#barcode_scan_input').focus(); return; }

        $('#barcode_scan_btn').prop('disabled', true)
                              .html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url:    BARCODE_SCAN_URL,
            method: 'GET',
            data:   { barcode },
            success: function (data) {
                if (!data.success) {
                    showScanResult('<i class="fas fa-times-circle"></i> ' + data.message, 'danger');
                    return;
                }

                // Duplicate check
                let duplicate = false;
                $('#ConsignmentTable tr.item-row').each(function () {
                    if ($(this).find('input[name*="[source_barcode]"]').val() === barcode) {
                        duplicate = true; return false;
                    }
                });
                if (duplicate) {
                    showScanResult(
                        '<i class="fas fa-exclamation-triangle"></i> <strong>' +
                        barcode + '</strong> is already on this consignment.', 'warning'
                    );
                    return;
                }

                // Remove blank starter row if untouched
                const firstRow = $('#ConsignmentTable tr.item-row').first();
                if ($('#ConsignmentTable tr.item-row').length === 1 &&
                    !firstRow.find('.item-name-input').val()) {
                    firstRow.next('.parts-row').remove();
                    firstRow.remove();
                }

                addNewRow();
                const newRow = $('#ConsignmentTable tr.item-row').last();

                newRow.find('.item-name-input').val(data.item_name || '');
                newRow.find('input[name*="[source_barcode]"]').val(barcode);
                newRow.find('input[name*="[item_description]"]').val(data.item_description || '');
                newRow.find('.gross-weight').val(parseFloat(data.gross_weight) || 0);
                newRow.find('.making-rate').val(data.making_rate || 0);
                newRow.find('.material-type').val(data.material_type || 'gold');
                newRow.find('.vat-percent').val(data.vat_percent || 0);

                snapPurity(newRow.find('.purity'), data.purity);

                if (data.parts && data.parts.length > 0) {
                    const partsRow  = newRow.next('.parts-row');
                    const partsBody = partsRow.find('.parts-table tbody');
                    const itemIndex = newRow.data('item-index');
                    partsRow.show();
                    data.parts.forEach((part, j) => {
                        partsBody.append(buildPartRowHtml(itemIndex, j, part));
                    });
                }

                recalcRow(newRow);

                newRow.addClass('table-warning');
                setTimeout(() => newRow.removeClass('table-warning'), 2000);

                const srcBadge = data.source === 'purchase'
                    ? '<span class="badge bg-info ms-1">from Purchase</span>'
                    : '<span class="badge bg-success ms-1">from Consignment</span>';
                showScanResult(
                    '<i class="fas fa-check-circle"></i> Added: <strong>' +
                    (data.item_name || barcode) + '</strong>' + srcBadge, 'success'
                );
            },
            error: function (xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message
                    : 'Search failed. Check barcode and try again.';
                showScanResult('<i class="fas fa-times-circle"></i> ' + msg, 'danger');
            },
            complete: function () {
                $('#barcode_scan_btn').prop('disabled', false)
                                     .html('<i class="fas fa-search"></i> Search');
                $('#barcode_scan_input').val('').focus();
            }
        });
    }

    $('#barcode_scan_input').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); handleBarcodeScan(); }
    });
    $('#barcode_scan_btn').on('click', handleBarcodeScan);

    // =========================================================================
    // EXCEL IMPORT
    // =========================================================================

    $('#excel_import').on('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const workbook = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
            const jsonData = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);

            if (!jsonData.length) { alert('No data found in the file.'); return; }

            const firstRow = $('#ConsignmentTable tr.item-row').first();
            if ($('#ConsignmentTable tr.item-row').length === 1 &&
                !firstRow.find('.item-name-input').val()) {
                firstRow.next('.parts-row').remove();
                firstRow.remove();
            }

            let currentItemRow = null;

            jsonData.forEach(function (row) {
                if (row['Item Name'] && String(row['Item Name']).trim() !== '') {
                    addNewRow();
                    currentItemRow = $('#ConsignmentTable tr.item-row').last();

                    currentItemRow.find('.item-name-input').val(row['Item Name']);
                    currentItemRow.find('input[name*="[item_description]"]').val(row['Description'] || '');
                    snapPurity(currentItemRow.find('.purity'), row['Purity'] || 0);
                    currentItemRow.find('.gross-weight').val(parseFloat(row['Gross Wt']) || 0);
                    currentItemRow.find('.making-rate').val(row['Making Rate'] || 0);
                    currentItemRow.find('.material-type').val(String(row['Material'] || 'gold').toLowerCase());
                    currentItemRow.find('.vat-percent').val(row['VAT %'] || 0);

                    const agreedVal = parseFloat(row['Agreed Value'] || 0);
                    currentItemRow.find('.agreed-value').val(agreedVal);
                    if (agreedVal > 0) currentItemRow.find('.agreed-value').data('manual', true);

                    recalcRow(currentItemRow);
                }

                if (row['Part Name'] && String(row['Part Name']).trim() !== '' && currentItemRow) {
                    const partsRow  = currentItemRow.next('.parts-row');
                    const partsBody = partsRow.find('.parts-table tbody');
                    const itemIndex = currentItemRow.data('item-index');
                    const partIndex = partsBody.find('tr').length;
                    partsRow.show();
                    partsBody.append(buildPartRowHtml(itemIndex, partIndex, {
                        item_name:        row['Part Name'],
                        part_description: row['Part Desc']  || '',
                        qty:              row['Part Qty']   || 0,
                        rate:             row['Part Rate']  || 0,
                        stone_qty:        row['Stone Qty']  || 0,
                        stone_rate:       row['Stone Rate'] || 0,
                        total:            0,
                    }));
                    partsBody.find('tr').last().find('.part-qty').trigger('input');
                }
            });

            calculateTotals();
            alert('Items imported successfully!');
            $('#excel_import').val('');
        };
        reader.readAsArrayBuffer(file);
    });

    // =========================================================================
    // PARTS TOGGLE
    // =========================================================================

    $(document).on('click', '.toggle-parts', function () {
        $(this).closest('tr').next('.parts-row').fadeToggle(200);
    });

    // =========================================================================
    // PRODUCT SELECTOR TOGGLE
    // =========================================================================

    $(document).on('click', '.toggle-product, .revert-to-name', function () {
        const isReverting = $(this).hasClass('revert-to-name');
        const wrapper     = $(this).closest('.product-wrapper');
        const isPart      = wrapper.closest('tr').hasClass('part-item-row');
        const itemIdx     = isPart
            ? wrapper.closest('.parts-row').prev('.item-row').data('item-index')
            : wrapper.closest('.item-row').data('item-index');
        const namePath = isPart
            ? `items[${itemIdx}][parts][${wrapper.closest('.part-item-row').data('part-index')}]`
            : `items[${itemIdx}]`;

        if (isReverting) {
            wrapper.html(`
                <input type="text" name="${namePath}[item_name]"
                       class="form-control item-name-input" placeholder="Item Name">
                <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
            `);
        } else {
            wrapper.html(`
                <select name="${namePath}[product_id]"
                        class="form-control select2-js product-select mb-1">
                    <option value="">Select Product</option>
                    ${products.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                </select>
                <select name="${namePath}[variation_id]"
                        class="form-control select2-js variation-select">
                    <option value="">Select Variation</option>
                </select>
                <button type="button" class="btn btn-link p-0 revert-to-name mt-1">Write Name</button>
            `).find('.select2-js').select2({ width: '100%' });
        }
    });

    $(document).on('change', '.product-select', function () {
        const productId       = $(this).val();
        const variationSelect = $(this).closest('tr').find('.variation-select');
        variationSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        if (!productId) {
            variationSelect.html('<option value="">Select Variation</option>').prop('disabled', false);
            return;
        }
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

    // =========================================================================
    // ROW INDEX MANAGEMENT
    // =========================================================================

    function updateRowIndexes() {
        $('#ConsignmentTable tr.item-row').each(function (i) {
            const itemRow = $(this);
            itemRow.attr('data-item-index', i);
            itemRow.find('input, select').each(function () {
                const name = $(this).attr('name');
                if (name) $(this).attr('name', name.replace(/items\[\d+\]/, `items[${i}]`));
            });
            itemRow.next('.parts-row').find('.part-item-row').each(function (j) {
                $(this).attr('data-part-index', j);
                $(this).find('input, select').each(function () {
                    const name = $(this).attr('name');
                    if (name) $(this).attr('name',
                        name.replace(/items\[\d+\]/, `items[${i}]`)
                            .replace(/parts\[\d+\]/, `parts[${j}]`)
                    );
                });
            });
        });
    }

    // =========================================================================
    // ADD NEW ITEM ROW
    // =========================================================================

    window.addNewRow = function (data) {
        data = data || null;
        const nextIndex = $('#ConsignmentTable tr.item-row').length;

        const purityOptions = `@foreach($purities as $p)
            <option value="{{ $p->value }}">{{ $p->label }}</option>
        @endforeach`;

        const rowHtml = `
        <tr class="item-row" data-item-index="${nextIndex}">
            <td>
                <div class="product-wrapper">
                    <input type="text" name="items[${nextIndex}][item_name]"
                           class="form-control item-name-input" placeholder="Item Name"
                           value="">
                    <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
                </div>
                <input type="hidden" name="items[${nextIndex}][source_barcode]" value="">
            </td>
            <td><input type="text" name="items[${nextIndex}][item_description]"
                       class="form-control" value=""></td>
            <td>
                <select name="items[${nextIndex}][purity]" class="form-control purity">
                    ${purityOptions}
                </select>
            </td>
            <td><input type="number" name="items[${nextIndex}][gross_weight]" step="any"
                       value="0" class="form-control gross-weight"></td>
            <td><input type="number" name="items[${nextIndex}][purity_weight]" step="any"
                       value="0" class="form-control purity-weight" readonly></td>
            <td><input type="number" name="items[${nextIndex}][col_995]" step="any"
                       value="0" class="form-control col-995" readonly></td>
            <td><input type="number" name="items[${nextIndex}][making_rate]" step="any"
                       value="0" class="form-control making-rate"></td>
            <td><input type="number" name="items[${nextIndex}][making_value]" step="any"
                       value="0" class="form-control making-value bg-light" readonly></td>
            <td>
                <select name="items[${nextIndex}][material_type]" class="form-control material-type">
                    <option value="gold">Gold</option>
                    <option value="diamond">Diamond</option>
                </select>
            </td>
            <td><input type="number" name="items[${nextIndex}][material_value]" step="any"
                       value="0" class="form-control material-value bg-light" readonly></td>
            <td><input type="number" name="items[${nextIndex}][vat_percent]" step="any"
                       value="0" class="form-control vat-percent"></td>
            <td><input type="number" name="items[${nextIndex}][agreed_value]" step="any"
                       value="0" class="form-control agreed-value fw-bold text-danger"></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-row-btn" title="Remove">
                    <i class="fas fa-times"></i>
                </button>
                <button type="button" class="btn btn-sm btn-primary toggle-parts" title="Parts">
                    <i class="fas fa-wrench"></i>
                </button>
            </td>
        </tr>
        <tr class="parts-row" style="display:none;background:#efefef">
            <td colspan="13">
                <div class="parts-wrapper">
                    <table class="table table-sm table-bordered parts-table">
                        <thead>
                            <tr>
                                <th>Part Name</th><th>Description</th>
                                <th>Diamond Ct.</th><th>Rate</th>
                                <th>Stone Ct.</th><th>Stone Rate</th>
                                <th>Total</th><th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary add-part">+ Add Part</button>
                </div>
            </td>
        </tr>`;

        $('#ConsignmentTable').append(rowHtml);
        const newItemRow = $('#ConsignmentTable tr.item-row').last();
        updateRowIndexes();

        if (data) {
            newItemRow.find('.item-name-input').val(data.item_name || '');
            newItemRow.find('input[name*="[item_description]"]').val(data.item_description || '');
            newItemRow.find('input[name*="[source_barcode]"]').val(data.source_barcode || '');
            newItemRow.find('.gross-weight').val(parseFloat(data.gross_weight) || 0);
            newItemRow.find('.making-rate').val(data.making_rate || 0);
            newItemRow.find('.material-type').val(data.material_type || 'gold');
            newItemRow.find('.vat-percent').val(data.vat_percent || 0);

            snapPurity(newItemRow.find('.purity'), data.purity);

            const agreedVal = parseFloat(data.agreed_value || 0);
            newItemRow.find('.agreed-value').val(agreedVal);
            if (agreedVal > 0) newItemRow.find('.agreed-value').data('manual', true);

            if (data.parts && data.parts.length > 0) {
                const partsRow  = newItemRow.next('.parts-row');
                const partsBody = partsRow.find('.parts-table tbody');
                partsRow.show();
                data.parts.forEach((part, j) => {
                    partsBody.append(buildPartRowHtml(nextIndex, j, part));
                });
            }

            recalcRow(newItemRow);
        }
    };

    // =========================================================================
    // REMOVE ITEM ROW
    // =========================================================================

    $(document).on('click', '.remove-row-btn', function () {
        const row = $(this).closest('tr');
        if ($('#ConsignmentTable tr.item-row').length > 1) {
            row.next('.parts-row').remove();
            row.remove();
            updateRowIndexes();
            calculateTotals();
        }
    });

    // =========================================================================
    // BUILD PART ROW HTML
    // =========================================================================

    function buildPartRowHtml(itemIndex, partIndex, data) {
        data = data || {};
        return `
        <tr class="part-item-row" data-part-index="${partIndex}">
            <td>
                <div class="product-wrapper">
                    <input type="text"
                           name="items[${itemIndex}][parts][${partIndex}][item_name]"
                           class="form-control item-name-input" placeholder="Part Name"
                           value="${escVal(data.item_name)}">
                    <button type="button" class="btn btn-link p-0 toggle-product">Select Product</button>
                </div>
            </td>
            <td><input type="text"
                       name="items[${itemIndex}][parts][${partIndex}][part_description]"
                       class="form-control" value="${escVal(data.part_description)}"></td>
            <td>
                <div class="input-group">
                    <input type="number"
                           name="items[${itemIndex}][parts][${partIndex}][qty]"
                           step="any" value="${data.qty || 0}" class="form-control part-qty">
                    <span class="input-group-text">Ct.</span>
                </div>
            </td>
            <td><input type="number"
                       name="items[${itemIndex}][parts][${partIndex}][rate]"
                       step="any" value="${data.rate || 0}" class="form-control part-rate"></td>
            <td><input type="number"
                       name="items[${itemIndex}][parts][${partIndex}][stone_qty]"
                       step="any" value="${data.stone_qty || 0}" class="form-control part-stone-qty"></td>
            <td><input type="number"
                       name="items[${itemIndex}][parts][${partIndex}][stone_rate]"
                       step="any" value="${data.stone_rate || 0}" class="form-control part-stone-rate"></td>
            <td><input type="number"
                       name="items[${itemIndex}][parts][${partIndex}][total]"
                       step="any" value="${data.total || 0}" class="form-control part-total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-part">
                <i class="fas fa-times"></i>
            </button></td>
        </tr>`;
    }

    // =========================================================================
    // ADD / REMOVE PART ROWS
    // =========================================================================

    $(document).on('click', '.add-part', function () {
        const partsBody = $(this).closest('.parts-wrapper').find('.parts-table tbody');
        const itemRow   = $(this).closest('.parts-row').prev('.item-row');
        const itemIndex = itemRow.data('item-index');
        const partIndex = partsBody.find('tr').length;
        partsBody.append(buildPartRowHtml(itemIndex, partIndex, {}));
    });

    $(document).on('click', '.remove-part', function () {
        const itemRow = $(this).closest('.parts-row').prev('.item-row');
        $(this).closest('tr').remove();
        recalcRow(itemRow);
    });

    // =========================================================================
    // PART CALCULATION
    // =========================================================================

    $(document).on('input', '.part-qty, .part-rate, .part-stone-qty, .part-stone-rate', function () {
        const row       = $(this).closest('tr');
        const qty       = parseFloat(row.find('.part-qty').val())       || 0;
        const rate      = parseFloat(row.find('.part-rate').val())       || 0;
        const stoneQty  = parseFloat(row.find('.part-stone-qty').val()) || 0;
        const stoneRate = parseFloat(row.find('.part-stone-rate').val())|| 0;
        row.find('.part-total').val(((qty * rate) + (stoneQty * stoneRate)).toFixed(4));
        recalcRow(row.closest('.parts-row').prev('.item-row'));
    });

    // =========================================================================
    // ITEM FIELD CHANGES
    // =========================================================================

    $(document).on('input change',
        '.gross-weight, .purity, .making-rate, .vat-percent, .material-type',
    function () {
        const row = $(this).closest('tr.item-row');
        if (row.length) recalcRow(row);
    });

    $(document).on('input', '.agreed-value', function () {
        $(this).data('manual', true);
        calculateTotals();
    });

    $(document).on('input', '#gold_rate_aed, #diamond_rate_aed', function () {
        $('#ConsignmentTable tr.item-row').each(function () { recalcRow($(this)); });
    });

    // =========================================================================
    // CORE ROW CALCULATION
    // =========================================================================

    function recalcRow(row) {
        const baseGross = parseFloat(row.find('.gross-weight').val()) || 0;

        let totalDiamondCTS = 0, totalStoneCTS = 0;
        row.next('.parts-row').find('.part-item-row').each(function () {
            totalDiamondCTS += parseFloat($(this).find('.part-qty').val())       || 0;
            totalStoneCTS   += parseFloat($(this).find('.part-stone-qty').val()) || 0;
        });

        const calcGross     = baseGross + (totalDiamondCTS / 5) + (totalStoneCTS / 5);
        const purity        = parseFloat(row.find('.purity').val())      || 0;
        const makingRate    = parseFloat(row.find('.making-rate').val())  || 0;
        const vatPercent    = parseFloat(row.find('.vat-percent').val())  || 0;
        const matType       = row.find('.material-type').val();
        const goldRate      = parseFloat($('#gold_rate_aed').val())       || 0;
        const diaRate       = parseFloat($('#diamond_rate_aed').val())    || 0;
        const rate          = matType === 'gold' ? goldRate : diaRate;
        const purityWeight  = calcGross * purity;
        const col995        = purityWeight > 0 ? purityWeight / 0.995 : 0;
        const makingValue   = calcGross * makingRate;
        const materialValue = rate * purityWeight;

        let partsTotal = 0;
        row.next('.parts-row').find('.part-item-row').each(function () {
            partsTotal += parseFloat($(this).find('.part-total').val()) || 0;
        });

        const vatAmount  = makingValue * vatPercent / 100;
        const autoAgreed = materialValue + makingValue + partsTotal + vatAmount;

        row.find('.purity-weight').val(purityWeight.toFixed(4));
        row.find('.col-995').val(col995.toFixed(4));
        row.find('.making-value').val(makingValue.toFixed(4));
        row.find('.material-value').val(materialValue.toFixed(4));

        const agreedInput = row.find('.agreed-value');
        if (!agreedInput.data('manual')) {
            agreedInput.val(autoAgreed.toFixed(2));
        }

        calculateTotals();
    }

    // =========================================================================
    // SUMMARY TOTALS
    // =========================================================================

    function calculateTotals() {
        let sumGross = 0, sumPurity = 0, sumMaking = 0,
            sumMaterial = 0, sumParts = 0, sumAgreed = 0;

        $('#ConsignmentTable tr.item-row').each(function () {
            const baseGross = parseFloat($(this).find('.gross-weight').val()) || 0;
            let diaCTS = 0, stoneCTS = 0;
            $(this).next('.parts-row').find('.part-item-row').each(function () {
                diaCTS   += parseFloat($(this).find('.part-qty').val())       || 0;
                stoneCTS += parseFloat($(this).find('.part-stone-qty').val()) || 0;
            });
            const calcGross = baseGross + (diaCTS / 5) + (stoneCTS / 5);

            sumGross    += calcGross;
            sumPurity   += parseFloat($(this).find('.purity-weight').val())  || 0;
            sumMaking   += parseFloat($(this).find('.making-value').val())   || 0;
            sumMaterial += parseFloat($(this).find('.material-value').val()) || 0;
            sumAgreed   += parseFloat($(this).find('.agreed-value').val())   || 0;

            $(this).next('.parts-row').find('.part-item-row').each(function () {
                sumParts += parseFloat($(this).find('.part-total').val()) || 0;
            });
        });

        $('#sum_gross_weight').val(sumGross.toFixed(4));
        $('#sum_purity_weight').val(sumPurity.toFixed(4));
        $('#sum_making_value').val(sumMaking.toFixed(2));
        $('#sum_material_value').val(sumMaterial.toFixed(2));
        $('#sum_parts_value').val(sumParts.toFixed(2));
        $('#sum_agreed_value').val(sumAgreed.toFixed(2));
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    function escVal(str) {
        return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // =========================================================================
    // FORM SUBMIT — serialize all editable items as a single JSON field
    //
    // Why: PHP's default max_input_vars = 1000 is easily exceeded when
    // importing 50+ items (each item has ~12 fields + parts = ~4000+ vars).
    // PHP silently truncates POST data beyond that limit, causing validation
    // errors like "items.49.agreed_value is required".
    //
    // Fix: disable all editable item inputs before submit (so they don't count
    // toward max_input_vars), then inject a single items_json hidden field
    // containing the entire items array as a JSON string. The controller
    // decodes this in resolveItems() before processing.
    //
    // Immutable rows (sold/returned) use hidden inputs appended directly to
    // #consignment-form by renderImmutableRow() — those are NOT inside
    // .item-row table cells, so they are NOT disabled here and POST normally.
    // =========================================================================

    document.getElementById('consignment-form').addEventListener('submit', function (e) {
        e.preventDefault();

        const btn = document.getElementById('submit_btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const itemsPayload = [];

        // Collect all editable (non-immutable) item rows
        $('#ConsignmentTable tr.item-row:not(.table-success):not(.table-warning)').each(function () {
            const row   = $(this);
            const parts = [];

            row.next('.parts-row').find('.part-item-row').each(function () {
                const pr = $(this);
                parts.push({
                    item_name:        pr.find('input[name*="[item_name]"]').val()        || '',
                    part_description: pr.find('input[name*="[part_description]"]').val() || '',
                    qty:              pr.find('.part-qty').val()        || 0,
                    rate:             pr.find('.part-rate').val()       || 0,
                    stone_qty:        pr.find('.part-stone-qty').val()  || 0,
                    stone_rate:       pr.find('.part-stone-rate').val() || 0,
                    total:            pr.find('.part-total').val()      || 0,
                });
            });

            itemsPayload.push({
                item_name:        row.find('.item-name-input').val()                  || '',
                item_description: row.find('input[name*="[item_description]"]').val() || '',
                source_barcode:   row.find('input[name*="[source_barcode]"]').val()   || '',
                purity:           row.find('.purity').val()         || 0,
                gross_weight:     row.find('.gross-weight').val()   || 0,
                purity_weight:    row.find('.purity-weight').val()  || 0,
                col_995:          row.find('.col-995').val()        || 0,
                making_rate:      row.find('.making-rate').val()    || 0,
                making_value:     row.find('.making-value').val()   || 0,
                material_type:    row.find('.material-type').val()  || 'gold',
                material_value:   row.find('.material-value').val() || 0,
                vat_percent:      row.find('.vat-percent').val()    || 0,
                agreed_value:     row.find('.agreed-value').val()   || 0,
                parts:            parts,
            });
        });

        // IMPORTANT: Remove ALL inputs/selects inside editable item rows AND their
        // associated parts rows from the DOM entirely before submitting.
        // This ensures zero items[N][field] keys reach PHP — preventing PHP from
        // creating partial items[] sub-arrays that would confuse validation.
        // Immutable row hidden inputs are appended to #consignment-form directly
        // (not inside table rows) so they are untouched and POST normally.
        $('#ConsignmentTable tr.item-row:not(.table-success):not(.table-warning), ' +
          '#ConsignmentTable tr.parts-row')
            .find('input, select, textarea').remove();

        // Inject entire items array as a single JSON string — no input var limit.
        $('<input type="hidden" name="items_json">')
            .val(JSON.stringify(itemsPayload))
            .appendTo('#consignment-form');

        // Now safe to submit
        this.submit();
    });

    // =========================================================================
    // LOAD EXISTING ITEMS ON EDIT
    // =========================================================================

    INIT_ITEMS.forEach(function (item) {
        if (item.item_status === 'sold' || item.item_status === 'returned') {
            renderImmutableRow(item);
        } else {
            addNewRow(item);
        }
    });

    if (!IS_EDIT) {
        addNewRow(); // blank starter row on create
    }

    calculateTotals();

    // =========================================================================
    // IMMUTABLE ROW (sold / returned) — cannot be edited
    // =========================================================================

    function renderImmutableRow(item) {
        const isReturned = item.item_status === 'returned';
        const rowClass   = isReturned ? 'table-warning' : 'table-success';
        const badge      = isReturned
            ? '<span class="badge bg-secondary">Returned</span>'
            : '<span class="badge bg-success">Sold</span>';
        const partsTotal = (item.parts || []).reduce((s, p) => s + (parseFloat(p.total) || 0), 0);

        $('#ConsignmentTable').append(`
        <tr class="item-row ${rowClass}" data-item-index="${$('#ConsignmentTable tr.item-row').length}">
            <td colspan="12">
                ${badge}
                ${item.barcode_number ? '<code class="ms-2">' + item.barcode_number + '</code>' : ''}
                <strong class="ms-2">${escVal(item.item_name || '-')}</strong>
                &nbsp;|&nbsp; GW: ${parseFloat(item.gross_weight || 0).toFixed(3)}g
                &nbsp;|&nbsp; Purity: ${parseFloat(item.purity || 0).toFixed(3)}
                &nbsp;|&nbsp; Agreed: AED ${parseFloat(item.agreed_value || 0).toFixed(2)}
                ${partsTotal > 0 ? '&nbsp;|&nbsp; Parts: AED ' + partsTotal.toFixed(2) : ''}
                <small class="text-muted ms-2">(Cannot edit ${item.item_status} items)</small>
            </td>
            <td class="text-center">—</td>
        </tr>
        <tr class="parts-row" style="display:none;"></tr>`);

        const idx    = $('#ConsignmentTable tr.item-row').length - 1;

        // These hidden inputs are appended directly to the FORM (not inside table cells),
        // so they will NOT be disabled by the submit handler and will POST normally.
        const fields = [
            'item_status','item_name','item_description','barcode_number','is_printed',
            'gross_weight','purity','purity_weight','col_995',
            'making_rate','making_value','material_type','material_rate','material_value',
            'parts_total','taxable_amount','vat_percent','vat_amount','agreed_value',
        ];
        fields.forEach(f => {
            $('<input type="hidden">').attr('name', `items[${idx}][${f}]`)
                                     .val(item[f] ?? '')
                                     .appendTo('#consignment-form');
        });
        (item.parts || []).forEach((p, j) => {
            ['item_name','part_description','qty','rate','stone_qty','stone_rate','total'].forEach(pf => {
                $('<input type="hidden">').attr('name', `items[${idx}][parts][${j}][${pf}]`)
                                         .val(p[pf] ?? 0)
                                         .appendTo('#consignment-form');
            });
        });
    }
});
</script>

@endsection