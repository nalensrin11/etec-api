<?php

use App\Http\Controllers\PortalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return redirect()->route($request->user('web') ? 'portal.dashboard' : 'portal.login');
});

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [PortalController::class, 'loginForm'])->name('portal.login');
    Route::post('/login', [PortalController::class, 'login']);
    Route::get('/register', [PortalController::class, 'instructorRegisterForm'])->name('portal.register');
    Route::post('/register', [PortalController::class, 'registerInstructor']);
    Route::get('/student/register', [PortalController::class, 'studentRegisterForm'])->name('portal.student-register');
    Route::post('/student/register', [PortalController::class, 'registerStudent']);
});
Route::middleware('auth:web')->group(function () {
    Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('portal.dashboard');
    Route::get('/admin', [PortalController::class, 'admin'])->name('portal.admin');
    Route::post('/admin/clear-data', [PortalController::class, 'clearProjectData'])->name('portal.admin.clear-data');
    Route::post('/classes', [PortalController::class, 'storeClass'])->name('portal.class.store');
    Route::post('/classes/join', [PortalController::class, 'join'])->name('portal.class.join');
    Route::get('/classes/{class}', [PortalController::class, 'showClass'])->name('portal.class.show');
    Route::get('/classes/{class}/api', [PortalController::class, 'docs'])->name('portal.class.docs');
    Route::get('/classes/{class}/api/download', [PortalController::class, 'downloadDocs'])->name('portal.class.docs.download');
    Route::post('/logout', [PortalController::class, 'logout'])->name('portal.logout');
});
