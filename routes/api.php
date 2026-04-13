<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LineConnectionController;
use App\Http\Controllers\Api\ShortcutController;
use App\Http\Controllers\Api\TransactionController;
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

// Health check (no throttle — used by load balancers / uptime monitors)
Route::get('/health', function () {
    return response()->json([
        'status'    => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// LINE Webhook — uses its own named limiter (not IP-based) because
// LINE sends all traffic from shared IPs; throttle:30,1 would block real events
Route::post('/line/webhook', [WebhookController::class, 'handle'])
    ->middleware(['verify.line.signature', 'throttle:line-webhook']);

// Public Auth Routes — stricter throttle to prevent brute-force (10/min per IP)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/register', [RegisterController::class, 'register']);
    Route::post('/auth/login',    [LoginController::class,    'login']);
    Route::get('/auth/line-auto-login', [LineAutoLoginController::class, 'login']);
});

// Protected Routes — generous throttle for authenticated users (via global api limiter)
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout']);
    Route::get('/auth/user',    [LoginController::class, 'user']);

    // LINE Connection
    Route::get('/line/connection',     [LineConnectionController::class, 'status']);
    Route::post('/line/generate-code', [LineConnectionController::class, 'generateCode']);
    Route::delete('/line/disconnect',  [LineConnectionController::class, 'disconnect']);

    // Transactions
    Route::apiResource('/transactions', TransactionController::class);
    Route::apiResource('/categories',   CategoryController::class);
    Route::apiResource('/shortcuts',    ShortcutController::class);

    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/chart',   [DashboardController::class, 'chart']);
    Route::get('/dashboard/recent',  [DashboardController::class, 'recent']);
});
