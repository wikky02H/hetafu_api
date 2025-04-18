<?php

use App\Http\BusinessLogics\DBLogic;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use App\Http\Helpers\JWTToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to ' . env('APP_NAME'),
        // "currentDateTime" => DBLogic::currentDateTime()
        // "result" => JWTToken::generateToken([
        //     "id" => 1,
        //     "sessionId" => 3
        // ])
    ], 200);
});

Route::middleware('auth.jwt')->group(function () {
    //Session
    Route::get('userSession', [UserController::class, 'getUserSessionDetails']);

    //User
    Route::post('user/logout', [UserController::class, 'userLogout']);

    // Order
    Route::get('order/address', [OrderController::class, 'userAddresses']);
    Route::post('order/address/save', [OrderController::class, 'saveAddresses']);
    Route::post('order/place', [OrderController::class, 'placeOrder']);
    Route::post('order/payment/initialize', [OrderController::class, 'initializePayment']);
    Route::post('order/payment/response', [OrderController::class, 'paymentResponse']);

    Route::post('admin/order/list', [OrderController::class, 'getOrderList']);
    Route::get('admin/order/count', [OrderController::class, 'getOrderCount']);
    Route::get('admin/orders/details/{orderNumber}', [OrderController::class, 'getOrderDetails']);

    //customers
    Route::post('admin/customer/list', [UserController::class, 'customerList']);
    Route::get('admin/customer/count', [UserController::class, 'customerCount']);

    #region Venkatesh
    Route::get('cart/list', [CartController::class, 'getCartItems']);
    Route::post('cart/add', [CartController::class, 'addToCart']);
    Route::delete('cart/remove', [CartController::class, 'removeFromCart']);
    Route::delete('cart/removeAll', [CartController::class, 'clearCart']);
    #endregion Venkatesh
});


//OTP
Route::post('otp/send', [UserController::class, 'sendOtp']);
Route::post('otp/resend', [UserController::class, 'resendOtp']);
Route::post('otp/verify', [UserController::class, 'verifyOtp']);

//products
Route::post('products/list', [ProductController::class, 'productList']);

#region Venkatesh
Route::get('product/details/{id}', [ProductController::class, 'getProductDetailsById']);
#endregion Venkatesh
