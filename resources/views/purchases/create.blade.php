@extends('layouts.app')

@section('title', 'Purchase | New Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
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
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">New Purchase Invoice</h2>
        </header>

        <div class="card-body">
          <div class="row">

            <div class="col-md-2 mb-3">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Payment Terms</label>
              <input type="text" name="payment_terms" class="form-control">
            </div>

            <div class="col-md-1 mb-3">
              <label>Bill #</label>
              <input type="text" name="bill_no" class="form-control">
            </div>

            <div class="col-md-2 mb-3">
              <label>Ref.</label>
              <input type="text" name="ref_no" class="form-control">
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3"></textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="purchaseTable">
              <thead>
                <tr>
                  <th>Item Name</th>
                  <th>Variation</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Price</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="Purchase1Table">
                <tr class="item-row" data-item-index="0">
                  <td>
                      <div class="product-wrapper">
                        <select name="items[0][item_id]" id="item_name1" class="form-control select2-js product-select" onchange="onItemNameChange(this)">
                          <option value="">Select product</option>
                          @foreach($products as $p)
                            <option value="{{ $p->id }}" data-unit-id="{{ $p->measurement_unit }}">
                              {{ $p->name }}
                            </option>
                          @endforeach
                        </select>

                        <input type="text" name="items[0][temp_product_name]" class="form-control new-product-input mt-1" style="display:none" placeholder="Enter new product name">

                        <button type="button" class="btn btn-link p-0 toggle-new"> + Product </button>
                      </div>
                    </td>

                  <td>
                    <select name="items[0][variation_id]" class="form-control select2-js variation-select">
                      <option value="">Select Variation</option>
                    </select>
                  </td>                  

                  <td><input type="number" name="items[0][quantity]" id="pur_qty1" class="form-control quantity" value="0" step="any" onchange="rowTotal(1)"></td>

                  <td>
                    <select name="items[0][unit]" id="unit1" class="form-control" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
                      @endforeach
                    </select>
                  </td>

                  <td><input type="number" name="items[0][price]" id="pur_price1" class="form-control" value="0" step="any" onchange="rowTotal(1)"></td>
                  <td><input type="number" id="amount1" class="form-control" value="0" step="any" disabled></td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                    <button type="button" class="btn btn-sm btn-primary toggle-parts"> <i class="fas fa-wrench"></i> </button>
                  </td>
                </tr>
                <tr class="parts-row" style="display:none;background:#efefef">
                  <td colspan="7">
                    <div class="parts-wrapper">
                      <table class="table table-sm table-bordered parts-table">
                        <thead>
                          <tr>
                            <th>Part</th>
                            <th>Qty</th>
                            <th>Rate</th>
                            <th>Wastage</th>
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
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow_btn()"><i class="fas fa-plus"></i> Add Item</button>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" id="totalAmount" class="form-control" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show">
            </div>
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="text" id="total_quantity" class="form-control" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show">
            </div>
            <div class="col-md-2">
              <label>Convance Charges</label>
              <input type="number" name="convance_charges" id="convance_charges" class="form-control" value="0" onchange="netTotal()">
            </div>
            <div class="col-md-2">
              <label>Labour Charges</label>
              <input type="number" name="labour_charges" id="labour_charges" class="form-control" value="0" onchange="netTotal()">
            </div>
            <div class="col-md-2">
              <label>Bill Discount</label>
              <input type="number" name="bill_discount" id="bill_discount" class="form-control" value="0" onchange="netTotal()">
            </div>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Net Amount: <strong class="text-danger">PKR <span id="netTotal">0.00</span></strong></h4>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"> <i class="fas fa-save"></i> Save Invoice</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var index = 2;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });

    // 🔹 Manual Product selection flow
    $(document).on('change', '.product-select', function () {
      const row = $(this).closest('tr');
      const productId = $(this).val();
      const preselectVariationId = $(this).data('preselectVariationId') || null;
      $(this).removeData('preselectVariationId');

      if (productId) {
        loadVariations(row, productId, preselectVariationId);
      } else {
        row.find('.variation-select')
          .html('<option value="">Select Variation</option>')
          .prop('disabled', false)
          .trigger('change');
      }
    });

    $(document).on('click', '.toggle-new', function () {
      let wrapper = $(this).closest('.product-wrapper');
      let select  = wrapper.find('.product-select');
      let input   = wrapper.find('.new-product-input');

      let select2 = select.next('.select2-container');

      if (input.is(':visible')) {
        input.hide().val('');
        select.show();
        select2.show();
        select.val('').trigger('change');
        $(this).text('+ New');
      } else {
        select.val('').trigger('change');
        select.hide();
        select2.hide();
        input.show().focus();
        $(this).text('Cancel');
      }
    });

    $(document).on('click', '.toggle-parts', function () {
      let itemRow = $(this).closest('tr');
      let partsRow = itemRow.next('.parts-row');
      partsRow.toggle();
    });

    $(document).on('click', '.add-part', function () {
      let partsRow = $(this).closest('.parts-row');
      let partsTable = partsRow.find('.parts-table tbody');
      let itemIndex = partsRow.prev('.item-row').data('item-index'); // ✅ gets numeric index
      addPartRow(partsTable, itemIndex);
    });

    $(document).on('click', '.remove-part', function () {
      $(this).closest('tr').remove();
    });

    $(document).on('input', '.part-qty, .part-rate, .part-wastage', function () {
      let row = $(this).closest('tr');

      let qty = parseFloat(row.find('.part-qty').val()) || 0;
      let rate = parseFloat(row.find('.part-rate').val()) || 0;
      let wastage = parseFloat(row.find('.part-wastage').val()) || 0;

      let total = (qty + wastage) * rate;
      row.find('.part-total').val(total.toFixed(2));

      recalcItemTotal(row);
    });


  });

  // 🔹 Keep all your existing functions exactly as they are
  function onItemNameChange(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.options[selectElement.selectedIndex];

    const itemId = selectedOption.value;
    const unitId = selectedOption.getAttribute('data-unit-id');

    const idMatch = selectElement.id.match(/\d+$/);
    if (!idMatch) return;

    const index = idMatch[0];

    const unitSelector = $(`#unit${index}`);
    unitSelector.val(String(unitId)).trigger('change.select2');
  }

  function addPartRow(partsTable, itemIndex) {
    let partIndex = partsTable.find('tr').length;

    let row = `
      <tr style="background:#efefef">
        <td>
          <select name="items[${itemIndex}][parts][${partIndex}][product_id]" class="form-control select2-js">
            <option value="">Select Part</option>
            ${products.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
          </select>
        </td>
        <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][qty]" class="form-control part-qty" step="any"></td>
        <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][rate]" class="form-control part-rate" step="any"></td>
        <td><input type="number" name="items[${itemIndex}][parts][${partIndex}][wastage]" class="form-control part-wastage" step="any"></td>
        <td><input type="number" class="form-control part-total" disabled></td>
        <td><button type="button" class="btn btn-sm btn-danger remove-part">×</button></td>
      </tr>
    `;

    partsTable.append(row);
    partsTable.find('.select2-js').select2({ width: '100%' });
  }

  function removeRow(button) {
    let rows = $('#Purchase1Table tr').length;
    if (rows > 1) {
      $(button).closest('tr').remove();
      $('#itemCount').val(--rows);
      tableTotal();
    }
  }

  function addNewRow_btn() {
    addNewRow();
  }

  function addNewRow() {
    let table = $('#Purchase1Table');
    let rowIndex = index - 1;

    let newRow = `
      <tr class="item-row" data-item-index="${rowIndex}">
        <td>
          <div class="product-wrapper">
            <select name="items[${rowIndex}][item_id]" id="item_name${index}" class="form-control select2-js product-select" onchange="onItemNameChange(this)">
              <option value="">Select product</option>
              ${products.map(p =>
                `<option value="${p.id}" data-unit-id="${p.measurement_unit}">
                  ${p.name}
                </option>`).join('')}
            </select>

            <input type="text" name="items[${rowIndex}][temp_product_name]" class="form-control new-product-input mt-1" style="display:none" placeholder="Enter new product name">

            <button type="button" class="btn btn-link p-0 toggle-new"> + Product </button>
          </div>
        </td>

        <td>
          <select name="items[${rowIndex}][variation_id]" class="form-control select2-js variation-select">
            <option value="">Select Variation</option>
          </select>
        </td>

        <td><input type="number" name="items[${rowIndex}][quantity]" id="pur_qty${index}" class="form-control quantity" value="0" step="any" onchange="rowTotal(${index})"></td>

        <td>
          <select name="items[${rowIndex}][unit]" id="unit${index}" class="form-control" required>
            <option value="">-- Select --</option>
            @foreach ($units as $unit)
              <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
            @endforeach
          </select>
        </td>

        <td><input type="number" name="items[${rowIndex}][price]" id="pur_price${index}" class="form-control" value="0" step="any" onchange="rowTotal(${index})"></td>
        <td><input type="number" id="amount${index}" class="form-control" value="0" step="any" disabled></td>
        <td>
          <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
          <button type="button" class="btn btn-sm btn-primary toggle-parts"> <i class="fas fa-wrench"></i> </button>
        </td>
      </tr>
    `;

    let partsRowHtml = `
      <tr class="parts-row" style="display:none;background:#efefef">
        <td colspan="7">
          <div class="parts-wrapper">
            <table class="table table-sm table-bordered parts-table">
              <thead>
                <tr>
                  <th>Part</th>
                  <th>Qty</th>
                  <th>Rate</th>
                  <th>Wastage</th>
                  <th>Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>

            <button type="button"
                    class="btn btn-sm btn-outline-primary add-part">
              + Add Part
            </button>
          </div>
        </td>
      </tr>`;


    table.append(newRow);
    table.append(partsRowHtml);
    $('#itemCount').val(index);
    $(`#item_name${index}`).select2();
    $(`#unit${index}`).select2();
    index++;
  }

  function rowTotal(row) {
    let quantity = parseFloat($('#pur_qty' + row).val()) || 0;
    let price = parseFloat($('#pur_price' + row).val()) || 0;
    $('#amount' + row).val((quantity * price).toFixed(2));
    tableTotal();
  }

  function tableTotal() {
    let total = 0, qty = 0;
    $('#Purchase1Table tr').each(function () {
      total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
      qty += parseFloat($(this).find('input[name="quantity[]"]').val()) || 0;
    });
    $('#totalAmount').val(total.toFixed(2));
    $('#total_amount_show').val(total.toFixed(2));
    $('#total_quantity').val(qty.toFixed(2));
    $('#total_quantity_show').val(qty.toFixed(2));
    netTotal();
  }

  function recalcItemTotal(partRow) {
    let partsRow = partRow.closest('.parts-row');
    let itemRow = partsRow.prev('tr');

    let sum = 0;
    partsRow.find('.part-total').each(function () {
      sum += parseFloat($(this).val()) || 0;
    });

    itemRow.find('.item-total').val(sum.toFixed(2));
  }

  function netTotal() {
    let total = parseFloat($('#totalAmount').val()) || 0;
    let conv = parseFloat($('#convance_charges').val()) || 0;
    let labour = parseFloat($('#labour_charges').val()) || 0;
    let discount = parseFloat($('#bill_discount').val()) || 0;
    let net = (total + conv + labour - discount).toFixed(2);
    $('#netTotal').text(formatNumberWithCommas(net));
    $('#net_amount').val(net);
  }

  function formatNumberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  // 🔹 Load Variations
  function loadVariations(row, productId, preselectVariationId = null) {
    const $variationSelect = row.find('.variation-select');
    $variationSelect.html('<option>Loading...</option>').prop('disabled', true);

    $.get(`/product/${productId}/variations`, function (data) {
      let options = '<option value="" disabled selected>Select Variation</option>';

      if ((data.variation || []).length > 0) {
        data.variation.forEach(v => {
          options += `<option value="${v.id}">${v.sku}</option>`;
        });
        $variationSelect.prop('disabled', false);
      } else {
        // ✅ Only placeholder, stays disabled
        options = '<option value="" disabled selected>No Variations Available</option>';
        $variationSelect.prop('disabled', true);
      }

      $variationSelect.html(options);

      if ($variationSelect.hasClass('select2-hidden-accessible')) {
        $variationSelect.select2('destroy');
      }
      $variationSelect.select2({ width: '100%', dropdownAutoWidth: true });

      if (preselectVariationId) {
        $variationSelect.val(String(preselectVariationId)).trigger('change');
      }
    });
  }

</script>

@endsection
