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

        foreach (['basics', 'constitutions', 'laws', 'vision', 'journey', 'acfcm', 'review'] as $step) {
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
        ])->assertRedirect('/apps/task-crm/journey');

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

    public function test_generate_builds_a_starter_app_on_smart_core(): void
    {
        $this->post('/apps', ['name' => 'HR CRM', 'template' => 'hr-crm']);
        $this->put('/apps/hr-crm/vision', ['core_vision' => 'Hire to exit in one place.', 'team_vision' => 'One record per employee, from offer to full and final.']);

        $response = $this->post('/apps/hr-crm/generate')->assertOk()->assertDownload('hr-crm-starter.zip');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        foreach ([
            'hr-crm-starter/install.sh',
            'hr-crm-starter/app-files/CLAUDE.md',
            'hr-crm-starter/app-files/config/workflow.php',
            'hr-crm-starter/app-files/config/laws.php',
            'hr-crm-starter/app-files/app/Workflow/Phases/OnboardingPhase.php',
            'hr-crm-starter/app-files/app/Workflow/Stages/Exit/FullAndFinalStage.php',
            'hr-crm-starter/app-files/app/Services/Ai/AiSuites.php',
        ] as $expected) {
            $this->assertContains($expected, $names);
        }

        $this->assertStringContainsString('One record per employee', $zip->getFromName('hr-crm-starter/app-files/CLAUDE.md'));
        $this->assertStringContainsString("new Status('paid', 'Paid', final: true)", $zip->getFromName('hr-crm-starter/app-files/app/Workflow/Stages/Exit/FullAndFinalStage.php'));
        $this->assertNotNull(SaasApp::where('slug', 'hr-crm')->value('generated_at'));
    }

    public function test_every_generated_php_file_is_valid_php(): void
    {
        $this->post('/apps', ['name' => 'Social CRM', 'template' => 'social-crm']);
        $this->post('/apps/social-crm/generate')->assertOk();

        $root = storage_path('app/generated/social-crm-starter');

        foreach (File::allFiles($root, true) as $file) {
            if (str_ends_with($file->getFilename(), '.php')) {
                exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $code);
                $this->assertSame(0, $code, implode("\n", $output));
            }
        }

        File::deleteDirectory($root);
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
