@extends('portal.layout')

@section('content')
    @php
        $apiUrl = url('/api');
        $isInstructor = auth()->user()->isInstructor() || auth()->user()->isAdmin();
        $publicPaths = ['/login', '/register', '/student/register'];
        $requiresAuth = fn (string $path) => ! str_starts_with($path, '/public/') && ! in_array($path, $publicPaths, true);
        $json = fn (array $data) => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $requestBody = function (string $method, string $path): ?array {
            if ($path === '/login') return ['email' => 'student@example.com', 'password' => 'password123'];
            if ($path === '/register') return ['name' => 'Instructor Dara', 'email' => 'dara@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'];
            if ($path === '/student/register') return ['name' => 'Sokha', 'email' => 'sokha@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'join_code' => 'WEB-A-X7K29'];
            if ($path === '/classes/join') return ['join_code' => 'WEB-A-X7K29'];
            if ($method === 'POST' && $path === '/classes') return ['name' => 'Web Development A', 'code' => 'WEB-A', 'status' => 'active'];
            if ($method === 'PUT' && str_starts_with($path, '/classes/')) return ['name' => 'Web Development A - Morning', 'status' => 'active'];
            if (str_contains($path, '/categories') && in_array($method, ['POST', 'PUT'])) return ['name' => 'Laptops', 'description' => 'Laptop products', 'status' => 'active'];
            if (str_contains($path, '/users') && in_array($method, ['POST', 'PUT'])) return ['name' => 'Sokha', 'email' => 'sokha@example.com', 'password' => 'password123', 'status' => 'active'];
            if (str_contains($path, '/products') && in_array($method, ['POST', 'PUT']) && ! str_contains($path, '/images')) return ['category_id' => 1, 'name' => 'iPhone 17 Pro', 'sku' => 'IP17-001', 'description' => 'Optional description', 'regular_price' => 1199, 'sale_price' => 1099, 'quantity' => 10, 'status' => 'active', 'images[]' => 'front.jpg'];
            if (str_contains($path, '/images') && $method === 'POST') return ['images[]' => 'front.jpg', 'images[] (second)' => 'back.jpg'];
            return null;
        };
        $groups = [[
            'title' => '1. Authentication API', 'access' => 'Login and registration endpoints.',
            'endpoints' => [['POST', '/login', 'Login and receive a bearer token'], ['POST', '/register', 'Register an instructor account'], ['POST', '/student/register', 'Register a student using a class join code'], ['POST', '/logout', 'Log out the authenticated user'], ['GET', '/me', 'Get the authenticated user']],
        ]];
        if ($isInstructor) $groups[] = [
            'title' => '2. Instructor API — Your Class Only', 'access' => 'Bearer token required. The server verifies instructor ownership for every class request.',
            'endpoints' => [['GET', '/classes', 'List only classes you own'], ['POST', '/classes', 'Create a class and join code'], ['GET', '/classes/'.$class->id, 'View this class workspace'], ['PUT', '/classes/'.$class->id, 'Update this class'], ['GET', '/classes/'.$class->id.'/documentation', 'Get generated class documentation'], ['GET', '/categories', 'List class categories'], ['POST', '/categories', 'Create a category'], ['PUT', '/categories/{id}', 'Update a category'], ['GET', '/users', 'List class students'], ['POST', '/users', 'Create a student'], ['PUT', '/users/{id}', 'Update a student']]
        ];
        $groups[] = [
            'title' => '3. Student / Public API — '.$class->name, 'access' => 'Public catalog URLs are scoped to this class. Student workspace endpoints require a bearer token.',
            'endpoints' => [['GET', '/public/categories?class_id='.$class->id, 'Public active categories'], ['GET', '/public/products?class_id='.$class->id, 'Public active products'], ['GET', '/public/products/{product}?class_id='.$class->id, 'Public product detail'], ['POST', '/classes/join', 'Join a class using its code'], ['GET', '/products', 'List products in the authenticated class'], ['POST', '/products', 'Create a product with up to 10 images using images[]'], ['GET', '/products/{id}', 'Get product detail'], ['PUT', '/products/{id}', 'Update the student’s own product'], ['DELETE', '/products/{id}', 'Delete the student’s own product']]
        ];
    @endphp

    <h1>API Documentation</h1>
    <div class="card">
        <h2>Base URL</h2>
        <code id="base-url">{{ $apiUrl }}</code>
        <button onclick="copyText(document.getElementById('base-url').textContent, this)">Copy</button>
        <a class="button" href="{{ route('portal.class.docs.download', $class) }}">Download PDF</a>
        <p>Protected requests require <code>Authorization: Bearer YOUR_TOKEN</code> and <code>Accept: application/json</code>.</p>
    </div>
    <input id="search" placeholder="Search endpoints..." oninput="document.querySelectorAll('.endpoint').forEach(e => e.hidden = !e.textContent.toLowerCase().includes(this.value.toLowerCase()))">

    @foreach ($groups as $group)
        <div class="card">
            <h2>{{ $group['title'] }}</h2><p>{{ $group['access'] }}</p>
            @foreach ($group['endpoints'] as [$method, $path, $description])
                @php
                    $authenticated = $requiresAuth($path);
                    $body = $requestBody($method, $path);
                    $successStatus = $method === 'POST' ? 201 : 200;
                    $success = $method === 'DELETE' ? ['message' => 'Deleted successfully.', 'status' => 200, 'data' => null] : ['message' => $description.' successfully.', 'status' => $successStatus, 'data' => ['id' => 1]];
                    $error = $authenticated ? ['message' => 'Unauthenticated.', 'status' => 401] : ['message' => 'The given data was invalid.', 'status' => 422, 'errors' => ['email' => ['The email field is required.']]];
                    $headers = ['Accept: application/json'];
                    if ($authenticated) {
                        $headers[] = 'Authorization: Bearer YOUR_TOKEN';
                    }
                    if ($body) {
                        $headers[] = 'Content-Type: '.(str_contains($path, '/products') && $method === 'POST' ? 'multipart/form-data' : 'application/json');
                    }
                @endphp
                <details class="endpoint" style="padding:12px 0;border-bottom:1px solid #e5e7eb">
                    <summary style="cursor:pointer">
                        <span class="method">{{ $method }}</span> <code>{{ $path }}</code>
                        <button type="button" onclick='event.preventDefault(); event.stopPropagation(); copyText(@json($apiUrl.$path), this)'>Copy URL</button>
                        <span>— {{ $description }}</span>
                    </summary>
                    <div style="padding:16px 0 4px">
                        <h3>Request headers</h3>
                        <pre>{{ implode("\n", $headers) }}</pre>
                        <h3>Request body</h3>
                        @if ($body)
                            <pre>{{ $json($body) }}</pre>
                        @else
                            <p>No request body is required.</p>
                        @endif
                        <h3>Success response — {{ $successStatus }}</h3><pre>{{ $json($success) }}</pre>
                        <h3>Error response</h3><pre>{{ $json($error) }}</pre>
                    </div>
                </details>
            @endforeach
        </div>
    @endforeach
@endsection
