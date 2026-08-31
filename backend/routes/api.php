<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\BackofficeController;
use App\Http\Controllers\Api\VisitorSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/slots', [AppointmentController::class, 'slots']);

Route::post('/session', [VisitorSessionController::class, 'store'])->middleware('throttle:10,1');
Route::post('/session/confirm', [VisitorSessionController::class, 'confirm'])->middleware('throttle:20,1');
Route::post('/session/discard', [VisitorSessionController::class, 'discard']);

Route::middleware('visitor')->group(function () {
    Route::get('/session/me', [VisitorSessionController::class, 'me']);
    Route::post('/session/logout', [VisitorSessionController::class, 'logout']);
    Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:8,1');

Route::middleware('admin')->prefix('admin')->group(function () {
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/slots', [BackofficeController::class, 'slots']);
    Route::delete('/appointments/{appointment}', [BackofficeController::class, 'cancelAppointment']);
});
