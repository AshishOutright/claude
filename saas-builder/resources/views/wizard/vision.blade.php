@extends('layouts.wizard')

@section('step')
    <h2 style="margin-top:0">Core Vision</h2>
    <label for="core_vision">Core Vision <span class="hint">what end users can expect from the app, in full</span></label>
    <textarea id="core_vision" name="core_vision" required>{{ old('core_vision', $app->core_vision) }}</textarea>

    <label for="team_vision">Simplified for the team <span class="hint">two or three lines everyone on the team can repeat</span></label>
    <textarea id="team_vision" name="team_vision" required style="min-height:80px">{{ old('team_vision', $app->team_vision) }}</textarea>

    <div class="grid2">
        <div>
            <label for="vision_before">Before <span class="hint">the user's day without the app</span></label>
            <textarea id="vision_before" name="vision_before">{{ old('vision_before', $app->vision_before) }}</textarea>
        </div>
        <div>
            <label for="vision_after">After <span class="hint">the user's day with it</span></label>
            <textarea id="vision_after" name="vision_after">{{ old('vision_after', $app->vision_after) }}</textarea>
        </div>
    </div>
@endsection
