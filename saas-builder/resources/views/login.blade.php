@extends('layouts.app')

@section('content')
    <div class="panel" style="max-width: 380px; margin: 40px auto;">
        <h1>Log in</h1>
        <form method="post" action="{{ url('/login') }}">
            @csrf
            <label for="password">Builder password</label>
            <input id="password" type="password" name="password" autofocus required>
            <div class="actions"><button class="btn">Log in</button></div>
        </form>
    </div>
@endsection
