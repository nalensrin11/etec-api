<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentClass;
use Illuminate\Http\Request;

class ClassDocumentationController extends Controller
{
    public function __invoke(Request $request, StudentClass $class)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->isInstructor() && ($class->instructor_id === $user->id || $class->created_by === $user->id)) || ($user->isStudent() && $user->class_id === $class->id), 403);

        return $this->success([
            'base_url' => url('/api'),
            'authentication_api' => [
                ['method' => 'POST', 'path' => '/login', 'description' => 'Login and receive a bearer token', 'auth_required' => false],
                ['method' => 'POST', 'path' => '/logout', 'description' => 'Logout current user', 'auth_required' => true],
                ['method' => 'GET', 'path' => '/me', 'description' => 'Get the authenticated user', 'auth_required' => true],
                ['method' => 'POST', 'path' => '/student/register', 'description' => 'Register a student with a class join code', 'auth_required' => false],
            ],
            'instructor_api' => $user->isAdmin() || $user->isInstructor() ? [
                ['method' => 'GET', 'path' => '/classes', 'description' => 'List classes owned by the instructor', 'auth_required' => true],
                ['method' => 'POST', 'path' => '/classes', 'description' => 'Create a class', 'auth_required' => true],
                ['method' => 'GET', 'path' => '/classes/'.$class->id, 'description' => 'View this class workspace', 'auth_required' => true],
                ['method' => 'GET', 'path' => '/users', 'description' => 'List students in this instructor class', 'auth_required' => true],
                ['method' => 'PUT', 'path' => '/categories/{id}', 'description' => 'Update a category in this class', 'auth_required' => true],
                ['method' => 'PUT', 'path' => '/users/{id}', 'description' => 'Update a student in this class', 'auth_required' => true],
            ] : [],
            'student_public_api' => [
                ['method' => 'GET', 'path' => '/public/categories?class_id='.$class->id, 'description' => 'Get active public categories for this class', 'auth_required' => false],
                ['method' => 'GET', 'path' => '/public/products?class_id='.$class->id, 'description' => 'Get active public products for this class', 'auth_required' => false],
                ['method' => 'POST', 'path' => '/classes/join', 'description' => 'Join a class using its join code', 'auth_required' => true],
                ['method' => 'GET', 'path' => '/products', 'description' => 'List products in your class', 'auth_required' => true],
                ['method' => 'POST', 'path' => '/products', 'description' => 'Create a product with up to 10 images', 'auth_required' => true, 'content_type' => 'multipart/form-data', 'fields' => ['category_id', 'name', 'sku', 'description?', 'regular_price', 'sale_price?', 'quantity', 'status', 'images[]?']],
                ['method' => 'GET', 'path' => '/products/{id}', 'description' => 'Get a product', 'auth_required' => true],
                ['method' => 'PUT', 'path' => '/products/{id}', 'description' => 'Update your product', 'auth_required' => true],
                ['method' => 'DELETE', 'path' => '/products/{id}', 'description' => 'Delete your product', 'auth_required' => true],
            ],
        ], 'Class API documentation retrieved successfully.');
    }
}
