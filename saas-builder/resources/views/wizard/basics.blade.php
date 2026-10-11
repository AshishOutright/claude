@extends('layouts.wizard')

@section('step')
    <h2 style="margin-top:0">App basics</h2>
    <label for="name">App name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $app->name) }}" required>

    <label for="summary">One line about it</label>
    <input id="summary" type="text" name="summary" value="{{ old('summary', $app->summary) }}">

    <p class="hint">Short name: <code>{{ $app->slug }}</code>. Starting journey: {{ $templates[$app->template]['label'] ?? $app->template }}.</p>
@endsection
