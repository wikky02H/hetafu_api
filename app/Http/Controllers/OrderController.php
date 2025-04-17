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
}
