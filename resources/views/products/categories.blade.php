@extends('layouts.app')

@section('title', 'Products | Categories')

@section('content')
  <div class="row">
    <div class="col">
      <section class="card">
        <header class="card-header">
            <div style="display: flex;justify-content: space-between;">
                <h2 class="card-title">Product Categories</h2>
                <div>
                    <button type="button" class="modal-with-form btn btn-primary mt-1" href="#addModal"> <i class="fas fa-plus"></i> Create Category</button>
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
                            <th>Name</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>Diamond</td>
                            <td>diamond</td>
                            <td>Raw</td>
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
                        <tr>
                            <td>2</td>
                            <td>Gold</td>
                            <td>gold</td>
                            <td>Raw</td>
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
                        <tr>
                            <td>3</td>
                            <td>Stone</td>
                            <td>stone</td>
                            <td>Raw</td>
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
                        <tr>
                            <td>4</td>
                            <td>Chain</td>
                            <td>chain</td>
                            <td>Raw</td>
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
                        <tr>
                            <td>5</td>
                            <td>Ring</td>
                            <td>ring</td>
                            <td>Finish Goods</td>
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
                        <tr>
                            <td>6</td>
                            <td>Earing</td>
                            <td>earing</td>
                            <td>Finish Goods</td>
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
                        <tr>
                            <td>7</td>
                            <td>Pendent</td>
                            <td>pendent</td>
                            <td>Finish Goods</td>
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
                        <tr>
                            <td>8</td>
                            <td>Bangel</td>
                            <td>bangel</td>
                            <td>Finish Goods</td>
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
                        <tr>
                            <td>9</td>
                            <td>Bangel</td>
                            <td>bangel</td>
                            <td>Finish Goods</td>
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
                        <tr>
                            <td>10</td>
                            <td>Necklace</td>
                            <td>necklace</td>
                            <td>Finish Goods</td>
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
                    <h2 class="card-title">New Category</h2>
                </header>
                <div class="card-body">
                    <div class="row form-group">
                        <div class="col-lg-6 mb-2">
                            <label>Name<span style="color: red;"><strong>*</strong></span></label>
                            <input type="text" class="form-control" placeholder="Category Name" name="name" required>
                        </div>
                        
                        <div class="col-lg-6 mb-2">
                            <label>Code<span style="color: red;"><strong>*</strong></span></label>
                            <input type="text" class="form-control" placeholder="Category Code" name="receivables" required>
                        </div>

                        <div class="col-12 col-lg-6 mb-2">
                            <label>Type<span style="color: red;"><strong>*</strong></span></label>
                            <select data-plugin-selecttwo class="form-control select2-js" required>
                                <option value="" disabled selected>Select Type</option>
                                <option value="">Raw</option>
                                <option value="">Finish Good</option>
                            </select>
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
    </div>
  </div>
@endsection