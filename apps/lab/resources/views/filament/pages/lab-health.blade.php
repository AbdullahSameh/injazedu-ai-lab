<x-filament-panels::page>
    <div>
        <x-filament::button wire:click="runHealth" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="runHealth">Run health checks</span>
            <span wire:loading wire:target="runHealth">Running health checks…</span>
        </x-filament::button>
    </div>

    @if ($hasRun)
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Check</th>
                        <th class="px-4 py-3">Target</th>
                        <th class="px-4 py-3">Expectation</th>
                        <th class="px-4 py-3">Outcome</th>
                        <th class="px-4 py-3">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($results as $result)
                        <tr wire:key="health-result-{{ $result['number'] }}">
                            <td class="px-4 py-3">{{ $result['number'] }}</td>
                            <td class="px-4 py-3 font-medium">{{ $result['name'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $result['target'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $result['expectation'] }}</td>
                            <td class="px-4 py-3 font-semibold uppercase">{{ $result['outcome'] }}</td>
                            <td class="px-4 py-3">{{ $result['detail'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-gray-500 dark:text-gray-400">
            No health run yet. Press the button to run all ten checks on demand.
        </p>
    @endif
</x-filament-panels::page>
