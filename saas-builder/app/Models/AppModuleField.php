<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppModuleField extends Model
{
    protected $fillable = ['app_module_id', 'name', 'label', 'type', 'type_arg', 'is_required', 'position'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AppModule::class, 'app_module_id');
    }

    /** @return list<string> */
    public function enumOptions(): array
    {
        return $this->type === 'enum' ? array_values(array_filter(array_map('trim', explode('|', (string) $this->type_arg)), 'strlen')) : [];
    }
}
