@extends('layouts.wizard')

@section('step')
    <h2 style="margin-top:0">CRM modules</h2>
    <p class="hint">
        The modules your CRM copy stores. One module per line: <code>Module: field, field:type, field:type!</code>.
        Mark the module that moves through the journey with <code>*</code> and required fields with <code>!</code>.
        Every module already has a required <code>name</code>, Date Created and an assigned user.
    </p>
    <p class="hint">
        Types: <code>varchar</code> (default), <code>text</code>, <code>int</code>, <code>decimal</code>, <code>date</code>, <code>datetime</code>,
        <code>bool</code>, <code>email</code>, <code>phone</code>, <code>url</code>, <code>enum(A|B|C)</code>, <code>relate(Module)</code>.
        A required <code>relate</code> means the record cannot be saved without it (law L-CODE-001).
    </p>

    <label for="modules">Modules</label>
    <textarea id="modules" class="outline" name="modules" required style="min-height:200px">{{ $modulesOutline }}</textarea>
    <p class="hint">Load a template:
        @foreach ($templates as $key => $template)
            <button type="button" class="btn link" data-modules="{{ $key }}">{{ $template['label'] }}</button>@if (! $loop->last) · @endif
        @endforeach
    </p>

    @if ($app->modules->isNotEmpty())
        <h2>Saved modules</h2>
        <div class="journey-preview">
            @foreach ($app->modules as $module)
                <div class="phase">
                    <strong>{{ $module->name }}</strong>
                    @if ($module->is_journey)<span class="tag">journey</span>@endif
                    <span class="hint">CRM module {{ $module->crmKey($app->crmPrefix()) }}</span>
                    <div>
                        @foreach ($module->fields as $field)
                            <span class="status {{ $field->is_required ? 'final' : '' }}">{{ $field->label }} · {{ $field->type }}@if ($field->type === 'relate') → {{ $field->type_arg }}@endif</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <script>
        (function () {
            const templates = @json(collect($templates)->map(fn ($t) => $t['modules']));
            const box = document.getElementById('modules');
            document.querySelectorAll('[data-modules]').forEach(btn => btn.addEventListener('click', () => {
                if (box.value.trim() === '' || confirm('Replace the modules with this template?')) {
                    box.value = templates[btn.dataset.modules];
                }
            }));
        })();
    </script>
@endsection
