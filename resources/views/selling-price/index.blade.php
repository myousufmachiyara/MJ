@extends('layouts.app')

@section('title', 'Purchase | Bulk Selling Price Update')

@section('content')
{{--
    FEATURE (bulk Selling Price update): a standalone screen listing every
    purchased item that hasn't been sold yet (same "not sold" rule POS uses
    — see SellingPriceController::index()), so Selling Price can be set or
    corrected for many items in one place without opening each Purchase
    Invoice individually.

    Two ways to save, both writing ONLY the selling_price column (no other
    invoice data is touched, nothing else recalculates):
      - Per-row "Save" button — PUTs just that row, reusing the exact same
        endpoint the Edit Purchase Invoice screen's own quick-save button
        already uses (routes/web.php: purchase_invoice_items.update_selling_price).
      - "Save All Changed" — POSTs every row whose value you've edited
        since page load (or since the last save) in one request, to
        SellingPriceController::bulkUpdate().

    FEATURE (export selected items): a checkbox column lets you pick a
    subset of rows and download a CSV of just those (barcode, item name,
    certificate no, gold weight, diamond ct, selling price) via
    SellingPriceController::export(). Purely a read/export — it never
    changes any data, so it's independent of the two save paths above.

    FEATURE (bulk Selling Price import, AED + USD): a second, independent
    Selling Price (USD) column has been added alongside the original AED
    one (now labeled "Selling Price (AED)") — see the
    selling_price_usd migration/model changes. Both the per-row Save
    button and "Save All Changed" now POST to
    SellingPriceController::bulkUpdate(), which accepts either/both price
    fields per row, so the old PurchaseInvoiceController quick-save PUT
    endpoint is no longer called from this screen (it's left completely
    untouched for the Edit Purchase Invoice screen that still uses it).
    An "Import" button uploads a CSV (same columns export() produces) and
    posts it to SellingPriceController::import(), which matches rows by
    Barcode Number and updates whichever price column(s) each row's cells
    actually contain.
--}}
<div class="row">
  <div class="col">
    <section class="card">

      @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show m-3 mb-0" role="alert">
          {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show m-3 mb-0" role="alert">
          {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      <header class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h2 class="card-title mb-0">Bulk Selling Price Update</h2>
          <small class="text-muted">Unsold purchased items only — {{ $items->count() }} item(s)</small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span id="dirtyStatus" class="text-muted small"></span>
          {{--
              FEATURE (bulk Selling Price import, AED + USD): the actual
              upload input is hidden — clicking "Import" just opens the
              native file picker, and choosing a file immediately submits
              this real (non-AJAX) multipart form, since a file download-
              style JSON response can't easily carry the resulting
              redirect+flash message pattern the rest of this screen uses.
          --}}
          <form id="importForm" action="{{ route('selling_price.import') }}" method="POST" enctype="multipart/form-data" class="d-inline-block m-0">
            @csrf
            <input type="file" name="file" id="importFileInput" accept=".csv,.txt" style="display:none;">
          </form>
          <button type="button" id="importBtn" class="btn btn-outline-secondary">
            <i class="fas fa-file-import"></i> Import
          </button>
          <button type="button" id="exportSelectedBtn" class="btn btn-outline-primary" disabled>
            <i class="fas fa-file-export"></i> Export Selected (<span id="selectedCount">0</span>)
          </button>
          <button type="button" id="saveAllBtn" class="btn btn-success" disabled>
            <i class="fas fa-save"></i> Save All Changed (<span id="dirtyCount">0</span>)
          </button>
        </div>
      </header>

      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-4">
            <input type="text" id="itemSearchBox" class="form-control"
                   placeholder="Search by Barcode / Item Code / SKU / Item Name / Certificate No"
                   value="{{ $search }}">
            <small class="text-muted">Filters the table below as you type.</small>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="sellingPriceTable">
            <thead>
              <tr>
                <th width="3%"><input type="checkbox" id="selectAllCheckbox" title="Select all (filtered rows)"></th>
                <th>#</th>
                <th>Barcode / Item Code</th>
                <th>Item Name</th>
                <th>Certificate No</th>
                <th>Category</th>
                <th>Subcategory</th>
                <th>Purchase Invoice</th>
                <th>Purchase Date</th>
                <th width="12%">Selling Price (AED)</th>
                <th width="12%">Selling Price (USD)</th>
                <th width="6%">Save</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($items as $index => $item)
                <tr class="item-row" data-item-id="{{ $item->id }}">
                  <td><input type="checkbox" class="row-select-checkbox" value="{{ $item->id }}"></td>
                  <td>{{ $index + 1 }}</td>
                  <td><span class="fw-bold text-primary">{{ $item->barcode_number }}</span></td>
                  <td>{{ $item->item_name }}</td>
                  <td>{{ $item->certificate_no ?? '-' }}</td>
                  <td>{{ $item->category->name ?? '-' }}</td>
                  <td>{{ $item->subcategory->name ?? '-' }}</td>
                  <td>
                    @if($item->purchaseInvoice)
                      <a href="{{ route('purchase_invoices.edit', $item->purchaseInvoice->id) }}" target="_blank">
                        {{ $item->purchaseInvoice->invoice_no }}
                      </a>
                    @else
                      -
                    @endif
                  </td>
                  <td>{{ $item->purchaseInvoice && $item->purchaseInvoice->invoice_date ? \Carbon\Carbon::parse($item->purchaseInvoice->invoice_date)->format('d M Y') : '-' }}</td>
                  <td>
                    <input type="number" step="any" min="0"
                           class="form-control form-control-sm selling-price-input"
                           data-original="{{ $item->selling_price }}"
                           value="{{ $item->selling_price }}"
                           placeholder="Not set">
                  </td>
                  <td>
                    <input type="number" step="any" min="0"
                           class="form-control form-control-sm selling-price-usd-input"
                           data-original="{{ $item->selling_price_usd }}"
                           value="{{ $item->selling_price_usd }}"
                           placeholder="Not set">
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-outline-success btn-sm selling-price-save-btn" title="Save this item">
                      <i class="fas fa-check"></i>
                    </button>
                    <div class="selling-price-save-status small mt-1"></div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="12" class="text-center text-muted">No unsold purchased items found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    </section>
  </div>
</div>

<script>
$(document).ready(function () {
    const CSRF_TOKEN                   = $('meta[name="csrf-token"]').attr('content');
    // FEATURE (bulk Selling Price import, AED + USD): both the per-row Save
    // button and "Save All Changed" now POST to bulkUpdate() below — the
    // old PurchaseInvoiceController per-item quick-save endpoint this
    // screen used to call directly is no longer referenced here (it's
    // still used, untouched, by the Edit Purchase Invoice screen itself).
    const BULK_UPDATE_URL              = '{{ route("selling_price.bulk_update") }}';
    const EXPORT_URL                   = '{{ route("selling_price.export") }}';

    const table = $('#sellingPriceTable').DataTable({
        pageLength: 50,
        order: [[1, 'desc']],
        columnDefs: [
            { orderable: false, targets: [0, 9, 10, 11] } // Select / Selling Price (AED) / Selling Price (USD) / Save columns
        ],
    });

    // Custom search box (separate from DataTables' own default search input,
    // since the header already asks for one place to type — barcode / item
    // code / SKU / item name / certificate no are all plain text columns
    // DataTables' global search already matches against).
    $('#itemSearchBox').on('keyup', function () {
        table.search(this.value).draw();
    });

    // ── Row selection (for Export Selected) ──────────────────────────────
    function updateSelectedState() {
        const count = $('.row-select-checkbox:checked').length;
        $('#selectedCount').text(count);
        $('#exportSelectedBtn').prop('disabled', count === 0);
    }

    $(document).on('change', '.row-select-checkbox', function () {
        updateSelectedState();
        // Keep the header "select all" checkbox in sync: checked only when
        // every currently-filtered row is checked, indeterminate when some
        // (but not all) of them are.
        const visibleBoxes = table.rows({ search: 'applied' }).nodes().to$().find('.row-select-checkbox');
        const checkedVisible = visibleBoxes.filter(':checked').length;
        $('#selectAllCheckbox').prop({
            checked: visibleBoxes.length > 0 && checkedVisible === visibleBoxes.length,
            indeterminate: checkedVisible > 0 && checkedVisible < visibleBoxes.length,
        });
    });

    // Selects/deselects only the rows the current search has left visible —
    // never a row that's filtered out of view, so "select all" can't
    // silently pick up items you can't currently see.
    $('#selectAllCheckbox').on('change', function () {
        const checked = $(this).is(':checked');
        table.rows({ search: 'applied' }).nodes().to$().find('.row-select-checkbox').prop('checked', checked);
        $(this).prop('indeterminate', false);
        updateSelectedState();
    });

    // Re-sync the header checkbox whenever the search/filter changes, since
    // "all visible" can change without any checkbox itself being clicked.
    table.on('search.dt draw.dt', function () {
        const visibleBoxes = table.rows({ search: 'applied' }).nodes().to$().find('.row-select-checkbox');
        const checkedVisible = visibleBoxes.filter(':checked').length;
        $('#selectAllCheckbox').prop({
            checked: visibleBoxes.length > 0 && checkedVisible === visibleBoxes.length,
            indeterminate: checkedVisible > 0 && checkedVisible < visibleBoxes.length,
        });
    });

    // ── Export Selected — real browser download via a throwaway hidden
    // form POST (not AJAX/fetch), so the server's file response triggers
    // a normal download instead of arriving as JSON in an XHR handler. ──
    $('#exportSelectedBtn').on('click', function () {
        const ids = $('.row-select-checkbox:checked').map(function () { return this.value; }).get();
        if (ids.length === 0) return;

        const form = $('<form>', { method: 'POST', action: EXPORT_URL, style: 'display:none;' });
        form.append($('<input>', { type: 'hidden', name: '_token', value: CSRF_TOKEN }));
        ids.forEach(function (id) {
            form.append($('<input>', { type: 'hidden', name: 'item_ids[]', value: id }));
        });
        $('body').append(form);
        form.trigger('submit');
        form.remove();
    });

    // FEATURE (bulk Selling Price import, AED + USD): "dirty" now means
    // either currency's input differs from its own last-saved value — a
    // row counts once even if both were edited, and a per-row save only
    // sends whichever of the two fields actually changed on that row (see
    // buildRowPayload() below), so editing just one currency never
    // overwrites the other with a stale value.
    function isRowDirty(row) {
        const aed = row.find('.selling-price-input');
        const usd = row.find('.selling-price-usd-input');
        return aed.val() !== (aed.data('original') ?? '').toString()
            || usd.val() !== (usd.data('original') ?? '').toString();
    }

    function updateDirtyState() {
        const dirtyRows = $('tr.item-row').filter(function () { return isRowDirty($(this)); });
        $('#dirtyCount').text(dirtyRows.length);
        $('#saveAllBtn').prop('disabled', dirtyRows.length === 0);
    }

    $(document).on('input', '.selling-price-input, .selling-price-usd-input', updateDirtyState);

    // Builds the bulkUpdate() payload for one row, including only the
    // price field(s) whose input actually changed since last save — so a
    // row where only USD was touched doesn't re-send (and re-round-trip)
    // an unchanged AED value.
    function buildRowPayload(row) {
        const itemId = row.data('item-id');
        const aed    = row.find('.selling-price-input');
        const usd    = row.find('.selling-price-usd-input');
        const payload = { id: itemId };

        if (aed.val() !== (aed.data('original') ?? '').toString()) {
            payload.selling_price = aed.val() === '' ? null : aed.val();
        }
        if (usd.val() !== (usd.data('original') ?? '').toString()) {
            payload.selling_price_usd = usd.val() === '' ? null : usd.val();
        }
        return payload;
    }

    // ── Per-row save — POSTs a single-item array to the same bulkUpdate()
    // endpoint "Save All Changed" uses, instead of the old AED-only
    // PurchaseInvoiceController quick-save PUT endpoint, so one row can
    // save just its AED price, just its USD price, or both at once. ──
    $(document).on('click', '.selling-price-save-btn', function () {
        const btn      = $(this);
        const row      = btn.closest('tr.item-row');
        const statusEl = row.find('.selling-price-save-status');
        const payload  = buildRowPayload(row);

        if (!('selling_price' in payload) && !('selling_price_usd' in payload)) {
            statusEl.removeClass('text-success').addClass('text-danger').html('<i class="fas fa-times-circle"></i> Nothing changed');
            clearTimeout(row.data('_sp_status_timer'));
            row.data('_sp_status_timer', setTimeout(() => statusEl.text(''), 3000));
            return;
        }

        const originalIcon = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        statusEl.removeClass('text-success text-danger').text('');

        $.ajax({
            url: BULK_UPDATE_URL,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            data: { items: [payload] },
            success: function (res) {
                if (res.skipped && res.skipped.length > 0) {
                    statusEl.addClass('text-danger').html('<i class="fas fa-times-circle"></i> Already sold — not saved');
                } else {
                    row.find('.selling-price-input').each(function () { $(this).data('original', $(this).val()); });
                    row.find('.selling-price-usd-input').each(function () { $(this).data('original', $(this).val()); });
                    statusEl.addClass('text-success').html('<i class="fas fa-check-circle"></i> Saved');
                }
                clearTimeout(row.data('_sp_status_timer'));
                row.data('_sp_status_timer', setTimeout(() => statusEl.text(''), 3000));
                updateDirtyState();
            },
            error: function (xhr) {
                const msg = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0])))
                    || 'Save failed.';
                statusEl.addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + msg);
            },
            complete: function () {
                btn.prop('disabled', false).html(originalIcon);
            }
        });
    });

    // ── Bulk save — every row where AED and/or USD differs from its
    // last-saved value; each row's payload only carries the field(s) that
    // actually changed on that row. ──
    $('#saveAllBtn').on('click', function () {
        const btn = $(this);
        const dirtyRows = $('tr.item-row').filter(function () { return isRowDirty($(this)); });

        if (dirtyRows.length === 0) return;

        const payload = dirtyRows.map(function () { return buildRowPayload($(this)); }).get();

        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        $('#dirtyStatus').removeClass('text-danger text-success').text('');

        $.ajax({
            url: BULK_UPDATE_URL,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            data: { items: payload },
            success: function (res) {
                dirtyRows.each(function () {
                    $(this).find('.selling-price-input').each(function () { $(this).data('original', $(this).val()); });
                    $(this).find('.selling-price-usd-input').each(function () { $(this).data('original', $(this).val()); });
                });

                let msg = res.updated + ' item(s) saved.';
                if (res.skipped && res.skipped.length > 0) {
                    msg += ' Skipped ' + res.skipped.length + ' item(s) already sold: ' + res.skipped.join(', ');
                }
                $('#dirtyStatus').addClass('text-success').text(msg);
                updateDirtyState();
            },
            error: function (xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Bulk save failed.';
                $('#dirtyStatus').addClass('text-danger').text(msg);
            },
            complete: function () {
                btn.html(originalHtml);
                updateDirtyState();
            }
        });
    });

    // ── Import — clicking "Import" opens the native file picker; choosing
    // a file submits the real multipart form immediately (a plain
    // redirect+flash round trip, same as every other form in this app —
    // no AJAX here, since the response is a full-page redirect back to
    // this screen with a session flash message). ──
    $('#importBtn').on('click', function () {
        $('#importFileInput').trigger('click');
    });

    $('#importFileInput').on('change', function () {
        if (this.files && this.files.length > 0) {
            $('#importForm').trigger('submit');
        }
    });
});
</script>
@endsection
