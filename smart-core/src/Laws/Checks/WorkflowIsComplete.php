<?php

namespace SmartCore\Laws\Checks;

use SmartCore\Laws\Law;
use SmartCore\Laws\LawResult;
use SmartCore\Workflow\SelectPhase;

class WorkflowIsComplete implements Law
{
    public function __construct(private readonly ?SelectPhase $phases)
    {
    }

    public function code(): string
    {
        return 'L-CODE-003';
    }

    public function title(): string
    {
        return 'Phase, stage and status are separate classes, and every stage can be finished';
    }

    public function check(string $basePath): LawResult
    {
        if ($this->phases === null) {
            return LawResult::fail(['No workflow is registered (config/workflow.php).']);
        }

        $problems = [];
        $phases = $this->phases->all();

        if ($phases === []) {
            $problems[] = 'The workflow has no phases.';
        }

        foreach ($phases as $phase) {
            $stages = $phase->stages();

            if ($stages === []) {
                $problems[] = "Phase {$phase->label()} has no stages.";
            }

            foreach ($stages as $stage) {
                if ($stage->statuses() === []) {
                    $problems[] = "Stage {$phase->label()} / {$stage->label()} has no statuses.";
                } elseif ($stage->finalStatuses() === []) {
                    $problems[] = "Stage {$phase->label()} / {$stage->label()} has no final status, so it can never finish.";
                }
            }
        }

        return $problems === [] ? LawResult::pass() : LawResult::fail($problems);
    }
}
