<?php

namespace App\Services\Builder;

use App\Models\SaasApp;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads and writes the phase / stage / status outline the wizard edits:
 *
 *     Onboarding
 *     - Offer: Drafted, Sent, Accepted*, Declined*
 *
 * A line without "-" starts a phase; "- Stage: statuses" adds a stage; "*" marks a final status.
 */
class JourneyOutline
{
    /**
     * @return list<array{name: string, stages: list<array{name: string, statuses: list<array{name: string, final: bool}>}>}>
     */
    public function parse(string $outline): array
    {
        $phases = [];
        $errors = [];

        foreach (preg_split('/\R/', $outline) as $number => $raw) {
            $line = trim($raw);
            $lineNo = $number + 1;

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_starts_with($line, '-')) {
                $phases[] = ['name' => $this->cleanName($line), 'stages' => []];

                continue;
            }

            if ($phases === []) {
                $errors[] = "Line {$lineNo}: a stage needs a phase line above it.";

                continue;
            }

            $body = trim(substr($line, 1));

            if (! str_contains($body, ':')) {
                $errors[] = "Line {$lineNo}: write the stage as \"- Stage: Status, Status*\".";

                continue;
            }

            [$stageName, $statusList] = array_map('trim', explode(':', $body, 2));
            $statuses = [];

            foreach (array_filter(array_map('trim', explode(',', $statusList)), 'strlen') as $status) {
                $final = str_ends_with($status, '*');
                $statuses[] = ['name' => $this->cleanName(rtrim($status, '*')), 'final' => $final];
            }

            if ($statuses === []) {
                $errors[] = "Line {$lineNo}: stage \"{$stageName}\" needs at least one status.";
            } elseif (! in_array(true, array_column($statuses, 'final'), true)) {
                $errors[] = "Line {$lineNo}: stage \"{$stageName}\" needs a final status, marked with *.";
            }

            $phases[array_key_last($phases)]['stages'][] = ['name' => $this->cleanName($stageName), 'statuses' => $statuses];
        }

        if ($phases === []) {
            $errors[] = 'Add at least one phase.';
        }

        foreach ($phases as $phase) {
            if ($phase['stages'] === []) {
                $errors[] = "Phase \"{$phase['name']}\" needs at least one stage.";
            }
        }

        $this->assertUniqueKeys($phases, $errors);

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        return $phases;
    }

    public function format(SaasApp $app): string
    {
        $lines = [];

        foreach ($app->phases()->with('stages.statuses')->get() as $phase) {
            $lines[] = $phase->name;

            foreach ($phase->stages as $stage) {
                $statuses = $stage->statuses->map(fn ($status) => $status->name.($status->is_final ? '*' : ''))->implode(', ');
                $lines[] = "- {$stage->name}: {$statuses}";
            }
        }

        return implode("\n", $lines);
    }

    public function save(SaasApp $app, string $outline): void
    {
        $phases = $this->parse($outline);

        DB::transaction(function () use ($app, $phases) {
            $app->phases()->delete();

            foreach ($phases as $p => $phaseData) {
                $phase = $app->phases()->create(['name' => $phaseData['name'], 'position' => $p]);

                foreach ($phaseData['stages'] as $s => $stageData) {
                    $stage = $phase->stages()->create(['name' => $stageData['name'], 'position' => $s]);

                    foreach ($stageData['statuses'] as $t => $statusData) {
                        $stage->statuses()->create(['name' => $statusData['name'], 'is_final' => $statusData['final'], 'position' => $t]);
                    }
                }
            }
        });
    }

    private function cleanName(string $name): string
    {
        return trim(preg_replace('/[^\pL\pN &\'\/-]+/u', '', $name));
    }

    /**
     * Phase and stage names become class names, so they must stay unique after conversion.
     */
    private function assertUniqueKeys(array $phases, array &$errors): void
    {
        $phaseKeys = [];

        foreach ($phases as $phase) {
            $key = Naming::className($phase['name']);

            if ($key === '') {
                $errors[] = "Phase \"{$phase['name']}\" needs letters in its name.";
            } elseif (isset($phaseKeys[$key])) {
                $errors[] = "Two phases are both called \"{$phase['name']}\".";
            }

            $phaseKeys[$key] = true;
            $stageKeys = [];

            foreach ($phase['stages'] as $stage) {
                $stageKey = Naming::className($stage['name']);

                if ($stageKey === '') {
                    $errors[] = "Stage \"{$stage['name']}\" needs letters in its name.";
                } elseif (isset($stageKeys[$stageKey])) {
                    $errors[] = "Phase \"{$phase['name']}\" has two stages called \"{$stage['name']}\".";
                }

                $stageKeys[$stageKey] = true;
            }
        }
    }
}
