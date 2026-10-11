<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Law extends Model
{
    protected $fillable = ['code', 'category', 'title', 'example', 'source', 'is_core', 'checked_by_core'];

    protected function casts(): array
    {
        return ['is_core' => 'boolean', 'checked_by_core' => 'boolean'];
    }
}
