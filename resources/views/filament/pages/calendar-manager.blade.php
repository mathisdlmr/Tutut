<x-filament::page>
    <div class="space-y-6">
        {{ $this->form }}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
    <div class="flex justify-between items-center mb-4">
        @if($previousMonth)
            <button wire:click="changeMonth('{{ $previousMonth }}')" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                <span>&larr; {{ __('pages.calendar_manager.previous_month') }}</span>
            </button>
        @else
            <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 text-gray-400 dark:text-gray-600 cursor-not-allowed rounded-md">
                <span>&larr; {{ __('pages.calendar_manager.previous_month') }}</span>
            </div>
        @endif
        <h2 class="text-xl font-semibold text-center capitalize">{{ $monthName }}</h2>
    </div>
    <div class="flex flex-wrap gap-x-4 gap-y-1 mb-4 text-sm text-gray-600 dark:text-gray-300">
        <span class="flex items-center gap-x-1.5">
            <span class="inline-block h-3 w-3 rounded-sm exam-period"></span>
            {{ __('pages.calendar_manager.legend_exam_period') }}
        </span>
        <span class="flex items-center gap-x-1.5">
            <span class="inline-block h-3 w-3 rounded-sm vacances-period"></span>
            {{ __('pages.calendar_manager.legend_vacances') }}
        </span>
        <span class="flex items-center gap-x-1.5">
            <span class="inline-block h-3 w-3 rounded-sm day-modified"></span>
            {{ __('pages.calendar_manager.legend_modified') }}
        </span>
    </div>
    <div>
        @if($nextMonth)
            <button wire:click="changeMonth('{{ $nextMonth }}')" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                <span>{{ __('pages.calendar_manager.next_month') }} &rarr;</span>
            </button>
        @else
            <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 text-gray-400 dark:text-gray-600 cursor-not-allowed rounded-md">
                <span>{{ __('pages.calendar_manager.next_month') }} &rarr;</span>
            </div>
        @endif
    </div>
    <div class="grid grid-cols-7 gap-1">
        @foreach ($daysOfWeek as $dayName)
            <div class="p-1 text-center font-medium text-gray-600 dark:text-gray-300">
                {{ $dayName }}
            </div>
        @endforeach
        @foreach ($weeks as $week)
            @foreach ($week as $day)
                @php
                    $infoClass = $day['override']
                        ? 'day-modified'
                        : (($day['isVacances'] ?? false)
                            ? 'vacances-period'
                            : (($day['isExamPeriod'] ?? false) ? 'exam-period' : ''));
                @endphp
                <div
                    @if($day['inActiveSemestre'])
                        wire:click="selectDate('{{ $day['date'] }}')"
                        class="aspect-square border rounded-md p-2 relative cursor-pointer transition-colors
                    @else
                        class="aspect-square border rounded-md p-2 relative cursor-not-allowed transition-colors
                    @endif
                    {{ $day['isCurrentMonth'] ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900 text-gray-400 dark:text-gray-600' }}
                    {{ $infoClass }}
                    {{ $day['isSelected'] ? 'ring-2 ring-primary-500' : '' }}
                    {{ !$day['inActiveSemestre'] ? 'opacity-50' : '' }}
                    {{ $day['isToday'] ? 'today' : 'border-gray-200 dark:border-gray-700' }}"
                >
                    <div class="flex justify-between items-center">
                        <span class="text-xl font-medium">{{ $day['day'] }}</span>
                        @if ($day['override'])
                            @if ($day['override']['is_holiday'])
                                <span class="text-base text-red-600 dark:text-red-400 font-medium">{{ __('pages.calendar_manager.holiday') }}</span>
                            @elseif ($day['override']['day_template'])
                                <span class="text-base text-blue-600 dark:text-blue-400 font-medium">
                                    {{ ucfirst(substr($day['override']['day_template'], 0, 3)) }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
</x-filament::page>

<style>
    .today {  /* On utilise une nouvelle classe pour bypass le JIT de Tailwind */
        background-color:rgba(59, 131, 246, 0.2);
        border-color: #3b82f6;
        border-width: 2px;
    }

    /* Mêmes classes "bypass JIT" pour les couleurs d'information du calendrier */
    .exam-period {
        background-color: rgba(248, 113, 113, 0.2); /* red-400, faible opacité */
    }

    .vacances-period {
        background-color: rgba(96, 165, 250, 0.25); /* blue-400 */
    }

    .day-modified {
        background-color: rgba(74, 222, 128, 0.25); /* green-400 */
    }
</style>