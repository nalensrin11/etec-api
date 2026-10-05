@extends('portal.layout')

@section('content')
    <h1>Admin Overview</h1>
    <div class="grid">
        @foreach ($counts as $label => $count)
            <div class="card"><h2>{{ ucfirst($label) }}</h2><p style="font-size:32px;margin:0">{{ $count }}</p></div>
        @endforeach
    </div>

    <div class="card" style="border-color:#fecaca">
        <h2>Clear project data</h2>
        <p>This permanently removes students, classes, categories, products, uploaded images, cache, jobs, and password-reset records.</p>
        <p>Roles and users whose role is <code>admin</code> or <code>instructor</code> are preserved.</p>
        <form method="post" action="{{ route('portal.admin.clear-data') }}" onsubmit="return confirm('This permanently clears project data. Continue?')">
            @csrf
            <label>Type <code>CLEAR</code> to confirm</label>
            <input name="confirmation" autocomplete="off" required>
            <button class="danger">Clear All Project Data</button>
        </form>
    </div>
@endsection
