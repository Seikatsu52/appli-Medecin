<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::get('/rdv', [AppointmentController::class, 'index']);
    Route::get('/rdv/unavailable-slots', [AppointmentController::class, 'unavailableSlots']);
    Route::post('/rdv', [AppointmentController::class, 'store']);
    Route::put('/rdv/{appointment}', [AppointmentController::class, 'update']);
    Route::patch('/rdv/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/rdv/{appointment}', [AppointmentController::class, 'destroy']);
});
