<?php

namespace SmartCore\Tests;

use PHPUnit\Framework\TestCase;
use SmartCore\Gateway\Authenticator;
use SmartCore\Gateway\Gateway;
use SmartCore\Gateway\ModuleRegistry;
use SmartCore\Gateway\Store\InMemoryStore;
use SmartCore\Gateway\TokenIssuer;
use SmartCore\Workflow\Phase;
use SmartCore\Workflow\SelectPhase;
use SmartCore\Workflow\SelectStage;
use SmartCore\Workflow\Stage;
use SmartCore\Workflow\Status;
use SmartCore\Workflow\Workflow;

class GatewayTest extends TestCase
{
    private Gateway $gateway;

    private string $token;

    protected function setUp(): void
    {
        $modules = ModuleRegistry::fromConfig([
            ['name' => 'departments', 'crm_module' => 'hr_Departments', 'label' => 'Departments', 'fields' => [
                ['name' => 'name', 'type' => 'varchar', 'label' => 'Name', 'required' => true],
            ]],
            ['name' => 'candidates', 'crm_module' => 'hr_Candidates', 'label' => 'Candidates', 'journey' => true, 'fields' => [
                ['name' => 'name', 'type' => 'varchar', 'label' => 'Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'Email'],
                ['name' => 'source', 'type' => 'enum', 'label' => 'Source', 'options' => ['Referral', 'Job Board']],
                ['name' => 'department', 'type' => 'relate', 'label' => 'Department', 'required' => true, 'relates_to' => 'departments'],
            ]],
        ]);

        $phases = new SelectPhase(new Workflow([TestHiringPhase::class, TestJoiningPhase::class]));
        $tokens = new TokenIssuer(str_repeat('s', 40));
        $auth = new class implements Authenticator {
            public function attempt(string $username, string $password): ?string
            {
                return $username === 'ashish' && $password === 'right' ? 'user-1' : null;
            }
        };

        $this->gateway = new Gateway($modules, new InMemoryStore(1_700_000_000), $auth, $tokens, $phases, new SelectStage($phases), 'HR CRM');
        $this->token = $this->call(['action' => 'login', 'username' => 'ashish', 'password' => 'right'], null)['data']['token'];
    }

    private function call(array $request, ?string $token = 'default'): array
    {
        return $this->gateway->handle($request, $token === 'default' ? $this->token : $token)['body'];
    }

    public function test_every_data_action_needs_a_login(): void
    {
        $this->assertSame('HR CRM', $this->call(['action' => 'ping'], null)['data']['app']);
        $this->assertSame('unauthenticated', $this->call(['action' => 'meta'], null)['error']['code']);
        $this->assertSame('unauthenticated', $this->call(['action' => 'meta'], 'forged.token')['error']['code']);
        $this->assertSame('bad_login', $this->call(['action' => 'login', 'username' => 'ashish', 'password' => 'wrong'], null)['error']['code']);
    }

    public function test_meta_describes_modules_and_journey(): void
    {
        $meta = $this->call(['action' => 'meta'])['data'];

        $this->assertSame(['departments', 'candidates'], array_column($meta['modules'], 'name'));
        $this->assertSame('department_id', $meta['modules'][1]['fields'][3]['key']);
        $this->assertSame(['hiring', 'joining'], array_column($meta['journey'], 'key'));
    }

    public function test_create_checks_fields_and_the_dependency_law(): void
    {
        $bad = $this->call(['action' => 'create', 'module' => 'candidates', 'data' => [
            'email' => 'not-an-email', 'source' => 'Billboard', 'department_id' => 'missing', 'salary' => 5,
        ]]);

        $this->assertSame('invalid', $bad['error']['code']);
        $this->assertSame(['email', 'source', 'salary', 'name', 'department_id'], array_keys($bad['error']['fields']));
        $this->assertStringContainsString('L-CODE-001', $bad['error']['fields']['department_id']);
    }

    public function test_a_record_moves_through_the_journey(): void
    {
        $dept = $this->call(['action' => 'create', 'module' => 'departments', 'data' => ['name' => 'Engineering']])['data'];
        $candidate = $this->call(['action' => 'create', 'module' => 'candidates', 'data' => ['name' => 'Asha', 'department_id' => $dept['id']]])['data'];

        $this->assertSame(['hiring', 'interview', 'scheduled'], [$candidate['journey_phase'], $candidate['journey_stage'], $candidate['journey_status']]);
        $this->assertSame('Engineering', $candidate['department_name']);

        $early = $this->call(['action' => 'advance', 'module' => 'candidates', 'id' => $candidate['id']]);
        $this->assertSame('journey', $early['error']['code']);

        $this->call(['action' => 'move', 'module' => 'candidates', 'id' => $candidate['id'], 'status' => 'passed']);
        $moved = $this->call(['action' => 'advance', 'module' => 'candidates', 'id' => $candidate['id']])['data'];
        $this->assertSame(['joining', 'documents', 'pending'], [$moved['journey_phase'], $moved['journey_stage'], $moved['journey_status']]);

        $wrong = $this->call(['action' => 'move', 'module' => 'candidates', 'id' => $candidate['id'], 'status' => 'passed']);
        $this->assertSame('journey', $wrong['error']['code']);

        $update = $this->call(['action' => 'update', 'module' => 'candidates', 'id' => $candidate['id'], 'data' => ['journey_status' => 'done']]);
        $this->assertSame('invalid', $update['error']['code']);
    }

    public function test_list_is_newest_first_and_filters_only_known_fields(): void
    {
        foreach (['Sales', 'Ops', 'HR'] as $name) {
            $this->call(['action' => 'create', 'module' => 'departments', 'data' => ['name' => $name]]);
        }

        $list = $this->call(['action' => 'list', 'module' => 'departments', 'per_page' => 2])['data'];
        $this->assertSame(['HR', 'Ops'], array_column($list['records'], 'name'));
        $this->assertSame(3, $list['total']);

        $this->assertSame(['Ops'], array_column($this->call(['action' => 'list', 'module' => 'departments', 'filters' => ['name' => 'Ops']])['data']['records'], 'name'));
        $this->assertSame('invalid_filter', $this->call(['action' => 'list', 'module' => 'departments', 'filters' => ['deleted' => '1']])['error']['code']);
    }

    public function test_tokens_expire(): void
    {
        $tokens = new TokenIssuer(str_repeat('k', 40), 60);
        $token = $tokens->issue('user-1', 1000);

        $this->assertSame('user-1', $tokens->verify($token, 1030));
        $this->assertNull($tokens->verify($token, 1100));
        $this->assertNull($tokens->verify($token.'x', 1030));
    }
}

class TestHiringPhase extends Phase
{
    public function key(): string { return 'hiring'; }
    public function label(): string { return 'Hiring'; }
    public function stageClasses(): array { return [TestInterviewStage::class]; }
}

class TestJoiningPhase extends Phase
{
    public function key(): string { return 'joining'; }
    public function label(): string { return 'Joining'; }
    public function stageClasses(): array { return [TestDocumentsStage::class]; }
}

class TestInterviewStage extends Stage
{
    public function key(): string { return 'interview'; }
    public function label(): string { return 'Interview'; }
    public function statuses(): array { return [new Status('scheduled', 'Scheduled'), new Status('passed', 'Passed', true), new Status('failed', 'Failed', true)]; }
}

class TestDocumentsStage extends Stage
{
    public function key(): string { return 'documents'; }
    public function label(): string { return 'Documents'; }
    public function statuses(): array { return [new Status('pending', 'Pending'), new Status('done', 'Done', true)]; }
}
