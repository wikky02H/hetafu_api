<?php

namespace App\Http\Controllers;

use App\Http\BusinessLogics\CartLogic;
use App\Http\BusinessLogics\CommonLogic;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;

class CartController extends Controller
{
    public function getCartItems(Request $request)
    {
        // $validator = CommonValidation::sendOtp($request->all());
        // if ($validator->fails()) {
        //     return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        // }
        try {
            $cart = CartLogic::getOrCreateCart($request->jwtUserId, $request->jwtSessionId);
            $cartItems = CartLogic::getCartItems($cart);
            return CommonLogic::jsonResponse("Success", 200, $cartItems);
        } catch (Exception $e) {
            Log::info('Error getCartItems', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function addToCart(Request $request)
    {
        // $validator = CommonValidation::sendOtp($request->all());
        // if ($validator->fails()) {
        //     return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        // }
        try {
            $cart = CartLogic::getOrCreateCart($request->jwtUserId, $request->jwtSessionId);
            $result = CartLogic::addToCart($cart, $request->productId, $request->quantity);
            Log::info('result',[$result]);
            if (!$result["success"]) {
                return CommonLogic::jsonResponse($result["error"], 400, $result["items"]);
            } else {
                return CommonLogic::jsonResponse($result["message"], 200, $result["items"]);
            }
        } catch (Exception $e) {
            Log::info('Error addToCart', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function removeFromCart(Request $request)
    {
        // $validator = CommonValidation::sendOtp($request->all());
        // if ($validator->fails()) {
        //     return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        // }
        try {
            $cart = CartLogic::getOrCreateCart($request->jwtUserId, $request->jwtSessionId);
            $result = CartLogic::removeFromCart($cart, $request->productId);
            if (!$result["success"]) {
                return CommonLogic::jsonResponse($result["error"], 400, $result["items"]);
            } else {
                return CommonLogic::jsonResponse($result["message"], 200, $result["items"]);
            }
        } catch (Exception $e) {
            Log::info('Error removeFromCart', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function clearCart(Request $request)
    {
        // $validator = CommonValidation::sendOtp($request->all());
        // if ($validator->fails()) {
        //     return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        // }
        try {
            $cart = CartLogic::getOrCreateCart($request->jwtUserId, $request->jwtSessionId);
            CartLogic::clearCartItems($cart);
            return CommonLogic::jsonResponse("Cart has been cleared", 200, null);
        } catch (Exception $e) {
            Log::info('Error clearCart', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
}
