@extends('layouts.app')

@section('title', $app->name.' · SaaS Builder')

@section('content')
    <h1>{{ $app->name }}</h1>
    <p class="lead">{{ $app->summary ?: 'New app' }}</p>

    <div class="wizard">
        <aside>
            <ol class="steps">
                @php $reached = \App\Services\Builder\WizardSteps::number($app->current_step); @endphp
                @foreach (\App\Services\Builder\WizardSteps::STEPS as $key => $label)
                    @php $n = \App\Services\Builder\WizardSteps::number($key); @endphp
                    <li class="{{ $key === $step ? 'active' : ($n < $reached ? 'done' : '') }}">
                        <a href="{{ route('wizard.show', [$app, $key]) }}"><span class="n">{{ $n }}</span>{{ $label }}</a>
                    </li>
                @endforeach
            </ol>
        </aside>

        <section>
            @if ($step === 'review')
                @yield('step')
            @else
                <form class="panel" method="post" action="{{ route('wizard.update', [$app, $step]) }}">
                    @csrf
                    @method('put')
                    @yield('step')
                    <div class="actions"><button class="btn">Save and continue</button></div>
                </form>
            @endif
        </section>
    </div>
@endsection
