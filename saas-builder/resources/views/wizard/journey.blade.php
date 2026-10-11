@extends('layouts.wizard')

@section('step')
    <h2 style="margin-top:0">Phases, stages and statuses</h2>
    <p class="hint">One line per phase. Under it, one line per stage: <code>- Stage: Status, Status, Final*</code>. A <code>*</code> marks a final status; every stage needs one so records can move on.</p>

    <div class="grid2">
        <div>
            <label for="outline">Journey</label>
            <textarea id="outline" class="outline" name="outline" required>{{ $outline }}</textarea>
            <p class="hint">Load a template:
                @foreach ($templates as $key => $template)
                    <button type="button" class="btn link" data-template="{{ $key }}">{{ $template['label'] }}</button>@if (! $loop->last) · @endif
                @endforeach
            </p>
        </div>
        <div>
            <label>Preview</label>
            <div id="preview" class="journey-preview"></div>
        </div>
    </div>

    <script>
        (function () {
            const templates = @json(collect($templates)->map(fn ($t) => $t['journey']));
            const box = document.getElementById('outline');
            const preview = document.getElementById('preview');

            function esc(s) { return s.replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c])); }

            function render() {
                let html = '';
                let open = false;
                box.value.split(/\r?\n/).forEach(raw => {
                    const line = raw.trim();
                    if (!line || line.startsWith('#')) return;
                    if (!line.startsWith('-')) {
                        if (open) html += '</div>';
                        html += '<div class="phase"><strong>' + esc(line) + '</strong>';
                        open = true;
                        return;
                    }
                    const [stage, list = ''] = line.slice(1).split(':');
                    const statuses = list.split(',').map(s => s.trim()).filter(Boolean)
                        .map(s => s.endsWith('*') ? '<span class="status final">' + esc(s.slice(0, -1)) + '</span>' : '<span class="status">' + esc(s) + '</span>').join('');
                    html += '<div class="stage">' + esc(stage.trim()) + '<br>' + statuses + '</div>';
                });
                preview.innerHTML = html + (open ? '</div>' : '');
            }

            document.querySelectorAll('[data-template]').forEach(btn => btn.addEventListener('click', () => {
                if (box.value.trim() === '' || confirm('Replace the journey with this template?')) {
                    box.value = templates[btn.dataset.template];
                    render();
                }
            }));
            box.addEventListener('input', render);
            render();
        })();
    </script>
@endsection
