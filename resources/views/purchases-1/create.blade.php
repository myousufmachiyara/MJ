@extends('layouts.app')

@section('title', 'Purchase | New Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices_1.store') }}" method="POST" enctype="multipart/form-data">
      @csrf

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
          <div class="row mb-3">
            <div class="col-md-2">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>

            <div class="col-md-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-4">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control"></textarea>
            </div>
          </div>

          {{-- TABLE --}}
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th width="15%" rowspan="2">Item Description</th>
                  <th rowspan="2">Purity</th>
                  <th rowspan="2">Gross Wt</th>
                  <th rowspan="2">Purity Wt</th>
                  <th colspan="2" class="text-center">Making</th>
                  <th rowspan="2">Metal Val</th>
                  <th rowspan="2">Taxable (MC)</th>
                  <th rowspan="2">VAT %</th>
                  <th rowspan="2">VAT Amt</th>
                  <th rowspan="2">Gross Total</th>
                  <th rowspan="2">Action</th>
                </tr>
                <tr>
                  <th>Rate</th>
                  <th>Value</th>
                </tr>
              </thead>

              <tbody id="Purchase1Table">
                <tr>
                  <td><input type="text" name="items[0][item_description]" class="form-control" required></td>
                  <td><input type="number" name="items[0][purity]" step="any" value="0" class="form-control"></td>

                  <td><input type="number" name="items[0][gross_weight]" step="any" value="0" class="form-control gross-weight"></td>
                  <td><input type="number" name="items[0][purity_weight]" step="any" value="0" class="form-control purity-weight"></td>

                  <td><input type="number" name="items[0][making_rate]"  step="any" value="0" class="form-control making-rate"></td>
                  <td><input type="number" name="items[0][making_value]" step="any" class="form-control making-value" readonly></td>

                  <td><input type="number" name="items[0][metal_value]" step="any" value="0" class="form-control metal-value"></td>
                  <td><input type="number" name="items[0][taxable_amount]" step="any" value="0" class="form-control taxable-amount"></td>

                  <td><input type="number" name="items[0][vat_percent]" class="form-control vat-percent" step="any" value="0"></td>
                  <td><input type="number" step="any" class="form-control vat-amount" readonly></td>

                  <td><input type="number" class="form-control item-total" readonly></td>

                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>

            <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">Add Item</button>
          </div>

          {{-- SUMMARY --}}
          <div class="row mt-5 mb-3">
            <div class="col-md-2">
              <label>Total Gross Wt</label>
              <input type="text" id="sum_gross_weight" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Purity Wt</label>
              <input type="text" id="sum_purity_weight" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Making</label>
              <input type="text" id="sum_making_value" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total Metal</label>
              <input type="text" id="sum_metal_value" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Total VAT</label>
              <input type="text" id="sum_vat_amount" class="form-control" readonly>
            </div>
            <div class="col-md-2">
              <label>Net Amount</label>
              <input type="text" id="net_amount_display" class="form-control text-danger fw-bold" readonly>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>

          {{-- PAYMENT METHOD --}}
          <hr>

          <div class="row mb-3">
            <div class="col-md-3">
              <label class="fw-bold">Payment Method</label>
              <select name="payment_method" id="payment_method" class="form-control" required>
                <option value="">Select Payment Method</option>
                <option value="cash">Cash</option>
                <option value="credit">Credit</option>
                <option value="cheque">Cheque</option>
                <option value="material">Metal + Making Cost</option>
              </select>
            </div>
          </div>

          {{-- CHEQUE DETAILS --}}
          <div class="row mb-3 d-none" id="cheque_fields">
            <div class="col-md-3">
              <label>Cheque No</label>
              <input type="text" name="cheque_no" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Cheque Date</label>
              <input type="date" name="cheque_date" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Bank Name</label>
              <input type="text" name="bank_name" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Cheque Amount</label>
              <input type="number" step="any" name="cheque_amount" class="form-control">
            </div>
          </div>

          {{-- MATERIAL + MAKING COST --}}
          <div class="row mb-3 d-none" id="material_fields">
            <div class="col-md-3">
              <label>Raw Metal Weight Given</label>
              <input type="number" step="any" name="material_weight" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Raw Metal Purity</label>
              <input type="number" step="any" name="material_purity" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Metal Adjustment Value</label>
              <input type="number" step="any" name="material_value" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Making Charges Payable</label>
              <input type="number" step="any" name="making_charges" class="form-control">
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Currency</h6>
            </div>

            <div class="card-body">
                <div class="row">

                    <div class="col-md-4">
                        <label class="form-label">Invoice Currency</label>
                        <select name="currency" id="currency" class="form-control">
                            <option value="AED" selected>AED – Dirhams</option>
                            <option value="USD">USD – Dollars</option>
                        </select>
                    </div>

                    <div class="col-md-4" id="exchangeRateBox" style="display:none;">
                        <label class="form-label">USD → AED Rate</label>
                        <input type="number" step="0.000001" name="exchange_rate"
                              id="exchange_rate"
                              class="form-control"
                              placeholder="e.g. 3.6725">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Converted Total (AED)</label>
                        <input type="text" id="converted_total"
                              class="form-control"
                              readonly>
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
  let index = 1;

  const currencySelect = document.getElementById('currency');
  const rateBox = document.getElementById('exchangeRateBox');
  const rateInput = document.getElementById('exchange_rate');
  const convertedTotal = document.getElementById('converted_total');

  function updateConversion() {
    let netTotal = parseFloat(document.getElementById('net_amount').value || 0);
    let rate = parseFloat(rateInput.value || 0);

    if (currencySelect.value === 'USD' && rate > 0) {
        convertedTotal.value = (netTotal * rate).toFixed(2);
    } else {
        convertedTotal.value = netTotal.toFixed(2);
    }
  }

  currencySelect.addEventListener('change', function () {
    if (this.value === 'USD') {
      rateBox.style.display = 'block';
    } else {
      rateBox.style.display = 'none';
      rateInput.value = '';
    }
    updateConversion();
  });

  rateInput.addEventListener('input', updateConversion);
  $(document).ready(function () {
    $('.select2-js').select2({
      width: '100%'
    });
  });

  function addNewRow() {
    let row = `
    <tr>
      <td><input type="text" name="items[${index}][item_description]" class="form-control"></td>
      <td><input type="number" name="items[${index}][purity]" step="any" value="0" class="form-control"></td>

      <td><input type="number" name="items[${index}][gross_weight]" step="any" value="0" class="form-control gross-weight"></td>
      <td><input type="number" name="items[${index}][purity_weight]" step="any" value="0" class="form-control purity-weight"></td>

      <td><input type="number" name="items[${index}][making_rate]" step="any" value="0" class="form-control making-rate" ></td>
      <td><input type="number" name="items[${index}][making_value]" step="any" class="form-control making-value" readonly></td>

      <td><input type="number" name="items[${index}][metal_value]" step="any" value="0" class="form-control metal-value"></td>
      <td><input type="number" name="items[${index}][taxable_amount]" step="any" value="0" class="form-control taxable-amount"></td>

      <td><input type="number" name="items[${index}][vat_percent]" class="form-control vat-percent" step="any" value="0"></td>
      <td><input type="number" step="any" class="form-control vat-amount" readonly></td>

      <td><input type="number" class="form-control item-total" readonly></td>

      <td>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
          <i class="fas fa-times"></i>
        </button>
      </td>
    </tr>`;
    $('#Purchase1Table').append(row);
    index++;
  }

  function removeRow(btn) {
    if ($('#Purchase1Table tr').length > 1) {
      $(btn).closest('tr').remove();
      calculateTotals();
    }
  }

  $(document).on('input', '.gross-weight,.purity-weight,.making-value,.metal-value,.taxable-amount,.vat_percent', calculateTotals);

  function calculateTotals() {
    let gross=0,purity=0,making=0,metal=0,vat=0,net=0;

    $('.gross-weight').each((i,e)=>gross+=+e.value||0);
    $('.purity-weight').each((i,e)=>purity+=+e.value||0);
    $('.making-value').each((i,e)=>making+=+e.value||0);
    $('.metal-value').each((i,e)=>metal+=+e.value||0);
    $('.vat_percent').each((i,e)=>vat+=+e.value||0);
    $('.taxable-amount').each((i,e)=>net+=+e.value||0);

    net += vat;

    $('#sum_gross_weight').val(gross.toFixed(2));
    $('#sum_purity_weight').val(purity.toFixed(2));
    $('#sum_making_value').val(making.toFixed(2));
    $('#sum_metal_value').val(metal.toFixed(2));
    $('#sum_vat_amount').val(vat.toFixed(2));
    $('#net_amount_display').val(net.toFixed(2));
    $('#net_amount').val(net.toFixed(2));
  }

  $('#payment_method').on('change', function () {
    let method = $(this).val();

    // Hide all conditional sections
    $('#cheque_fields').addClass('d-none');
    $('#material_fields').addClass('d-none');

    if (method === 'cheque') {
      $('#cheque_fields').removeClass('d-none');
    }

    if (method === 'material') {
      $('#material_fields').removeClass('d-none');
    }
  });

</script>
@endsection
