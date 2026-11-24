@extends('layouts.app')

@section('title', 'Offline / Live Sync')

@section('content')
<div class="row">
  <div class="col">

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('info'))
      <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <section class="card">
      <header class="card-header">
        <h2 class="card-title">Offline / Live Sync</h2>
      </header>

      <div class="card-body">
        @if($isOfflineMode)
          <p>
            This is an <strong>offline</strong> installation. Use the button below whenever
            you're ready to send everything new since the last sync up to the live system.
          </p>
          <p class="text-muted">
            This creates a file — save it somewhere you can carry to the live system
            (a USB drive, or email it to yourself), then upload it on the
            live site's own Sync page.
          </p>

          <form action="{{ route('sync.export') }}" method="POST" class="mb-4">
            @csrf
            <button type="submit" class="btn btn-primary">
              <i class="fa fa-upload"></i> Export for Sync
            </button>
          </form>
        @else
          <p>
            Upload the file your offline install's <strong>Export for Sync</strong>
            button produced. Re-uploading the same file by mistake is safe —
            it's recognized and skipped rather than imported twice.
          </p>

          <form action="{{ route('sync.import') }}" method="POST" enctype="multipart/form-data" class="mb-4">
            @csrf
            <div class="row align-items-end">
              <div class="col-md-6">
                <label>Sync file</label>
                <input type="file" name="sync_file" class="form-control" accept=".json" required>
              </div>
              <div class="col-md-3">
                <button type="submit" class="btn btn-primary mt-2 mt-md-0">
                  <i class="fa fa-download"></i> Import
                </button>
              </div>
            </div>
          </form>
        @endif

        <h4>Sync History</h4>
        @if($history->isEmpty())
          <p class="text-muted">Nothing synced yet.</p>
        @else
          <table class="table table-striped">
            <thead>
              <tr>
                <th>When</th>
                <th>Direction</th>
                <th>File</th>
                <th>Records</th>
              </tr>
            </thead>
            <tbody>
              @foreach($history as $batch)
                <tr>
                  <td>{{ $batch->created_at->format('d-M-Y h:i A') }}</td>
                  <td>
                    @if($batch->direction === 'exported')
                      <span class="badge bg-primary">Exported</span>
                    @else
                      <span class="badge bg-success">Imported</span>
                    @endif
                  </td>
                  <td>{{ $batch->file_name ?? '-' }}</td>
                  <td>
                    @php $total = collect($batch->table_counts ?? [])->sum(); @endphp
                    {{ $total }} record{{ $total === 1 ? '' : 's' }}
                    @if($batch->table_counts)
                      <br>
                      <small class="text-muted">
                        {{ collect($batch->table_counts)->map(fn($c, $t) => "$t: $c")->implode(', ') }}
                      </small>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </div>
    </section>

  </div>
</div>
@endsection
