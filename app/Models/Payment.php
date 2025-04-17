<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'payment_status_id',
        'amount',
        'payment_method',
        'transaction_id',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentStatus()
    {
        return $this->belongsTo(OrderStatus::class, 'payment_status_id');
    }
}
