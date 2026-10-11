<?php

namespace App\Http\Controllers;

use App\Models\Constitution;
use App\Models\Law;
use App\Models\SaasApp;
use App\Services\Builder\JourneyOutline;
use App\Services\Builder\StarterAppGenerator;
use App\Services\Builder\WizardSteps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class WizardController extends Controller
{
    public function show(SaasApp $app, string $step, JourneyOutline $outline, StarterAppGenerator $generator): View
    {
        abort_unless(WizardSteps::exists($step), 404);
        $app->load(['constitutions', 'laws', 'acfcmChoices', 'phases.stages.statuses']);

        return view("wizard.{$step}", [
            'app' => $app,
            'step' => $step,
            'constitutions' => Constitution::orderBy('code')->get(),
            'laws' => Law::orderBy('category')->orderBy('code')->get()->groupBy('category'),
            'categories' => config('playbook.law_categories'),
            'acfcm' => config('playbook.acfcm'),
            'templates' => config('playbook.templates'),
            'outline' => old('outline', $outline->format($app)),
            'files' => $step === 'review' ? $generator->listFiles($app) : collect(),
        ]);
    }

    public function update(Request $request, SaasApp $app, string $step, JourneyOutline $outline): RedirectResponse
    {
        abort_unless(WizardSteps::exists($step) && $step !== 'review', 404);

        match ($step) {
            'basics' => $this->saveBasics($request, $app),
            'constitutions' => $this->saveConstitutions($request, $app),
            'laws' => $this->saveLaws($request, $app),
            'vision' => $this->saveVision($request, $app),
            'journey' => $this->saveJourney($request, $app, $outline),
            'acfcm' => $this->saveAcfcm($request, $app),
        };

        $next = WizardSteps::next($step);
        $app->update(['current_step' => $next]);

        return redirect()->route('wizard.show', [$app, $next]);
    }

    private function saveBasics(Request $request, SaasApp $app): void
    {
        $app->update($request->validate([
            'name' => 'required|string|max:80',
            'summary' => 'nullable|string|max:255',
        ]));
    }

    private function saveConstitutions(Request $request, SaasApp $app): void
    {
        $data = $request->validate([
            'constitutions' => 'array',
            'constitutions.*' => 'integer|exists:constitutions,id',
            'new_title' => 'nullable|string|max:255',
            'new_reason' => 'nullable|string|max:500',
        ]);

        $ids = $data['constitutions'] ?? [];

        if (filled($data['new_title'] ?? null)) {
            $ids[] = Constitution::create([
                'code' => $this->nextCode(Constitution::class, 'S-'),
                'title' => $data['new_title'],
                'reason' => $data['new_reason'] ?? null,
                'source' => "Added while building {$app->name}",
            ])->id;
        }

        $app->constitutions()->sync($ids);
    }

    private function saveLaws(Request $request, SaasApp $app): void
    {
        $data = $request->validate([
            'laws' => 'array',
            'laws.*' => 'integer|exists:laws,id',
            'new_title' => 'nullable|string|max:255',
            'new_category' => ['nullable', Rule::in(array_keys(config('playbook.law_categories')))],
            'new_example' => 'nullable|string|max:500',
        ]);

        $ids = $data['laws'] ?? [];

        if (filled($data['new_title'] ?? null)) {
            $category = $data['new_category'] ?? 'code';
            $ids[] = Law::create([
                'code' => $this->nextCode(Law::class, 'L-'.strtoupper($category).'-'),
                'category' => $category,
                'title' => $data['new_title'],
                'example' => $data['new_example'] ?? null,
                'source' => "Added while building {$app->name}",
            ])->id;
        }

        $app->laws()->sync($ids);
    }

    private function saveVision(Request $request, SaasApp $app): void
    {
        $app->update($request->validate([
            'core_vision' => 'required|string|max:5000',
            'team_vision' => 'required|string|max:2000',
            'vision_before' => 'nullable|string|max:2000',
            'vision_after' => 'nullable|string|max:2000',
        ]));
    }

    private function saveJourney(Request $request, SaasApp $app, JourneyOutline $outline): void
    {
        $request->validate(['outline' => 'required|string|max:20000']);

        try {
            $outline->save($app, $request->input('outline'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['outline' => explode("\n", $e->getMessage())]);
        }
    }

    private function saveAcfcm(Request $request, SaasApp $app): void
    {
        $pillars = config('playbook.acfcm');
        $data = $request->validate([
            'items' => 'array',
            'items.*' => 'array',
            'custom' => 'array',
            'custom.*' => 'nullable|string|max:255',
        ]);

        $app->acfcmChoices()->delete();

        foreach ($pillars as $pillar => $meta) {
            foreach ($data['items'][$pillar] ?? [] as $key) {
                if (isset($meta['items'][$key])) {
                    $app->acfcmChoices()->create(['pillar' => $pillar, 'item_key' => $key, 'label' => $meta['items'][$key]]);
                }
            }

            foreach (preg_split('/\R/', (string) ($data['custom'][$pillar] ?? '')) as $line) {
                if (trim($line) !== '') {
                    $app->acfcmChoices()->create(['pillar' => $pillar, 'item_key' => null, 'label' => trim($line)]);
                }
            }
        }
    }

    /** @param class-string<Constitution|Law> $model */
    private function nextCode(string $model, string $prefix): string
    {
        $highest = $model::where('code', 'like', $prefix.'%')->pluck('code')
            ->map(fn ($code) => (int) substr($code, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
    }
}
