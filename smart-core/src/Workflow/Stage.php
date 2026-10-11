<?php

namespace SmartCore\Workflow;

use InvalidArgumentException;

/**
 * A stage inside a phase, e.g. "Interview" inside "Onboarding".
 * Each generated stage class only declares its key, label and statuses.
 */
abstract class Stage
{
    abstract public function key(): string;

    abstract public function label(): string;

    /** @return list<Status> */
    abstract public function statuses(): array;

    public function initialStatus(): Status
    {
        $statuses = $this->statuses();

        if ($statuses === []) {
            throw new InvalidArgumentException("Stage [{$this->key()}] has no statuses.");
        }

        return $statuses[0];
    }

    public function status(string $key): Status
    {
        foreach ($this->statuses() as $status) {
            if ($status->key === $key) {
                return $status;
            }
        }

        throw new InvalidArgumentException("Stage [{$this->key()}] has no status [{$key}].");
    }

    public function hasStatus(string $key): bool
    {
        foreach ($this->statuses() as $status) {
            if ($status->key === $key) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Status> */
    public function finalStatuses(): array
    {
        return array_values(array_filter($this->statuses(), fn (Status $status) => $status->final));
    }
}
