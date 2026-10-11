<?php

namespace App\Services\Builder;

use App\Models\Constitution;
use App\Models\Law;
use App\Models\SaasApp;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new app pre-filled from the playbook: core constitutions and laws ticked,
 * every ACFCM item ticked, and the journey from the chosen template.
 */
class NewAppFactory
{
    public function __construct(private readonly JourneyOutline $outline)
    {
    }

    public function create(array $basics): SaasApp
    {
        return DB::transaction(function () use ($basics) {
            $app = SaasApp::create($basics + ['current_step' => 'constitutions']);

            $app->constitutions()->sync(Constitution::where('is_core', true)->pluck('id'));
            $app->laws()->sync(Law::where('is_core', true)->pluck('id'));

            foreach (config('playbook.acfcm') as $pillar => $meta) {
                foreach ($meta['items'] as $key => $label) {
                    $app->acfcmChoices()->create(['pillar' => $pillar, 'item_key' => $key, 'label' => $label]);
                }
            }

            $template = config("playbook.templates.{$app->template}") ?? config('playbook.templates.blank');
            $this->outline->save($app, $template['journey']);

            return $app;
        });
    }
}
