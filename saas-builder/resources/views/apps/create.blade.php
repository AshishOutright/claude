@extends('layouts.app')

@section('content')
    <h1>Start a new app</h1>
    <p class="lead">Name it and pick a starting journey. The core constitutions, laws and ACFCM items come ticked; you change them in the next steps.</p>

    <form class="panel" method="post" action="{{ route('apps.store') }}">
        @csrf
        <label for="name">App name</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="HR CRM" required>

        <label for="slug">Short name <span class="hint">used for folders and links; leave empty to use the app name</span></label>
        <input id="slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="hr-crm">

        <label for="summary">One line about it <span class="hint">optional</span></label>
        <input id="summary" type="text" name="summary" value="{{ old('summary') }}" placeholder="Hire, manage and exit employees in one place">

        <label>Starting journey</label>
        <div class="template-pick">
            @foreach ($templates as $key => $template)
                <label class="choice">
                    <input type="radio" name="template" value="{{ $key }}" @checked(old('template', 'blank') === $key)>
                    <span>{{ $template['label'] }}</span>
                </label>
            @endforeach
        </div>

        <div class="actions"><button class="btn">Start</button><a href="{{ route('apps.index') }}">Cancel</a></div>
    </form>
@endsection
