<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to ' . env('APP_NAME'),
        // "result" => OrderLogic::getOrderDetailsById(41)
    ], 200);
});

Route::middleware('auth.jwt')->group(function () {
    Route::get('api/userSession', [UserController::class, 'getUserSessionDetails']);
});

//OTP
Route::post('otp/send', [UserController::class, 'sendOtp']);
Route::post('otp/resend', [UserController::class, 'resendOtp']);
Route::post('otp/verify', [UserController::class, 'verifyOtp']);

//Orders
Route::post('order/list', [OrderController::class, 'getOrderList']);
Route::get('order/count', [OrderController::class, 'getOrderCount']);
Route::get('orders/details/{id}', [OrderController::class, 'getOrderDetails']);


#region Venkatesh
Route::get('cart/list', [CartController::class, 'getCartItems']);
Route::post('cart/add', [CartController::class, 'addToCart']);
Route::delete('cart/remove', [CartController::class, 'removeFromCart']);
Route::delete('cart/removeAll', [CartController::class, 'clearCart']);
#endregion Venkatesh
