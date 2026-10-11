<?php

namespace Tests\Feature;

use App\Models\Constitution;
use App\Models\Law;
use App\Models\SaasApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class NewAppWizardTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_a_new_app_starts_with_the_core_constitutions_laws_and_template_journey(): void
    {
        $this->post('/apps', ['name' => 'HR CRM', 'template' => 'hr-crm'])
            ->assertRedirect('/apps/hr-crm/constitutions');

        $app = SaasApp::where('slug', 'hr-crm')->firstOrFail();

        $this->assertSame(Constitution::where('is_core', true)->count(), $app->constitutions()->count());
        $this->assertSame(Law::where('is_core', true)->count(), $app->laws()->count());
        $this->assertSame(['Onboarding', 'Employment', 'Exit'], $app->phases()->pluck('name')->all());
        $this->assertTrue($app->acfcmChoices()->where('item_key', 'aisuites_client')->exists());
    }

    public function test_every_step_saves_and_moves_on(): void
    {
        $this->post('/apps', ['name' => 'Task CRM', 'template' => 'task-crm']);
        $app = SaasApp::where('slug', 'task-crm')->firstOrFail();

        foreach (['basics', 'constitutions', 'laws', 'vision', 'modules', 'journey', 'acfcm', 'review'] as $step) {
            $this->get("/apps/task-crm/{$step}")->assertOk();
        }

        $this->put('/apps/task-crm/constitutions', [
            'constitutions' => [Constitution::where('code', 'S-001')->value('id')],
            'new_title' => 'Clients see their own tasks only',
            'new_reason' => 'Privacy',
        ])->assertRedirect('/apps/task-crm/laws');

        $this->assertSame(['S-001', 'S-009'], $app->constitutions()->pluck('code')->all());

        $this->put('/apps/task-crm/laws', [
            'laws' => Law::whereIn('code', ['L-UI-001', 'L-CODE-003'])->pluck('id')->all(),
            'new_title' => 'Every task has an owner',
            'new_category' => 'data',
        ])->assertRedirect('/apps/task-crm/vision');

        $this->assertSame(['L-CODE-003', 'L-DATA-003', 'L-UI-001'], $app->laws()->pluck('code')->all());

        $this->put('/apps/task-crm/vision', [
            'core_vision' => 'Every client task is tracked from intake to feedback.',
            'team_vision' => 'No task is lost.',
        ])->assertRedirect('/apps/task-crm/modules');

        $this->put('/apps/task-crm/modules', ['modules' => "Tasks*: due:date!, client:relate(Clients)!\nClients: email:email"])
            ->assertRedirect('/apps/task-crm/journey');

        $this->assertSame(['Tasks', 'Clients'], $app->modules()->pluck('name')->all());
        $this->assertSame(['name', 'due', 'client'], $app->modules()->first()->fields()->pluck('name')->all());

        $this->put('/apps/task-crm/journey', ['outline' => "Plan\n- Intake: New, Accepted*"])
            ->assertRedirect('/apps/task-crm/acfcm');

        $this->assertSame(['Accepted'], $app->phases()->first()->stages()->first()->statuses()->where('is_final', true)->pluck('name')->all());

        $this->put('/apps/task-crm/acfcm', [
            'items' => ['automation' => ['journey_events'], 'ml' => ['aisuites_client']],
            'custom' => ['controlling' => "Limit tasks per client"],
        ])->assertRedirect('/apps/task-crm/review');

        $this->assertSame(['journey_events', null, 'aisuites_client'], $app->acfcmChoices()->orderBy('id')->pluck('item_key')->all());
        $this->assertSame('review', $app->fresh()->current_step);
    }

    public function test_a_journey_with_mistakes_shows_what_to_fix(): void
    {
        $this->post('/apps', ['name' => 'Broken', 'template' => 'blank']);

        $this->from('/apps/broken/journey')
            ->put('/apps/broken/journey', ['outline' => "Plan\n- Intake: New, Open"])
            ->assertRedirect('/apps/broken/journey')
            ->assertSessionHasErrors('outline');
    }

    public function test_generate_builds_crm_modules_smart_gateway_and_react_app(): void
    {
        $this->post('/apps', ['name' => 'HR CRM', 'template' => 'hr-crm']);
        $this->put('/apps/hr-crm/vision', ['core_vision' => 'Hire to exit in one place.', 'team_vision' => 'One record per employee, from offer to full and final.']);

        $response = $this->post('/apps/hr-crm/generate')->assertOk()->assertDownload('hr-crm.zip');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        foreach ([
            'hr-crm/CLAUDE.md',
            'hr-crm/install-crm.sh',
            'hr-crm/crm/modules/hrcrm_Employees/vardefs.php',
            'hr-crm/crm/modules/hrcrm_Departments/metadata/listviewdefs.php',
            'hr-crm/crm/custom/Extension/application/Ext/EntryPointRegistry/hr-crm_smart_gateway.php',
            'hr-crm/crm/custom/smart_gateway/entry.php',
            'hr-crm/crm/custom/smart_gateway/config.php',
            'hr-crm/crm/custom/smart_gateway/core/Gateway/Gateway.php',
            'hr-crm/crm/custom/smart_gateway/app/Workflow/Stages/Exit/FullAndFinalStage.php',
            'hr-crm/frontend/src/sg.js',
            'hr-crm/frontend/.env.example',
            'hr-crm/frontend/package.json',
        ] as $expected) {
            $this->assertContains($expected, $names);
        }

        $this->assertNotContains('hr-crm/crm/custom/smart_gateway/core/Laravel/CheckLaws.php', $names);
        $this->assertStringContainsString('One record per employee', $zip->getFromName('hr-crm/CLAUDE.md'));
        $this->assertStringContainsString("'module' => 'hrcrm_Departments'", $zip->getFromName('hr-crm/crm/modules/hrcrm_Employees/vardefs.php'));
        $this->assertStringContainsString("'rhs_key' => 'department_id'", $zip->getFromName('hr-crm/crm/modules/hrcrm_Employees/vardefs.php'));
        $this->assertStringContainsString('<title>HR CRM</title>', $zip->getFromName('hr-crm/frontend/index.html'));
        $this->assertNotNull(SaasApp::where('slug', 'hr-crm')->value('generated_at'));
    }

    public function test_a_generated_app_passes_its_laws_and_gateway_smoke_test(): void
    {
        foreach (['hr-crm', 'task-crm', 'social-crm', 'blank'] as $template) {
            $slug = "check-{$template}";
            $this->post('/apps', ['name' => "Check {$template}", 'slug' => $slug, 'template' => $template]);
            $this->post("/apps/{$slug}/generate")->assertOk();

            $root = storage_path("app/generated/{$slug}");

            foreach (File::allFiles($root, true) as $file) {
                if (str_ends_with($file->getFilename(), '.php')) {
                    exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $code);
                    $this->assertSame(0, $code, implode("\n", $output));
                }
            }

            foreach (['bin/laws-check.php', 'tests/gateway-smoke.php'] as $script) {
                $output = [];
                exec('php '.escapeshellarg("{$root}/{$script}").' 2>&1', $output, $code);
                $this->assertSame(0, $code, "{$template} {$script}:\n".implode("\n", $output));
            }

            File::deleteDirectory($root);
            File::delete(storage_path("app/generated/{$slug}.zip"));
        }
    }

    public function test_the_laws_check_catches_a_crm_list_view_without_date_created_first(): void
    {
        $this->post('/apps', ['name' => 'Broken List', 'template' => 'blank']);
        $this->post('/apps/broken-list/generate')->assertOk();

        $root = storage_path('app/generated/broken-list');
        $file = "{$root}/crm/modules/brokenli_Records/metadata/listviewdefs.php";
        File::put($file, "<?php\n\$listViewDefs['brokenli_Records'] = ['NAME' => [], 'DATE_ENTERED' => []];\n");

        exec('php '.escapeshellarg("{$root}/bin/laws-check.php").' 2>&1', $output, $code);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('must list DATE_ENTERED first', implode("\n", $output));
        File::deleteDirectory($root);
    }

    public function test_module_mistakes_show_what_to_fix(): void
    {
        $this->post('/apps', ['name' => 'Bad Modules', 'template' => 'blank']);

        $this->from('/apps/bad-modules/modules')
            ->put('/apps/bad-modules/modules', ['modules' => "Tasks: owner:relate(People)\nPeople: deleted"])
            ->assertRedirect('/apps/bad-modules/modules')
            ->assertSessionHasErrors('modules');
    }

    public function test_the_app_list_puts_date_created_first(): void
    {
        $this->post('/apps', ['name' => 'HR CRM', 'template' => 'hr-crm']);

        $this->get('/apps')->assertOk()->assertSeeInOrder(['Date Created', 'App', 'seconds ago', 'HR CRM']);
    }

    public function test_the_library_lists_constitutions_and_laws_from_the_playbook(): void
    {
        $this->get('/library')->assertOk()->assertSee('Laws are bound to code')->assertSee('L-SEC-001');
    }

    public function test_the_builder_needs_the_password_when_one_is_set(): void
    {
        config(['builder.password' => 'secret']);

        $this->get('/apps')->assertRedirect('/login');
        $this->post('/login', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/login', ['password' => 'secret'])->assertRedirect('/apps');
        $this->get('/apps')->assertOk();
    }

    public function test_the_builder_keeps_its_own_laws(): void
    {
        $this->artisan('laws:check')->assertSuccessful();
    }
}
