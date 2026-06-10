<?php

use App\Http\Controllers\Front\CheckoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('wspay/callback', function () {
    return response()->json([
        'success' => true,
        'message' => 'WSPay callback endpoint is ready. Transaction reports must be sent with POST.',
    ]);
})->name('wspay.callback.health');

Route::post('wspay/callback', [CheckoutController::class, 'wspayCallback'])->name('wspay.callback');
