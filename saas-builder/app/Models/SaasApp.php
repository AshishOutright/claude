<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasApp extends Model
{
    protected $fillable = [
        'name', 'slug', 'summary', 'template', 'core_vision', 'team_vision',
        'vision_before', 'vision_after', 'current_step', 'generated_at',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function constitutions(): BelongsToMany
    {
        return $this->belongsToMany(Constitution::class, 'saas_app_constitution')->orderBy('code');
    }

    public function laws(): BelongsToMany
    {
        return $this->belongsToMany(Law::class, 'saas_app_law')->orderBy('code');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(AppPhase::class)->orderBy('position');
    }

    public function acfcmChoices(): HasMany
    {
        return $this->hasMany(AcfcmChoice::class);
    }

    public function hasAcfcm(string $itemKey): bool
    {
        return $this->acfcmChoices->contains('item_key', $itemKey);
    }
}
