<?php

namespace SmartCore;

use Illuminate\Support\ServiceProvider;
use SmartCore\Console\CheckLaws;
use SmartCore\Laws\LawRegistry;
use SmartCore\Workflow\SelectPhase;
use SmartCore\Workflow\SelectStage;
use SmartCore\Workflow\Workflow;

class SmartCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Workflow::class, fn ($app) => new Workflow($app['config']->get('workflow.phases', [])));
        $this->app->singleton(SelectPhase::class);
        $this->app->singleton(SelectStage::class);
        $this->app->singleton(LawRegistry::class, fn ($app) => new LawRegistry(
            $app['config']->get('laws.enabled', []),
            $app->make(SelectPhase::class),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'smartcore');

        if ($this->app->runningInConsole()) {
            $this->commands([CheckLaws::class]);
        }
    }
}
