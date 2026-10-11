<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcfcmChoice extends Model
{
    protected $fillable = ['saas_app_id', 'pillar', 'item_key', 'label'];

    public function saasApp(): BelongsTo
    {
        return $this->belongsTo(SaasApp::class);
    }
}
