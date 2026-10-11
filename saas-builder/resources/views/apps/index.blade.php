@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Apps</h1>
            <p class="lead">Each app starts from the base constitutions and laws, then gets its vision, journey and ACFCM bar.</p>
        </div>
        <a class="btn" href="{{ route('apps.create') }}">Start a new app</a>
    </div>

    @php
        $rows = $apps->map(fn ($app) => [
            'created_at' => $app->created_at,
            'name' => $app->name,
            'progress' => $app->generated_at ? 'Generated '.$app->generated_at->diffForHumans() : \App\Services\Builder\WizardSteps::STEPS[$app->current_step],
            'url' => route('wizard.show', [$app, $app->generated_at ? 'review' : $app->current_step]),
        ]);
    @endphp

    @if ($apps->isEmpty())
        <div class="panel">No apps yet. <a href="{{ route('apps.create') }}">Start the first one</a>.</div>
    @else
        <x-smartcore::list-view :rows="$rows" :columns="['name' => 'App', 'progress' => 'Where it is']" href-field="url" />
    @endif
@endsection
