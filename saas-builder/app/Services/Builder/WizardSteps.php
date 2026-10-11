<?php

namespace App\Services\Builder;

/**
 * The wizard follows the playbook order: constitution, laws, vision, journey, then the ACFCM bar.
 */
class WizardSteps
{
    public const STEPS = [
        'basics' => 'App basics',
        'constitutions' => 'Constitution',
        'laws' => 'Laws',
        'vision' => 'Core Vision',
        'journey' => 'Phases, stages, statuses',
        'acfcm' => 'ACFCM',
        'review' => 'Review and generate',
    ];

    public static function exists(string $step): bool
    {
        return isset(self::STEPS[$step]);
    }

    public static function next(string $step): string
    {
        $keys = array_keys(self::STEPS);
        $index = array_search($step, $keys, true);

        return $keys[$index + 1] ?? 'review';
    }

    public static function number(string $step): int
    {
        return array_search($step, array_keys(self::STEPS), true) + 1;
    }
}
