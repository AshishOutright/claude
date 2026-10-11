<?php

namespace SmartCore\Workflow;

/**
 * One status inside a stage, e.g. "Offer Sent". A final status closes the stage.
 */
final class Status
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $final = false,
    ) {
    }
}
