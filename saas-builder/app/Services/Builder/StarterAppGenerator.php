<?php

namespace App\Services\Builder;

use App\Models\SaasApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Builds the starter app for a finished wizard, in the three parts every app has:
 *
 *  - crm/       the CRM copy's modules (SuiteCRM), copied into the CRM's root
 *  - crm/custom/smart_gateway/   the Smart Gateway entry point, running on Smart Core
 *  - frontend/  the React app, which reads and writes CRM data only through the Smart Gateway
 *
 * plus the docs and CLAUDE.md agents build against, the laws check and CI.
 */
class StarterAppGenerator
{
    private const GATEWAY = 'crm/custom/smart_gateway';

    public function __construct(private readonly SuiteCrmModuleWriter $crm)
    {
    }

    /** @return array<string, string> relative path => file contents */
    public function files(SaasApp $app): array
    {
        $app->loadMissing(['constitutions', 'laws', 'phases.stages.statuses', 'acfcmChoices', 'modules.fields']);

        $files = [
            'CLAUDE.md' => $this->claudeMd($app),
            'docs/CONSTITUTION.md' => $this->constitutionMd($app),
            'docs/LAWS.md' => $this->lawsMd($app),
            'docs/VISION.md' => $this->visionMd($app),
            'docs/JOURNEY.md' => $this->journeyMd($app),
            'docs/MODULES.md' => $this->modulesMd($app),
            'docs/SMART_GATEWAY.md' => $this->gatewayMd($app),
            'install-crm.sh' => $this->installScript($app),
            '.gitignore' => "node_modules/\ndist/\n.env\n.env.*\n!.env.example\ntests/dev-data.json\n",
            '.github/workflows/laws.yml' => $this->ciWorkflow(),
            self::GATEWAY.'/config.php' => $this->gatewayConfig($app),
            self::GATEWAY.'/CORE_VERSION' => 'Copied from smart-core on '.now()->toDateString()." by the SaaS Builder. Update by regenerating or copying smart-core/src again.\n",
        ];

        foreach ($this->crm->files($app) as $path => $contents) {
            $files['crm/'.$path] = $contents;
        }

        foreach ($this->stubs($app) as $path => $contents) {
            $files[$path] = $contents;
        }

        foreach ($this->coreFiles() as $path => $contents) {
            $files[self::GATEWAY.'/core/'.$path] = $contents;
        }

        foreach ($app->phases as $phase) {
            $files[self::GATEWAY.'/app/Workflow/Phases/'.Naming::className($phase->name).'Phase.php'] = $this->phaseClass($phase);

            foreach ($phase->stages as $stage) {
                $files[self::GATEWAY.'/app/Workflow/Stages/'.Naming::className($phase->name).'/'.Naming::className($stage->name).'Stage.php'] = $this->stageClass($phase, $stage);
            }
        }

        $files['README.md'] = $this->readme($app, count($files));
        ksort($files);

        return $files;
    }

    /**
     * Writes the starter app to storage and zips it. Returns the zip path.
     */
    public function build(SaasApp $app): string
    {
        $root = storage_path('app/generated/'.$app->slug);
        File::deleteDirectory($root);

        foreach ($this->files($app) as $path => $contents) {
            File::ensureDirectoryExists(dirname($root.'/'.$path));
            File::put($root.'/'.$path, $contents);
        }

        chmod($root.'/install-crm.sh', 0755);

        $zipPath = storage_path('app/generated/'.$app->slug.'.zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create {$zipPath}.");
        }

        foreach (File::allFiles($root, true) as $file) {
            $name = $app->slug.'/'.$file->getRelativePathname();
            $zip->addFile($file->getPathname(), $name);

            if (str_ends_with($name, '.sh')) {
                $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100755 << 16);
            }
        }

        $zip->close();
        $app->forceFill(['generated_at' => now()])->save();

        return $zipPath;
    }

    /** @return Collection<int, string> */
    public function listFiles(SaasApp $app): Collection
    {
        return collect(array_keys($this->files($app)))->reject(fn ($p) => str_starts_with($p, self::GATEWAY.'/core/'))->values();
    }

    // ---- Static parts: React app, gateway entry, laws check, smoke test ----

    /** @return array<string, string> */
    private function stubs(SaasApp $app): array
    {
        $root = resource_path('starter');
        $files = [];

        foreach (File::allFiles($root, true) as $file) {
            $files[str_replace('\\', '/', $file->getRelativePathname())] = strtr($file->getContents(), [
                '__APP_NAME__' => $app->name,
                '__APP_SLUG__' => $app->slug,
            ]);
        }

        return $files;
    }

    /** Smart Core's framework-free parts, so the gateway runs inside the CRM without Composer. */
    private function coreFiles(): array
    {
        $src = rtrim(config('builder.smart_core_path'), '/').'/src';

        if (! is_dir($src)) {
            throw new RuntimeException("Smart Core was not found at {$src}. Set SMART_CORE_PATH.");
        }

        $files = [];

        foreach (['Workflow', 'Laws', 'Gateway'] as $dir) {
            foreach (File::allFiles($src.'/'.$dir) as $file) {
                $files[$dir.'/'.str_replace('\\', '/', $file->getRelativePathname())] = $file->getContents();
            }
        }

        return $files;
    }

    // ---- Docs ------------------------------------------------------------

    private function readme(SaasApp $app, int $files): string
    {
        $summary = $app->summary ? "\n{$app->summary}\n" : '';
        $prefix = $app->crmPrefix();
        $modules = $app->modules->map(fn ($m) => "`{$m->crmKey($prefix)}`")->implode(', ');

        return <<<MD
# {$app->name}
{$summary}
Generated by the SaaS Builder on {$this->today()} ({$files} files). Every app has three parts:

| Part | Folder | What it is |
|---|---|---|
| CRM | `crm/` | The modules this app stores ({$modules}), copied into your SuiteCRM copy |
| Smart Gateway | `crm/custom/smart_gateway/` | The one endpoint for all data: `index.php?entryPoint=smart_gateway` |
| React app | `frontend/` | The screens people use; they talk only to the Smart Gateway |

## 1. Install into the CRM copy

```bash
./install-crm.sh /path/to/your/suitecrm
```

Then in the CRM: **Admin → Repair → Quick Repair and Rebuild**, and run the SQL it shows to create the tables.
Add the gateway settings to `config_override.php` (the script prints a fresh secret):

```php
\$sugar_config['smart_gateway'] = [
    'secret' => '<64 random characters>',
    'allowed_origins' => ['http://localhost:5173', 'https://app.example.com'],
    'requests_per_minute' => 120,
];
```

## 2. Run the React app

```bash
cd frontend
cp .env.example .env      # set VITE_SG_URL to your CRM's smart_gateway link
npm install
npm run dev
```

Log in with a CRM username and password.

No CRM yet? Run a local gateway that keeps records in a JSON file, set `VITE_SG_URL=http://localhost:8080`, and log
in as `dev` / `dev`:

```bash
php -S localhost:8080 tests/dev-gateway.php
```

## 3. Check the laws

```bash
php bin/laws-check.php        # fails when a checked law is broken
php tests/gateway-smoke.php   # runs the gateway against an in-memory store
```

CI (`.github/workflows/laws.yml`) runs both and builds the React app on every pull request.

MD;
    }

    private function claudeMd(SaasApp $app): string
    {
        $constitution = $app->constitutions->map(fn ($c) => "- **{$c->code} {$c->title}.** {$c->reason}")->implode("\n");
        $laws = $app->laws->map(fn ($l) => "- **{$l->code}** {$l->title}".($l->checked_by_core ? ' _(checked by `bin/laws-check.php`)_' : ''))->implode("\n");

        return <<<MD
# {$app->name}: brief for agents

Read this before writing code. It is generated by the SaaS Builder; change it there and regenerate, or edit it here
and keep `docs/` in sync.

## Vision for the team

{$this->orDash($app->team_vision)}

Full Core Vision: `docs/VISION.md`.

## How this app is built

1. **CRM copy** (`crm/`): SuiteCRM stores the modules in `docs/MODULES.md`. Each is a real module with its own table.
2. **Smart Gateway** (`crm/custom/smart_gateway/`): the one endpoint for all data. Actions and request format: `docs/SMART_GATEWAY.md`.
3. **React app** (`frontend/`): screens built from the gateway's `meta` action. It never talks to the CRM any other way.

Shared logic lives in Smart Core (`crm/custom/smart_gateway/core/`, copied from the `smart-core` folder of the builder
repo). Fix shared logic there, not in this app.

## Constitution

{$constitution}

## Laws

{$laws}

Run `php bin/laws-check.php` before every commit. A failing law blocks the merge in CI.

## Journey (phase, stage, status)

Records in **{$app->journeyModule()?->name}** move through:

{$this->journeyBullets($app)}

Phases and stages are classes in `crm/custom/smart_gateway/app/Workflow/`. Status changes go through the gateway's
`move` and `advance` actions, which use `SelectPhase` and `SelectStage`; never write `journey_*` fields directly.

## ACFCM

{$this->acfcmLines($app)}

## Rules for code here

- PHP 8.3 in the gateway, React in the frontend. All PHP in classes and methods.
- New data goes through a gateway action. Add a field in the CRM module's vardefs and in `config.php`, and the React screens pick it up from `meta`.
- Lists use `frontend/src/components/ListView.jsx` (Date Created first, as "3 days ago"). CRM list views keep DATE_ENTERED first.
- Security is in the gateway (tokens, validation, rate limits). `.htaccess` only routes requests.
- AI calls go through aisuites.ai, never a provider SDK in a screen.

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

        return "# {$app->name} laws\n\nUI and development boundaries. \"Automatic\" laws are checked by `php bin/laws-check.php` in CI; the rest are checked in review.\n\n| Code | Area | Law | Example | Checked |\n|---|---|---|---|---|\n{$rows}\n";
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
        return "# {$app->name} journey\n\nRecords in {$app->journeyModule()?->name} move through these phases, stages and statuses. Final statuses are in bold; a record moves to the next stage only from a final status.\n\n".$this->journeyBullets($app)."\n";
    }

    private function modulesMd(SaasApp $app): string
    {
        $out = "# {$app->name} CRM modules\n\nEvery module also has Name, Date Created, Date Modified and Assigned User. Required fields are marked *.\n";

        foreach ($app->modules as $module) {
            $out .= "\n## {$module->name}".($module->is_journey ? ' (journey)' : '')."\n\nCRM module `{$module->crmKey($app->crmPrefix())}`, gateway name `{$module->apiName()}`.\n\n| Field | Key | Type |\n|---|---|---|\n";

            foreach ($module->fields as $field) {
                $type = $field->type.($field->type_arg ? " ({$field->type_arg})" : '');
                $key = $field->type === 'relate' ? $field->name.'_id' : $field->name;
                $out .= "| {$field->label}".($field->is_required ? ' *' : '')." | `{$key}` | {$type} |\n";
            }

            if ($module->is_journey) {
                $out .= "| Phase, Stage, Status | `journey_phase`, `journey_stage`, `journey_status` | set by `move` and `advance` |\n";
            }
        }

        return $out;
    }

    private function gatewayMd(SaasApp $app): string
    {
        $journey = $app->journeyModule()?->apiName() ?? 'records';
        $first = $app->modules->first()?->apiName() ?? 'records';

        return <<<MD
# Smart Gateway

One endpoint for all of {$app->name}'s data: `POST <crm>/index.php?entryPoint=smart_gateway` with a JSON body.
Every response is `{"ok": true, "data": ...}` or `{"ok": false, "error": {"code", "message", "fields"}}`.

Log in first and send the token as `Authorization: Bearer <token>` on every other call. Tokens are signed with
`smart_gateway.secret` and expire after 12 hours.

| Action | Body | Returns |
|---|---|---|
| `ping` | | app name, no login needed |
| `login` | `username`, `password` (a CRM user) | `token` |
| `meta` | | modules, fields and the journey, to build screens from |
| `list` | `module`, optional `filters` {field: value}, `page`, `per_page` (max 100) | `records` newest first, `total` |
| `get` | `module`, `id` | the record |
| `create` | `module`, `data` | the new record; journey records start at the first stage |
| `update` | `module`, `id`, `data` | the record |
| `delete` | `module`, `id` | `deleted` |
| `move` | `module`, `id`, `status` | the record, with a new status in its current stage |
| `advance` | `module`, `id` | the record, at the next stage (its status must be final) |

Example:

```json
{"action": "list", "module": "{$first}", "per_page": 10}
{"action": "move", "module": "{$journey}", "id": "…", "status": "…"}
```

What the gateway enforces: only known fields, type checks, required fields, related records must exist (law
L-CODE-001), journey rules, a per-IP request limit, and CORS only for `smart_gateway.allowed_origins`.

MD;
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

    // ---- Gateway config and workflow classes -----------------------------

    private function gatewayConfig(SaasApp $app): string
    {
        $prefix = $app->crmPrefix();
        $modules = $app->modules->map(fn ($module) => [
            'name' => $module->apiName(),
            'crm_module' => $module->crmKey($prefix),
            'label' => $module->name,
            'journey' => $module->is_journey,
            'fields' => $module->fields->map(fn ($f) => array_filter([
                'name' => $f->name,
                'type' => $f->type,
                'label' => $f->label,
                'required' => $f->is_required ?: null,
                'options' => $f->enumOptions() ?: null,
                'relates_to' => $f->type === 'relate' ? $app->modules->firstWhere('name', $f->type_arg)?->apiName() : null,
            ], fn ($v) => $v !== null))->all(),
        ])->all();

        $config = [
            'app' => $app->name,
            'workflow' => $app->phases->map(fn ($p) => 'SmartApp\\Workflow\\Phases\\'.Naming::className($p->name).'Phase')->all(),
            'laws' => $app->laws->pluck('title', 'code')->all(),
            'modules' => $modules,
            'acfcm' => collect(config('playbook.acfcm'))->map(fn ($meta, $pillar) => $app->acfcmChoices->where('pillar', $pillar)->map(fn ($c) => $c->item_key ?? $c->label)->values()->all())->all(),
        ];

        return "<?php\n\n// Generated by the SaaS Builder: the modules, journey and laws the Smart Gateway serves.\n// Change behaviour here (ACFCM: 100% flexible), not in gateway code.\nreturn ".$this->export($config).";\n";
    }

    private function phaseClass($phase): string
    {
        $class = Naming::className($phase->name).'Phase';
        $folder = Naming::className($phase->name);
        $key = Naming::key($phase->name);
        $label = var_export($phase->name, true);
        $stages = $phase->stages->map(fn ($s) => "            \\SmartApp\\Workflow\\Stages\\{$folder}\\".Naming::className($s->name).'Stage::class,')->implode("\n");

        return <<<PHP
<?php

namespace SmartApp\Workflow\Phases;

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

namespace SmartApp\Workflow\Stages\\{$folder};

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

    // ---- Install and CI --------------------------------------------------

    private function installScript(SaasApp $app): string
    {
        return <<<BASH
#!/usr/bin/env bash
# Copies the {$app->name} modules and the Smart Gateway into a SuiteCRM copy.
# Usage: ./install-crm.sh /path/to/suitecrm
set -euo pipefail

HERE="\$(cd "\$(dirname "\$0")" && pwd)"
CRM="\${1:-}"

if [ -z "\$CRM" ] || [ ! -f "\$CRM/config.php" ]; then
  echo "Give the path to your SuiteCRM copy (the folder with config.php):"
  echo "  ./install-crm.sh /var/www/suitecrm"
  exit 1
fi

php "\$HERE/bin/laws-check.php"
cp -R "\$HERE/crm/." "\$CRM/"

echo
echo "Copied. Next, in the CRM: Admin > Repair > Quick Repair and Rebuild, and run the SQL it shows."
echo "Then add this to \$CRM/config_override.php:"
echo
echo "\\\$sugar_config['smart_gateway'] = ["
echo "    'secret' => '\$(php -r 'echo bin2hex(random_bytes(32));')',"
echo "    'allowed_origins' => ['http://localhost:5173'],"
echo "    'requests_per_minute' => 120,"
echo "];"

BASH;
    }

    private function ciWorkflow(): string
    {
        return <<<'YAML'
# Laws in CI: every pull request checks the laws, runs the gateway smoke test and builds the React app.
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

      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"

      - name: Laws
        run: php bin/laws-check.php

      - name: Smart Gateway smoke test
        run: php tests/gateway-smoke.php

      - uses: actions/setup-node@v4
        with:
          node-version: "20"

      - name: Build the React app
        working-directory: frontend
        run: |
          npm install --no-audit --no-fund
          npm run build

YAML;
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

    private function export(array $value, int $depth = 0): string
    {
        if ($value === []) {
            return '[]';
        }

        $pad = str_repeat('    ', $depth + 1);
        $isList = array_is_list($value);
        $lines = [];

        foreach ($value as $k => $v) {
            $item = is_array($v) ? $this->export($v, $depth + 1) : var_export($v, true);
            $lines[] = $pad.($isList ? '' : var_export($k, true).' => ').$item.',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth).']';
    }
}
