@extends('layouts.app')
@section('title', 'Sale Return | New')
@section('content')
<div class="row">
  <div class="col">
    <form id="return-form" action="{{ route('sale_return.store') }}" method="POST">
      @csrf

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif

      <section class="card">
        <header class="card-header"><h2 class="card-title">New Sale Return</h2></header>
        <div class="card-body">

          {{-- ===================== HEADER ===================== --}}
          <div class="row mb-5">
            <div class="col-md-3">
              <label class="fw-bold">Select Sale Invoice <span class="text-danger">*</span></label>
              <select name="sale_invoice_id" id="sale_invoice_id" class="form-control select2-js" required>
                <option value="">Select Invoice</option>
                @foreach ($invoices as $invoice)
                  <option value="{{ $invoice->id }}">
                    {{ $invoice->invoice_no }} — {{ $invoice->customer->name ?? '' }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Return Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-2">
              <label>Customer</label>
              <input type="text" id="customer_display" class="form-control bg-light" readonly>
              <input type="hidden" name="customer_id" id="customer_id">
            </div>
            <div class="col-md-3">
              <label>Reason for Return <span class="text-danger">*</span></label>
              <input type="text" name="reason" class="form-control" required placeholder="e.g. Defective, Wrong item...">
            </div>
            <div class="col-md-2">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          {{-- ===================== RATES ===================== --}}
          <div class="row mb-5" id="rates_section" style="display:none;">
            <div class="col-md-2">
              <label>Gold Rate (USD / <b>Ounce</b>)</label>
              <input type="number" step="any" id="r_gold_rate_usd" class="form-control bg-light" readonly>
              <input type="hidden" name="gold_rate_usd" id="gold_rate_usd">
            </div>
            <div class="col-md-2">
              <label>Gold Rate (AED / <b>Ounce</b>)</label>
              <input type="number" step="any" id="r_gold_rate_aed_ounce" class="form-control bg-light" readonly>
              <input type="hidden" name="gold_rate_aed_ounce" id="gold_rate_aed_ounce">
            </div>
            <div class="col-md-2">
              <label class="text-primary">Gold Rate (AED / <b>Gram</b>)</label>
              <input type="number" step="any" id="r_gold_rate_aed" class="form-control bg-light" readonly>
              <input type="hidden" name="gold_rate_aed" id="gold_rate_aed">
              <small class="text-danger fw-bold">Used for calculations</small>
            </div>
            <div class="col-md-2">
              <label>Diamond Rate (USD) / Ct.</label>
              <input type="number" step="any" id="r_diamond_rate_usd" class="form-control bg-light" readonly>
              <input type="hidden" name="diamond_rate_usd" id="diamond_rate_usd">
            </div>
            <div class="col-md-2">
              <label>Diamond Rate (AED) / Ct.</label>
              <input type="number" step="any" id="r_diamond_rate_aed" class="form-control bg-light" readonly>
              <input type="hidden" name="diamond_rate_aed" id="diamond_rate_aed">
            </div>
            <div class="col-md-2">
              <label>Currency</label>
              <input type="text" id="currency_display" class="form-control bg-light" readonly>
              <input type="hidden" name="currency" id="currency" value="AED">
              <input type="hidden" name="exchange_rate" id="exchange_rate">
            </div>
          </div>

          {{-- ===================== ITEMS TABLE ===================== --}}
          <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
              <h2 class="card-title">Invoice Items</h2>
              <div id="select_all_wrap" style="display:none;">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn_select_all">
                  <i class="fas fa-check-double"></i> Select All
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="btn_deselect_all">
                  <i class="fas fa-times"></i> Deselect All
                </button>
              </div>
            </header>
            <div class="table-responsive">
              <div id="no_invoice_msg" class="p-3 text-muted">
                <i class="fas fa-info-circle"></i> Please select a sale invoice above to load its items.
              </div>
              <div id="loading_msg" class="p-3 text-primary" style="display:none;">
                <i class="fas fa-spinner fa-spin"></i> Loading invoice items...
              </div>
              <table class="table table-bordered d-none" id="returnItemsTable">
                <thead>
                  <tr>
                    <th width="4%"  rowspan="2" class="text-center">✓</th>
                    <th width="10%" rowspan="2">Item Name</th>
                    <th width="10%" rowspan="2">Description</th>
                    <th width="6%"  rowspan="2">Purity</th>
                    <th rowspan="2">Gross Wt</th>
                    <th rowspan="2">Purity Wt</th>
                    <th rowspan="2">995</th>
                    <th colspan="2" class="text-center">Making</th>
                    <th width="6%" rowspan="2">Material</th>
                    <th rowspan="2">Material Val</th>
                    <th rowspan="2">MC</th>
                    <th rowspan="2">VAT %</th>
                    <th rowspan="2">VAT Amt</th>
                    <th rowspan="2">Gross Total</th>
                    <th width="5%" rowspan="2">Action</th>
                  </tr>
                  <tr>
                    <th>Rate</th>
                    <th>Value</th>
                  </tr>
                </thead>
                <tbody id="returnTableBody"></tbody>
              </table>
            </div>
          </section>

          {{-- ===================== SUMMARY ===================== --}}
          <div class="row mt-5 mb-5" id="summary_section" style="display:none;">
            <div class="col-md-2">
              <label>Total Purity Wt</label>
              <input type="text" id="sum_purity_weight" class="form-control text-success fw-bold" readonly>
            </div>
            <div class="col-md-2">
              <label>Total 995</label>
              <input type="text" id="sum_995" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Making</label>
              <input type="text" id="sum_making" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Material Val.</label>
              <input type="text" id="sum_material" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Parts Val.</label>
              <input type="text" id="sum_parts" class="form-control text-warning fw-bold" readonly>
            </div>
            <div class="col-md-2">
              <label>Total VAT</label>
              <input type="text" id="sum_vat" class="form-control" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Items Selected</label>
              <input type="text" id="sum_count" class="form-control text-success fw-bold" readonly>
            </div>
            <div class="col-md-2 mt-3">
              <label>Net Return Amount</label>
              <input type="text" id="sum_total" class="form-control text-danger fw-bold" readonly>
            </div>
          </div>

          {{-- ===================== REFUND METHOD ===================== --}}
          <div class="row mb-3">
            <div class="col-md-2">
              <label class="fw-bold">Refund Method <span class="text-danger">*</span></label>
              <select name="refund_method" id="refund_method" class="form-control" required>
                <option value="">Select Method</option>
                <option value="credit_note">Credit Note</option>
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="material_return">Material Return (Gold)</option>
              </select>
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
            <div class="col-md-2"><label>Cheque No</label><input type="text" name="cheque_no" class="form-control"></div>
            <div class="col-md-2"><label>Cheque Date</label><input type="date" name="cheque_date" class="form-control"></div>
            <div class="col-md-2"><label>Cheque Amount</label><input type="number" step="any" name="cheque_amount" class="form-control"></div>
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
            <div class="col-md-2"><label>Customer Bank</label><input type="text" name="transfer_to_bank" class="form-control"></div>
            <div class="col-md-2"><label>Account Title</label><input type="text" name="account_title" class="form-control"></div>
            <div class="col-md-2"><label>Account Number</label><input type="text" name="account_no" class="form-control"></div>
            <div class="col-md-2"><label>Transaction Ref</label><input type="text" name="transaction_id" class="form-control"></div>
            <div class="col-md-2"><label>Transfer Date</label><input type="date" name="transfer_date" class="form-control"></div>
            <div class="col-md-2 mt-2"><label>Transfer Amount</label><input type="number" step="any" name="transfer_amount" class="form-control"></div>
          </div>

        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('sale_return.index') }}" class="btn btn-secondary me-2">Cancel</a>
          <button type="submit" class="btn btn-danger" id="submit_btn">
            <i class="fas fa-save"></i> Save Return
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
$(document).ready(function () {

    $('.select2-js').select2({ width: '100%' });

    let invoiceItems = [];

    $('#sale_invoice_id').on('change', function () {
        const invoiceId = $(this).val();

        $('#no_invoice_msg').hide();
        $('#returnItemsTable').addClass('d-none');
        $('#rates_section').hide();
        $('#summary_section').hide();
        $('#select_all_wrap').hide();
        $('#returnTableBody').empty();
        $('#customer_display').val('');
        $('#customer_id').val('');
        removeInjectedFields();
        invoiceItems = [];

        if (!invoiceId) { $('#no_invoice_msg').show(); return; }

        $('#loading_msg').show();

        $.get(`/sale-return/${invoiceId}/items`)
            .done(function (data) {
                $('#loading_msg').hide();

                if (!data.success) { $('#no_invoice_msg').show(); return; }

                const inv    = data.invoice;
                invoiceItems = data.items;

                $('#r_gold_rate_usd').val(inv.gold_rate_usd);
                $('#r_gold_rate_aed_ounce').val(inv.gold_rate_aed_ounce);
                $('#r_gold_rate_aed').val(inv.gold_rate_aed);
                $('#r_diamond_rate_usd').val(inv.diamond_rate_usd);
                $('#r_diamond_rate_aed').val(inv.diamond_rate_aed);
                $('#gold_rate_usd').val(inv.gold_rate_usd);
                $('#gold_rate_aed_ounce').val(inv.gold_rate_aed_ounce);
                $('#gold_rate_aed').val(inv.gold_rate_aed);
                $('#diamond_rate_usd').val(inv.diamond_rate_usd);
                $('#diamond_rate_aed').val(inv.diamond_rate_aed);
                $('#currency').val(inv.currency);
                $('#exchange_rate').val(inv.exchange_rate);
                $('#currency_display').val(inv.currency);
                $('#rates_section').show();

                $('#customer_display').val(inv.customer_name);
                $('#customer_id').val(inv.customer_id);

                const tbody = $('#returnTableBody');

                invoiceItems.forEach(function (item, i) {
                    const hasParts    = item.parts && item.parts.length > 0;
                    const isReturned  = item.already_returned === true;
                    const rowClass    = isReturned ? 'item-row table-secondary text-muted' : 'item-row';
                    const badge       = isReturned ? '<span class="badge bg-danger ms-1">Returned</span>' : '';

                    tbody.append(`
                    <tr class="${rowClass}" data-index="${i}">
                        <td class="text-center">
                            <input type="checkbox" class="item-select form-check-input" data-index="${i}"
                                   ${isReturned ? 'disabled title="Already returned"' : ''}>
                        </td>
                        <td class="fw-semibold">${escHtml(item.item_name || '-')}${badge}</td>
                        <td>${escHtml(item.item_description || '-')}</td>
                        <td>${fmtNum(item.purity, 3)}</td>
                        <td>${fmtNum(item.gross_weight, 3)}</td>
                        <td>${fmtNum(item.purity_weight, 3)}</td>
                        <td>${fmtNum(item.col_995, 3)}</td>
                        <td>${fmtNum(item.making_rate, 2)}</td>
                        <td>${fmtNum(item.making_value, 2)}</td>
                        <td><span class="badge bg-${item.material_type === 'gold' ? 'warning text-dark' : 'info'}">${item.material_type}</span></td>
                        <td>${fmtNum(item.material_value, 2)}</td>
                        <td>${fmtNum(item.taxable_amount, 2)}</td>
                        <td>${fmtNum(item.vat_percent, 0)}%</td>
                        <td>${fmtNum(item.vat_amount, 2)}</td>
                        <td class="fw-bold text-end">${fmtNum(item.item_total, 2)}</td>
                        <td class="text-center">
                            ${hasParts
                                ? `<button type="button" class="btn btn-sm btn-primary toggle-parts-btn" data-index="${i}"><i class="fas fa-wrench"></i></button>`
                                : '<span class="text-muted">—</span>'
                            }
                        </td>
                    </tr>`);

                    if (hasParts) {
                        let partsHtml = `
                        <tr class="parts-display-row" id="parts-row-${i}" style="background:#efefef;">
                            <td colspan="16">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead><tr>
                                        <th>Part Name</th><th>Description</th>
                                        <th>Diamond Ct.</th><th>Rate</th>
                                        <th>Stone Ct.</th><th>Stone Rate</th><th>Total</th>
                                    </tr></thead>
                                    <tbody>`;

                        item.parts.forEach(function (part) {
                            partsHtml += `
                                        <tr>
                                            <td>${escHtml(part.item_name || '-')}</td>
                                            <td>${escHtml(part.part_description || '-')}</td>
                                            <td>${fmtNum(part.qty, 3)} Ct</td>
                                            <td>${fmtNum(part.rate, 2)}</td>
                                            <td>${fmtNum(part.stone_qty || 0, 3)}</td>
                                            <td>${fmtNum(part.stone_rate || 0, 2)}</td>
                                            <td class="fw-bold text-warning">${fmtNum(part.total, 2)}</td>
                                        </tr>`;
                        });

                        partsHtml += `</tbody></table></td></tr>`;
                        tbody.append(partsHtml);
                        $(`#parts-row-${i}`).hide();
                    }
                });

                $('#returnItemsTable').removeClass('d-none');
                $('#summary_section').show();
                $('#select_all_wrap').show();
                calculateSummary();
            })
            .fail(function () {
                $('#loading_msg').hide();
                $('#no_invoice_msg').show().text('Failed to load invoice items. Please try again.');
            });
    });

    $(document).on('click', '.toggle-parts-btn', function () {
        $(`#parts-row-${$(this).data('index')}`).fadeToggle(200);
    });

    $('#btn_select_all').on('click', function () {
        $('.item-select:not(:disabled)').prop('checked', true);
        calculateSummary();
    });

    $('#btn_deselect_all').on('click', function () {
        $('.item-select:not(:disabled)').prop('checked', false);
        calculateSummary();
    });

    $(document).on('change', '.item-select', function () { calculateSummary(); });

    function removeInjectedFields() {
        $('#return-form input[name^="items["]').remove();
    }

    function calculateSummary() {
        removeInjectedFields();

        let sumPurityWt = 0, sum995 = 0, sumMaterial = 0,
            sumMaking = 0, sumParts = 0, sumVat = 0, sumTotal = 0;
        let itemIndex = 0;

        $('.item-select:checked').each(function () {
            const poolIndex = parseInt($(this).data('index'));
            const item      = invoiceItems[poolIndex];
            if (!item) return;

            sumPurityWt += parseFloat(item.purity_weight) || 0;
            sum995      += parseFloat(item.col_995)        || 0;
            sumMaterial += parseFloat(item.material_value) || 0;
            sumMaking   += parseFloat(item.making_value)   || 0;
            sumVat      += parseFloat(item.vat_amount)     || 0;
            sumTotal    += parseFloat(item.item_total)     || 0;

            if (item.parts && item.parts.length > 0) {
                item.parts.forEach(p => { sumParts += parseFloat(p.total) || 0; });
            }

            const scalarFields = {
                'sale_invoice_item_id': item.id,
                'item_name':            item.item_name        || '',
                'item_description':     item.item_description || '',
                'barcode_number':       item.barcode_number   || '',
                'gross_weight':         item.gross_weight     || 0,
                'purity':               item.purity           || 0,
                'purity_weight':        item.purity_weight    || 0,
                'col_995':              item.col_995          || 0,
                'material_type':        item.material_type    || 'gold',
                'material_rate':        item.material_rate    || 0,
                'material_value':       item.material_value   || 0,
                'making_rate':          item.making_rate      || 0,
                'making_value':         item.making_value     || 0,
                'taxable_amount':       item.taxable_amount   || 0,
                'vat_percent':          item.vat_percent      || 0,
                'vat_amount':           item.vat_amount       || 0,
                'item_total':           item.item_total       || 0,
            };

            Object.entries(scalarFields).forEach(([field, value]) => {
                $('#return-form').append(
                    $('<input>').attr({ type: 'hidden', name: `items[${itemIndex}][${field}]`, value: value ?? '' })
                );
            });

            if (item.parts && item.parts.length > 0) {
                item.parts.forEach((part, j) => {
                    const partFields = {
                        'item_name':        part.item_name        || '',
                        'part_description': part.part_description || '',
                        'qty':              part.qty              || 0,
                        'rate':             part.rate             || 0,
                        'stone_qty':        part.stone_qty        || 0,
                        'stone_rate':       part.stone_rate       || 0,
                        'total':            part.total            || 0,
                    };
                    Object.entries(partFields).forEach(([field, value]) => {
                        $('#return-form').append(
                            $('<input>').attr({ type: 'hidden', name: `items[${itemIndex}][parts][${j}][${field}]`, value: value ?? 0 })
                        );
                    });
                });
            }

            itemIndex++;
        });

        $('#sum_purity_weight').val(sumPurityWt.toFixed(4));
        $('#sum_995').val(sum995.toFixed(4));
        $('#sum_material').val(sumMaterial.toFixed(2));
        $('#sum_making').val(sumMaking.toFixed(2));
        $('#sum_parts').val(sumParts.toFixed(2));
        $('#sum_vat').val(sumVat.toFixed(2));
        $('#sum_total').val(sumTotal.toFixed(2));
        $('#sum_count').val(itemIndex + ' item(s) selected');
    }

    $('#refund_method').on('change', function () {
        const val = $(this).val();
        $('#cheque_fields, #bank_transfer_fields').addClass('d-none');
        if (val === 'cheque')        $('#cheque_fields').removeClass('d-none');
        if (val === 'bank_transfer') $('#bank_transfer_fields').removeClass('d-none');
    });

    $('#return-form').on('submit', function (e) {
        if ($('.item-select:checked').length === 0) {
            e.preventDefault();
            alert('Please select at least one item to return.');
            return;
        }
        const btn = document.getElementById('submit_btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    });

    function fmtNum(val, dp) { return parseFloat(val || 0).toFixed(dp); }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
});
</script>
@endsection