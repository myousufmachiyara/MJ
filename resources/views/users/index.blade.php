@extends('layouts.app')

@section('title', 'Users | All Users')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between">
        <h2 class="card-title">Users</h2>
        <div>
          <a href="#addModal" class="modal-with-form btn btn-primary">
            <i class="fas fa-plus"></i> Add User
          </a>
        </div>
      </header>

      <div class="card-body">
        @if (session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @elseif (session('error'))
          <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="cust-datatable-default">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role(s)</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($users as $index => $user)
                <tr>
                  <td>{{ $index + 1 }}</td>
                  <td>{{ $user->name }}</td>
                  <td>{{ $user->email ?? 'N/A'}}</td>
                  <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
               
                  <td class="actions">
                    <a href="#updateModal" class="text-primary modal-with-form" onclick="getUser({{ $user->id }})">
                      <i class="fa fa-edit"></i>
                    </a>
                    <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-link p-0 m-0 text-danger">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Add User Modal -->
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form action="{{ route('users.store') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <header class="card-header">
            <h2 class="card-title">Add User</h2>
          </header>
          <div class="card-body">
            <div class="mb-2">
              <label>Name</label>
              <input type="text" name="name" class="form-control" required />
            </div>
            <div class="mb-2">
              <label>Username</label>
              <input type="text" name="username" class="form-control" required />
            </div>
            <div class="mb-2">
              <label>Password</label>
              <input type="password" name="password" class="form-control" required />
            </div>
            <div class="mb-2">
              <label>Confirm Password</label>
              <input type="password" name="password_confirmation" class="form-control" required />
            </div>
            <div class="mb-2">
              <label>Role</label>
              <select name="role" class="form-control" required>
                <option value="">-- Select Role --</option>
                @foreach($roles as $role)
                  <option value="{{ $role->id }}">{{ $role->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-2">
              <label>Signature</label>
              <input type="file" name="signature" accept="image/*" class="form-control" />
            </div>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Create</button>
            <button class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

    <!-- Edit User Modal -->
    <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form id="updateForm" method="POST" enctype="multipart/form-data">
          @csrf
          @method('PUT')
          <header class="card-header">
            <h2 class="card-title">Edit User</h2>
          </header>
          <div class="card-body">
            <input type="hidden" name="id" id="edit_user_id">

            <div class="mb-2">
              <label>Name</label>
              <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            <div class="mb-2">
              <label>Username</label>
              <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>
            <div class="mb-2">
              <label>Role</label>
              <select name="role" id="edit_role" class="form-control" required>
                <option value="">-- Select Role --</option>
                @foreach($roles as $role)
                  <option value="{{ $role->id }}">{{ $role->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-2">
              <label>Signature</label>
              <input type="file" name="signature" accept="image/*" class="form-control" />
              <div id="current_signature_preview" style="margin-top: 10px;"></div>
            </div>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Update</button>
            <button class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>
  </div>
</div>

<script>
  function getUser(id) {
    fetch(`/users/${id}`)
      .then(response => response.json())
      .then(res => {
        if (res.status) {
          const user = res.data;
          document.getElementById('updateForm').action = `/users/${user.id}`;
          document.getElementById('edit_user_id').value = user.id;
          document.getElementById('edit_name').value = user.name;
          document.getElementById('edit_username').value = user.username;
          document.getElementById('edit_role').value = user.roles[0]?.id || '';

          let previewDiv = document.getElementById('current_signature_preview');
          previewDiv.innerHTML = '';

          if (user.signature_url) {
            const img = document.createElement('img');
            img.src = user.signature_url;
            img.alt = 'Current Signature';
            img.style.maxHeight = '100px';
            img.style.border = '1px solid #ccc';
            previewDiv.appendChild(img);
          }

        } else {
          alert('User not found.');
        }
      })
      .catch(err => {
        console.error('Failed to load user:', err);
        alert('Error loading user details.');
      });
  }
</script>
@endsection
