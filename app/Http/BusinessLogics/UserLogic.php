<?php

namespace App\Http\BusinessLogics;

use App\Models\Session;
use App\Models\User;
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
    public static function getCustomerList($payload)
    {
        $pageLimit = $payload['pageLimit'];
        $page = $payload['currentPage'];
        $sortBy = $payload['sortBy'];

        try {
            $query = User::select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.created_at as joinDate',
                    DB::raw('COUNT(orders.id) as totalOrders'),
                    DB::raw('SUM(orders.total_amount) as totalAmount')
                )
                ->join('orders', 'orders.user_id', '=', 'users.id')
                ->groupBy('users.id', 'users.name', 'users.email', 'users.created_at')
                ->whereNull('users.deleted_at')
                ->orderBy('users.created_at', strtolower($sortBy));

            return $query->paginate($pageLimit, ['*'], 'page', $page);
        } catch (Exception $e) {
            Log::error('Error in getCustomerList', ['exception' => $e]);
            return null;
        }
    }
    public static function customerCount()
    {
        try {
            $query = DB::selectOne("
                SELECT
                    COUNT(DISTINCT u.id) AS totalCustomer,
                    COUNT(DISTINCT CASE
                        WHEN MONTH(IFNULL(u.updated_at, u.created_at)) = MONTH(CURDATE())
                        AND YEAR(IFNULL(u.updated_at, u.created_at)) = YEAR(CURDATE())
                        THEN u.id
                    END) AS activeThisMonth,
                    COUNT(DISTINCT CASE
                        WHEN MONTH(u.created_at) = MONTH(CURDATE())
                        AND YEAR(u.created_at) = YEAR(CURDATE())
                        THEN u.id
                    END) AS newThisMonth
                FROM users u
                INNER JOIN orders o ON o.user_id = u.id
                WHERE u.deleted_at IS NULL
            ");
            return [
                'totalCustomer' => (int)$query->totalCustomer,
                'activeThisMonth' => (int)$query->activeThisMonth,
                'newThisMonth' => (int)$query->newThisMonth
            ];
        } catch (Exception $e) {
            Log::info('Error customerCount', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
    // activeThisMonth unique users id i need to count
}
