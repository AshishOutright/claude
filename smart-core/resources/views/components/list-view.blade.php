{{-- L-UI-001: every list starts with Date Created, shown as relative time. --}}
@props(['rows', 'columns' => [], 'createdField' => 'created_at', 'hrefField' => null])
<table {{ $attributes->merge(['class' => 'smartcore-list']) }}>
    <thead>
        <tr>
            <th>Date Created</th>
            @foreach ($columns as $label)
                <th>{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td title="{{ data_get($row, $createdField) }}">{{ \Illuminate\Support\Carbon::parse(data_get($row, $createdField))->diffForHumans() }}</td>
                @foreach ($columns as $field => $label)
                    @if ($loop->first && $hrefField && data_get($row, $hrefField))
                        <td><a href="{{ data_get($row, $hrefField) }}">{{ data_get($row, $field) }}</a></td>
                    @else
                        <td>{{ data_get($row, $field) }}</td>
                    @endif
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) + 1 }}">Nothing here yet.</td></tr>
        @endforelse
    </tbody>
</table>
