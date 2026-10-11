<?php

namespace App\Models;

use App\Services\Builder\Naming;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppModule extends Model
{
    protected $fillable = ['saas_app_id', 'name', 'is_journey', 'position'];

    protected function casts(): array
    {
        return ['is_journey' => 'boolean'];
    }

    public function saasApp(): BelongsTo
    {
        return $this->belongsTo(SaasApp::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(AppModuleField::class)->orderBy('position');
    }

    /** Name used in Smart Gateway requests, e.g. "candidates". */
    public function apiName(): string
    {
        return Naming::key($this->name);
    }

    /** SuiteCRM module key, e.g. "hrcrm_Candidates". */
    public function crmKey(string $prefix): string
    {
        return $prefix.'_'.Naming::className($this->name);
    }
}
