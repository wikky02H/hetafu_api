<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Session extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'user_id',
        'token',
        'expires_at',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
