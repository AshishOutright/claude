<?php

namespace SmartCore\Gateway;

use RuntimeException;

final class GatewayException extends RuntimeException
{
    /** @param array<string, string> $fields field key => problem */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }
}
