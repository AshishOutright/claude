<?php

namespace SmartCore\Laws;

use SmartCore\Laws\Checks\HtaccessRoutesOnly;
use SmartCore\Laws\Checks\ListViewDateCreatedFirst;
use SmartCore\Laws\Checks\NoRouteClosures;
use SmartCore\Laws\Checks\RequiredDependencies;
use SmartCore\Laws\Checks\WorkflowIsComplete;
use SmartCore\Workflow\SelectPhase;

/**
 * Turns the app's config/laws.php into Law objects, using a built-in check when the core has one.
 */
class LawRegistry
{
    /** Law codes the core can check automatically. */
    public const CHECKS = [
        'L-UI-001' => ListViewDateCreatedFirst::class,
        'L-CODE-001' => RequiredDependencies::class,
        'L-CODE-002' => NoRouteClosures::class,
        'L-CODE-003' => WorkflowIsComplete::class,
        'L-SEC-001' => HtaccessRoutesOnly::class,
    ];

    /** @param array<string, string> $enabled law code => title */
    public function __construct(private readonly array $enabled, private readonly ?SelectPhase $phases = null)
    {
    }

    /** @return list<Law> */
    public function laws(): array
    {
        $laws = [];

        foreach ($this->enabled as $code => $title) {
            $class = self::CHECKS[$code] ?? null;

            $laws[] = match (true) {
                $class === WorkflowIsComplete::class => new WorkflowIsComplete($this->phases),
                $class !== null => new $class(),
                default => new ManualLaw($code, $title),
            };
        }

        return $laws;
    }
}
