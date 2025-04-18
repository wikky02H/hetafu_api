<?php

namespace App\Http\Controllers;

use App\Http\BusinessLogics\CommonLogic;
use App\Http\BusinessLogics\ProductLogic;
use App\Http\Validation\CommonValidation;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;

class ProductController extends Controller
{
    public function productList(Request $request)
    {
        $validator = CommonValidation::pagination($request->all());

        if ($validator->fails()) {
            return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        }

        try {
            $productsDetails = ProductLogic::getProductList([
                "pageLimit" => $request->pageLimit,
                "sortBy" => (isset($request->sortBy) && in_array(strtolower($request->sortBy), ['asc', 'desc'])) ? $request->sortBy : 'desc',
                "currentPage" => $request->currentPage
            ]);

            if ($productsDetails) {
                return CommonLogic::jsonResponse("Product list fetched successfully", 200, $productsDetails);
            } else {
                return CommonLogic::jsonResponse("Unable to fetch product list", 500, null);
            }
        } catch (Exception $e) {
            Log::error('Error in productList controller', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
    public function getProductDetailsById($id)
    {
        try {
            $product = ProductLogic::detailsById($id);
            return CommonLogic::jsonResponse("Success", 200, $product);
        } catch (Exception $e) {
            Log::info('Error getProductDetailsById', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
}
