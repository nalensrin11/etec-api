@extends('portal.layout')
@section('content')
<h1>{{ $class->name }}</h1><div class="card"><p>Class join code: <code id="join-code">{{ $class->join_code }}</code> <button onclick="copyText(document.getElementById('join-code').textContent,this)">Copy Code</button></p><p>{{ $students->count() }} Students · {{ $products->count() }} Products</p><a class="button" href="{{ route('portal.class.docs',$class) }}">API Documentation</a></div>
@if(auth()->user()->isInstructor())<div class="card"><h2>Students</h2><table><tr><th>Name</th><th>Email</th><th>Products</th></tr>@forelse($students as $student)<tr><td>{{ $student->name }}</td><td>{{ $student->email }}</td><td>{{ $student->created_products_count }}</td></tr>@empty<tr><td colspan="3">No students yet.</td></tr>@endforelse</table></div>@endif
<div class="card"><h2>Products</h2><table><tr><th>Product</th><th>Student</th><th>Price</th></tr>@forelse($products as $product)<tr><td>{{ $product->name }}</td><td>{{ $product->creator->name }}</td><td>${{ $product->regular_price }}</td></tr>@empty<tr><td colspan="3">No products yet.</td></tr>@endforelse</table></div>
@endsection
