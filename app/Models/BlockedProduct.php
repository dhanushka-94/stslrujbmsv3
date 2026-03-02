<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedProduct extends Model
{
    protected $fillable = ['source_product_id'];

    /** Global list of source product IDs that are blocked (hidden in all jobs). */
    public static function blockedProductIds(): array
    {
        return static::pluck('source_product_id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
