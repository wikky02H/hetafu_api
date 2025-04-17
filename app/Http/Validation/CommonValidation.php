<?php

namespace App\Http\Validation;

use Illuminate\Support\Facades\Validator;

class CommonValidation
{
    static function sendOtp($data)
    {
        return Validator::make($data, [
            'type' => 'required|string|in:email,mobile',
            'email' => 'nullable|email|required_if:type,email',
            'mobile' => 'nullable|regex:/^[0-9]{10}$/|required_if:type,mobile',
        ]);
    }
    static function verifyOtp($data)
    {
        return Validator::make($data, [
            'type' => 'required|in:email,mobile',
            'email' => 'nullable|email|required_if:type,email',
            'mobile' => 'nullable|string|required_if:type,mobile',
            'otp' => 'required|string',
        ]);
    }
    static function pagination($data)
    {
        return Validator::make($data, [
            'pageLimit' => 'required|integer|min:1',
            'currentPage' => 'required|integer|min:1',
        ]);
    }
}
