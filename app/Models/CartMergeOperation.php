<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartMergeOperation extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cart_merge_operations';

    protected $fillable = [
        'user_id',
        'merge_token',
        'product_id',
        'quantity',
        'limited',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'limited' => 'boolean',
        'created_at' => 'datetime',
    ];
}
