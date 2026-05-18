@extends('layouts.app')
@section('title', 'Edit Purchase Return #' . $purchaseReturn->return_no)
@section('content')
<div class="row"><div class="col">
  <form id="edit-form" action="{{ route('purchase_return.update', $purchaseReturn->id) }}" method="POST">
    @csrf @method('PUT')

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Edit Return <span class="text-danger">#{{ $purchaseReturn->return_no }}</span></h2>
      </header>
      <div class="card-body">

        {{-- ===================== HEADER ===================== --}}
        <div class="row mb-5">
          <div class="col-md-3">
            <label class="fw-bold">Purchase Invoice</label>
            <select name="purchase_invoice_id" class="form-control select2-js" required>
              <option value="">Select Invoice</option>
              @foreach ($invoices as $invoice)
                <option value="{{ $invoice->id }}"
                  data-vendor-id="{{ $invoice->vendor_id }}"
                  {{ $purchaseReturn->purchase_invoice_id == $invoice->id ? 'selected' : '' }}>
                  {{ $invoice->invoice_no }} — {{ $invoice->vendor->name ?? '' }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label>Return Date</label>
            <input type="date" name="return_date" class="form-control"
              value="{{ \Carbon\Carbon::parse($purchaseReturn->return_date)->format('Y-m-d') }}" required>
          </div>
          <div class="col-md-2">
            <label>Vendor</label>
            <input type="text" class="form-control bg-light" value="{{ $purchaseReturn->vendor->name ?? '' }}" readonly>
            <input type="hidden" name="vendor_id" value="{{ $purchaseReturn->vendor_id }}">
          </div>
          <div class="col-md-3">
            <label>Reason for Return <span class="text-danger">*</span></label>
            <input type="text" name="reason" class="form-control" value="{{ $purchaseReturn->reason }}" required>
          </div>
          <div class="col-md-2">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="1">{{ $purchaseReturn->remarks }}</textarea>
          </div>
        </div>

        {{-- ===================== RATES ===================== --}}
        <div class="row mb-5">
          <div class="col-md-2">
            <label>Gold Rate (USD / <b>Ounce</b>)</label>
            <input type="number" step="any" class="form-control bg-light" value="{{ $purchaseReturn->gold_rate_usd }}" readonly>
            <input type="hidden" name="gold_rate_usd" value="{{ $purchaseReturn->gold_rate_usd }}">
          </div>
          <div class="col-md-2">
            <label>Gold Rate (AED / <b>Ounce</b>)</label>
            <input type="number" step="any" class="form-control bg-light" value="{{ $purchaseReturn->gold_rate_aed_ounce }}" readonly>
            <input type="hidden" name="gold_rate_aed_ounce" value="{{ $purchaseReturn->gold_rate_aed_ounce }}">
          </div>
          <div class="col-md-2">
            <label class="text-primary">Gold Rate (AED / <b>Gram</b>)</label>
            <input type="number" step="any" class="form-control bg-light" value="{{ $purchaseReturn->gold_rate_aed }}" readonly>
            <input type="hidden" name="gold_rate_aed" value="{{ $purchaseReturn->gold_rate_aed }}">
            <small class="text-danger fw-bold">Used for calculations</small>
          </div>
          <div class="col-md-2">
            <label>Diamond Rate (USD) / Ct.</label>
            <input type="number" step="any" class="form-control bg-light" value="{{ $purchaseReturn->diamond_rate_usd }}" readonly>
            <input type="hidden" name="diamond_rate_usd" value="{{ $purchaseReturn->diamond_rate_usd }}">
          </div>
          <div class="col-md-2">
            <label>Diamond Rate (AED) / Ct.</label>
            <input type="number" step="any" class="form-control bg-light" value="{{ $purchaseReturn->diamond_rate_aed }}" readonly>
            <input type="hidden" name="diamond_rate_aed" value="{{ $purchaseReturn->diamond_rate_aed }}">
          </div>
          <div class="col-md-2">
            <label>Currency</label>
            <input type="text" class="form-control bg-light" value="{{ $purchaseReturn->currency }}" readonly>
            <input type="hidden" name="currency"       value="{{ $purchaseReturn->currency }}">
            <input type="hidden" name="exchange_rate"  value="{{ $purchaseReturn->exchange_rate }}">
          </div>
        </div>

        {{-- ===================== ITEMS TABLE ===================== --}}
        <section class="card">
          <header class="card-header">
            <h2 class="card-title">Returned Items</h2>
          </header>
          <div class="table-responsive">
            <table class="table table-bordered" id="editItemsTable">
              <thead>
                <tr>
                  <th width="4%"  rowspan="2" class="text-center">#</th>
                  <th width="10%" rowspan="2">Item Name</th>
                  <th width="10%" rowspan="2">Description</th>
                  <th width="6%"  rowspan="2">Purity</th>
                  <th rowspan="2">Net Wt</th>
                  <th rowspan="2">Gold Gross Wt</th>
                  <th rowspan="2">Purity Wt</th>
                  <th rowspan="2">995</th>
                  <th colspan="2" class="text-center">Making</th>
                  <th width="6%" rowspan="2">Material</th>
                  <th rowspan="2">Material Val</th>
                  <th rowspan="2">MC</th>
                  <th rowspan="2">VAT %</th>
                  <th rowspan="2">VAT Amt</th>
                  <th rowspan="2">Gross Total</th>
                  <th width="5%" rowspan="2">Parts</th>
                </tr>
                <tr>
                  <th>Rate</th>
                  <th>Value</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($itemsData as $i => $item)
                  {{-- Main item row --}}
                  <tr class="item-row">
                    <td class="text-center fw-bold">{{ $i + 1 }}</td>
                    <td class="fw-semibold">{{ $item['item_name'] ?? '-' }}</td>
                    <td>{{ $item['item_description'] ?? '-' }}</td>
                    <td>{{ number_format($item['purity'], 3) }}</td>
                    <td>{{ number_format($item['net_weight'], 3) }}</td>
                    <td>{{ number_format($item['gross_weight'], 3) }}</td>
                    <td>{{ number_format($item['purity_weight'], 3) }}</td>
                    <td>{{ number_format($item['col_995'], 3) }}</td>
                    <td>{{ number_format($item['making_rate'], 2) }}</td>
                    <td>{{ number_format($item['making_value'], 2) }}</td>
                    <td>
                      <span class="badge bg-{{ $item['material_type'] === 'gold' ? 'warning text-dark' : 'info' }}">
                        {{ $item['material_type'] }}
                      </span>
                    </td>
                    <td>{{ number_format($item['material_value'], 2) }}</td>
                    <td>{{ number_format($item['taxable_amount'], 2) }}</td>
                    <td>{{ number_format($item['vat_percent'], 0) }}%</td>
                    <td>{{ number_format($item['vat_amount'], 2) }}</td>
                    <td class="fw-bold text-end">{{ number_format($item['item_total'], 2) }}</td>
                    <td class="text-center">
                      @if(!empty($item['parts']))
                        <button type="button" class="btn btn-sm btn-primary toggle-edit-parts" data-index="{{ $i }}">
                          <i class="fas fa-wrench"></i>
                        </button>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                  </tr>

                  {{-- Parts sub-table --}}
                  @if(!empty($item['parts']))
                    <tr class="edit-parts-row" id="edit-parts-row-{{ $i }}" style="background:#efefef; display:none;">
                      <td colspan="17">
                        <table class="table table-sm table-bordered mb-0">
                          <thead>
                            <tr>
                              <th>Part Name</th>
                              <th>Description</th>
                              <th>Diamond Ct.</th>
                              <th>Rate</th>
                              <th>Stone Ct.</th>
                              <th>Stone Rate</th>
                              <th>Cert. Charges</th>
                              <th>Total</th>
                            </tr>
                          </thead>
                          <tbody>
                            @foreach($item['parts'] as $part)
                              <tr>
                                <td>{{ $part['item_name'] ?? '-' }}</td>
                                <td>{{ $part['part_description'] ?? '-' }}</td>
                                <td>{{ number_format($part['qty'], 3) }} Ct</td>
                                <td>{{ number_format($part['rate'], 2) }}</td>
                                <td>{{ number_format($part['stone_qty'] ?? 0, 3) }}</td>
                                <td>{{ number_format($part['stone_rate'] ?? 0, 2) }}</td>
                                <td>{{ number_format($part['certification_charges'] ?? 0, 2) }}</td>
                                <td class="fw-bold text-warning">{{ number_format($part['total'], 2) }}</td>
                              </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </td>
                    </tr>
                  @endif

                  {{-- Hidden fields for form submission --}}
                  @foreach(['purchase_invoice_item_id','item_name','item_description','barcode_number','net_weight','gross_weight','purity','purity_weight','col_995','material_type','material_rate','material_value','making_rate','making_value','taxable_amount','vat_percent','vat_amount','item_total'] as $field)
                    <input type="hidden" name="items[{{ $i }}][{{ $field }}]" value="{{ $item[$field] ?? '' }}">
                  @endforeach

                  @foreach($item['parts'] as $j => $part)
                    @foreach(['item_name','part_description','qty','rate','stone_qty','stone_rate','certification_charges','total'] as $pf)
                      <input type="hidden" name="items[{{ $i }}][parts][{{ $j }}][{{ $pf }}]" value="{{ $part[$pf] ?? 0 }}">
                    @endforeach
                  @endforeach

                @endforeach
              </tbody>
            </table>
          </div>
        </section>

        {{-- ===================== SUMMARY ===================== --}}
        <div class="row mt-5 mb-5">
          <div class="col-md-2">
            <label>Total Purity Wt</label>
            <input type="text" class="form-control text-success fw-bold"
              value="{{ number_format($purchaseReturn->items->sum('purity_weight'), 4) }}" readonly>
          </div>
          <div class="col-md-2">
            <label>Total 995</label>
            <input type="text" class="form-control"
              value="{{ number_format($purchaseReturn->items->sum('col_995'), 4) }}" readonly>
          </div>
          <div class="col-md-2">
            <label>Total Making</label>
            <input type="text" class="form-control"
              value="{{ number_format($purchaseReturn->total_making_value, 2) }}" readonly>
          </div>
          <div class="col-md-2">
            <label>Total Material Val.</label>
            <input type="text" class="form-control"
              value="{{ number_format($purchaseReturn->total_material_value, 2) }}" readonly>
          </div>
          <div class="col-md-2">
            <label>Total Parts Val.</label>
            <input type="text" class="form-control text-warning fw-bold"
              value="{{ number_format($purchaseReturn->total_parts_value, 2) }}" readonly>
          </div>
          <div class="col-md-2">
            <label>Total VAT</label>
            <input type="text" class="form-control"
              value="{{ number_format($purchaseReturn->total_vat_amount, 2) }}" readonly>
          </div>
          <div class="col-md-2 mt-3">
            <label>Items in Return</label>
            <input type="text" class="form-control text-success fw-bold"
              value="{{ $purchaseReturn->items->count() }} item(s)" readonly>
          </div>
          <div class="col-md-2 mt-3">
            <label>Net Return Amount</label>
            <input type="text" class="form-control text-danger fw-bold"
              value="{{ number_format($purchaseReturn->net_amount, 2) }}" readonly>
          </div>
        </div>

        {{-- ===================== REFUND METHOD ===================== --}}
        <div class="row mb-3">
          <div class="col-md-2">
            <label class="fw-bold">Refund Method <span class="text-danger">*</span></label>
            <select name="refund_method" id="refund_method" class="form-control" required>
              <option value="">Select</option>
              @foreach(['credit_note','cash','bank_transfer','cheque','material_return'] as $rm)
                <option value="{{ $rm }}" {{ $purchaseReturn->refund_method === $rm ? 'selected' : '' }}>
                  {{ ucwords(str_replace('_', ' ', $rm)) }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="row mb-3 {{ $purchaseReturn->refund_method === 'cheque' ? '' : 'd-none' }}" id="cheque_fields">
          <div class="col-md-2">
            <label>Bank Name</label>
            <select name="bank_name" class="form-control select2-js">
              <option value="">Select Bank</option>
              @foreach ($banks as $bank)
                <option value="{{ $bank->id }}" {{ $purchaseReturn->bank_name == $bank->id ? 'selected' : '' }}>
                  {{ $bank->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2"><label>Cheque No</label><input type="text" name="cheque_no" class="form-control" value="{{ $purchaseReturn->cheque_no }}"></div>
          <div class="col-md-2"><label>Cheque Date</label><input type="date" name="cheque_date" class="form-control" value="{{ $purchaseReturn->cheque_date }}"></div>
          <div class="col-md-2"><label>Cheque Amount</label><input type="number" step="any" name="cheque_amount" class="form-control" value="{{ $purchaseReturn->cheque_amount }}"></div>
        </div>

        <div class="row mb-3 {{ $purchaseReturn->refund_method === 'bank_transfer' ? '' : 'd-none' }}" id="bank_transfer_fields">
          <div class="col-md-2">
            <label>Transfer From Bank</label>
            <select name="transfer_from_bank" class="form-control select2-js">
              <option value="">Select Bank</option>
              @foreach ($banks as $bank)
                <option value="{{ $bank->id }}" {{ $purchaseReturn->transfer_from_bank == $bank->id ? 'selected' : '' }}>
                  {{ $bank->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2"><label>Vendor Bank</label><input type="text" name="transfer_to_bank" class="form-control" value="{{ $purchaseReturn->transfer_to_bank }}"></div>
          <div class="col-md-2"><label>Account Title</label><input type="text" name="account_title" class="form-control" value="{{ $purchaseReturn->account_title }}"></div>
          <div class="col-md-2"><label>Account No</label><input type="text" name="account_no" class="form-control" value="{{ $purchaseReturn->account_no }}"></div>
          <div class="col-md-2"><label>Transaction Ref</label><input type="text" name="transaction_id" class="form-control" value="{{ $purchaseReturn->transaction_id }}"></div>
          <div class="col-md-2"><label>Transfer Date</label>
            <input type="date" name="transfer_date" class="form-control"
              value="{{ $purchaseReturn->transfer_date ? \Carbon\Carbon::parse($purchaseReturn->transfer_date)->format('Y-m-d') : '' }}">
          </div>
          <div class="col-md-2 mt-2"><label>Transfer Amount</label><input type="number" step="any" name="transfer_amount" class="form-control" value="{{ $purchaseReturn->transfer_amount }}"></div>
        </div>

      </div>
      <footer class="card-footer text-end">
        <a href="{{ route('purchase_return.index') }}" class="btn btn-secondary me-2">Cancel</a>
        <button type="submit" class="btn btn-primary" id="update_btn">
          <i class="fas fa-save"></i> Update Return
        </button>
      </footer>
    </section>
  </form>
</div></div>

<script>
$(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });

    // ── Parts toggle (wrench button) ──
    $(document).on('click', '.toggle-edit-parts', function () {
        const i = $(this).data('index');
        $(`#edit-parts-row-${i}`).fadeToggle(200);
    });

    // ── Refund method toggle ──
    $('#refund_method').on('change', function () {
        const val = $(this).val();
        $('#cheque_fields, #bank_transfer_fields').addClass('d-none');
        if (val === 'cheque')        $('#cheque_fields').removeClass('d-none');
        if (val === 'bank_transfer') $('#bank_transfer_fields').removeClass('d-none');
    });

    // ── Prevent double submit ──
    document.getElementById('edit-form').addEventListener('submit', function () {
        const btn = document.getElementById('update_btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    });
});
</script>
@endsection