<?php

namespace App\Http\BusinessLogics;

use App\Models\Session;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserLogic
{
    public static function getUserSessionDetails($token)
    {
        try {
            $getQuery = Session::select(
                'u.uuid',
                'u.email',
                'u.id as userId',
                'u.name',
                'u.mobile',
                'sessions.id as sessionId',
                'sessions.token',
                'sessions.expires_at as expiresAt',
                'r.name as role'
            )
                ->leftJoin('users as u', 'u.id', '=', 'sessions.user_id')
                ->leftJoin('roles as r', 'r.id', '=', 'u.role')
                ->where('sessions.token', $token)
                ->first();
            if ($getQuery) {
                $data = [
                    "userDetails" => (object)[
                        "id" => $getQuery->userId,
                        "uuid" => $getQuery->uuid,
                        "name" => $getQuery->name,
                        "mobile" => $getQuery->mobile,
                        "email" => $getQuery->email,
                        "role" => $getQuery->role
                    ],
                    "sessionDetails" => (object)[
                        "id" => $getQuery->sessionId,
                        "token" => $getQuery->token,
                        "expiresAt" => $getQuery->expiresAt,
                    ]
                ];
                return $data;
            } else {
                return [];
            }
        } catch (Exception $e) {
            Log::info('Error getUserSessionDetails', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
    public static function logoutUserByToken(string $sessionId): bool
    {
        try {
            $deleted = Session::where('id', $sessionId)->delete();
            return $deleted > 0;
        } catch (Exception $e) {
            Log::info('Error in logoutUserByToken', ['exception' => $e]);
            return false;
        }
    }
}
