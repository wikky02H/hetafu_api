<?php

namespace App\Http\BusinessLogics;

use App\Models\BillingAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ShippingAddress;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        // $decryptValues = explode('&', $rcvdString);
        // Log::info("decryptValues", [$decryptValues]);
        // $dataSize = sizeof($decryptValues);
        // Log::info("dataSize", [$dataSize]);

        $responseData = CCAvenueLogic::parseCCAvenueResponse($rcvdString);
        Log::info("responseData", [$responseData]);
        $orderNumber = $responseData["order_id"];
        $lowerCasedOrderStatus = strtolower($responseData["order_status"]);

        Order::where("order_number", $orderNumber)
            ->update([
                "order_status_id" => $lowerCasedOrderStatus == "success" ? 3 : 4,
            ]);
        $order = Order::select("*")
            ->where("order_number", $orderNumber)
            ->first();

        $paymentStatusId = 1;
        if ($lowerCasedOrderStatus == "success") $paymentStatusId = 2;
        else if ($lowerCasedOrderStatus == "failure" || $lowerCasedOrderStatus == "failed") $paymentStatusId = 3;
        Payment::create([
            'order_id' => $order->id,
            'payment_status_id' => $paymentStatusId,
            'amount' => $order->total_amount,
            'payment_method' => $responseData["payment_mode"],
            'transaction_id' => $responseData["tracking_id"],
        ]);

        return $lowerCasedOrderStatus == "success" ? "/order-confirmation/$orderNumber" : "/payment-failed/$orderNumber";
    }

    public static function confirmationEmail($orderNumber)
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

            $paymentDetails = Payment::select("*")
                ->where("order_id", $order->id)
                ->first();

            $deliveryDate = Carbon::parse($order->created_at)->addDays(2);

            $html = "
                    <!DOCTYPE html>
      <html>
      <head>
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Order Confirmation</title>
        <style>
          @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');
          
          body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: #4B5563;
            background-color: #F9FAFB;
          }
          .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #FFFFFF;
            border-radius: 12px;
            margin-top: 24px;
            margin-bottom: 24px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #E5E7EB;
          }
          .header {
            padding: 32px 0 24px;
            text-align: center;
            background: linear-gradient(135deg, #F9FAFB 0%, #FFFFFF 100%);
            border-bottom: 1px solid #E5E7EB;
          }
          .logo {
            color: #111827;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.5px;
            margin: 0;
          }
          .subtitle {
            color: #6B7280;
            margin: 8px 0 0;
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0.25px;
          }
          .section {
            padding: 24px 32px;
            border-bottom: 1px solid #E5E7EB;
          }
          .section-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 16px;
            color: #111827;
            letter-spacing: -0.25px;
          }
          .success-banner {
            background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
            padding: 20px;
            text-align: center;
            margin-bottom: 24px;
            border-radius: 8px;
            border: 1px solid #A7F3D0;
          }
          .success-title {
            color: #065F46;
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 8px;
          }
          .success-text {
            color: #047857;
            margin: 0;
            font-size: 14px;
          }
          .order-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 16px;
          }
          .meta-item {
            font-size: 14px;
          }
          .meta-label {
            color: #6B7280;
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
          }
          .meta-value {
            color: #111827;
            font-weight: 500;
          }
          .product {
            display: flex;
            padding: 16px 0;
            border-bottom: 1px solid #E5E7EB;
            align-items: flex-start;
          }
          .product:last-child {
            border-bottom: none;
          }
          .product-image {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 6px;
            margin-right: 16px;
            border: 1px solid #E5E7EB;
          }
          .product-details {
            flex: 1;
          }
          .product-name {
            font-size: 15px;
            margin: 0 0 6px;
            color: #111827;
            font-weight: 500;
          }
          .product-description {
            color: #6B7280;
            font-size: 13px;
            margin: 0 0 8px;
            line-height: 1.4;
          }
          .product-meta {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            align-items: center;
          }
          .product-price {
            color: #065F46;
            font-weight: 500;
          }
          .product-quantity {
            color: #6B7280;
            font-size: 13px;
          }
          .product-total {
            font-weight: 500;
            color: #111827;
          }
          .total-section {
            text-align: right;
            padding: 16px;
            background-color: #F9FAFB;
            border-radius: 8px;
            margin-top: 16px;
          }
          .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
          }
          .total-label {
            color: #6B7280;
            font-size: 14px;
          }
          .total-value {
            color: #111827;
            font-weight: 500;
          }
          .total-amount {
            font-size: 18px;
            font-weight: 600;
            margin: 8px 0 0;
            color: #111827;
          }
          .address-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
          }
          @media (min-width: 480px) {
            .address-grid {
              grid-template-columns: 1fr 1fr;
            }
          }
          .address-box {
            background-color: #F9FAFB;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
          }
          .address-title {
            font-size: 15px;
            margin: 0 0 12px;
            color: #111827;
            font-weight: 500;
          }
          .address-text {
            font-size: 14px;
            line-height: 1.5;
            margin: 6px 0;
            color: #4B5563;
          }
          .notes-box {
            background-color: #F9FAFB;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            border: 1px solid #E5E7EB;
          }
          .customer-box {
            background-color: #F9FAFB;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            border: 1px solid #E5E7EB;
          }
          .footer {
            text-align: center;
            padding: 24px;
            color: #6B7280;
            font-size: 13px;
            background-color: #F9FAFB;
          }
          .footer-text {
            margin: 0 0 12px;
            line-height: 1.5;
          }
          .thank-you {
            color: #065F46;
            font-weight: 500;
            margin-bottom: 16px;
            display: block;
            font-size: 15px;
          }
          .divider {
            height: 1px;
            background-color: #E5E7EB;
            margin: 24px 0;
          }
        </style>
      </head>
      <body>
        <div class='container'>
          <!-- Header -->
          <div class='header'>
            <h1 class='logo'>HETAFU</h1>
            <p class='subtitle'>Order Confirmation</p>
          </div>
    
          <!-- Success Message -->
          <div class='section' style='padding-bottom: 16px;'>
            <div class='success-banner'>
              <h2 class='success-title'>Thank you for your order!</h2>
              <p class='success-text'>Your order #$order->order_number has been confirmed and is being processed.</p>
            </div>
    
            <!-- Customer Details -->
            <div class='customer-box'>
              <h3 class='section-title' style='margin-bottom: 12px;'>Customer Details</h3>
              <div class='address-text'>
                <p style='margin: 6px 0;'><strong>Name:</strong> " . $customerInformation["name"] . "</p>
                <p style='margin: 6px 0;'><strong>Email:</strong> " . $customerInformation["email"] . "</p>
                " . $customerInformation["phone"] ? "<p style='margin: 6px 0;'><strong>Phone:</strong>" . $customerInformation["phone"] . "</p>" : "" . "
              </div>
            </div>
          </div>
    
          <!-- Order Meta -->
          <div class='section' style='padding-top: 16px;'>
            <div class='order-meta'>
              <div class='meta-item'>
                <span class='meta-label'>Order Date</span>
                <span class='meta-value'>" . Carbon::parse($order->createdAt)->format("D, d M Y") . "</span>
              </div>
              <div class='meta-item'>
                <span class='meta-label'>Order Number</span>
                <span class='meta-value'>#" . $order->order_number . "</span>
              </div>
              <div class='meta-item'>
                <span class='meta-label'>Payment Method</span>
                <span class='meta-value'> " . $paymentDetails->payment_method || 'Credit Card' . "</span>
              </div>
              <div class='meta-item'>
                <span class='meta-label'>Transaction Id</span>
                <span class='meta-value'>" . $paymentDetails->transaction_id || 'NILL' . "</span>
              </div>
             
            </div>
            
          </div>
    
          <!-- Products -->
          <div class='section'>
            <h3 class='section-title'>Order Summary</h3>";

            foreach ($orderItems as $item) {
                $html .= "
                <div class='product'>
                    <div class='product-details'>
                        <h4 class='product-name'>" . htmlspecialchars($item['name']) . "</h4>";

                if (!empty($item['description'])) {
                    $html .= "<p class='product-description'>" . htmlspecialchars($item['description']) . "</p>";
                }

                $itemPrice = number_format($item['price'], 2);
                $itemTotal = number_format($item['price'] * $item['quantity'], 2);

                $html .= "
                        <div class='product-meta'>
                            <div>
                                <span class='product-price'>₹{$itemPrice}</span>
                                <span class='product-quantity'>× {$item['quantity']}</span>
                            </div>
                            <span class='product-total'>₹{$itemTotal}</span>
                        </div>
                    </div>
                </div>";
            }

            //     "<div class='total-section'>
            //       ${order.subtotal ? `
            //         <div class='total-row'>
            //           <span class='total-label'>Subtotal</span>
            //           <span class='total-value'>₹${order.subtotal.toFixed(2)}</span>
            //         </div>
            //       ` : ''}
            //       ${order.shipping ? `
            //         <div class='total-row'>
            //           <span class='total-label'>Shipping</span>
            //           <span class='total-value'>₹${order.shipping.toFixed(2)}</span>
            //         </div>
            //       ` : ''}
            //       ${order.tax ? `
            //         <div class='total-row'>
            //           <span class='total-label'>Tax</span>
            //           <span class='total-value'>₹${order.tax.toFixed(2)}</span>
            //         </div>
            //       ` : ''}
            //       <div class='divider'></div>
            //       <p class='total-amount'>Total: ₹${order.totalAmount.toFixed(2)}</p>
            //     </div>
            //   </div>
            $html .= "
          <!-- Shipping/Billing Address -->
          ";
            $final_address = $shippingAddress ? $shippingAddress : $billingAddress;
            Log::info('final_address', [$final_address]);
            if ($final_address) {
                $html .= "
                <div class='section'>
              <h3 class='section-title'>Delivery Information</h3>
              <div class='address-grid'>
                 <div class='address-box'>
                    <h4 class='address-title'>Shipping Address</h4>
                    <p class='address-text'>" . $customerInformation["name"] . "</p>
                    <p class='address-text'>" . $final_address->city . ", " . $final_address->state . ", " . $final_address->zipCode . "</p>
                    <p class='address-text'>" . $final_address->country . "</p>";
                if ($final_address->phoneNumber) {
                    $html .= "<p class='address-text'>Phone: " . $final_address->phoneNumber . "</p>";
                }
                $html .= "</div>
              </div>
            </div>
            ";

                //   $html .= "
                //   <!-- Order Notes -->
                //   ${order.orderNotes ? `
                //     <div class="section">
                //       <div class="notes-box">
                //         <h4 class="address-title">Order Notes</h4>
                //         <p class="address-text">${order.orderNotes}</p>
                //       </div>
                //     </div>
                //   ` : ''}

                $html .= "
          <!-- Footer -->
          <div class='footer'>
            <span class='thank-you'>Thank you for shopping with us</span>
            <p class='footer-text'>If you have any questions about your order, please contact our customer support at support@hetafu.com</p>
            <p class='footer-text'>© " . Carbon::now()->year . " Hetafu. All rights reserved.</p>
          </div>
        </div>
      </body>
      </html>
                ";

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . env('RESEND_API_KEY'),
                ])->withOptions([
                    'verify' => false,
                ])
                    ->timeout(60)
                    ->post('https://api.resend.com/emails', [
                        'from' => 'Hetafu <orders@hetafu.com>',
                        'to' => [$customerInformation["email"]],
                        'subject' => "Order Confirmation - Hetafu #$order->order_number",
                        'html' => $html,
                    ]);
            }
            Log::info("response", [$response]);
            return true;
        } catch (Exception $err) {
            Log::info("error", [$err]);
            return false;
        }
    }
}
