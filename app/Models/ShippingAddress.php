<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'company_name',
        'address',
        'country',
        'state',
        'city',
        'zip_code',
        'email',
        'phone_number',
        'is_default',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
