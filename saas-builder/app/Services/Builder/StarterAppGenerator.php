<?php

namespace App\Services\Builder;

use App\Models\SaasApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Builds the starter app for a finished wizard: an overlay for a fresh Laravel install that
 * runs on the Smart Core package, plus the docs and CLAUDE.md agents build against.
 */
class StarterAppGenerator
{
    /** @return array<string, string> relative path => file contents */
    public function files(SaasApp $app): array
    {
        $app->loadMissing(['constitutions', 'laws', 'phases.stages.statuses', 'acfcmChoices']);

        $files = [
            'install.sh' => $this->installScript($app),
            'app-files/CLAUDE.md' => $this->claudeMd($app),
            'app-files/docs/CONSTITUTION.md' => $this->constitutionMd($app),
            'app-files/docs/LAWS.md' => $this->lawsMd($app),
            'app-files/docs/VISION.md' => $this->visionMd($app),
            'app-files/docs/JOURNEY.md' => $this->journeyMd($app),
            'app-files/config/workflow.php' => $this->workflowConfig($app),
            'app-files/config/laws.php' => $this->lawsConfig($app),
            'app-files/config/acfcm.php' => $this->acfcmConfig($app),
            'app-files/app/Workflow/SelectPhase.php' => $this->selectorClass('SelectPhase', 'select_phase: which phase a record is in and which comes next.'),
            'app-files/app/Workflow/SelectStage.php' => $this->selectorClass('SelectStage', 'select_stage: finds stages, moves records forward and guards status changes.'),
            'app-files/database/migrations/2026_01_01_000000_create_journey_entries_table.php' => $this->journeyMigration(),
            'app-files/app/Models/JourneyEntry.php' => $this->journeyModel(),
            'app-files/app/Services/Journey.php' => $this->journeyService($app),
            'app-files/app/Http/Controllers/JourneyController.php' => $this->journeyController(),
            'app-files/resources/views/journey/index.blade.php' => $this->journeyView($app),
            'app-files/routes/web.php' => $this->routes(),
            'app-files/tests/Feature/LawsTest.php' => $this->lawsTest(),
            'app-files/tests/Feature/JourneyTest.php' => $this->journeyTest($app),
            'app-files/.github/workflows/laws.yml' => $this->ciWorkflow(),
        ];

        foreach ($app->phases as $phase) {
            $files['app-files/app/Workflow/Phases/'.Naming::className($phase->name).'Phase.php'] = $this->phaseClass($phase);

            foreach ($phase->stages as $stage) {
                $path = 'app-files/app/Workflow/Stages/'.Naming::className($phase->name).'/'.Naming::className($stage->name).'Stage.php';
                $files[$path] = $this->stageClass($phase, $stage);
            }
        }

        if ($app->hasAcfcm('journey_events')) {
            $files['app-files/app/Events/JourneyMoved.php'] = $this->journeyEvent();
        }

        if ($app->hasAcfcm('feature_toggles') || $app->hasAcfcm('plan_limits')) {
            $files['app-files/app/Support/Features.php'] = $this->featuresClass();
        }

        if ($app->hasAcfcm('aisuites_client')) {
            $files['app-files/config/aisuites.php'] = $this->aisuitesConfig();
            $files['app-files/app/Services/Ai/AiSuites.php'] = $this->aisuitesClient($app);
        }

        if ($app->hasAcfcm('ai_feedback_log')) {
            $files['app-files/database/migrations/2026_01_01_000001_create_ai_feedback_table.php'] = $this->aiFeedbackMigration();
        }

        $files['README.md'] = $this->readme($app, count($files) - 1);
        ksort($files);

        return $files;
    }

    /**
     * Writes the starter app to storage and zips it. Returns the zip path.
     */
    public function build(SaasApp $app): string
    {
        $root = storage_path('app/generated/'.$app->slug.'-starter');
        File::deleteDirectory($root);

        foreach ($this->files($app) as $path => $contents) {
            File::ensureDirectoryExists(dirname($root.'/'.$path));
            File::put($root.'/'.$path, $contents);
        }

        chmod($root.'/install.sh', 0755);

        $zipPath = storage_path('app/generated/'.$app->slug.'-starter.zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create {$zipPath}.");
        }

        foreach (File::allFiles($root, true) as $file) {
            $name = $app->slug.'-starter/'.$file->getRelativePathname();
            $zip->addFile($file->getPathname(), $name);

            if ($file->getFilename() === 'install.sh') {
                $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100755 << 16);
            }
        }

        $zip->close();
        $app->forceFill(['generated_at' => now()])->save();

        return $zipPath;
    }

    // ---- Docs ------------------------------------------------------------

    private function readme(SaasApp $app, int $files): string
    {
        $summary = $app->summary ? "\n{$app->summary}\n" : '';

        return <<<MD
# {$app->name} starter
{$summary}
Generated by the SaaS Builder on {$this->today()}. It holds {$files} files that sit on top of a fresh Laravel app and
the Smart Core package, so the journey, laws and ACFCM settings below are already in code on day one.

## Install

Needs PHP 8.3, Composer and a checkout of the Smart Core (`smart-core/` in the AshishOutright/claude repo).

```bash
./install.sh {$app->slug} /path/to/claude/smart-core
cd {$app->slug}
php artisan migrate
php artisan laws:check
php artisan test
```

`install.sh` creates the Laravel app, copies `app-files/` over it and installs Smart Core. Set your MySQL details in
`.env` before `migrate`.

## What is inside

- `CLAUDE.md`: the brief every Claude Code agent reads before building (vision, constitution, laws, journey, ACFCM).
- `docs/`: the constitution, laws, vision and journey for the team.
- `app/Workflow/`: one class per phase and per stage, plus `SelectPhase` and `SelectStage`.
- `config/workflow.php`, `config/laws.php`, `config/acfcm.php`: the journey, the laws `laws:check` enforces, and the ACFCM switches.
- `/journey`: a starter screen listing journey entries, built with the core list view.
- `.github/workflows/laws.yml`: CI that fails a pull request when a law breaks.

MD;
    }

    private function claudeMd(SaasApp $app): string
    {
        $constitution = $app->constitutions->map(fn ($c) => "- **{$c->code} {$c->title}.** {$c->reason}")->implode("\n");
        $laws = $app->laws->map(fn ($l) => "- **{$l->code}** {$l->title}".($l->checked_by_core ? ' _(checked by `laws:check`)_' : ''))->implode("\n");
        $acfcm = $this->acfcmLines($app);

        return <<<MD
# {$app->name}: brief for agents

Read this before writing code. It is generated from the SaaS Builder; change it there and regenerate, or edit it
here and keep `docs/` in sync.

## Vision for the team

{$this->orDash($app->team_vision)}

Full Core Vision: `docs/VISION.md`.

## Constitution

{$constitution}

## Laws

{$laws}

Run `php artisan laws:check` before every commit. A failing law blocks the merge in CI.

## Journey (phase, stage, status)

{$this->journeyBullets($app)}

The journey lives in `config/workflow.php` and `app/Workflow/`. Never hard-code a phase, stage or status string in
a controller: go through `App\Workflow\SelectPhase`, `App\Workflow\SelectStage` and `App\Services\Journey`.

## ACFCM

{$acfcm}

## How to build here

- PHP 8.3, Laravel, MySQL. All code in classes and methods; routes point at controllers.
- Extend the Smart Core (`outright/smart-core`) instead of copying what it already does.
- List screens use `<x-smartcore::list-view>`. Models that depend on another record use `SmartCore\Laws\RequiresDependencies`.
- Security is middleware in the app. `public/.htaccess` only routes requests.

MD;
    }

    private function constitutionMd(SaasApp $app): string
    {
        $rows = $app->constitutions->map(fn ($c) => "| {$c->code} | {$c->title} | {$this->cell($c->reason)} | {$this->cell($c->example)} |")->implode("\n");

        return "# {$app->name} constitution\n\nThe boundaries and high-level expectations of the app.\n\n| Code | Constitution | Reason | Example |\n|---|---|---|---|\n{$rows}\n";
    }

    private function lawsMd(SaasApp $app): string
    {
        $categories = config('playbook.law_categories');
        $rows = $app->laws->map(fn ($l) => "| {$l->code} | ".($categories[$l->category] ?? $l->category)." | {$l->title} | {$this->cell($l->example)} | ".($l->checked_by_core ? 'Automatic' : 'Review').' |')->implode("\n");

        return "# {$app->name} laws\n\nUI and development boundaries. \"Automatic\" laws are checked by `php artisan laws:check` in CI; the rest are checked in review.\n\n| Code | Area | Law | Example | Checked |\n|---|---|---|---|---|\n{$rows}\n";
    }

    private function visionMd(SaasApp $app): string
    {
        return <<<MD
# {$app->name} vision

## Core Vision

{$this->orDash($app->core_vision)}

## Simplified for the team

{$this->orDash($app->team_vision)}

## Before

{$this->orDash($app->vision_before)}

## After

{$this->orDash($app->vision_after)}

MD;
    }

    private function journeyMd(SaasApp $app): string
    {
        return "# {$app->name} journey\n\nEvery record moves through these phases, stages and statuses. Final statuses are in bold.\n\n".$this->journeyBullets($app)."\n";
    }

    private function journeyBullets(SaasApp $app): string
    {
        $lines = [];

        foreach ($app->phases as $phase) {
            $lines[] = "- **{$phase->name}**";

            foreach ($phase->stages as $stage) {
                $statuses = $stage->statuses->map(fn ($s) => $s->is_final ? "**{$s->name}**" : $s->name)->implode(', ');
                $lines[] = "  - {$stage->name}: {$statuses}";
            }
        }

        return implode("\n", $lines);
    }

    private function acfcmLines(SaasApp $app): string
    {
        $lines = [];

        foreach (config('playbook.acfcm') as $pillar => $meta) {
            $chosen = $app->acfcmChoices->where('pillar', $pillar)->pluck('label');
            $lines[] = "- **{$meta['label']}:** ".($chosen->isEmpty() ? '_nothing chosen yet_' : $chosen->implode('; '));
        }

        return implode("\n", $lines);
    }

    // ---- Config ----------------------------------------------------------

    private function workflowConfig(SaasApp $app): string
    {
        $classes = $app->phases->map(fn ($p) => '        App\\Workflow\\Phases\\'.Naming::className($p->name).'Phase::class,')->implode("\n");

        return <<<PHP
<?php

// The journey, in order. Each phase class lists its stages; each stage class lists its statuses.
return [

    'phases' => [
{$classes}
    ],

];

PHP;
    }

    private function lawsConfig(SaasApp $app): string
    {
        $rows = $app->laws->map(fn ($l) => '        '.var_export($l->code, true).' => '.var_export($l->title, true).',')->implode("\n");

        return <<<PHP
<?php

// Laws this app is built against. `php artisan laws:check` runs the core's check for each code it knows
// and lists the rest for review.
return [

    'enabled' => [
{$rows}
    ],

];

PHP;
    }

    private function acfcmConfig(SaasApp $app): string
    {
        $out = "<?php\n\n// ACFCM switches chosen in the SaaS Builder. Change behaviour here, not in code.\nreturn [\n\n";

        foreach (config('playbook.acfcm') as $pillar => $meta) {
            $out .= "    // {$meta['label']}: {$meta['help']}\n    '{$pillar}' => [\n";

            foreach ($meta['items'] as $key => $label) {
                $out .= "        '{$key}' => ".($app->hasAcfcm($key) ? 'true' : 'false').",\n";
            }

            foreach ($app->acfcmChoices->where('pillar', $pillar)->whereNull('item_key') as $custom) {
                $out .= '        '.var_export(Naming::key($custom->label), true)." => true, // {$this->comment($custom->label)}\n";
            }

            if ($pillar === 'controlling' && ($app->hasAcfcm('feature_toggles') || $app->hasAcfcm('plan_limits'))) {
                $out .= "\n        // Per plan. A user or team setting overrides its plan.\n        'plans' => [\n";
                $out .= "            'free' => ['features' => ['ai' => false], 'limits' => ['records' => 100, 'users' => 1, 'ai_credits' => 0]],\n";
                $out .= "            'pro' => ['features' => ['ai' => true], 'limits' => ['records' => 10000, 'users' => 10, 'ai_credits' => 1000]],\n";
                $out .= "        ],\n";
            }

            $out .= "    ],\n\n";
        }

        return $out."];\n";
    }

    private function aisuitesConfig(): string
    {
        return <<<'PHP'
<?php

// aisuites.ai is the central LLM platform (law L-AI-001). Every AI call goes through App\Services\Ai\AiSuites.
return [

    'url' => env('AISUITES_URL', 'https://aisuites.ai/api'),

    'key' => env('AISUITES_KEY'),

    'timeout' => (int) env('AISUITES_TIMEOUT', 30),

];

PHP;
    }

    // ---- Workflow classes ------------------------------------------------

    private function phaseClass($phase): string
    {
        $class = Naming::className($phase->name).'Phase';
        $folder = Naming::className($phase->name);
        $key = Naming::key($phase->name);
        $label = var_export($phase->name, true);
        $stages = $phase->stages->map(fn ($s) => "            \\App\\Workflow\\Stages\\{$folder}\\".Naming::className($s->name).'Stage::class,')->implode("\n");

        return <<<PHP
<?php

namespace App\Workflow\Phases;

use SmartCore\Workflow\Phase;

class {$class} extends Phase
{
    public function key(): string
    {
        return '{$key}';
    }

    public function label(): string
    {
        return {$label};
    }

    public function stageClasses(): array
    {
        return [
{$stages}
        ];
    }
}

PHP;
    }

    private function stageClass($phase, $stage): string
    {
        $folder = Naming::className($phase->name);
        $class = Naming::className($stage->name).'Stage';
        $key = Naming::key($stage->name);
        $label = var_export($stage->name, true);
        $statuses = $stage->statuses->map(fn ($s) => '            new Status('.var_export(Naming::key($s->name), true).', '.var_export($s->name, true).($s->is_final ? ', final: true' : '').'),')->implode("\n");

        return <<<PHP
<?php

namespace App\Workflow\Stages\\{$folder};

use SmartCore\Workflow\Stage;
use SmartCore\Workflow\Status;

class {$class} extends Stage
{
    public function key(): string
    {
        return '{$key}';
    }

    public function label(): string
    {
        return {$label};
    }

    public function statuses(): array
    {
        return [
{$statuses}
        ];
    }
}

PHP;
    }

    private function selectorClass(string $class, string $doc): string
    {
        return <<<PHP
<?php

namespace App\Workflow;

use SmartCore\Workflow\\{$class} as CoreSelector;

/**
 * {$doc}
 * Skeleton class: app-specific rules go here; the shared logic stays in Smart Core.
 */
class {$class} extends CoreSelector
{
}

PHP;
    }

    // ---- Journey starter module -----------------------------------------

    private function journeyMigration(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where each record is in the journey. One row per record; history goes in journey_moves.
        Schema::create('journey_entries', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('phase', 60);
            $table->string('stage', 60);
            $table->string('status', 60);
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id']);
        });

        Schema::create('journey_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_entry_id')->constrained()->cascadeOnDelete();
            $table->string('from_phase', 60)->nullable();
            $table->string('from_stage', 60)->nullable();
            $table->string('from_status', 60)->nullable();
            $table->string('to_phase', 60);
            $table->string('to_stage', 60);
            $table->string('to_status', 60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_moves');
        Schema::dropIfExists('journey_entries');
    }
};

PHP;
    }

    private function journeyModel(): string
    {
        return <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JourneyEntry extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'phase', 'stage', 'status'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

PHP;
    }

    private function journeyService(SaasApp $app): string
    {
        $events = $app->hasAcfcm('journey_events');
        $useEvent = $events ? "use App\\Events\\JourneyMoved;\n" : '';
        $fire = $events ? "\n            JourneyMoved::dispatch(\$entry, \$from);\n" : '';

        return <<<PHP
<?php

namespace App\Services;

{$useEvent}use App\Models\JourneyEntry;
use App\Workflow\SelectPhase;
use App\Workflow\SelectStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Starts records on the journey and moves them through statuses, stages and phases.
 */
class Journey
{
    public function __construct(private readonly SelectPhase \$phases, private readonly SelectStage \$stages)
    {
    }

    public function start(Model \$subject): JourneyEntry
    {
        \$phase = \$this->phases->first();
        \$stage = \$phase->firstStage();

        return \$this->record(new JourneyEntry([
            'subject_type' => \$subject->getMorphClass(),
            'subject_id' => \$subject->getKey(),
        ]), \$phase->key(), \$stage->key(), \$stage->initialStatus()->key);
    }

    public function setStatus(JourneyEntry \$entry, string \$status): JourneyEntry
    {
        \$this->stages->assertCanMove(\$entry->phase, \$entry->stage, \$entry->status, \$status);

        return \$this->record(\$entry, \$entry->phase, \$entry->stage, \$status);
    }

    /**
     * Moves to the next stage (or the next phase's first stage). The current status must be final.
     */
    public function advance(JourneyEntry \$entry): JourneyEntry
    {
        if (! \$this->stages->find(\$entry->phase, \$entry->stage)->status(\$entry->status)->final) {
            throw new RuntimeException('Finish this stage (reach a final status) before moving on.');
        }

        \$next = \$this->stages->next(\$entry->phase, \$entry->stage);

        if (\$next === null) {
            throw new RuntimeException('This record has finished the journey.');
        }

        [\$phase, \$stage] = \$next;

        return \$this->record(\$entry, \$phase->key(), \$stage->key(), \$stage->initialStatus()->key);
    }

    private function record(JourneyEntry \$entry, string \$phase, string \$stage, string \$status): JourneyEntry
    {
        return DB::transaction(function () use (\$entry, \$phase, \$stage, \$status) {
            \$from = \$entry->exists ? \$entry->only(['phase', 'stage', 'status']) : null;
            \$entry->fill(compact('phase', 'stage', 'status'))->save();

            DB::table('journey_moves')->insert([
                'journey_entry_id' => \$entry->id,
                'from_phase' => \$from['phase'] ?? null,
                'from_stage' => \$from['stage'] ?? null,
                'from_status' => \$from['status'] ?? null,
                'to_phase' => \$phase,
                'to_stage' => \$stage,
                'to_status' => \$status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
{$fire}
            return \$entry;
        });
    }
}

PHP;
    }

    private function journeyEvent(): string
    {
        return <<<'PHP'
<?php

namespace App\Events;

use App\Models\JourneyEntry;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired on every journey move, so notifications and jobs react without manual steps (ACFCM: Automation).
 */
class JourneyMoved
{
    use Dispatchable;

    /** @param array{phase: string, stage: string, status: string}|null $from */
    public function __construct(public readonly JourneyEntry $entry, public readonly ?array $from)
    {
    }
}

PHP;
    }

    private function journeyController(): string
    {
        return <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Models\JourneyEntry;
use App\Workflow\SelectPhase;
use Illuminate\View\View;

class JourneyController extends Controller
{
    public function index(SelectPhase $phases): View
    {
        return view('journey.index', [
            'phases' => $phases->all(),
            'entries' => JourneyEntry::latest()->limit(100)->get(),
        ]);
    }
}

PHP;
    }

    private function journeyView(SaasApp $app): string
    {
        $name = e($app->name);

        return <<<BLADE
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$name} journey</title>
</head>
<body>
    <h1>{$name} journey</h1>

    @foreach (\$phases as \$phase)
        <h2>{{ \$phase->label() }}</h2>
        <ul>
            @foreach (\$phase->stages() as \$stage)
                <li>{{ \$stage->label() }}: {{ collect(\$stage->statuses())->pluck('label')->implode(', ') }}</li>
            @endforeach
        </ul>
    @endforeach

    <h2>Latest entries</h2>
    <x-smartcore::list-view :rows="\$entries" :columns="['subject_type' => 'Record', 'subject_id' => 'ID', 'phase' => 'Phase', 'stage' => 'Stage', 'status' => 'Status']" />
</body>
</html>

BLADE;
    }

    private function routes(): string
    {
        return <<<'PHP'
<?php

use App\Http\Controllers\JourneyController;
use Illuminate\Support\Facades\Route;

// Law L-CODE-002: every route points at a controller method.
Route::redirect('/', '/journey');
Route::get('/journey', [JourneyController::class, 'index'])->name('journey.index');

PHP;
    }

    private function featuresClass(): string
    {
        return <<<'PHP'
<?php

namespace App\Support;

/**
 * Feature and limit controller (ACFCM: Controlling features). Reads config/acfcm.php plans.
 * Wire $overrides from the user's or team's settings when the core's billing module lands.
 */
class Features
{
    public static function enabled(string $feature, string $plan = 'free', array $overrides = []): bool
    {
        return (bool) ($overrides['features'][$feature] ?? config("acfcm.controlling.plans.{$plan}.features.{$feature}", false));
    }

    public static function limit(string $limit, string $plan = 'free', array $overrides = []): int
    {
        return (int) ($overrides['limits'][$limit] ?? config("acfcm.controlling.plans.{$plan}.limits.{$limit}", 0));
    }
}

PHP;
    }

    private function aisuitesClient(SaasApp $app): string
    {
        $logs = $app->hasAcfcm('ai_feedback_log');
        $logMethod = $logs ? <<<'PHP'

    /**
     * Log an AI output a user edited, so it becomes eval data (ACFCM: Machine learning).
     */
    public function logEdit(string $feature, string $prompt, string $aiOutput, string $userOutput): void
    {
        DB::table('ai_feedback')->insert([
            'feature' => $feature,
            'prompt' => $prompt,
            'ai_output' => $aiOutput,
            'user_output' => $userOutput,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
PHP : '';
        $useDb = $logs ? "use Illuminate\\Support\\Facades\\DB;\n" : '';

        return <<<PHP
<?php

namespace App\Services\Ai;

{$useDb}use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The only door to AI in this app (law L-AI-001). Adjust the request shape to the aisuites.ai API you use.
 */
class AiSuites
{
    public function complete(string \$feature, string \$prompt): string
    {
        if (! config('aisuites.key')) {
            throw new RuntimeException('Set AISUITES_KEY in .env to use AI features.');
        }

        \$response = Http::withToken(config('aisuites.key'))
            ->timeout(config('aisuites.timeout'))
            ->post(rtrim(config('aisuites.url'), '/').'/complete', ['feature' => \$feature, 'prompt' => \$prompt])
            ->throw();

        return (string) \$response->json('text');
    }
{$logMethod}
}

PHP;
    }

    private function aiFeedbackMigration(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 60);
            $table->text('prompt');
            $table->text('ai_output');
            $table->text('user_output');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
    }
};

PHP;
    }

    // ---- Tests and CI ----------------------------------------------------

    private function lawsTest(): string
    {
        return <<<'PHP'
<?php

namespace Tests\Feature;

use Tests\TestCase;

class LawsTest extends TestCase
{
    public function test_the_app_keeps_its_laws(): void
    {
        $this->artisan('laws:check')->assertSuccessful();
    }
}

PHP;
    }

    private function journeyTest(SaasApp $app): string
    {
        $phases = $app->phases->count();
        $first = $app->phases->first();
        $firstStage = $first?->stages->first();
        $firstPhaseKey = var_export(Naming::key($first?->name ?? ''), true);
        $firstStageKey = var_export(Naming::key($firstStage?->name ?? ''), true);
        $firstStatusKey = var_export(Naming::key($firstStage?->statuses->first()?->name ?? ''), true);

        return <<<PHP
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Journey;
use App\Workflow\SelectPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_journey_has_every_phase(): void
    {
        \$this->assertCount({$phases}, app(SelectPhase::class)->all());
    }

    public function test_a_record_starts_at_the_first_stage(): void
    {
        \$entry = app(Journey::class)->start(User::factory()->create());

        \$this->assertSame([{$firstPhaseKey}, {$firstStageKey}, {$firstStatusKey}], [\$entry->phase, \$entry->stage, \$entry->status]);
    }

    public function test_the_journey_screen_loads(): void
    {
        \$this->get('/journey')->assertOk();
    }
}

PHP;
    }

    private function ciWorkflow(): string
    {
        return <<<'YAML'
# Laws in CI: every pull request runs laws:check and the tests. A broken law blocks the merge.
# Smart Core is read from the AshishOutright/claude repo; add a CORE_REPO_TOKEN secret that can read it.
name: Laws

on:
  pull_request:
  push:
    branches: [main]

jobs:
  laws:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/checkout@v4
        with:
          repository: AshishOutright/claude
          token: ${{ secrets.CORE_REPO_TOKEN }}
          path: .smart-core-src

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install
        run: |
          composer config repositories.smart-core path .smart-core-src/smart-core
          composer install --no-interaction --prefer-dist
          cp .env.example .env
          php artisan key:generate

      - name: Laws
        run: php artisan laws:check

      - name: Tests
        run: php artisan test

YAML;
    }

    private function installScript(SaasApp $app): string
    {
        return <<<BASH
#!/usr/bin/env bash
# Creates {$app->name} on a fresh Laravel app with Smart Core installed.
# Usage: ./install.sh [folder] [path to smart-core]
set -euo pipefail

HERE="\$(cd "\$(dirname "\$0")" && pwd)"
TARGET="\${1:-{$app->slug}}"
CORE="\${2:-\${SMART_CORE_PATH:-}}"

if [ -z "\$CORE" ] || [ ! -f "\$CORE/composer.json" ]; then
  echo "Give the path to smart-core (the folder in the AshishOutright/claude repo):"
  echo "  ./install.sh {$app->slug} /path/to/claude/smart-core"
  exit 1
fi
CORE="\$(cd "\$CORE" && pwd)"

composer create-project laravel/laravel "\$TARGET" --no-interaction
cp -R "\$HERE/app-files/." "\$TARGET/"
cd "\$TARGET"
rm -f tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php resources/views/welcome.blade.php
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --no-interaction
composer config repositories.smart-core path "\$CORE"
composer require outright/smart-core:@dev --no-interaction
[ -d .git ] || git init -q

php artisan laws:check
echo
echo "{$app->name} is ready in \$TARGET. Set your MySQL details in .env, then run: php artisan migrate"

BASH;
    }

    // ---- Helpers ---------------------------------------------------------

    private function today(): string
    {
        return now()->format('j M Y');
    }

    private function orDash(?string $text): string
    {
        return trim((string) $text) !== '' ? trim($text) : '_Not written yet._';
    }

    private function cell(?string $text): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], (string) $text);
    }

    private function comment(string $text): string
    {
        return str_replace(["\n", '*/'], [' ', ''], $text);
    }

    /** @return Collection<int, string> */
    public function listFiles(SaasApp $app): Collection
    {
        return collect(array_keys($this->files($app)));
    }
}
