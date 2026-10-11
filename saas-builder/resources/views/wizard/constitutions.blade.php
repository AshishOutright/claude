@extends('layouts.wizard')

@section('step')
    <h2 style="margin-top:0">Constitution</h2>
    <p class="hint">The app's boundaries and high-level expectations. Core ones come ticked; untick what this app does not need.</p>

    @php $chosen = old('constitutions', $app->constitutions->pluck('id')->all()); @endphp
    @foreach ($constitutions as $item)
        <label class="choice">
            <input type="checkbox" name="constitutions[]" value="{{ $item->id }}" @checked(in_array($item->id, $chosen))>
            <span>
                <span class="code">{{ $item->code }}</span>
                <span class="title">{{ $item->title }}</span>
                @if ($item->is_core)<span class="tag">core</span>@endif
                <div class="meta">{{ $item->reason }}@if ($item->example) · e.g. {{ $item->example }}@endif</div>
            </span>
        </label>
    @endforeach

    <h2>Add a new one</h2>
    <p class="hint">It is saved to the library, so later apps can pick it too.</p>
    <div class="grid2">
        <div><label for="new_title">Constitution</label><input id="new_title" type="text" name="new_title" value="{{ old('new_title') }}"></div>
        <div><label for="new_reason">Why</label><input id="new_reason" type="text" name="new_reason" value="{{ old('new_reason') }}"></div>
    </div>
@endsection
