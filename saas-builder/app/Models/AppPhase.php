<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppPhase extends Model
{
    protected $fillable = ['saas_app_id', 'name', 'position'];

    public function saasApp(): BelongsTo
    {
        return $this->belongsTo(SaasApp::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(AppStage::class)->orderBy('position');
    }
}
