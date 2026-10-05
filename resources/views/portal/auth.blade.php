@extends('portal.layout')
@section('content')
<div class="card" style="max-width:460px;margin:50px auto">
    <h1>{{ $mode === 'login' ? 'Login' : ($mode === 'student' ? 'Student registration' : 'Instructor registration') }}</h1>
    <form method="post" action="{{ $mode === 'login' ? route('portal.login') : ($mode === 'student' ? route('portal.student-register') : route('portal.register')) }}">
        @csrf
        @if ($mode !== 'login')
            <label>Name</label><input name="name" value="{{ old('name') }}" required>
        @endif
        <label>Email</label><input name="email" type="email" value="{{ old('email') }}" required>
        <label>Password</label><input name="password" type="password" required>
        @if ($mode !== 'login')
            <label>Confirm Password</label><input name="password_confirmation" type="password" required>
            @if ($mode === 'student')
                <label>Class Join Code</label><input name="join_code" value="{{ old('join_code') }}" required>
            @endif
        @endif
        <button>{{ $mode === 'login' ? 'Login' : 'Create account' }}</button>
    </form>
    <p>
        @if ($mode === 'login')
            Instructor? <a href="{{ route('portal.register') }}">Register</a> · Student? <a href="{{ route('portal.student-register') }}">Register with join code</a>
        @else
            <a href="{{ route('portal.login') }}">Already have an account? Login</a>
        @endif
    </p>
</div>
@endsection
