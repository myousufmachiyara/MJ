@extends('layouts.app')

@section('title', 'Products | All Products')

@section('content')
  <div class="row">
    <div class="col">
      <section class="card">
        <header class="card-header">
            <div style="display: flex;justify-content: space-between;">
                <h2 class="card-title">All Products</h2>
                <div>
                    <button type="button" class="modal-with-form btn btn-primary" href="#addModal"> <i class="fas fa-plus"></i> Create Products</button>
                </div>
            </div>
            @if ($errors->has('error'))
                <strong class="text-danger">{{ $errors->first('error') }}</strong>
            @endif
        </header>
       
        <div class="card-body">
          <div class="modal-wrapper table-scroll">
                <table class="table table-bordered table-striped mb-0" id="cust-datatable-default">
                    <thead>
                        <tr>
                            <th>S.NO</th>
                            <th>Picture</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>SKU</th>
                            <th>Raw Info.</th>
                            <th>Total Weight</th>
                            <th>Price</th>
                            <th>Att.</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><img src="https://puregold.pk/wp-content/uploads/LR00123-1.jpg" alt="Project Image" width="60" height="60" style="object-fit: cover; border-radius: 6px;"></td>
                            <td>Article Name</td>
                            <td>Ring</td>
                            <td>R-00007</td>
                            <td><a class="modal-with-form text-primary" href="#viewRaw"><i class="fa fa-expand"></i></a></td>
                            <td>Total Raw Weight</td>
                            <td>4,30,000</td>
                            <td><a class="text-primary" href=""><i class="fa fa-link"></i></a></td>
                            <td>
                                
                                <a class="text-primary" href="">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <!-- Delete Link (with Confirmation) -->
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this purchase order?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-danger bg-transparent" style="border:none">
                                    <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                                
                            </td>                        
                        </tr>
                    </tbody>
                </table>
          </div>
        </div>
      </section>

      <div id="addModal" class="modal-block modal-block-primary mfp-hide">
        <section class="card">
            <form method="post" id="addForm" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                @csrf
                <header class="card-header">
                    <h2 class="card-title">New Product</h2>
                </header>
                <div class="card-body">
                    <div class="row form-group">
                        <div class="col-12 col-lg-6 mb-2">
                            <label>SKU <span style="color: red;"><strong>(System Generated)</strong></span></label>
                            <input type="text" class="form-control" placeholder="SKU" disabled required>
                        </div>  

                        <div class="col-12 col-lg-6 mb-2">
                            <label>Product Name<span style="color: red;"><strong>*</strong></span></label>
                            <input type="text" class="form-control" placeholder="Account Name" name="name" required>
                        </div>
                        <div class="col-12 col-lg-6 mb-2">
                            <label>Type<span style="color: red;"><strong>*</strong></span></label>
                            <select data-plugin-selecttwo class="form-control select2-js" required>
                                <option value="" disabled selected>Select Type</option>
                                <option value="">Raw</option>
                                <option value="">Finish Good</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-6 mb-2">
                            <label>Category<span style="color: red;"><strong>*</strong></span></label>
                            <select data-plugin-selecttwo class="form-control select2-js"  required>
                                <option value="" disabled selected>Select Category</option>
                            </select>
                        </div>
                      
                        <div class="col-12 col-lg-6 mb-2">
                            <label>Cat Design</label>
                            <input type="file" class="form-control" name="cat_design" accept="application/pdf, image/png, image/jpeg">
                        </div>
                        <div class="col-12 col-lg-6 mb-2">
                            <label>Images</label>
                            <input type="file" class="form-control" name="att[]" multiple accept="application/pdf, image/png, image/jpeg">
                        </div>
                    </div>
                </div>
                <footer class="card-footer">
                    <div class="row">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Create</button>
                            <button class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </div>
                </footer>
            </form>
        </section>
      </div>

      <div id="viewRaw" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Raw Info.</h2>
                </header>
                <div class="card-body">
                    <div class="row form-group">
                        
                    </div>
                </div>
                <footer class="card-footer">
                    <div class="row">
                        <div class="col-md-12 text-end">
                            <button class="btn btn-default modal-dismiss">Close</button>
                        </div>
                    </div>
                </footer>
            </section>
      </div>
    </div>
  </div>
@endsection