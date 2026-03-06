<?php

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Models\CalendarOverride;
use App\Models\Semestre;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Page de gestion du calendrier
 *
 * Cette page permet aux administrateurs et tuteurs privilégiés de gérer le calendrier,
 * en définissant des exceptions dans le planning (jours fériés, modifications ponctuelles).
 * Fonctionnalités:
 * - Visualisation du calendrier par mois
 * - Possibilité de marquer des jours comme jours fériés
 * - Possibilité de changer le template de jour (ex: faire suivre un planning de lundi un mardi)
 * - Navigation entre les mois dans les limites du semestre actif
 */
class CalendarManager extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    public static function getNavigationLabel(): string
    {
        return __('pages.calendar_manager.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('resources.admin.navigation_group.gestion');
    }

    public function getTitle(): string
    {
        return __('pages.calendar_manager.title');
    }

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.calendar-manager';

    public ?array $overrides = [];
    public ?array $daysOfWeek = [];
    public ?string $currentMonth = null;
    public ?string $selectedDate = null;
    public ?string $selectedDayTemplate = null;
    public bool $isHoliday = false;
    public ?string $semestreStartMonth = null;
    public ?string $semestreEndMonth = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::Administrator->value ||
               Auth::user()->role === Roles::EmployedPrivilegedTutor->value);
    }

    protected function getFormSchema(): array
    {
        $activeSemestre = Semestre::getActive();
        $dateConstraints = [];

        if ($activeSemestre) {
            $dateConstraints = [
                DatePicker::make('selectedDate')
                    ->label(__('pages.calendar_manager.selected_date'))
                    ->required()
                    ->minDate($activeSemestre->debut)
                    ->maxDate($activeSemestre->fin)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        if ($state) {
                            $this->loadExistingOverride($state);
                        }
                    }),
            ];
        } else {
            $dateConstraints = [
                DatePicker::make('selectedDate')
                    ->label(__('pages.calendar_manager.selected_date'))
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        if ($state) {
                            $this->loadExistingOverride($state);
                        }
                    }),
            ];
        }

        return [
            Forms\Components\Section::make(__('pages.calendar_manager.schedule_modification'))
                ->description(__('pages.calendar_manager.schedule_description'))
                ->schema([
                    Forms\Components\Card::make()
                        ->schema([
                            ...$dateConstraints,
                            Forms\Components\Grid::make()
                                ->schema([
                                    Toggle::make('isHoliday')
                                        ->label(__('pages.calendar_manager.holiday'))
                                        ->helperText(__('pages.calendar_manager.holiday_help_text'))
                                        ->reactive()
                                        ->afterStateUpdated(function ($state) {
                                            if ($state) {
                                                $this->selectedDayTemplate = null;
                                            }
                                        }),
                                    Select::make('selectedDayTemplate')
                                        ->label(__('pages.calendar_manager.day_template'))
                                        ->options([
                                            'Lundi' => __('pages.calendar_manager.days.monday'),
                                            'Mardi' => __('pages.calendar_manager.days.tuesday'),
                                            'Mercredi' => __('pages.calendar_manager.days.wednesday'),
                                            'Jeudi' => __('pages.calendar_manager.days.thursday'),
                                            'Vendredi' => __('pages.calendar_manager.days.friday'),
                                            'Samedi' => __('pages.calendar_manager.days.saturday'),
                                            'Dimanche' => __('pages.calendar_manager.days.sunday'),
                                        ])
                                        ->placeholder(__('pages.calendar_manager.select_day'))
                                        ->disabled(fn () => $this->isHoliday)
                                        ->reactive()
                                        ->afterStateUpdated(function ($state) {
                                            if ($state) {
                                                $this->isHoliday = false;
                                            }
                                        }),
                                ])
                                ->columns(2),
                        ])
                        ->columns(1),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('save')
                            ->label(__('pages.calendar_manager.save'))
                            ->action('saveOverride')
                            ->color('primary')
                            ->disabled(fn () => !$this->selectedDate || ($this->isHoliday === false && empty($this->selectedDayTemplate))),
                        Forms\Components\Actions\Action::make('delete')
                            ->label(__('pages.calendar_manager.delete'))
                            ->action('deleteOverride')
                            ->color('danger')
                            ->visible(fn () => $this->overrideExists($this->selectedDate)),
                    ]),
                ])
                ->columnSpanFull(),
        ];
    }

    protected function getViewData(): array
    {
        $this->initializeCalendar();

        $activeSemestre = Semestre::getActive();

        $locale = app()->getLocale();
        $carbonLocale = $locale === 'fr' ? 'fr_FR' : 'en_US';

        return [
            'monthName' => Carbon::parse($this->currentMonth)->locale($carbonLocale)->isoFormat('MMMM YYYY'),
            'daysOfWeek' => $this->daysOfWeek,
            'weeks' => $this->generateCalendarData(),
            'previousMonth' => $this->canNavigateToPreviousMonth() ? Carbon::parse($this->currentMonth)->subMonth()->format('Y-m') : null,
            'nextMonth' => $this->canNavigateToNextMonth() ? Carbon::parse($this->currentMonth)->addMonth()->format('Y-m') : null,
            'hasActiveSemestre' => $activeSemestre !== null,
        ];
    }

    public function mount()
    {
        $activeSemestre = Semestre::getActive();

        if ($activeSemestre) {
            $semestreStart = Carbon::parse($activeSemestre->debut);
            $semestreEnd = Carbon::parse($activeSemestre->fin);

            $this->semestreStartMonth = $semestreStart->format('Y-m');
            $this->semestreEndMonth = $semestreEnd->format('Y-m');

            $now = now();
            if ($now->lt($semestreStart)) {
                $this->currentMonth = $this->semestreStartMonth;
            } elseif ($now->gt($semestreEnd)) {
                $this->currentMonth = $this->semestreEndMonth;
            } else {
                $this->currentMonth = $now->format('Y-m');
            }
        } else {
            $this->currentMonth = now()->format('Y-m');
        }

        $this->initializeCalendar();
        $this->loadAllOverrides();
    }

    private function canNavigateToPreviousMonth()
    {
        if (!$this->semestreStartMonth) {
            return true;
        }

        $currentMonth = Carbon::parse($this->currentMonth);
        $semestreStart = Carbon::parse($this->semestreStartMonth);

        return $currentMonth->gt($semestreStart) ||
               ($currentMonth->year == $semestreStart->year && $currentMonth->month == $semestreStart->month);
    }

    private function canNavigateToNextMonth()
    {
        if (!$this->semestreEndMonth) {
            return true;
        }

        $currentMonth = Carbon::parse($this->currentMonth);
        $semestreEnd = Carbon::parse($this->semestreEndMonth);

        return $currentMonth->lt($semestreEnd) ||
               ($currentMonth->year == $semestreEnd->year && $currentMonth->month == $semestreEnd->month);
    }

    public function loadExistingOverride($date)
    {
        $override = CalendarOverride::where('date', Carbon::parse($date))->first();

        if ($override) {
            $this->isHoliday = $override->is_holiday;
            $this->selectedDayTemplate = $override->is_holiday ? null : $override->day_template;
        } else {
            $this->isHoliday = false;
            $this->selectedDayTemplate = null;
        }
    }

    public function overrideExists($date)
    {
        return $date && CalendarOverride::where('date', Carbon::parse($date))->exists();
    }

    public function selectDate($date)
    {
        $activeSemestre = Semestre::getActive();
        if ($activeSemestre) {
            $selectedDate = Carbon::parse($date);
            $debut = Carbon::parse($activeSemestre->debut);
            $fin = Carbon::parse($activeSemestre->fin);

            if ($selectedDate->lt($debut) || $selectedDate->gt($fin)) {
                return;
            }
        }

        $this->selectedDate = $date;
        $this->loadExistingOverride($date);
    }

    public function changeMonth($month)
    {
        if ($this->semestreStartMonth && $this->semestreEndMonth) {
            $targetMonth = Carbon::parse($month);
            $startMonth = Carbon::parse($this->semestreStartMonth);
            $endMonth = Carbon::parse($this->semestreEndMonth);

            if ($targetMonth->lt($startMonth->startOfMonth())) {
                $month = $this->semestreStartMonth;
            } elseif ($targetMonth->gt($endMonth->endOfMonth())) {
                $month = $this->semestreEndMonth;
            }
        }

        $this->currentMonth = $month;
        $this->loadAllOverrides();
    }

    public function saveOverride()
    {
        if (!$this->selectedDate) {
            return;
        }

        $activeSemestre = Semestre::getActive();
        if ($activeSemestre) {
            $selectedDate = Carbon::parse($this->selectedDate);
            $debut = Carbon::parse($activeSemestre->debut);
            $fin = Carbon::parse($activeSemestre->fin);

            if ($selectedDate->lt($debut) || $selectedDate->gt($fin)) {
                Notification::make()
                    ->title(__('pages.calendar_manager.date_out_of_range'))
                    ->body(__('pages.calendar_manager.date_must_be_between', [
                        'start' => $debut->format('d/m/Y'),
                        'end' => $fin->format('d/m/Y')
                    ]))
                    ->danger()
                    ->send();
                return;
            }
        }

        CalendarOverride::updateOrCreate(
            ['date' => Carbon::parse($this->selectedDate)],
            [
                'is_holiday' => $this->isHoliday,
                'day_template' => $this->isHoliday ? null : $this->selectedDayTemplate,
            ]
        );

        $this->loadAllOverrides();

        Notification::make()
            ->title(__('pages.calendar_manager.modification_saved'))
            ->success()
            ->send();
    }

    public function deleteOverride()
    {
        if (!$this->selectedDate) {
            return;
        }

        CalendarOverride::where('date', Carbon::parse($this->selectedDate))->delete();

        $this->loadAllOverrides();
        $this->isHoliday = false;
        $this->selectedDayTemplate = null;

        Notification::make()
            ->title(__('pages.calendar_manager.modification_deleted'))
            ->success()
            ->send();
    }

    private function initializeCalendar()
    {
        $locale = app()->getLocale();

        if ($locale === 'fr') {
            $this->daysOfWeek = [
                'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'
            ];
        } else {
            $this->daysOfWeek = [
                'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'
            ];
        }
    }

    private function loadAllOverrides()
    {
        $startDate = Carbon::parse($this->currentMonth)->startOfMonth();
        $endDate = Carbon::parse($this->currentMonth)->endOfMonth();

        $overrides = CalendarOverride::whereBetween('date', [$startDate, $endDate])->get();

        $this->overrides = [];
        foreach ($overrides as $override) {
            $this->overrides[$override->date->format('Y-m-d')] = [
                'is_holiday' => $override->is_holiday,
                'day_template' => $override->day_template,
            ];
        }
    }

    private function generateCalendarData()
    {
        $startOfMonth = Carbon::parse($this->currentMonth)->startOfMonth();
        $endOfMonth = Carbon::parse($this->currentMonth)->endOfMonth();

        $calendarStart = $startOfMonth->copy();
        if ($calendarStart->dayOfWeek !== 1) {
            $calendarStart->previous(Carbon::MONDAY);
        }

        $calendarEnd = $endOfMonth->copy();
        if ($calendarEnd->dayOfWeek !== 0) {
            $calendarEnd->next(Carbon::SUNDAY);
        }

        $activeSemestre = Semestre::getActive();
        $semestreStartDate = $activeSemestre ? Carbon::parse($activeSemestre->debut) : null;
        $semestreEndDate = $activeSemestre ? Carbon::parse($activeSemestre->fin) : null;

        $days = [];
        $currentDay = $calendarStart->copy();

        while ($currentDay <= $calendarEnd) {
            $weekNum = $currentDay->weekOfYear;

            if (!isset($days[$weekNum])) {
                $days[$weekNum] = [];
            }

            $date = $currentDay->format('Y-m-d');
            $isCurrentMonth = $currentDay->month === Carbon::parse($this->currentMonth)->month;
            $isToday = $currentDay->isToday();
            $isSelected = $date === $this->selectedDate;

            $inActiveSemestre = true;
            if ($activeSemestre) {
                $inActiveSemestre = $currentDay->between($semestreStartDate, $semestreEndDate);
            }

            $dayData = [
                'date' => $date,
                'day' => $currentDay->day,
                'isCurrentMonth' => $isCurrentMonth,
                'isToday' => $isToday,
                'isSelected' => $isSelected,
                'inActiveSemestre' => $inActiveSemestre,
                'override' => isset($this->overrides[$date]) ? $this->overrides[$date] : null,
            ];

            $days[$weekNum][] = $dayData;
            $currentDay->addDay();
        }

        return $days;
    }
}
