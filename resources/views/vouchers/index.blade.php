@extends('layouts.app')

@section('title', ucfirst($type) . ' Vouchers')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">
          <i class="fas fa-money-check-alt me-2"></i>
          {{ ucfirst($type) }} Vouchers
        </h2>
        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
          <i class="fas fa-plus"></i> Add New
        </button>
      </header>

      {{-- ── Permission-aware tab bar ───────────────────────────────────────── --}}
      <div class="card-body pb-0 pt-2">
        <ul class="nav nav-tabs">

          {{-- Purchase tab: only users who can create purchase invoices --}}
          @can('purchase_invoices.index')
            <li class="nav-item">
              <a class="nav-link {{ $type === 'purchase' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'purchase') }}">
                Purchase
              </a>
            </li>
          @endcan

          {{-- Sale tab: only users who can access sale invoices --}}
          @can('sale_invoices.index')
            <li class="nav-item">
              <a class="nav-link {{ $type === 'sale' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'sale') }}">
                Sale
              </a>
            </li>
          @endcan

          {{-- Journal / Payment / Receipt: vouchers.index permission --}}
          @can('vouchers.index')
            <li class="nav-item">
              <a class="nav-link {{ $type === 'journal' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'journal') }}">
                Journal
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ $type === 'payment' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'payment') }}">
                Payment
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ $type === 'receipt' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'receipt') }}">
                Receipt
              </a>
            </li>
          @endcan

        </ul>
      </div>

      <div class="card-body">

        @if(session('success'))
          <div class="alert alert-success alert-dismissible mb-3">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            {{ session('success') }}
          </div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger alert-dismissible mb-3">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            {{ session('error') }}
          </div>
        @endif

        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0" id="voucher-datatable">
            <thead>
              <tr>
                <th>Voucher #</th>
                <th>Date</th>
                <th>Debit Account(s)</th>
                <th>Credit Account(s)</th>
                <th>Source Document</th>
                <th>Remarks</th>
                <th class="text-end">Total (AED)</th>
                <th class="text-center" style="width:90px">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($vouchers as $row)
                <tr>
                  <td class="fw-bold">
                    {{ $row->voucher_no }}
                    @if($row->is_auto)
                      <span class="badge bg-info text-dark ms-1" style="font-size:.65rem;">auto</span>
                    @endif
                  </td>

                  <td class="text-nowrap">{{ $row->voucher_date?->format('d-m-Y') }}</td>

                  <td style="min-width:200px">
                    @foreach($row->display_debits as $d)
                      <div class="d-flex justify-content-between gap-2 py-0" style="line-height:1.4">
                        <span style="font-size:.85rem">{{ $d['account'] }}</span>
                        <span class="text-muted text-nowrap" style="font-size:.8rem">{{ number_format($d['amount'], 2) }}</span>
                      </div>
                    @endforeach
                  </td>

                  <td style="min-width:200px">
                    @foreach($row->display_credits as $c)
                      <div class="d-flex justify-content-between gap-2 py-0" style="line-height:1.4">
                        <span style="font-size:.85rem">{{ $c['account'] }}</span>
                        <span class="text-muted text-nowrap" style="font-size:.8rem">{{ number_format($c['amount'], 2) }}</span>
                      </div>
                    @endforeach
                  </td>

                  <td>
                    @if($row->reference_label)
                      @if($row->reference_link)
                        <a href="{{ $row->reference_link }}"
                           class="badge bg-warning text-dark text-decoration-none"
                           style="font-size:.72rem;white-space:normal;"
                           title="Open source document">
                          <i class="fas fa-external-link-alt me-1"></i>{{ $row->reference_label }}
                        </a>
                      @else
                        <span class="badge bg-secondary" style="font-size:.72rem;white-space:normal;">
                          {{ $row->reference_label }}
                        </span>
                      @endif
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>

                  <td>
                    <span class="text-muted" style="font-size:.82rem;">
                      {{ Str::limit($row->remarks ?? '', 50) }}
                    </span>
                  </td>

                  <td class="text-end fw-bold text-nowrap">
                    {{ number_format($row->display_total, 2) }}
                  </td>

                  <td class="text-center text-nowrap actions">
                    <a class="text-success me-1"
                       href="{{ route('vouchers.print', ['type' => $type, 'id' => $row->id]) }}"
                       title="Print PDF" target="_blank">
                      <i class="fas fa-print"></i>
                    </a>

                    @if(!$row->is_auto)
                      <a class="text-primary me-1 modal-with-form"
                         href="#updateModal"
                         onclick="getVoucherDetails({{ $row->id }})"
                         title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                    @else
                      <span class="text-secondary me-1"
                            title="Auto-generated — edit the source document ({{ $row->reference_label ?? 'invoice' }}) to modify accounting entries"
                            style="cursor:help;">
                        <i class="fas fa-lock"></i>
                      </span>
                    @endif

                    <a class="text-danger modal-with-form"
                       href="#deleteModal"
                       onclick="setDeleteId({{ $row->id }})"
                       title="Delete">
                      <i class="fas fa-trash-alt"></i>
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                    No {{ ucfirst($type) }} vouchers found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </section>


    {{-- ============================================================ --}}
    {{-- ADD VOUCHER MODAL                                            --}}
    {{-- ============================================================ --}}
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST"
              action="{{ route('vouchers.store', $type) }}"
              enctype="multipart/form-data"
              onkeydown="return event.key !== 'Enter';">
          @csrf
          <input type="hidden" name="voucher_type" value="{{ $type }}">

          <header class="card-header">
            <h2 class="card-title">Add {{ ucfirst($type) }} Voucher</h2>
          </header>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="voucher_date"
                       value="{{ date('Y-m-d') }}" required>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Debit Account <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_dr_sid" required>
                  <option value="" disabled selected>Select Account</option>
                  @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">[{{ $acc->account_code }}] {{ $acc->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Credit Account <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_cr_sid" required>
                  <option value="" disabled selected>Select Account</option>
                  @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">[{{ $acc->account_code }}] {{ $acc->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Amount (AED) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="amount"
                       step="0.01" min="0.01" value="0" required>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Attachments</label>
                <input type="file" class="form-control" name="att[]" multiple
                       accept=".zip,application/pdf,image/png,image/jpeg">
              </div>
              <div class="col-lg-12 mb-2">
                <label>Remarks</label>
                <textarea rows="2" class="form-control" name="remarks"></textarea>
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Save Voucher</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>


    {{-- ============================================================ --}}
    {{-- EDIT VOUCHER MODAL                                           --}}
    {{-- ============================================================ --}}
    <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="updateForm" enctype="multipart/form-data"
              onkeydown="return event.key !== 'Enter';">
          @csrf
          @method('PUT')
          <input type="hidden" name="voucher_type" value="{{ $type }}">

          <header class="card-header">
            <h2 class="card-title">
              Edit {{ ucfirst($type) }} Voucher
              <span id="edit_voucher_no" class="text-primary fs-6 ms-1"></span>
            </h2>
          </header>

          <div class="card-body">
            <div id="auto_voucher_alert" class="alert alert-warning d-none">
              <i class="fas fa-lock me-1"></i>
              This voucher was <strong>auto-generated from an invoice</strong>.
              Accounting entries are shown below as read-only.
              To change them, edit the source invoice.
              <span id="auto_ref_link"></span>
            </div>

            <div id="simple_fields">
              <div class="row">
                <div class="col-lg-6 mb-2">
                  <label>Date <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" name="voucher_date" id="update_date" required>
                </div>
                <div class="col-lg-6 mb-2">
                  <label>Debit Account <span class="text-danger">*</span></label>
                  <select class="form-control select2-js" name="ac_dr_sid" id="update_ac_dr_sid" required>
                    <option value="">Select Account</option>
                    @foreach($accounts as $acc)
                      <option value="{{ $acc->id }}">[{{ $acc->account_code }}] {{ $acc->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-lg-6 mb-2">
                  <label>Credit Account <span class="text-danger">*</span></label>
                  <select class="form-control select2-js" name="ac_cr_sid" id="update_ac_cr_sid" required>
                    <option value="">Select Account</option>
                    @foreach($accounts as $acc)
                      <option value="{{ $acc->id }}">[{{ $acc->account_code }}] {{ $acc->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-lg-6 mb-2">
                  <label>Amount (AED) <span class="text-danger">*</span></label>
                  <input type="number" class="form-control" name="amount" id="update_amount"
                         step="0.01" min="0.01" required>
                </div>
                <div class="col-lg-6 mb-2">
                  <label>Attachments</label>
                  <input type="file" class="form-control" name="att[]" multiple
                         accept=".zip,application/pdf,image/png,image/jpeg">
                </div>
                <div class="col-lg-12 mb-2">
                  <label>Remarks</label>
                  <textarea rows="2" class="form-control" name="remarks" id="update_remarks"></textarea>
                </div>
              </div>
            </div>

            <div id="entries_panel" class="d-none">
              <p class="fw-bold mb-1 small text-muted text-uppercase">Accounting Entries (read-only)</p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Account</th>
                      <th class="text-end">Debit</th>
                      <th class="text-end">Credit</th>
                      <th>Narration</th>
                    </tr>
                  </thead>
                  <tbody id="entries_tbody"></tbody>
                </table>
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary" id="update_submit_btn">Update Voucher</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>


    {{-- ============================================================ --}}
    {{-- DELETE MODAL                                                  --}}
    {{-- ============================================================ --}}
    <div id="deleteModal" class="modal-block modal-block-warning mfp-hide">
      <section class="card">
        <form method="POST" id="deleteForm">
          @csrf
          @method('DELETE')
          <header class="card-header">
            <h2 class="card-title">Delete Voucher</h2>
          </header>
          <div class="card-body">
            <p class="mb-1">Are you sure you want to delete this voucher?</p>
            <p class="text-muted small mb-0">
              <i class="fas fa-exclamation-triangle text-warning"></i>
              For auto-generated vouchers this will also delete all linked accounting entries.
              The entries will be recreated automatically when the source invoice is saved again.
            </p>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-danger">Yes, Delete</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

  </div>
</div>

<script>
function getVoucherDetails(id) {
    const type = '{{ $type }}';
    document.getElementById('auto_voucher_alert').classList.add('d-none');
    document.getElementById('entries_panel').classList.add('d-none');
    document.getElementById('entries_tbody').innerHTML = '';
    document.getElementById('simple_fields').style.display = '';
    document.getElementById('update_submit_btn').disabled  = false;
    document.getElementById('auto_ref_link').innerHTML     = '';
    document.getElementById('updateForm').action = `/vouchers/${type}/${id}`;

    fetch(`/vouchers/${type}/${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('edit_voucher_no').textContent = '#' + data.voucher_no;
            document.getElementById('update_date').value           = data.date    ?? '';
            document.getElementById('update_amount').value         = data.amount  ?? 0;
            document.getElementById('update_remarks').value        = data.remarks ?? '';

            if (data.is_simple) {
                $('#update_ac_dr_sid').val(data.ac_dr_sid).trigger('change');
                $('#update_ac_cr_sid').val(data.ac_cr_sid).trigger('change');
            } else {
                document.getElementById('auto_voucher_alert').classList.remove('d-none');
                document.getElementById('simple_fields').style.display = 'none';
                document.getElementById('update_submit_btn').disabled  = true;

                const tbody = document.getElementById('entries_tbody');
                (data.entries || []).forEach(e => {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td style="font-size:.85rem">${e.account_name}</td>
                            <td class="text-end" style="font-size:.85rem">${e.debit  > 0 ? Number(e.debit).toFixed(2)  : ''}</td>
                            <td class="text-end" style="font-size:.85rem">${e.credit > 0 ? Number(e.credit).toFixed(2) : ''}</td>
                            <td class="text-muted" style="font-size:.8rem">${e.narration ?? ''}</td>
                        </tr>`);
                });
                document.getElementById('entries_panel').classList.remove('d-none');
            }
        })
        .catch(() => alert('Failed to load voucher details. Please try again.'));
}

function setDeleteId(id) {
    const type = '{{ $type }}';
    document.getElementById('deleteForm').action = `/vouchers/${type}/${id}`;
}
</script>
@endsection