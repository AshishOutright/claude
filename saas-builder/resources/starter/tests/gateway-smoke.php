<?php

// Runs the Smart Gateway with an in-memory store: logs in, creates a record in every module,
// then walks the journey module through its first stage. Usage: php tests/gateway-smoke.php

use SmartCore\Gateway\Authenticator;
use SmartCore\Gateway\FieldDefinition;
use SmartCore\Gateway\Gateway;
use SmartCore\Gateway\ModuleRegistry;
use SmartCore\Gateway\Store\InMemoryStore;
use SmartCore\Gateway\TokenIssuer;
use SmartCore\Workflow\SelectPhase;
use SmartCore\Workflow\SelectStage;
use SmartCore\Workflow\Workflow;

$root = dirname(__DIR__);
require $root.'/crm/custom/smart_gateway/bootstrap.php';
$config = require $root.'/crm/custom/smart_gateway/config.php';

final class SmokeAuthenticator implements Authenticator
{
    public function attempt(string $username, string $password): ?string
    {
        return 'smoke-user';
    }
}

final class Smoke
{
    private Gateway $gateway;

    private ModuleRegistry $modules;

    private SelectStage $stages;

    private string $token = '';

    public function __construct(array $config)
    {
        $phases = new SelectPhase(new Workflow($config['workflow']));
        $this->stages = new SelectStage($phases);
        $this->modules = ModuleRegistry::fromConfig($config['modules']);
        $this->gateway = new Gateway($this->modules, new InMemoryStore(), new SmokeAuthenticator(), new TokenIssuer(str_repeat('x', 32)), $phases, $this->stages, $config['app']);
    }

    public function run(): void
    {
        $this->token = $this->call(['action' => 'login', 'username' => 'smoke', 'password' => 'smoke'])['token'];
        $this->call(['action' => 'meta']);
        $created = $this->createEveryModule();

        $journey = $this->modules->journeyModule();
        $record = $created[$journey->name];
        $stage = $this->stages->find($record['journey_phase'], $record['journey_stage']);
        $final = $stage->finalStatuses()[0];

        $record = $this->call(['action' => 'move', 'module' => $journey->name, 'id' => $record['id'], 'status' => $final->key]);
        $next = $this->stages->next($record['journey_phase'], $record['journey_stage']);

        if ($next !== null) {
            $record = $this->call(['action' => 'advance', 'module' => $journey->name, 'id' => $record['id']]);
        }

        echo "Smart Gateway smoke test passed: created a record in ".count($created)." modules; {$journey->label} is now at {$record['journey_phase']} / {$record['journey_stage']} / {$record['journey_status']}.\n";
    }

    /** Creates modules in dependency order, so required relate fields point at real records. */
    private function createEveryModule(): array
    {
        $created = [];
        $pending = $this->modules->all();

        while ($pending !== []) {
            $progress = false;

            foreach ($pending as $index => $module) {
                $data = $this->sample($module->fields, $created);

                if ($data === null) {
                    continue;
                }

                $created[$module->name] = $this->call(['action' => 'create', 'module' => $module->name, 'data' => $data]);
                unset($pending[$index]);
                $progress = true;
            }

            if (! $progress) {
                $this->fail('Required relate fields point at each other in a circle, so no module can be created first.');
            }
        }

        return $created;
    }

    /** @param list<FieldDefinition> $fields */
    private function sample(array $fields, array $created): ?array
    {
        $data = [];

        foreach ($fields as $field) {
            if ($field->type === 'relate') {
                if (isset($created[$field->relatesTo])) {
                    $data[$field->inputKey()] = $created[$field->relatesTo]['id'];
                } elseif ($field->required) {
                    return null;
                }

                continue;
            }

            $data[$field->inputKey()] = match ($field->type) {
                'int' => 3,
                'decimal' => '12.50',
                'date' => '2026-01-15',
                'datetime' => '2026-01-15 10:30:00',
                'bool' => true,
                'email' => 'smoke@example.com',
                'url' => 'https://example.com',
                'phone' => '+1 555 0100',
                'enum' => $field->options[0],
                default => 'Smoke '.$field->label,
            };
        }

        return $data;
    }

    private function call(array $request): array
    {
        $body = $this->gateway->handle($request, $this->token ?: null)['body'];

        if (! $body['ok']) {
            $this->fail(($request['action'] ?? '?').' failed: '.json_encode($body['error']));
        }

        return $body['data'];
    }

    private function fail(string $message): never
    {
        fwrite(STDERR, "Smart Gateway smoke test failed. {$message}\n");
        exit(1);
    }
}

(new Smoke($config))->run();
