<?php

// Smart Gateway: index.php?entryPoint=smart_gateway. The one endpoint the React app (and any client) uses.
if (! defined('sugarEntry') || ! sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once __DIR__.'/bootstrap.php';

use SmartCore\Gateway\Gateway;
use SmartCore\Gateway\HttpKernel;
use SmartCore\Gateway\ModuleRegistry;
use SmartCore\Gateway\RateLimiter;
use SmartCore\Gateway\SuiteCrm\SuiteCrmAuthenticator;
use SmartCore\Gateway\SuiteCrm\SuiteCrmStore;
use SmartCore\Gateway\TokenIssuer;
use SmartCore\Workflow\SelectPhase;
use SmartCore\Workflow\SelectStage;
use SmartCore\Workflow\Workflow;

$config = require __DIR__.'/config.php';
$settings = $GLOBALS['sugar_config']['smart_gateway'] ?? [];
$secret = (string) ($settings['secret'] ?? '');

if (strlen($secret) < 32) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => ['code' => 'not_configured', 'message' => 'Set smart_gateway.secret (32 or more characters) in config_override.php.']]);
    exit;
}

$phases = new SelectPhase(new Workflow($config['workflow']));

$gateway = new Gateway(
    ModuleRegistry::fromConfig($config['modules']),
    new SuiteCrmStore(array_column($config['modules'], 'crm_module', 'name')),
    new SuiteCrmAuthenticator(),
    new TokenIssuer($secret, (int) ($settings['token_ttl'] ?? 43200)),
    $phases,
    new SelectStage($phases),
    $config['app'],
    [SuiteCrmAuthenticator::class, 'actAs'],
);

HttpKernel::run(
    $gateway,
    $settings['allowed_origins'] ?? [],
    new RateLimiter(sugar_cached('smart_gateway/rate'), (int) ($settings['requests_per_minute'] ?? 120)),
);
