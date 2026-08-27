@props(['stat'])

<div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
    @if ($stat['linkable'])
        <a href="{{ $stat['url'] }}" class="text-2xl font-semibold text-primary-600 hover:underline dark:text-primary-400">
            {{ $stat['display'] }}
        </a>
    @else
        <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $stat['display'] }}</p>
    @endif
</div>
