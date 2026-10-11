@extends('layouts.app')

@section('content')
    <h1>Constitutions and laws</h1>
    <p class="lead">The base library every new app picks from. Seeded from the playbook sheet; anything added while building an app shows up here too.</p>

    <div class="grid2">
        <div class="panel">
            <h2 style="margin-top:0">Constitutions ({{ $constitutions->count() }})</h2>
            @foreach ($constitutions as $item)
                <div class="library-item">
                    <span class="code">{{ $item->code }}</span> <strong>{{ $item->title }}</strong>
                    @if ($item->is_core)<span class="tag">core</span>@endif
                    <div class="hint">{{ $item->reason }}</div>
                    @if ($item->source)<div class="hint">Source: {{ $item->source }}</div>@endif
                </div>
            @endforeach
        </div>
        <div class="panel">
            <h2 style="margin-top:0">Laws ({{ $laws->flatten()->count() }})</h2>
            @foreach ($laws as $category => $items)
                <h3>{{ $categories[$category] ?? $category }}</h3>
                @foreach ($items as $item)
                    <div class="library-item">
                        <span class="code">{{ $item->code }}</span> <strong>{{ $item->title }}</strong>
                        @if ($item->is_core)<span class="tag">core</span>@endif
                        @if ($item->checked_by_core)<span class="tag ok">checked</span>@endif
                        @if ($item->source)<div class="hint">Source: {{ $item->source }}</div>@endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
@endsection
