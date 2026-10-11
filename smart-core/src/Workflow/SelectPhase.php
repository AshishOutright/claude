<?php

namespace SmartCore\Workflow;

use InvalidArgumentException;

/**
 * select_phase: the one place that answers "which phase is this, and what comes next".
 */
class SelectPhase
{
    public function __construct(protected readonly Workflow $workflow)
    {
    }

    /** @return list<Phase> */
    public function all(): array
    {
        return $this->workflow->phases();
    }

    public function first(): Phase
    {
        $phases = $this->all();

        if ($phases === []) {
            throw new InvalidArgumentException('The workflow has no phases.');
        }

        return $phases[0];
    }

    public function find(string $key): Phase
    {
        foreach ($this->all() as $phase) {
            if ($phase->key() === $key) {
                return $phase;
            }
        }

        throw new InvalidArgumentException("Unknown phase [{$key}].");
    }

    public function next(string $key): ?Phase
    {
        $phases = $this->all();

        foreach ($phases as $index => $phase) {
            if ($phase->key() === $key) {
                return $phases[$index + 1] ?? null;
            }
        }

        throw new InvalidArgumentException("Unknown phase [{$key}].");
    }
}
