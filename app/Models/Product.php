<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'id',
        'category_id',
        'name',
        'description',
        'price',
        'stock',
        'image_url',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
