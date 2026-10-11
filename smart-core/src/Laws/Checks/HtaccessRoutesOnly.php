<?php

namespace SmartCore\Laws\Checks;

use SmartCore\Laws\Law;
use SmartCore\Laws\LawResult;

class HtaccessRoutesOnly implements Law
{
    private const SECURITY_DIRECTIVES = '/^\s*(Deny|Allow\s+from|Order\s|Require\s|AuthType|AuthUserFile|AuthName|Satisfy)\b/i';

    private const FILES = ['.htaccess', 'public/.htaccess', 'crm/.htaccess', 'frontend/public/.htaccess'];

    public function code(): string
    {
        return 'L-SEC-001';
    }

    public function title(): string
    {
        return 'Security lives in the app; .htaccess only routes requests';
    }

    public function check(string $basePath): LawResult
    {
        $problems = [];

        foreach (self::FILES as $relative) {
            $path = $basePath.'/'.$relative;

            if (! is_file($path)) {
                continue;
            }

            foreach (file($path) as $number => $line) {
                if (preg_match(self::SECURITY_DIRECTIVES, $line)) {
                    $problems[] = $relative.':'.($number + 1).' has an access rule; enforce it in the app (Smart Gateway or middleware) instead.';
                }
            }
        }

        return $problems === [] ? LawResult::pass() : LawResult::fail($problems);
    }
}
