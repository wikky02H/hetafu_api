<?php

namespace App\Http\Controllers;

use App\Http\BusinessLogics\CartLogic;
use App\Http\BusinessLogics\CommonLogic;
use App\Http\BusinessLogics\OrderLogic;
use App\Http\Validation\CommonValidation;
use App\Models\Otp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function getOrderList(Request $request)
    {
        $validator = CommonValidation::pagination($request->all());
        if ($validator->fails()) {
            return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        }
        try {
            $getOrderList = OrderLogic::getOrderList([
                "pageLimit" => $request->pageLimit,
                "sortBy" => (isset($request->sortBy) && in_array(strtolower($request->sortBy), ['asc', 'desc'])) ? $request->sortBy : 'desc',
                "currentPage" => $request->currentPage
            ]);
            return CommonLogic::jsonResponse("Order List retrieved successfully", 200, $getOrderList);
        } catch (Exception $e) {
            Log::info('Error removeFromCart', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function getOrderCount()
    {
        try {
            $getOrderList = OrderLogic::getOrderCount();
            return CommonLogic::jsonResponse("orderList", 200, $getOrderList);
        } catch (Exception $e) {
            Log::info('Error removeFromCart', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
    public function getOrderDetails($id)
    {
        if (!$id) {
            return CommonLogic::jsonResponse("Invalid ID", 400, null);
        }

        try {
            $getOrderData = OrderLogic::getOrderDetailsById($id);

            return is_array($getOrderData)
                ? CommonLogic::jsonResponse("Order details fetched successfully", 200, $getOrderData)
                : CommonLogic::jsonResponse("Unable to fetch order details", 500, null);
        } catch (Exception $e) {
            Log::error('Error getOrderDetails', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function userAddresses(Request $request)
    {
        try {
            $billingAddress = OrderLogic::billingAddressesByUserId($request->jwtUserId);
            $shippingAddress = OrderLogic::billingAddressesByUserId($request->jwtUserId);
            return CommonLogic::jsonResponse(
                "Success",
                200,
                [
                    "billing" => $billingAddress,
                    "shipping" => $shippingAddress,
                    "useSameAddress" => !$shippingAddress,
                ],
            );
        } catch (Exception $e) {
            Log::info('Error userAddresses', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function saveAddresses(Request $request)
    {
        try {
            OrderLogic::billingAddressesByUserId($request->jwtUserId);
            OrderLogic::saveAddresses($request->jwtUserId, $request->billing, $request->shipping, $request->useSameAddress);
            return CommonLogic::jsonResponse(
                "Success",
                200,
                null,
            );
        } catch (Exception $e) {
            Log::info('Error saveAddresses', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function placeOrder(Request $request)
    {
        DB::beginTransaction();
        try {
            $orderNumber = OrderLogic::placeOrder($request->jwtUserId, [
                "items" => $request->items,
                "billingAddress" => $request->billingAddress,
                "shippingAddress" => $request->shippingAddress ? $request->shippingAddress : $request->billingAddress,
                "totalAmount" => $request->totalAmount,
                "paymentMethod" => $request->paymentMethod,
            ]);
            DB::commit();
            return CommonLogic::jsonResponse(
                "Success",
                200,
                [
                    "orderNumber" => $orderNumber,
                ],
            );
        } catch (Exception $e) {
            Log::info('Error placeOrder', [$e]);
            DB::rollBack();
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function initializePayment(Request $request)
    {
        try {
            $encryptedData = OrderLogic::initializePayment($request->jwtUserId, $request->orderNumber);
            return CommonLogic::jsonResponse(
                "Success",
                200,
                [
                    "encryptedData" => $encryptedData,
                    "accessCode" => env("CCAVENUE_ACCESS_CODE"),
                ],
            );
        } catch (Exception $e) {
            Log::info('Error initializePayment', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function paymentResponse(Request $request)
    {
        DB::beginTransaction();
        try {
            Log::info("request", [$request]);
            $encryptedData = OrderLogic::paymentResponse($request->encResp);
            DB::commit();
            return CommonLogic::jsonResponse(
                "Success",
                200,
                null,
            );
        } catch (Exception $e) {
            Log::info('Error paymentResponse', [$e]);
            DB::rollBack();
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
}
