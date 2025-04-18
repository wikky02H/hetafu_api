<?php

namespace App\Http\Controllers;

use App\Http\BusinessLogics\CommonLogic;
use App\Http\BusinessLogics\DBLogic;
use App\Http\BusinessLogics\UserLogic;
use App\Http\Helpers\JWTToken;
use App\Http\Validation\CommonValidation;
use App\Models\Otp;
use App\Models\Session;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function sendOtp(Request $request)
    {
        $validator = CommonValidation::sendOtp($request->all());
        if ($validator->fails()) {
            return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        }
        try {
            $otp = rand(100000, 999999);
            $otpData = [
                'otp' => $otp,
                'type' => $request->type === 'email' ? 0 : 1,
                'created_at' => Carbon::now(),
            ];
            if ($request->type === 'email') {
                $otpData['email'] = $request->email;
            } else {
                $otpData['mobile'] = $request->mobile;
            }
            $isCreated = Otp::create($otpData);
            if ($isCreated) {
                if ((string)$request->type === 'email') {
                    $sendEmail = CommonLogic::sendEmailOtp($request->email, $otp);
                    if (!$sendEmail) {
                        return CommonLogic::jsonResponse("Email OTP sending failed:", 500, null);
                    }
                }
            }
            return CommonLogic::jsonResponse("OTP sent successfully", 200, null);
        } catch (Exception $e) {
            Log::info('Error sendOtp', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function resendOtp(Request $request)
    {
        $validator = CommonValidation::sendOtp($request->all());
        if ($validator->fails()) {
            return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        }

        try {
            $otp = rand(100000, 999999);

            if ($request->type === 'email') {
                $existingOtp = Otp::where('email', $request->email)->delete();

                $otpData = [
                    'otp' => $otp,
                    'email' => $request->email,
                    'type' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
                Otp::create($otpData);

                $sendEmail = CommonLogic::sendEmailOtp($request->email, $otp);
                if (!$sendEmail) {
                    return CommonLogic::jsonResponse("Email OTP sending failed:", 500, null);
                }
            } else {
                $existingOtp = Otp::where('mobile', $request->mobile)->delete();

                $otpData = [
                    'otp' => $otp,
                    'mobile' => $request->mobile,
                    'type' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
                Otp::create($otpData);

                // $sendEmail = CommonLogic::sendEmailOtp($request->email, $otp); // For email, you might want to send an email OTP for mobile as well
                // if (!$sendEmail) {
                //     return CommonLogic::jsonResponse("Mobile OTP sending failed:", 500, null);
                // }
            }

            return CommonLogic::jsonResponse("OTP resent successfully", 200, null);
        } catch (Exception $e) {
            Log::info('Error resendOtp', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
    public function verifyOtp(Request $request)
    {
        $validator = CommonValidation::verifyOtp($request->all());
        if ($validator->fails()) {
            return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        }
        try {

            $identifier = $request->type === 'email' ? 'email' : 'mobile';
            $value = $request->{$identifier};

            $otpRecord = Otp::where($identifier, $value)
                ->where('otp', $request->otp)
                ->first();

            if (!$otpRecord) {
                return CommonLogic::jsonResponse("Invalid OTP", 400, null);
            }
            $userExists = User::where($identifier, $value)->first();
            $userId = $userExists->id ?? null;
            if (!$userExists) {
                $uuid = str_replace('-', '', Str::uuid()->toString());
                User::create([
                    $identifier => $value,
                    'uuid' => $uuid,
                    'email_verified' => $request->type === 'email' ? true : false,
                    'role' => 1,
                ]);
                $getUser = User::where($identifier, $value)->first();
                $userId = $getUser->id ?? null;
            }
            $otpRecord = Otp::where($identifier, $value)
                ->where('otp', $request->otp)
                ->delete();
            $sessionId = DBLogic::getMaxId('sessions');
            $generateToken = JWTToken::generateToken([
                "id" => $userId,
                "sessionId" => $sessionId
            ]);
            Session::insert([
                'id'          => $sessionId,
                'user_id'     => $userId,
                'token'       => $generateToken['token'],
                'expires_at'  => $generateToken['expires'],
                'created_at'  => now(),
            ]);
            return CommonLogic::jsonResponse("OTP verified successfully", 200, [
                "token" => $generateToken['token'],
                "expiresAt" => $generateToken['expires'],
            ]);
        } catch (Exception $e) {
            Log::info('Error verifyOtp', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
    public function getUserSessionDetails(Request $request)
    {
        try {
            $authHeader = $request->header('Authorization');
            if ($authHeader) {
                $token = explode(" ", $authHeader)[1];
            } else {
                $token = null;
            }
            $details = UserLogic::getUserSessionDetails($token);
            if (count($details) > 0) {
                return CommonLogic::jsonResponse("detail retrieved Successfully", 200, $details);
            } else {
                return CommonLogic::jsonResponse("Not Found", 404, null);
            }
        } catch (Exception $e) {
            Log::info('Error getUserSessionDetails', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function userLogout(Request $request)
    {
        $sessionId = $request->jwtSessionId;
        try {
            $isLoggedOut  = UserLogic::logoutUserByToken($sessionId);
            return CommonLogic::jsonResponse(
                $isLoggedOut ? "User logged out successfully" : "Failed to log out user",
                $isLoggedOut ? 200 : 500,
                null
            );
        } catch (Exception $e) {
            Log::info('Error userLogout', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function customerList(Request $request)
    {
        $validator = CommonValidation::pagination($request->all());

        if ($validator->fails()) {
            return CommonLogic::jsonResponse("Validation error", 400, $validator->errors(), true);
        }

        try {
            $productsDetails = UserLogic::getCustomerList([
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
            Log::error('Error in customerList controller', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }

    public function customerCount()
    {
        try {
            $getOrderList = UserLogic::customerCount();
            return CommonLogic::jsonResponse("Success", 200, $getOrderList);
        } catch (Exception $e) {
            Log::info('Error customerCount', [$e]);
            return CommonLogic::jsonResponse("Internal server error", 500, null);
        }
    }
}
