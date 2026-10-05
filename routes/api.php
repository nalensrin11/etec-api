<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\ClassDocumentationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return response()->json(['message' => 'Authenticated user retrieved successfully.', 'status' => 200, 'data' => $request->user()]);
})->middleware('auth:api');
Route::get('/me', function (Request $request) {
    return response()->json(['message' => 'Authenticated user retrieved successfully.', 'status' => 200, 'data' => $request->user()]);
})->middleware('auth:api');

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::post('student/register', [AuthController::class, 'registerStudent']);
Route::get('public/categories', [CategoryController::class, 'publicIndex']);
Route::get('public/products', [ProductController::class, 'publicIndex']);
Route::get('public/products/promotions', [ProductController::class, 'publicPromotions']);
Route::get('public/products/{product}', [ProductController::class, 'publicShow']);

Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('classes/join', [AuthController::class, 'joinClass']);
    Route::get('classes/{class}/documentation', ClassDocumentationController::class);
    Route::apiResource('classes', ClassController::class)->only(['index', 'show', 'store']);
    Route::put('classes/{class}', [ClassController::class, 'update'])->name('classes.update');
    Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'destroy']);
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'destroy']);
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::apiResource('products', ProductController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('products/{product}/images', [ProductImageController::class, 'store']);
    Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy']);
    Route::put('products/{product}/images/{image}/primary', [ProductImageController::class, 'primary']);
});
