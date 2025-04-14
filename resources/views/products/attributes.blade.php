@extends('layouts.app')

@section('title', 'Products | Attributes')

@section('content')
  <div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header">
                <div style="display: flex;justify-content: space-between;">
                    <h2 class="card-title">Product Attributes</h2>
                    <div>
                        <button type="button" class="modal-with-form btn btn-primary" href="#addModal"> <i class="fas fa-plus"></i> Create Attribute</button>
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
                    <th>S.No</th>
                    <th>Name</th>
                    <th>Values</th>
                    <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    
                </tbody>
                </table>
            </div>
            </div>
        </section>

        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="post" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header">
                        <h2 class="card-title">New Attribute</h2>
                    </header>
                    <div class="card-body">
                        <div class="mb-3">
                            <label>Name<span style="color: red;"><strong>*</strong></span></label>
                            <input type="text" class="form-control" placeholder="Name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label>Values<span style="color: red;"><strong>*</strong></span></label>
                            <textarea type="text" class="form-control" rows="3" placeholder="Enter comma separated values" name="values" required></textarea>
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