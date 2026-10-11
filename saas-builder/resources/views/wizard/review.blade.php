@extends('layouts.wizard')

@section('step')
    <div class="panel">
        <h2 style="margin-top:0">Review and generate</h2>
        <dl class="summary">
            <dt>Simplified vision</dt>
            <dd>{{ $app->team_vision ?: 'Not written yet. Add it in Core Vision.' }}</dd>
            <dt>Constitution ({{ $app->constitutions->count() }})</dt>
            <dd>{{ $app->constitutions->map(fn ($c) => $c->code.' '.$c->title)->implode("\n") ?: 'None chosen' }}</dd>
            <dt>Laws ({{ $app->laws->count() }}, {{ $app->laws->where('checked_by_core', true)->count() }} checked automatically)</dt>
            <dd>{{ $app->laws->map(fn ($l) => $l->code.' '.$l->title)->implode("\n") ?: 'None chosen' }}</dd>
            <dt>CRM modules ({{ $app->modules->count() }})</dt>
            <dd>{{ $app->modules->map(fn ($m) => $m->name.($m->is_journey ? ' (journey)' : '').': '.$m->fields->pluck('label')->implode(', '))->implode("\n") }}</dd>
            <dt>Journey</dt>
            <dd>
                <div class="journey-preview">
                    @foreach ($app->phases as $phase)
                        <div class="phase"><strong>{{ $phase->name }}</strong>
                            @foreach ($phase->stages as $stage)
                                <div class="stage">{{ $stage->name }}<br>
                                    @foreach ($stage->statuses as $status)<span class="status {{ $status->is_final ? 'final' : '' }}">{{ $status->name }}</span>@endforeach
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </dd>
            <dt>ACFCM</dt>
            <dd>@foreach ($acfcm as $pillar => $meta){{ $meta['label'] }}: {{ $app->acfcmChoices->where('pillar', $pillar)->count() }} items
@endforeach</dd>
        </dl>

        @if (! $app->core_vision || ! $app->team_vision)
            <p class="errors">The Core Vision is still empty. You can generate now, but the docs and CLAUDE.md will say "Not written yet".</p>
        @endif

        <form method="post" action="{{ route('apps.generate', $app) }}" class="actions">
            @csrf
            <button class="btn">Generate the app (.zip)</button>
            @if ($app->generated_at)<span class="hint">Last generated {{ $app->generated_at->diffForHumans() }}</span>@endif
        </form>
    </div>

    <div class="panel" style="margin-top:16px">
        <h2 style="margin-top:0">What you get ({{ $files->count() }} files, plus Smart Core)</h2>
        <p class="hint">
            <strong>crm/</strong> goes into your SuiteCRM copy (<code>./install-crm.sh /path/to/suitecrm</code>), including the Smart Gateway at
            <code>index.php?entryPoint=smart_gateway</code>. <strong>frontend/</strong> is the React app, which talks only to the gateway.
        </p>
        <ul class="files">
            @foreach ($files as $file)<li>{{ $file }}</li>@endforeach
        </ul>
    </div>
@endsection
