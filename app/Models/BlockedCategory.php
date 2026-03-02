<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedCategory extends Model
{
    protected $fillable = ['source_category_id'];

    /** Global list of source category IDs that are blocked (hidden in all jobs). */
    public static function blockedCategoryIds(): array
    {
        return static::pluck('source_category_id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
