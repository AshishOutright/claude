@extends('layouts.wizard')

@section('step')
    <h2 style="margin-top:0">ACFCM</h2>
    <p class="hint">The bar every app is built to. Each ticked item adds code or config to the starter app.</p>

    @foreach ($acfcm as $pillar => $meta)
        @php
            $chosen = old("items.{$pillar}", $app->acfcmChoices->where('pillar', $pillar)->whereNotNull('item_key')->pluck('item_key')->all());
            $custom = old("custom.{$pillar}", $app->acfcmChoices->where('pillar', $pillar)->whereNull('item_key')->pluck('label')->implode("\n"));
        @endphp
        <div class="pillar">
            <h3>{{ $meta['label'] }}</h3>
            <p class="hint" style="margin:0 0 8px">{{ $meta['help'] }}</p>
            @foreach ($meta['items'] as $key => $label)
                <label class="choice">
                    <input type="checkbox" name="items[{{ $pillar }}][]" value="{{ $key }}" @checked(in_array($key, $chosen))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
            <label for="custom-{{ $pillar }}" class="hint">More for this app, one per line</label>
            <textarea id="custom-{{ $pillar }}" name="custom[{{ $pillar }}]" style="min-height:60px">{{ $custom }}</textarea>
        </div>
    @endforeach
@endsection
