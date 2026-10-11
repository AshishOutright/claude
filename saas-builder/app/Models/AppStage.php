<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppStage extends Model
{
    protected $fillable = ['app_phase_id', 'name', 'position'];

    public function phase(): BelongsTo
    {
        return $this->belongsTo(AppPhase::class, 'app_phase_id');
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(AppStatus::class)->orderBy('position');
    }
}
