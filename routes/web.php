<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditDashboardController;
use App\Http\Controllers\AuditParametersController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuditDashboardController::class, 'index'])->name('dashboard');
    Route::get('/schools', [AuditDashboardController::class, 'schools'])->name('schools');
    Route::put('/schools/{school}', [AuditDashboardController::class, 'updateSchool'])->name('schools.update');

    Route::middleware('admin')->group(function () {
        Route::get('/parameters', [AuditParametersController::class, 'index'])->name('parameters');
        Route::put('/parameters', [AuditParametersController::class, 'update'])->name('parameters.update');
        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    });
});
