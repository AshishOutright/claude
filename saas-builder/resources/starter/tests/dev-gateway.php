<?php

// A local Smart Gateway with no CRM: records are kept in tests/dev-data.json.
// Run: php -S localhost:8080 tests/dev-gateway.php   then set VITE_SG_URL=http://localhost:8080 in frontend/.env
// Log in with username "dev" and password "dev".

use SmartCore\Gateway\Authenticator;
use SmartCore\Gateway\Gateway;
use SmartCore\Gateway\HttpKernel;
use SmartCore\Gateway\ModuleRegistry;
use SmartCore\Gateway\Store\JsonFileStore;
use SmartCore\Gateway\TokenIssuer;
use SmartCore\Workflow\SelectPhase;
use SmartCore\Workflow\SelectStage;
use SmartCore\Workflow\Workflow;

$root = dirname(__DIR__);
require $root.'/crm/custom/smart_gateway/bootstrap.php';
$config = require $root.'/crm/custom/smart_gateway/config.php';

final class DevAuthenticator implements Authenticator
{
    public function attempt(string $username, string $password): ?string
    {
        return $username === 'dev' && $password === 'dev' ? 'dev-user' : null;
    }
}

$phases = new SelectPhase(new Workflow($config['workflow']));

HttpKernel::run(
    new Gateway(
        ModuleRegistry::fromConfig($config['modules']),
        new JsonFileStore(__DIR__.'/dev-data.json'),
        new DevAuthenticator(),
        new TokenIssuer(str_repeat('d', 32)),
        $phases,
        new SelectStage($phases),
        $config['app'].' (local)',
    ),
    ['http://localhost:5173', 'http://127.0.0.1:5173'],
);
