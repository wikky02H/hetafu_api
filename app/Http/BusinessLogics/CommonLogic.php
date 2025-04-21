<?php

namespace App\Http\BusinessLogics;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CommonLogic
{
    public static function jsonResponse($message, $statusCode, $data, $validation = false, $pagination = false)
    {
        if ($validation && $pagination === false) {
            return response()->json([
                'message' => $message,
                'errors' => $data,
            ], $statusCode);
        } else if ($pagination === false && $data != null || $data === [] || $data === false && !$validation) {
            return response()->json([
                'message' => $message,
                'result' => $data,
            ], $statusCode);
        } else if ($pagination === true && $data !== null) {
            return response()->json([
                'message' => $message,
                'result' => $data['data'],
                'paginationOptions' => $data['paginationOptions']
            ], $statusCode);
        } else {
            return response()->json([
                'message' => $message,
                'result' => isset($data) ? $data : null,
            ], $statusCode);
        }
    }
    public static function sendEmailOtp(string $email, string $otp): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('RESEND_API_KEY'),
            ])->withOptions([
                'verify' => false,
            ])->post('https://api.resend.com/emails', [
                'from' => 'customercare@hetafu.com',
                'to' => [$email],
                'subject' => 'Your OTP for Email Verification - HETAFU',
                'html' => '
                    <body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">
                        <div style="max-width: 500px; margin: auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05);">
                            <h2 style="color: #333333;">Email Verification</h2>
                            <p style="font-size: 16px; color: #555555;">Dear Customer,</p>
                            <p style="font-size: 16px; color: #555555;">Your One-Time Password (OTP) to verify your email is:</p>
                            <p style="font-size: 24px; font-weight: bold; color: #007BFF; margin: 20px 0;">' . $otp . '</p>
                            <p style="font-size: 14px; color: #777777;">This OTP is valid for 15 minutes. Please do not share it with anyone.</p>
                            <p style="font-size: 14px; color: #777777;">If you did not request this, please ignore this email.</p>
                            <hr style="margin: 30px 0;">
                            <p style="font-size: 12px; color: #999999;">- Team HETAFU</p>
                        </div>
                    </body>
                ',
            ]);
            Log::info('response', [$response]);
            return true;
        } catch (Exception $e) {
            Log::error("Email OTP sending failed: " . $e->getMessage());
            return false;
        }
    }

    public static function sendMobileOtp(int $mobile, string $otp): bool
    {
        try {
            $response = Http::post('http://bulkpush.digimate/BULK_API/InstantBulkJsonPushV2', [
                "keyword" => "HETAFU",
                "timeStamp" => (string)time(),
                "dataSet" => array([
                    "MESSAGE" => "Hetafu sms test $otp",
                    "OA" => "", // "AIRTEL"
                    "MSISDN" => (string)$mobile,
                    "CHANNEL" => "SMS",
                    "CAMPAIGN_NAME" => "HETAFU_OTP_VERIFICATION",
                    "CIRCLE_NAME" => "DLT_TEST",
                    "USER_NAME" => "Hetafu",
                    "DLT_TM_ID" => env("AIRTEL_TEMPLATE_ID"),
                    "DLT_CT_ID" => "", // env("AIRTEL_TEMPLATE_ID"),
                    "DLT_PE_ID" => env("AIRTEL_PE_ID"),
                ]),
            ]);
            Log::info("response", [$response]);
            return true;
        } catch (Exception $e) {
            Log::error("Mobile OTP sending failed: " . $e->getMessage());
            return false;
        }
    }
}
