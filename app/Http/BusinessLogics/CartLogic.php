<?php

namespace App\Http\BusinessLogics;

use App\Models\Cart;
use App\Models\CartItem;

class CartLogic
{
    public static function getOrCreateCart($userId, $sessionId)
    {
        $cart = null;
        if ($userId) {
            $cart = Cart::select(
                "id",
                "user_id as userId",
                "session_id as sessionId",
            )
                ->where("user_id", $userId)
                ->first();
            if (!$cart) {
                $cart = Cart::create([
                    "user_id" => $userId,
                    "created_at" => DBLogic::currentDateTime(),
                ]);
            }
        } else if ($sessionId) {
            $cart = Cart::select("*")
                ->where("session_id", $sessionId)
                ->first();
            if (!$cart) {
                $cart = Cart::create([
                    "session_id" => $sessionId,
                    "created_at" => DBLogic::currentDateTime(),
                ]);
            }
        }
        return $cart;
    }

    public static function addToCart($cart, $productId, $quantity = 1)
    {
        $success = false;
        $message = "";
        $error = "";
        $items = array();
        if (!$productId) {
            $success = false;
            $error = "Product ID is required";
            return [
                "success" => $success,
                "error" => $error,
                "message" => $message,
                "items" => $items,
            ];
        }

        $isCartItemExists = CartItem::select(
            "id",
            "cart_id as cartId",
            "product_id as productId",
            "quantity"
        )
            ->where("cart_id", $cart->id)
            ->where("product_id", $productId)
            ->first();

        if ($isCartItemExists) {
            // Update quantity if item exists
            CartItem::where("id", $isCartItemExists->id)
                ->update([
                    "quantity" => (int)$isCartItemExists->quantity + $quantity,
                    "updated_at" => DBLogic::currentDateTime(),
                ]);
        } else {
            // Create new cart item if it doesn't exist
            CartItem::create([
                "cart_id" => $cart->id,
                "product_id" => $productId,
                "quantity" => $quantity,
                "created_at" => DBLogic::currentDateTime(),
            ]);
        }

        // Fetch updated cart items
        $items = self::getCartItems($cart);
        $success = true;
        $message = "Item added to cart";

        return [
            "success" => $success,
            "message" => $message,
            "error" => $error,
            "items" => $items,
        ];
    }

    public static function removeFromCart($cart, $productId)
    {
        $success = false;
        $message = "";
        $error = "";
        $items = array();
        if (!$productId) {
            $success = false;
            $error = "Product ID is required";
            return [
                "success" => $success,
                "error" => $error,
                "message" => $message,
                "items" => $items,
            ];
        }

        $isCartItemExists = CartItem::select(
            "id",
            "cart_id as cartId",
            "product_id as productId",
            "quantity"
        )
            ->where("cart_id", $cart->id)
            ->where("product_id", $productId)
            ->first();

        if (!$isCartItemExists) {
            $success = false;
            $error = "Item not found in cart";
            return [
                "success" => $success,
                "error" => $error,
                "message" => $message,
                "items" => $items,
            ];
        }

        if ($isCartItemExists->quantity <= 1) {
            // Delete the item if quantity would become 0
            CartItem::where("id", $isCartItemExists->id)
                ->update([
                    "quantity" => 0,
                    "deleted_at" => DBLogic::currentDateTime(),
                ]);
        } else {
            // Decrement quantity if more than 1
            CartItem::where("id", $isCartItemExists->id)
                ->update([
                    "quantity" => $isCartItemExists->quantity - 1,
                    "updated_at" => DBLogic::currentDateTime(),
                ]);
        }

        $success = true;
        $message = "Item quantity updated";

        return [
            "success" => $success,
            "message" => $message,
            "error" => $error,
            "items" => $items,
        ];
    }

    public static function getCartItems($cart)
    {
        $cartItems = CartItem::innerJoin("products", "products.id", "=", "cart_items.product_id")
            ->select(
                "cart_items.id as id",
                "cart_items.product_id as productId",
                "cart_items.quantity as quantity",
                "products.name as name",
                "products.price as price",
                "products.stock as stock",
                "products.image_url as imageUrl",
            )
            ->where("cartId", $cart->id)
            ->get();

        return $cartItems;
    }

    public static function clearCartItems($cart)
    {
        $cartItems = CartItem::where("cartId", $cart->id)
            ->update([
                "quantity" => 0,
                "deleted_at" => DBLogic::currentDateTime(),
            ]);

        return $cartItems;
    }
}
