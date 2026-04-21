{{-- ============================================================
     resources/views/consignments/create.blade.php
     AND
     resources/views/consignments/edit.blade.php
     Use @include or two separate files — the core form is identical.
     For edit: pass $consignment, $itemsJson; direction field is hidden/readonly.
     ============================================================ --}}
@extends('layouts.app')
@section('title', isset($consignment) ? 'Edit Consignment' : 'New Consignment')

@section('content')

@php $isEdit = isset($consignment); @endphp

<div class="card">
  <div class="card-body p-0">

    <div class="border-bottom px-3 py-2 d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="fas fa-handshake me-2 text-primary"></i>
        {{ $isEdit ? 'Edit — ' . $consignment->consignment_no : 'New Consignment' }}
      </h5>
      <a href="{{ $isEdit ? route('consignments.show', $consignment->id) : route('consignments.index') }}"
         class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
      </a>
    </div>

    @if($errors->any())
      <div class="alert alert-danger mx-3 mt-3 mb-0">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
    @endif

    <form method="POST"
          action="{{ $isEdit ? route('consignments.update', $consignment->id) : route('consignments.store') }}">
      @csrf
      @if($isEdit) @method('PUT') @endif

      <div class="p-3">

        {{-- ── Header fields ─────────────────────────────────────────── --}}
        <div class="row g-3 mb-3">

          {{-- Direction (create only) --}}
          @if(!$isEdit)
          <div class="col-md-3">
            <label class="fw-bold">Direction <span class="text-danger">*</span></label>
            <select name="direction" id="direction" class="form-select" required>
              <option value="">-- Select --</option>
              <option value="inbound"  {{ old('direction') === 'inbound'  ? 'selected' : '' }}>
                ↓ Inbound — we receive goods from partner
              </option>
              <option value="outbound" {{ old('direction') === 'outbound' ? 'selected' : '' }}>
                ↑ Outbound — we send goods to partner
              </option>
            </select>
            <small class="text-muted">Inbound = barcodes generated. Outbound = no barcodes.</small>
          </div>
          @else
          <input type="hidden" name="direction" value="{{ $consignment->direction }}">
          <div class="col-md-3">
            <label class="fw-bold">Direction</label>
            <div class="form-control bg-light">
              @if($consignment->direction === 'inbound')
                <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>Inbound</span>
              @else
                <span class="badge bg-primary"><i class="fas fa-arrow-up me-1"></i>Outbound</span>
              @endif
            </div>
          </div>
          @endif

          {{-- Partner --}}
          <div class="col-md-4">
            <label class="fw-bold">Partner (Customer / Vendor) <span class="text-danger">*</span></label>
            <select name="partner_id" class="form-select select2-js" required>
              <option value="">-- Select Partner --</option>
              @foreach($partners as $p)
                <option value="{{ $p->id }}"
                  {{ old('partner_id', $isEdit ? $consignment->partner_id : '') == $p->id ? 'selected' : '' }}>
                  {{ $p->name }} [{{ strtoupper($p->account_type) }}]
                </option>
              @endforeach
            </select>
          </div>

          {{-- Duration label --}}
          <div class="col-md-2">
            <label class="fw-bold">Duration Label</label>
            <input type="text" name="duration_label" class="form-control"
                   placeholder="e.g. 3 months"
                   value="{{ old('duration_label', $isEdit ? $consignment->duration_label : '') }}">
          </div>

          {{-- Start date --}}
          <div class="col-md-1-5 col-md-2">
            <label class="fw-bold">Start Date <span class="text-danger">*</span></label>
            <input type="date" name="start_date" class="form-control" required
                   value="{{ old('start_date', $isEdit ? $consignment->start_date->format('Y-m-d') : now()->format('Y-m-d')) }}">
          </div>

          {{-- End date --}}
          <div class="col-md-2">
            <label class="fw-bold">End Date</label>
            <input type="date" name="end_date" class="form-control"
                   value="{{ old('end_date', $isEdit && $consignment->end_date ? $consignment->end_date->format('Y-m-d') : '') }}">
            <small class="text-muted">Leave blank for open-ended.</small>
          </div>

          {{-- Rates (used for material value calc) --}}
          <div class="col-md-2">
            <label class="fw-bold">Gold Rate (AED/g)</label>
            <input type="number" step="0.0001" name="gold_rate_aed" id="goldRateAed"
                   class="form-control" value="{{ old('gold_rate_aed', 0) }}" min="0">
          </div>
          <div class="col-md-2">
            <label class="fw-bold">Diamond Rate (AED/g)</label>
            <input type="number" step="0.0001" name="diamond_rate_aed" id="diaRateAed"
                   class="form-control" value="{{ old('diamond_rate_aed', 0) }}" min="0">
          </div>

          {{-- Remarks --}}
          <div class="col-12">
            <label class="fw-bold">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2"
            >{{ old('remarks', $isEdit ? $consignment->remarks : '') }}</textarea>
          </div>

        </div>

        {{-- ── Items ─────────────────────────────────────────────────── --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="fw-bold mb-0"><i class="fas fa-list me-1"></i>Items</h6>
          <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
            <i class="fas fa-plus me-1"></i>Add Item
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered" id="itemsTable">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th style="min-width:150px">Item Name</th>
                <th style="min-width:100px">Description</th>
                <th>Type</th>
                <th style="min-width:80px">Purity</th>
                <th style="min-width:90px">Gross Wt</th>
                <th style="min-width:90px">Making Rate</th>
                <th>VAT %</th>
                <th style="min-width:110px">Agreed Value</th>
                <th style="min-width:80px">Purity Wt</th>
                <th style="min-width:100px">Making Val</th>
                <th style="min-width:100px">Material Val</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="itemsBody">
              {{-- JS-rendered rows --}}
            </tbody>
            <tfoot>
              <tr class="table-secondary fw-bold">
                <td colspan="8" class="text-end">TOTAL</td>
                <td id="footAgreed" class="text-end text-primary">0.00</td>
                <td id="footPurity" class="text-end">0.000</td>
                <td id="footMaking" class="text-end">0.00</td>
                <td id="footMaterial" class="text-end">0.00</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="d-flex justify-content-end mt-3">
          <button type="submit" class="btn btn-success px-4">
            <i class="fas fa-save me-1"></i>
            {{ $isEdit ? 'Update Consignment' : 'Save Consignment' }}
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

{{-- ── JavaScript ──────────────────────────────────────────────────────── --}}
<script>
const IS_EDIT    = {{ $isEdit ? 'true' : 'false' }};
const PURITIES   = @json($purities->pluck('value','name'));
const INIT_ITEMS = {!! $isEdit ? $itemsJson : '[]' !!};

let rowIdx = 0;

function getGoldRate()   { return parseFloat(document.getElementById('goldRateAed').value) || 0; }
function getDiaRate()    { return parseFloat(document.getElementById('diaRateAed').value)  || 0; }

function calcRow(tr) {
    const gw   = parseFloat(tr.querySelector('[data-f="gross_weight"]').value) || 0;
    const pur  = parseFloat(tr.querySelector('[data-f="purity"]').value)       || 0;
    const mr   = parseFloat(tr.querySelector('[data-f="making_rate"]').value)  || 0;
    const vat  = parseFloat(tr.querySelector('[data-f="vat_percent"]').value)  || 0;
    const mt   = tr.querySelector('[data-f="material_type"]').value;
    const rate = mt === 'gold' ? getGoldRate() : getDiaRate();

    const pw   = gw * pur;
    const mv   = mr * gw;
    const matv = rate * pw;
    const vatv = mv * (vat / 100);
    const agreed = matv + mv + vatv;

    tr.querySelector('[data-f="purity_weight"]').value  = pw.toFixed(4);
    tr.querySelector('[data-f="making_value"]').value   = mv.toFixed(2);
    tr.querySelector('[data-f="material_value"]').value = matv.toFixed(2);

    const agreedInput = tr.querySelector('[data-f="agreed_value"]');
    // Only auto-set if user hasn't manually entered
    if (!agreedInput.dataset.manual || agreedInput.dataset.manual === '0') {
        agreedInput.value = agreed.toFixed(2);
    }

    updateFooter();
}

function updateFooter() {
    let totAgreed = 0, totPurity = 0, totMaking = 0, totMaterial = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        totAgreed   += parseFloat(tr.querySelector('[data-f="agreed_value"]')?.value) || 0;
        totPurity   += parseFloat(tr.querySelector('[data-f="purity_weight"]')?.value)|| 0;
        totMaking   += parseFloat(tr.querySelector('[data-f="making_value"]')?.value) || 0;
        totMaterial += parseFloat(tr.querySelector('[data-f="material_value"]')?.value)|| 0;
    });
    document.getElementById('footAgreed').textContent   = totAgreed.toFixed(2);
    document.getElementById('footPurity').textContent   = totPurity.toFixed(3);
    document.getElementById('footMaking').textContent   = totMaking.toFixed(2);
    document.getElementById('footMaterial').textContent = totMaterial.toFixed(2);
}

function buildRow(data = {}, idx = rowIdx++, isSold = false) {
    const tr = document.createElement('tr');
    if (isSold) tr.classList.add('table-success');

    // Helper to create hidden input (for sold/returned rows)
    function hiddenField(fname, val) {
        return `<input type="hidden" name="items[${idx}][${fname}]" value="${val ?? ''}">`;
    }

    if (isSold) {
        // Sold / returned rows: display-only, all fields hidden
        tr.innerHTML = `
            ${hiddenField('item_status', data.item_status)}
            ${hiddenField('item_name', data.item_name)}
            ${hiddenField('item_description', data.item_description)}
            ${hiddenField('barcode_number', data.barcode_number)}
            ${hiddenField('is_printed', data.is_printed ? 1 : 0)}
            ${hiddenField('gross_weight', data.gross_weight)}
            ${hiddenField('purity', data.purity)}
            ${hiddenField('purity_weight', data.purity_weight)}
            ${hiddenField('col_995', data.col_995)}
            ${hiddenField('making_rate', data.making_rate)}
            ${hiddenField('making_value', data.making_value)}
            ${hiddenField('material_type', data.material_type)}
            ${hiddenField('material_rate', data.material_rate)}
            ${hiddenField('material_value', data.material_value)}
            ${hiddenField('parts_total', data.parts_total)}
            ${hiddenField('taxable_amount', data.taxable_amount)}
            ${hiddenField('vat_percent', data.vat_percent)}
            ${hiddenField('vat_amount', data.vat_amount)}
            ${hiddenField('agreed_value', data.agreed_value)}
            <td colspan="12">
                <span class="badge bg-success me-2">SOLD</span>
                <strong>${data.barcode_number || ''}</strong> — ${data.item_name || '-'}
                &nbsp;|&nbsp; GW: ${parseFloat(data.gross_weight||0).toFixed(3)}g
                &nbsp;|&nbsp; Agreed: AED ${parseFloat(data.agreed_value||0).toFixed(2)}
            </td>
            <td></td>`;
        return tr;
    }

    if (data.item_status === 'returned') {
        tr.classList.add('table-warning');
        tr.innerHTML = `
            ${hiddenField('item_status', 'returned')}
            ${hiddenField('item_name', data.item_name)}
            ${hiddenField('agreed_value', data.agreed_value)}
            ${hiddenField('gross_weight', data.gross_weight)}
            ${hiddenField('purity', data.purity)}
            ${hiddenField('purity_weight', data.purity_weight)}
            ${hiddenField('making_rate', data.making_rate)}
            ${hiddenField('making_value', data.making_value)}
            ${hiddenField('material_type', data.material_type)}
            ${hiddenField('material_value', data.material_value)}
            ${hiddenField('vat_percent', data.vat_percent)}
            ${hiddenField('vat_amount', data.vat_amount)}
            ${hiddenField('parts_total', data.parts_total)}
            ${hiddenField('taxable_amount', data.taxable_amount)}
            <td colspan="12">
                <span class="badge bg-secondary me-2">RETURNED</span>
                ${data.item_name || '-'}
            </td>
            <td></td>`;
        return tr;
    }

    // Active in_stock row
    tr.innerHTML = `
        <td class="text-center row-num"></td>
        <td><input type="text" name="items[${idx}][item_name]" class="form-control form-control-sm" value="${data.item_name||''}" data-f="item_name"></td>
        <td><input type="text" name="items[${idx}][item_description]" class="form-control form-control-sm" value="${data.item_description||''}" data-f="item_description"></td>
        <td>
          <select name="items[${idx}][material_type]" class="form-select form-select-sm" data-f="material_type">
            <option value="gold"    ${(data.material_type||'gold')==='gold'    ? 'selected':''}>Gold</option>
            <option value="diamond" ${(data.material_type||'')==='diamond' ? 'selected':''}>Diamond</option>
          </select>
        </td>
        <td><input type="number" step="0.001" min="0" max="1" name="items[${idx}][purity]" class="form-control form-control-sm" value="${data.purity||''}" data-f="purity" placeholder="0.916"></td>
        <td><input type="number" step="0.001" min="0" name="items[${idx}][gross_weight]" class="form-control form-control-sm" value="${data.gross_weight||''}" data-f="gross_weight"></td>
        <td><input type="number" step="0.01"  min="0" name="items[${idx}][making_rate]" class="form-control form-control-sm" value="${data.making_rate||''}" data-f="making_rate"></td>
        <td><input type="number" step="0.01"  min="0" max="100" name="items[${idx}][vat_percent]" class="form-control form-control-sm" value="${data.vat_percent||0}" data-f="vat_percent"></td>
        <td><input type="number" step="0.01"  min="0" name="items[${idx}][agreed_value]" class="form-control form-control-sm fw-bold" value="${data.agreed_value||''}" data-f="agreed_value" data-manual="0"></td>
        <td><input type="number" step="0.0001" name="items[${idx}][purity_weight]" class="form-control form-control-sm bg-light" value="${data.purity_weight||''}" data-f="purity_weight" readonly></td>
        <td><input type="number" step="0.01" name="items[${idx}][making_value]" class="form-control form-control-sm bg-light" value="${data.making_value||''}" data-f="making_value" readonly></td>
        <td><input type="number" step="0.01" name="items[${idx}][material_value]" class="form-control form-control-sm bg-light" value="${data.material_value||''}" data-f="material_value" readonly></td>
        <td class="text-center">
          <button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="fas fa-times"></i></button>
        </td>`;

    // Hidden supplemental fields
    ['col_995','parts_total','taxable_amount','vat_amount','barcode_number','is_printed','item_status'].forEach(f => {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = `items[${idx}][${f}]`;
        inp.value = data[f] ?? (f === 'item_status' ? 'in_stock' : (f === 'is_printed' ? 0 : ''));
        inp.dataset.f = f;
        tr.appendChild(inp);
    });

    // Events
    tr.querySelectorAll('[data-f="gross_weight"],[data-f="purity"],[data-f="making_rate"],[data-f="vat_percent"],[data-f="material_type"]')
      .forEach(el => el.addEventListener('input', () => calcRow(tr)));

    tr.querySelector('[data-f="agreed_value"]').addEventListener('input', function() {
        this.dataset.manual = '1'; // user is overriding
        updateFooter();
    });

    tr.querySelector('.remove-item').addEventListener('click', () => {
        tr.remove();
        reNumber();
        updateFooter();
    });

    return tr;
}

function reNumber() {
    document.querySelectorAll('#itemsBody tr .row-num').forEach((el, i) => {
        el.textContent = i + 1;
    });
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.getElementById('addItemBtn').addEventListener('click', () => {
    const tr = buildRow({});
    document.getElementById('itemsBody').appendChild(tr);
    reNumber();
    calcRow(tr);
});

// Rate change recalculates all editable rows
['goldRateAed','diaRateAed'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => {
        document.querySelectorAll('#itemsBody tr').forEach(tr => {
            if (tr.querySelector('[data-f="gross_weight"]:not([readonly])')) calcRow(tr);
        });
    });
});

// Select2
$(document).ready(() => {
    $('.select2-js').select2({ width: '100%' });
});

// Load existing items on edit
INIT_ITEMS.forEach(item => {
    const isSold = item.item_status === 'sold';
    const tr = buildRow(item, rowIdx++, isSold);
    document.getElementById('itemsBody').appendChild(tr);
});
reNumber();
updateFooter();
</script>
@endsection