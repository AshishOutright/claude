<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Constitution extends Model
{
    protected $fillable = ['code', 'title', 'reason', 'example', 'source', 'is_core'];

    protected function casts(): array
    {
        return ['is_core' => 'boolean'];
    }
}
