<?php

use App\Http\Controllers\Api\LineConnectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LineAutoLoginController;
use App\Http\Controllers\Line\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// LINE Webhook (no auth, uses LINE signature verification)
Route::post('/line/webhook', [WebhookController::class, 'handle'])
    ->middleware('verify.line.signature');

// Public Auth Routes
Route::post('/auth/register', [RegisterController::class, 'register']);
Route::post('/auth/login', [LoginController::class, 'login']);
Route::get('/auth/line-auto-login', [LineAutoLoginController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout']);
    Route::get('/auth/user', [LoginController::class, 'user']);

    // LINE Connection
    Route::get('/line/connection', [LineConnectionController::class, 'status']);
    Route::post('/line/generate-code', [LineConnectionController::class, 'generateCode']);
    Route::delete('/line/disconnect', [LineConnectionController::class, 'disconnect']);

    // TODO: Add in Phase 3
    // Route::apiResource('/transactions', TransactionController::class);
    // Route::apiResource('/categories', CategoryController::class);
    // Route::apiResource('/shortcuts', ShortcutController::class);

    // TODO: Add in Phase 5
    // Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    // Route::get('/dashboard/chart', [DashboardController::class, 'chart']);
    // Route::get('/dashboard/recent', [DashboardController::class, 'recent']);
});
