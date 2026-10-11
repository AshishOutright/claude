<?php

namespace SmartCore\Workflow;

use InvalidArgumentException;

/**
 * A phase of the customer journey, e.g. Onboarding, Employment, Exit.
 * Each generated phase class only declares its key, label and stage classes.
 */
abstract class Phase
{
    abstract public function key(): string;

    abstract public function label(): string;

    /** @return list<class-string<Stage>> */
    abstract public function stageClasses(): array;

    /** @return list<Stage> */
    public function stages(): array
    {
        return array_map(fn (string $class) => new $class(), $this->stageClasses());
    }

    public function stage(string $key): Stage
    {
        foreach ($this->stages() as $stage) {
            if ($stage->key() === $key) {
                return $stage;
            }
        }

        throw new InvalidArgumentException("Phase [{$this->key()}] has no stage [{$key}].");
    }

    public function firstStage(): Stage
    {
        $stages = $this->stages();

        if ($stages === []) {
            throw new InvalidArgumentException("Phase [{$this->key()}] has no stages.");
        }

        return $stages[0];
    }
}
