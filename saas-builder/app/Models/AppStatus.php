<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppStatus extends Model
{
    protected $fillable = ['app_stage_id', 'name', 'is_final', 'position'];

    protected function casts(): array
    {
        return ['is_final' => 'boolean'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(AppStage::class, 'app_stage_id');
    }
}
