<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10px; line-height: 1.4; }
        h1 { font-size: 22px; margin: 0 0 4px; } h2 { font-size: 15px; margin: 22px 0 6px; color: #1d4ed8; }
        h3 { font-size: 11px; margin: 12px 0 3px; } p { margin: 3px 0 8px; }
        .meta { color: #475569; margin-bottom: 16px; } .endpoint { border-top: 1px solid #dbe3ef; padding: 9px 0; page-break-inside: avoid; }
        .method { display: inline-block; width: 42px; color: #1d4ed8; font-weight: bold; } code { font-family: DejaVu Sans Mono, monospace; font-size: 9px; }
        pre { background: #f1f5f9; border: 1px solid #dbe3ef; padding: 6px; white-space: pre-wrap; margin: 4px 0; font-size: 8px; }
        .footer { position: fixed; bottom: -18px; font-size: 8px; color: #64748b; }
    </style>
</head>
<body>
    @php
        $auth = [['POST', '/login', 'Login and receive a bearer token'], ['POST', '/register', 'Register an instructor'], ['POST', '/student/register', 'Register a student with join code'], ['POST', '/logout', 'Log out'], ['GET', '/me', 'Get authenticated user']];
        $instructor = [['GET', '/classes', 'List owned classes'], ['POST', '/classes', 'Create a class'], ['GET', '/classes/'.$class->id, 'View class workspace'], ['PUT', '/classes/'.$class->id, 'Update class'], ['GET', '/categories', 'List class categories'], ['POST', '/categories', 'Create category'], ['PUT', '/categories/{id}', 'Update category'], ['GET', '/users', 'List students'], ['POST', '/users', 'Create student'], ['PUT', '/users/{id}', 'Update student']];
        $student = [['GET', '/public/categories?class_id='.$class->id, 'Public categories for this class'], ['GET', '/public/products?class_id='.$class->id, 'Public products for this class'], ['POST', '/classes/join', 'Join using class code'], ['GET', '/products', 'List class products'], ['POST', '/products', 'Create product with images[]'], ['GET', '/products/{id}', 'Get product'], ['PUT', '/products/{id}', 'Update own product'], ['DELETE', '/products/{id}', 'Delete own product']];
        $groups = [['Authentication API', 'Public login and token endpoints.', $auth], ['Student / Public API - '.$class->name, 'Public catalog URLs are scoped to class '.$class->code.'. Product workspace calls need a student bearer token.', $student]];
        if ($isInstructor) array_splice($groups, 1, 0, [['Instructor API - Your Class Only', 'Bearer token required. Server verifies class ownership.', $instructor]]);
    @endphp
    <h1>API Documentation</h1>
    <p class="meta">Class: {{ $class->name }} ({{ $class->code }})<br>Base URL: {{ $apiUrl }}</p>
    <p>Protected requests: <code>Authorization: Bearer YOUR_TOKEN</code> and <code>Accept: application/json</code>.</p>
    @foreach ($groups as [$title, $access, $endpoints])
        <h2>{{ $title }}</h2><p>{{ $access }}</p>
        @foreach ($endpoints as [$method, $path, $description])
            @php
                $protected = ! str_starts_with($path, '/public/') && ! in_array($path, ['/login', '/register', '/student/register'], true);
                $headers = 'Accept: application/json'.($protected ? "\nAuthorization: Bearer YOUR_TOKEN" : '');
                $body = match (true) {
                    $path === '/login' => '{ "email": "student@example.com", "password": "password123" }',
                    $path === '/register' => '{ "name": "Instructor Dara", "email": "dara@example.com", "password": "password123", "password_confirmation": "password123" }',
                    $path === '/student/register' => '{ "name": "Sokha", "email": "sokha@example.com", "password": "password123", "password_confirmation": "password123", "join_code": "WEB-A-X7K29" }',
                    $path === '/classes/join' => '{ "join_code": "WEB-A-X7K29" }',
                    str_contains($path, '/products') && in_array($method, ['POST', 'PUT']) => '{ "category_id": 1, "name": "iPhone 17 Pro", "sku": "IP17-001", "regular_price": 1199, "quantity": 10, "status": "active" }',
                    in_array($method, ['POST', 'PUT']) => '{ "name": "Example name", "status": "active" }',
                    default => 'No request body is required.',
                };
            @endphp
            <div class="endpoint">
                <span class="method">{{ $method }}</span><code>{{ $path }}</code><p>{{ $description }}</p>
                <h3>Request headers</h3><pre>{{ $headers }}</pre>
                <h3>Request body</h3><pre>{{ $body }}</pre>
                <h3>Success response</h3><pre>{ "message": "Request completed successfully.", "status": {{ $method === 'POST' ? 201 : 200 }}, "data": { "id": 1 } }</pre>
                <h3>Error response</h3><pre>{ "message": "{{ $protected ? 'Unauthenticated.' : 'The given data was invalid.' }}", "status": {{ $protected ? 401 : 422 }} }</pre>
            </div>
        @endforeach
    @endforeach
    <div class="footer">Generated {{ now()->format('Y-m-d H:i') }} - Classroom Portal</div>
</body>
</html>
