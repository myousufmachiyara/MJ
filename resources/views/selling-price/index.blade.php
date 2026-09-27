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

      <header class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h2 class="card-title mb-0">Bulk Selling Price Update</h2>
          <small class="text-muted">Unsold purchased items only — {{ $items->count() }} item(s)</small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span id="dirtyStatus" class="text-muted small"></span>
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
                <th>#</th>
                <th>Barcode / Item Code</th>
                <th>Item Name</th>
                <th>Certificate No</th>
                <th>Category</th>
                <th>Subcategory</th>
                <th>Purchase Invoice</th>
                <th>Purchase Date</th>
                <th width="14%">Selling Price</th>
                <th width="6%">Save</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($items as $index => $item)
                <tr class="item-row" data-item-id="{{ $item->id }}">
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
                  <td class="text-center">
                    <button type="button" class="btn btn-outline-success btn-sm selling-price-save-btn" title="Save this item">
                      <i class="fas fa-check"></i>
                    </button>
                    <div class="selling-price-save-status small mt-1"></div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="10" class="text-center text-muted">No unsold purchased items found.</td>
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
    const SELLING_PRICE_SAVE_URL_BASE  = '{{ url("/purchase-invoice-items") }}';
    const BULK_UPDATE_URL              = '{{ route("selling_price.bulk_update") }}';

    const table = $('#sellingPriceTable').DataTable({
        pageLength: 50,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: [8, 9] } // Selling Price / Save columns
        ],
    });

    // Custom search box (separate from DataTables' own default search input,
    // since the header already asks for one place to type — barcode / item
    // code / SKU / item name / certificate no are all plain text columns
    // DataTables' global search already matches against).
    $('#itemSearchBox').on('keyup', function () {
        table.search(this.value).draw();
    });

    function updateDirtyState() {
        const dirtyRows = $('.selling-price-input').filter(function () {
            return $(this).val() !== ($(this).data('original') ?? '').toString();
        });
        $('#dirtyCount').text(dirtyRows.length);
        $('#saveAllBtn').prop('disabled', dirtyRows.length === 0);
    }

    $(document).on('input', '.selling-price-input', updateDirtyState);

    // ── Per-row save — reuses the exact same endpoint/behavior as the Edit
    // Purchase Invoice screen's own quick Selling Price save button. ──
    $(document).on('click', '.selling-price-save-btn', function () {
        const btn      = $(this);
        const row      = btn.closest('tr.item-row');
        const itemId   = row.data('item-id');
        const input    = row.find('.selling-price-input');
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
            success: function (res) {
                const saved = res.selling_price === null ? '' : res.selling_price;
                input.val(saved).data('original', saved);
                statusEl.addClass('text-success').html('<i class="fas fa-check-circle"></i> Saved');
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

    // ── Bulk save — every row whose value differs from its last-saved value. ──
    $('#saveAllBtn').on('click', function () {
        const btn = $(this);
        const dirtyRows = $('.selling-price-input').filter(function () {
            return $(this).val() !== ($(this).data('original') ?? '').toString();
        });

        if (dirtyRows.length === 0) return;

        const payload = dirtyRows.map(function () {
            const input = $(this);
            const rawVal = input.val();
            return {
                id: input.closest('tr.item-row').data('item-id'),
                selling_price: rawVal === '' ? null : rawVal,
            };
        }).get();

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
                    const input = $(this);
                    input.data('original', input.val());
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
});
</script>
@endsection
