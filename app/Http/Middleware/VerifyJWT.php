<?php

namespace App\Http\Middleware;

use App\Http\BusinessLogics\CommonLogic;
use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyJWT
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader) {
            return CommonLogic::jsonResponse("Authorization token not provided", 401, null);
        }
        $token = explode(" ", $authHeader)[1];
        try {
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), env('JWT_ALGO')));
            if (isset($decoded->exp) && time() > $decoded->exp) {
                return CommonLogic::jsonResponse("Token has expired", 401, null);
            }
            $request->attributes->add(['userIdAttr' => (array) $decoded->id]);
            $request->merge([
                "jwtUserId" => $decoded->id,
                "jwtSessionId" => $decoded->sessionId
            ]);
        } catch (Exception $e) {
            Log::error('JWT Error: ' . $e->getMessage());
            return CommonLogic::jsonResponse("Invalid or expired token", 401, null);
        }
        return $next($request);
    }
}
