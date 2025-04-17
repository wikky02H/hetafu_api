<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'id',
        'uuid',
        'email',
        'mobile',
        'name',
        'email_verified',
        'image',
        'role',
    ];

    public function roleRelation()
    {
        return $this->belongsTo(Role::class, 'role');
    }
}
