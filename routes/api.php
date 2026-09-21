<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SessionController;
use App\Models\Subject;
use Illuminate\Support\Facades\Route;

// Admin-managed subject list — no admin CMS until Phase 4, so this just reads the seeded set.
Route::get('/subjects', fn () => Subject::whereNull('user_id')->get(['id', 'name', 'color_key']));

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/google', [AuthController::class, 'googleSignIn']);
Route::post('/auth/apple', [AuthController::class, 'appleSignIn']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/me', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/onboarding/subjects', [ProfileController::class, 'updateSubjects']);
    Route::put('/onboarding/goal', [ProfileController::class, 'updateGoal']);
    Route::put('/settings/reminders', [ProfileController::class, 'updateReminderSettings']);

    Route::get('/home', [HomeController::class, 'index']);

    Route::get('/sessions', [SessionController::class, 'index']);
    Route::post('/sessions', [SessionController::class, 'store']);
    Route::post('/sessions/copy-last-week', [SessionController::class, 'copyLastWeek']);
    Route::get('/sessions/{session}', [SessionController::class, 'show']);
    Route::put('/sessions/{session}', [SessionController::class, 'update']);
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy']);
    Route::post('/sessions/{session}/start', [SessionController::class, 'start']);
    Route::post('/sessions/{session}/complete', [SessionController::class, 'complete']);
    Route::post('/sessions/{session}/reflect', [SessionController::class, 'reflect']);
});
