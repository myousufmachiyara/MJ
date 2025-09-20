@extends('layouts.app')

@section('title', 'Market Rates')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Market Rates</h2>
        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
          <i class="fas fa-plus"></i> Add New
        </button>
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0" id="rates-datatable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Variation</th>
                <th>Rate / Unit</th>
                <th>Effective Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($rates as $row)
                <tr>
                  <td>{{ $row->id }}</td>
                  <td>{{ $row->product->name ?? '-' }}</td>
                  <td>{{ $row->variation->name ?? '-' }}</td>
                  <td><strong>{{ number_format($row->rate_per_unit, 2) }}</strong></td>
                  <td>{{ \Carbon\Carbon::parse($row->effective_date)->format('d-m-Y') }}</td>
                  <td class="actions">
                    <a class="text-primary modal-with-form" onclick="getRateDetails({{ $row->id }})" href="#updateModal"><i class="fas fa-edit"></i></a>
                    <a class="btn btn-link p-0 m-0 text-danger" onclick="setDeleteId({{ $row->id }})" href="#deleteModal"><i class="fas fa-trash-alt"></i></a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Add Rate Modal -->
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="post" action="{{ route('market_rates.store') }}" onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">Add Market Rate</h2>
          </header>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Product <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="product_id" id="add_product_id" required onchange="loadVariations('add')">
                  <option value="" disabled selected>Select Product</option>
                  @foreach($products as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Variation</label>
                <select class="form-control select2-js" name="variation_id" id="add_variation_id">
                  <option value="">Select Variation</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Rate / Unit <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="rate_per_unit" step="any" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Effective Date</label>
                <input type="date" class="form-control" name="effective_date" value="{{ date('Y-m-d') }}" required>
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Add Rate</button>
            <button class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

    <!-- Update Rate Modal -->
    <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="updateForm" onkeydown="return event.key != 'Enter';">
          @csrf
          @method('PUT')

          <header class="card-header">
            <h2 class="card-title">Update Market Rate</h2>
          </header>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Rate ID</label>
                <input type="text" class="form-control" id="update_id" disabled>
                <input type="hidden" name="rate_id" id="update_id_hidden">
              </div>

              <div class="col-lg-6 mb-2">
                <label>Product <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="product_id" id="update_product_id" required onchange="loadVariations('update')">
                  <option value="" disabled>Select Product</option>
                  @foreach($products as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Variation</label>
                <select class="form-control select2-js" name="variation_id" id="update_variation_id">
                  <option value="">Select Variation</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Rate / Unit <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="rate_per_unit" id="update_rate_per_unit" step="any" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Effective Date</label>
                <input type="date" class="form-control" name="effective_date" id="update_effective_date" required>
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Update Rate</button>
            <button class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal-block modal-block-warning mfp-hide">
      <section class="card">
        <form method="POST" id="deleteForm">
          @csrf
          @method('DELETE')
          <header class="card-header">
            <h2 class="card-title">Delete Rate</h2>
          </header>
          <div class="card-body">
            <p>Are you sure you want to delete this rate?</p>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-danger">Delete</button>
            <button class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

  </div>
</div>

<script>
function getRateDetails(id) {
    document.getElementById('updateForm').action = `/market_rates/${id}`;
    fetch(`/market_rates/${id}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('update_id').value = id;
            document.getElementById('update_id_hidden').value = id;
            $('#update_product_id').val(data.product_id).trigger('change');

            setTimeout(() => {
                $('#update_variation_id').val(data.variation_id).trigger('change');
            }, 500);

            document.getElementById('update_rate_per_unit').value = data.rate_per_unit;
            document.getElementById('update_effective_date').value = data.effective_date;
        });
}

function setDeleteId(id) {
  document.getElementById('deleteForm').action = `/market_rates/${id}`;
}

// AJAX: Load variations when product changes
function loadVariations(prefix) {
    let productId = document.getElementById(prefix + '_product_id').value;
    let variationSelect = document.getElementById(prefix + '_variation_id');

    fetch(`/products/${productId}/variations`)
      .then(res => res.json())
      .then(data => {
        variationSelect.innerHTML = '<option value="">Select Variation</option>';
        data.forEach(v => {
          variationSelect.innerHTML += `<option value="${v.id}">${v.name}</option>`;
        });
      });
}
</script>
@endsection
