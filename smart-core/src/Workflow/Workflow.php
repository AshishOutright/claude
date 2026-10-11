<?php

namespace SmartCore\Workflow;

/**
 * The app's full journey: the ordered phase classes listed in config/workflow.php.
 */
class Workflow
{
    /** @param list<class-string<Phase>> $phaseClasses */
    public function __construct(private readonly array $phaseClasses)
    {
    }

    /** @return list<Phase> */
    public function phases(): array
    {
        return array_map(fn (string $class) => new $class(), $this->phaseClasses);
    }
}
