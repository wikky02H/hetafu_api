<?php

namespace App\Http\Helpers;

use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;

class JWTToken
{
    public static function generateToken(array $additional_payload  = [])
    {
        try {
            $current_epoch_time = time();
            $token_exp = strtotime(env("JWT_EXP"));
            $payload = array_merge([
                "iss" => env("API_BASE_URL"),
                "iat" => $current_epoch_time,
                "nbf" => $current_epoch_time,
                "exp" => $token_exp,
            ], $additional_payload);
            $token = JWT::encode($payload, env("JWT_SECRET"), env("JWT_ALGO"));
            $response = [
                'token' => $token,
                'expires' => Carbon::parse($token_exp)->format('Y-m-d H:i:s'),
            ];
            return $response;
        } catch (Exception $e) {
            Log::info("jwt issue error => " . $e->getMessage());
            return false;
        }
    }

    public static function parse($token)
    {
        try {
            $decoded = (array) JWT::decode($token, new Key(env("JWT_SECRET"), env("JWT_ALGO")));
            // Log::info("decoded", $decoded);
            $expiry = $decoded["exp"];
            if (time() > $expiry) {
                return "Token has expired.";
            }
            return [
                "id" => $decoded["id"],
            ];
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            Log::info("jwt parse error => {$error_message}");
            if ($error_message == "Expired token") {
                return "Token has expired.";
            }
            return "Cannot parse token.";
        }
    }
}
