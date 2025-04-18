<?php

namespace App\Http\BusinessLogics;

use App\Models\Order;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderLogic
{
    public static function getOrderList($payload)
    {
        $pageLimit = $payload['pageLimit'];
        $page = $payload['currentPage'];
        $sortBy = $payload['sortBy'];
        Log::info('page', [$page]);
        try {
            $query = Order::select(
                'orders.id as id',
                'orders.order_number as orderNumber',
                'oss.name as orderStatus',
                'orders.created_at as date',
                'orders.total_amount as totalAmount'
            )
                ->leftJoin('order_items as ois', 'ois.order_id', '=', 'orders.id')
                ->leftJoin('order_statuses as oss', 'oss.id', '=', 'orders.order_status_id')
                ->orderBy('orders.created_at', strtolower($sortBy));
            $getList = $query->paginate($pageLimit, ['*'], 'page', $page);
            return $getList;
        } catch (Exception $e) {
            Log::info('Error getOrderList', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
    public static function getOrderCount()
    {

        try {
            // $totalCount = Order::count();

            // $pendingCount = Order::whereHas('orderStatus', function($query) {
            //     $query->where('name', 'PENDING');
            // })->count();

            // $processingCount = Order::whereHas('orderStatus', function($query) {
            //     $query->where('name', 'PROCESSING');
            // })->count();

            // $deliveredCount = Order::whereHas('orderStatus', function($query) {
            //     $query->where('name', 'DELIVERED');
            // })->count();
            $counts = DB::table('orders')
                ->selectRaw('
                COUNT(*) as totalOrders,
                SUM(CASE WHEN os.name = "PENDING" THEN 1 ELSE 0 END) as pendingCount,
                SUM(CASE WHEN os.name = "PROCESSING" THEN 1 ELSE 0 END) as processingCount,
                SUM(CASE WHEN os.name = "DELIVERED" THEN 1 ELSE 0 END) as deliveredCount
            ')
                ->leftJoin('order_statuses as os', 'os.id', '=', 'orders.order_status_id')
                ->first();
            $data = [
                'totalOrders' => (int)$counts->totalOrders,
                'pendingCount' => (int)$counts->pendingCount,
                'processingCount' => (int)$counts->processingCount,
                'deliveredCount' => (int)$counts->deliveredCount,
            ];
            return $data;
        } catch (Exception $e) {
            Log::info('Error getOrderCount', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
    public static function getOrderDetailsById($id)
    {
        try {
            $order = Order::with([
                'user:id,name,email,mobile',
                'orderStatus:id,name',
                'orderItems.product.category'
            ])->find($id);

            if (!$order) {
                return CommonLogic::jsonResponse("Order not found", 404, null);
            }

            // order details
            $orderDetails = [
                'id'       => $order->id,
                'number'   => $order->order_number,
                'statusId'        => $order->orderStatus->id ?? null,
                'status'        => $order->orderStatus->name ?? null,
                'totalAmount'   => $order->total_amount,
                'createdAt'     => $order->created_at->toDateTimeString(),
            ];

            // customer details
            $customerInformation = [
                'customerId'    => $order->user->id ?? null,
                'name'          => $order->user->name ?? null,
                'email'         => $order->user->email ?? null,
                'phone'         => $order->user->phone ?? null,
            ];

            // billing and shipping
            $billingAddress = $order->billing_address;
            $shippingAddress = $order->shipping_address;

            // items
            $orderItems = $order->orderItems->map(function ($item) {
                return [
                    'id'            => $item->id,
                    'name'          => $item->name,
                    'quantity'          => $item->quantity,
                    'price'             => $item->price,
                    'image'             => $item->image,
                    'productDetails'    => [
                        'id'       => $item->product->id ?? null,
                        'name'       => $item->product->name ?? null,
                        'description' => $item->product->description ?? null,
                        'price'      => $item->product->price ?? null,
                        'stock'      => $item->product->stock ?? null,
                        'imageUrl'   => $item->product->image_url ?? null,
                        'categoryId'   => $item->product->category->id ?? null,
                        'category'   => $item->product->category->name ?? null
                    ]
                ];
            })->unique('id')->values();
            $data = [
                'orderDetails'        => $orderDetails,
                'customerInformation' => $customerInformation,
                'billingAddress'      => $billingAddress,
                'shippingAddress'     => $shippingAddress,
                'orderItems'          => $orderItems
            ];
            return $data;
        } catch (Exception $e) {
            Log::error('Error getOrderDetailsById', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
}
