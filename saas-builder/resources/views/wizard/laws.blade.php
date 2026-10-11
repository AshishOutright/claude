@extends('layouts.wizard')

@section('step')
    <h2 style="margin-top:0">Laws</h2>
    <p class="hint">UI and development boundaries. Laws marked <span class="tag ok">checked</span> fail the build when broken (<code>php artisan laws:check</code>); the rest are checked in review.</p>

    @php $chosen = old('laws', $app->laws->pluck('id')->all()); @endphp
    @foreach ($laws as $category => $items)
        <h2>{{ $categories[$category] ?? $category }}</h2>
        @foreach ($items as $item)
            <label class="choice">
                <input type="checkbox" name="laws[]" value="{{ $item->id }}" @checked(in_array($item->id, $chosen))>
                <span>
                    <span class="code">{{ $item->code }}</span>
                    <span class="title">{{ $item->title }}</span>
                    @if ($item->is_core)<span class="tag">core</span>@endif
                    @if ($item->checked_by_core)<span class="tag ok">checked</span>@endif
                    @if ($item->example)<div class="meta">e.g. {{ $item->example }}</div>@endif
                </span>
            </label>
        @endforeach
    @endforeach

    <h2>Add a new law</h2>
    <div class="grid2">
        <div><label for="new_title">Law</label><input id="new_title" type="text" name="new_title" value="{{ old('new_title') }}"></div>
        <div>
            <label for="new_category">Area</label>
            <select id="new_category" name="new_category">
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected(old('new_category') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <label for="new_example">Example</label>
    <input id="new_example" type="text" name="new_example" value="{{ old('new_example') }}">
@endsection
