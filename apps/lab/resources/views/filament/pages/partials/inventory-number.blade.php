@props(['row'])

@if ($row['linkable'])
    <a href="{{ $row['url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
        {{ $row['display'] }}
    </a>
@else
    <span class="font-medium text-gray-500 dark:text-gray-400">{{ $row['display'] }}</span>
@endif
