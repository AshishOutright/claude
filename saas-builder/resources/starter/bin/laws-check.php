<?php

// Checks the app against its laws. CI runs this on every pull request; a broken law blocks the merge.
// Usage: php bin/laws-check.php

use SmartCore\Laws\LawRegistry;
use SmartCore\Laws\LawRunner;
use SmartCore\Workflow\SelectPhase;
use SmartCore\Workflow\Workflow;

$root = dirname(__DIR__);
require $root.'/crm/custom/smart_gateway/bootstrap.php';
$config = require $root.'/crm/custom/smart_gateway/config.php';

$registry = new LawRegistry($config['laws'], new SelectPhase(new Workflow($config['workflow'])));
$results = (new LawRunner($registry))->run($root);

echo LawRunner::report($results);
exit(LawRunner::failed($results) ? 1 : 0);
