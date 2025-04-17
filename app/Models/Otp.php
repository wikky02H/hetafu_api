<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'email',
        'mobile',
        'type',
        'otp',
        'created_at',
    ];
    protected $casts = [
        'type' => 'boolean',
        'created_at' => 'datetime',
    ];
}
