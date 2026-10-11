<?php

namespace SmartCore\Workflow;

use InvalidArgumentException;

/**
 * select_stage: finds a stage, moves a record to the next stage, and guards status changes.
 */
class SelectStage
{
    public function __construct(protected readonly SelectPhase $phases)
    {
    }

    public function find(string $phaseKey, string $stageKey): Stage
    {
        return $this->phases->find($phaseKey)->stage($stageKey);
    }

    /**
     * The next [phase, stage] pair, crossing into the next phase when a phase ends.
     *
     * @return array{0: Phase, 1: Stage}|null
     */
    public function next(string $phaseKey, string $stageKey): ?array
    {
        $phase = $this->phases->find($phaseKey);
        $stages = $phase->stages();

        foreach ($stages as $index => $stage) {
            if ($stage->key() !== $stageKey) {
                continue;
            }

            if (isset($stages[$index + 1])) {
                return [$phase, $stages[$index + 1]];
            }

            $nextPhase = $this->phases->next($phaseKey);

            return $nextPhase ? [$nextPhase, $nextPhase->firstStage()] : null;
        }

        throw new InvalidArgumentException("Phase [{$phaseKey}] has no stage [{$stageKey}].");
    }

    /**
     * Checks that a status change stays inside the stage and does not reopen a final status.
     */
    public function assertCanMove(string $phaseKey, string $stageKey, string $fromStatus, string $toStatus): void
    {
        $stage = $this->find($phaseKey, $stageKey);

        if (! $stage->hasStatus($toStatus)) {
            throw new InvalidArgumentException("Stage [{$stageKey}] has no status [{$toStatus}].");
        }

        if ($stage->status($fromStatus)->final) {
            throw new InvalidArgumentException("Status [{$fromStatus}] is final; move the record to the next stage instead.");
        }
    }
}
