<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SaaS Builder')</title>
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}">
</head>
<body>
    <header class="top">
        <a class="brand" href="{{ route('apps.index') }}">SaaS Builder</a>
        @if (session('builder_authenticated') || ! config('builder.password'))
            <nav>
                <a href="{{ route('apps.index') }}">Apps</a>
                <a href="{{ route('library') }}">Constitutions and laws</a>
            </nav>
            @if (config('builder.password'))
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn link">Log out</button></form>
            @endif
        @endif
    </header>
    <main>
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</body>
</html>
