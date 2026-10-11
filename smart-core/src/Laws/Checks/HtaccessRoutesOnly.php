<?php

namespace SmartCore\Laws\Checks;

use SmartCore\Laws\Law;
use SmartCore\Laws\LawResult;

class HtaccessRoutesOnly implements Law
{
    private const SECURITY_DIRECTIVES = '/^\s*(Deny|Allow\s+from|Order\s|Require\s|AuthType|AuthUserFile|AuthName|Satisfy)\b/i';

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

        foreach ([$basePath.'/.htaccess', $basePath.'/public/.htaccess'] as $path) {
            if (! is_file($path)) {
                continue;
            }

            foreach (file($path) as $number => $line) {
                if (preg_match(self::SECURITY_DIRECTIVES, $line)) {
                    $problems[] = basename(dirname($path)).'/.htaccess:'.($number + 1).' has an access rule; enforce it with app middleware instead.';
                }
            }
        }

        return $problems === [] ? LawResult::pass() : LawResult::fail($problems);
    }
}
