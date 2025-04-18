<?php

namespace App\Http\BusinessLogics;

use App\Models\BillingAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingAddress;
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
                'orders.created_at as createdAt',
                'orders.total_amount as totalAmount',
                'u.name as userName',
                'u.email as userEmail',
                DB::raw("
                    (SELECT
                        SUM(price * quantity)
                    FROM order_items
                    WHERE order_items.order_id = orders.id) as orderTotal
                ")
            )
                ->join("users as u", "u.id", "=", "orders.user_id")
                // ->leftJoin('order_items as ois', 'ois.order_id', '=', 'orders.id')
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
    public static function getOrderDetailsById($orderNumber)
    {
        try {
            $order = Order::with([
                'user:id,name,email,mobile',
                'orderStatus:id,name',
                'orderItems.product.category'
            ])
                ->where("order_number", $orderNumber)
                ->first();

            if (!$order) {
                return CommonLogic::jsonResponse("Order not found", 404, null);
            }

            // order details
            $orderDetails = [
                'id'       => $order->id,
                'orderNumber'   => $order->order_number,
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
            $billingAddress = json_decode($order->billing_address);
            $shippingAddress = json_decode($order->shipping_address);

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

    public static function billingAddressesByUserId($user_id)
    {
        $billingAddress = BillingAddress::select(
            'first_name as firstName',
            'last_name as lastName',
            'company_name as companyName',
            'address',
            'country',
            'state',
            'city',
            'zip_code as zipCode',
            'email',
            'phone_number as phoneNumber',
            'is_default as isDefault',
            DB::raw("'billing' as `type`"),
        )
            ->where("user_id", $user_id)
            ->where("is_default", true)
            ->orderBy("created_at", "desc")
            ->first();

        return $billingAddress;
    }

    public static function shippingAddressesByUserId($user_id)
    {
        $shippingAddress = ShippingAddress::select(
            'first_name as firstName',
            'last_name as lastName',
            'company_name as companyName',
            'address',
            'country',
            'state',
            'city',
            'zip_code as zipCode',
            'email',
            'phone_number as phoneNumber',
            'is_default as isDefault',
            DB::raw("'shipping' as `type`"),
        )
            ->where("user_id", $user_id)
            ->where("is_default", true)
            ->orderBy("created_at", "desc")
            ->first();

        return $shippingAddress;
    }

    public static function saveAddresses($userId, $billing, $shipping, $useSameAddress)
    {
        BillingAddress::create([
            'user_id' => $userId,
            'first_name' => $billing["firstName"],
            'last_name' => $billing["lastName"],
            'company_name' => $billing["companyName"],
            'address' => $billing["address"],
            'country' => $billing["country"],
            'state' => $billing["state"],
            'city' => $billing["city"],
            'zip_code' => $billing["zipCode"],
            'email' => $billing["email"],
            'phone_number' => $billing["phoneNumber"],
            'is_default' => true,
        ]);

        ShippingAddress::create([
            'user_id' => $userId,
            'first_name' => $useSameAddress ? $billing["firstName"] : $shipping["firstName"],
            'last_name' => $useSameAddress ? $billing["lastName"] : $shipping["lastName"],
            'company_name' => $useSameAddress ? $billing["companyName"] : $shipping["companyName"],
            'address' => $useSameAddress ? $billing["address"] : $shipping["address"],
            'country' => $useSameAddress ? $billing["country"] : $shipping["country"],
            'state' => $useSameAddress ? $billing["state"] : $shipping["state"],
            'city' => $useSameAddress ? $billing["city"] : $shipping["city"],
            'zip_code' => $useSameAddress ? $billing["zipCode"] : $shipping["zipCode"],
            'email' => $useSameAddress ? $billing["email"] : $shipping["email"],
            'phone_number' => $useSameAddress ? $billing["phoneNumber"] : $shipping["phoneNumber"],
            'is_default' => true,
        ]);
    }

    public static function placeOrder($userId, $orderDetails)
    {
        // $orderNumber = `ORD${Date.now()}${Math.floor(Math.random() * 1000)}`;
        $orderNumber = "ORD" . (int)(microtime(true) * 1000) . rand(0, 999);

        $order = Order::create([
            'user_id' => $userId,
            'order_status_id' => 1,
            'order_number' => $orderNumber,
            'billing_address' => json_encode($orderDetails["billingAddress"]),
            'shipping_address' => json_encode($orderDetails["shippingAddress"]),
            'total_amount' => $orderDetails["totalAmount"],
        ]);

        $orderItems = array();
        foreach ($orderDetails["items"] as $row) {
            $orderItems[] = [
                'order_id' => $order->id,
                'product_id' => $row["productId"],
                'quantity' => $row["quantity"],
                'price' => $row["price"],
                'image' => $row["image"],
                'name' => $row["name"],
            ];
            DB::raw("
                UPDATE `products`
                SET `stock` = `stock` - 1
                WHERE `id` = ?
                    AND `stock` > 0
            ", [$row["productId"]]);
        }
        OrderItem::insert($orderItems);

        return $orderNumber;
    }

    public static function initializePayment($userId, $orderNumber)
    {
        $order = Order::select("*")
            ->where("order_number", $orderNumber)
            ->first();

        $order_items = OrderItem::select("*")
            ->where("order_id", $order->id)
            ->get();

        $amount = number_format($order->total_amount, 2, ".", "");

        $redirect_url = env("UI_APP_BASE_URL") . "/api/payment/response";
        // $redirect_url = "http://localhost:8000/api/order/payment/response";
        $billing_address = json_decode($order->billing_address);
        Log::info("billing_address", [$billing_address]);
        $merchantData = array(
            "merchant_id" => env("CCAVENUE_MERCHANT_ID"),
            "order_id" => $orderNumber,
            "currency" => "INR",
            "amount" => $amount,
            "redirect_url" => $redirect_url,
            "cancel_url" => $redirect_url,
            "language" => "EN",
            "billing_name" => $billing_address->firstName . " " . $billing_address->lastName,
            "billing_address" => $billing_address->address,
            "billing_city" => $billing_address->city,
            "billing_state" => $billing_address->state,
            "billing_zip" => $billing_address->zipCode,
            "billing_country" => $billing_address->country,
            "billing_tel" => $billing_address->phoneNumber,
            "billing_email" => $billing_address->email,
            "merchant_param1" => $userId, // Additional parameter for reference
            "tid" => "TID" . round(microtime(true) * 1000), // Unique transaction ID
            "integration_type" => "iframe_normal",
        );

        Log::info("merchantData", $merchantData);

        $merchantDataStringified = "";
        foreach ($merchantData as $key => $value) {
            $merchantDataStringified .= $key . '=' . $value . '&';
        }

        Log::info("merchantDataStringified", [$merchantDataStringified]);

        $encryptedData = CCAvenueLogic::encryptCC($merchantDataStringified, env("CCAVENUE_WORKING_KEY"));
        Log::info("encryptedData", [$encryptedData]);

        return $encryptedData;
    }

    public static function paymentResponse($encResp)
    {
        Log::info("encResp", [$encResp]);
        $workingKey = env("CCAVENUE_WORKING_KEY"); //Working Key should be provided here.
        // $encResponse = $_POST["encResp"];
        $encResponse = $encResp;

        $rcvdString = CCAvenueLogic::decryptCC($encResponse, $workingKey);        //Crypto Decryption used as per the specified working key.
        $order_status = "";
        Log::info("rcvdString", [$rcvdString]);
        $decryptValues = explode('&', $rcvdString);
        Log::info("decryptValues", [$decryptValues]);
        $dataSize = sizeof($decryptValues);
        Log::info("dataSize", [$dataSize]);

        // Order::where("order_number", $orderNumber)
        //     ->update([
        //         "order_status_id" => $orderStatus === "success" ? 3 : 4,
        //     ]);
        // $order = Order::select("*")
        //     ->where("order_number", $orderNumber)
        //     ->first();

        // Payment::create([
        //     'order_id' => $order->id,
        //     'payment_status_id' => $orderStatus,
        //     'amount' => $totalAmount,
        //     'payment_method' => $paymentMethod,
        //     'transaction_id' => $transactionId,
        // ]);
    }
}
