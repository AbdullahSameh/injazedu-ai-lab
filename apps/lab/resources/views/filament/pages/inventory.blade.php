<x-filament-panels::page>
    <x-filament::callout
        icon="heroicon-o-exclamation-triangle"
        color="warning"
        :heading="__('console.inventory.limits_title')"
    >
        <ul class="list-disc ps-4 space-y-1">
            <li>{{ __('console.inventory.limits_media') }}</li>
            <li>{{ __('console.inventory.limits_answer_gap') }}</li>
        </ul>
    </x-filament::callout>

    <x-filament::section :heading="__('console.inventory.quality_cards')">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($cards as $card)
                @include('filament.pages.partials.inventory-stat', ['stat' => $card])
            @endforeach
        </div>
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-filament::section :heading="__('console.inventory.answer_key_integrity')">
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($answerKeyIntegrity as $row)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ $row['label'] }}</span>
                        @include('filament.pages.partials.inventory-number', ['row' => $row])
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        <x-filament::section :heading="__('console.inventory.option_count_distribution')">
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($optionCountDistribution as $row)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ $row['options_count'] }}</span>
                        @include('filament.pages.partials.inventory-number', ['row' => $row])
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('console.inventory.by_category')">
        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($byCategory as $row)
                <li class="flex items-center justify-between py-2">
                    <span>{{ $row['label'] }}</span>
                    @include('filament.pages.partials.inventory-number', ['row' => $row])
                </li>
            @endforeach
        </ul>
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-filament::section :heading="__('console.inventory.by_course')">
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($byCourse['rows'] as $row)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ $row['label'] }}</span>
                        @include('filament.pages.partials.inventory-number', ['row' => $row])
                    </li>
                @endforeach
            </ul>
            @if ($byCourse['total_groups'] > count($byCourse['rows']))
                <x-filament::link :href="$byCourse['view_all_url']" class="mt-3 inline-block">
                    {{ __('console.inventory.view_all', ['count' => $byCourse['total_groups']]) }}
                </x-filament::link>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('console.inventory.by_quiz')">
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($byQuiz['rows'] as $row)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ $row['label'] }}</span>
                        @include('filament.pages.partials.inventory-number', ['row' => $row])
                    </li>
                @endforeach
            </ul>
            @if ($byQuiz['total_groups'] > count($byQuiz['rows']))
                <x-filament::link :href="$byQuiz['view_all_url']" class="mt-3 inline-block">
                    {{ __('console.inventory.view_all', ['count' => $byQuiz['total_groups']]) }}
                </x-filament::link>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('console.nav.import_errors')">
        <x-filament::link :href="$this->getImportErrorsUrl()">
            {{ __('console.inventory.view_import_errors') }}
        </x-filament::link>
    </x-filament::section>
</x-filament-panels::page>
