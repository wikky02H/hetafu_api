<?php

namespace App\Http\BusinessLogics;

use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\Log;

class ProductLogic
{
    public static function getProductList($payload)
    {
        $pageLimit = $payload['pageLimit'];
        $page = $payload['currentPage'];
        $sortBy = $payload['sortBy'];

        try {
            $query = Product::select(
                'id',
                'name',
                'description',
                'price',
                'stock',
                'image_url as imageUrl'
            )
            ->whereNull('deleted_at')
            ->orderBy('created_at', strtolower($sortBy));

            return $query->paginate($pageLimit, ['*'], 'page', $page);
        } catch (Exception $e) {
            Log::error('Error in getProductList', [$e]);
            return null;
        }
    }
    public static function detailsById($id)
    {
        $product = Product::leftJoin("categories", "categories.id", "=", "products.category_id")
            ->select(
                "products.id",
                "products.name",
                "products.description",
                "products.price",
                "products.stock",
                "products.image_url as imageUrl",
                "categories.id as categoryId",
                "categories.name as category",
            )
            ->where("products.id", $id)
            ->first();
        return $product;
    }
}
