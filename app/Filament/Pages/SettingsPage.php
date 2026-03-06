<?php

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Models\UV;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Page de paramètres généraux
 *
 * Cette page permet aux administrateurs et tuteurs privilégiés de configurer
 * les paramètres globaux de l'application.
 * Fonctionnalités:
 * - Configuration des horaires d'inscription pour chaque type d'utilisateur
 * - Paramétrage des délais d'annulation de créneaux
 * - Gestion du catalogue des UVs (unités de valeur)
 * - Mise à jour automatique des UVs depuis une API externe
 */
class SettingsPage extends Page implements Tables\Contracts\HasTable, Forms\Contracts\HasForms
{
    use Tables\Concerns\InteractsWithTable;
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static string $view = 'filament.pages.settings-page';
    protected static ?int $navigationSort = 4;

    public $employedTutorRegistrationDay;
    public $employedTutorRegistrationTime;
    public $tutorRegistrationDay;
    public $tutorRegistrationTime;
    public $tuteeRegistrationDay;
    public $tuteeRegistrationTime;
    public $minTimeCancellationTime;
    public $useOneDayBeforeCancellation = false;
    public $maxStudentFor1Tutor;
    public $maxStudentFor2Tutors;

    protected $settings = [
        'employedTutorRegistrationDay' => null,
        'employedTutorRegistrationTime' => null,
        'tutorRegistrationDay' => null,
        'tutorRegistrationTime' => null,
        'tuteeRegistrationDay' => null,
        'tuteeRegistrationTime' => null,
        'minTimeCancellationTime' => null,
        'useOneDayBeforeCancellation' => false,
        'maxStudentFor1Tutor' => null,
        'maxStudentFor2Tutors' => null,
    ];

    protected function getDays(): array
    {
        return [
            'monday' => __('resources.pages.settings.days.monday'),
            'tuesday' => __('resources.pages.settings.days.tuesday'),
            'wednesday' => __('resources.pages.settings.days.wednesday'),
            'thursday' => __('resources.pages.settings.days.thursday'),
            'friday' => __('resources.pages.settings.days.friday'),
            'saturday' => __('resources.pages.settings.days.saturday'),
            'sunday' => __('resources.pages.settings.days.sunday'),
        ];
    }

    public function getTitle(): string
    {
        return __('resources.pages.settings.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.pages.settings.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::Administrator->value
            || Auth::user()->role === Roles::EmployedPrivilegedTutor->value);
    }

    public function mount(): void
    {
        $this->loadSettings();
        $this->form->fill($this->settings);
    }

    protected function loadSettings(): void
    {
        if (Storage::exists('settings.json')) {
            $this->settings = json_decode(Storage::get('settings.json'), true) ?: $this->settings;
        }
    }

    public function saveSettings(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $this->settings[$key] = $value;
        }

        // Si on utilise "la veille", on vide les champs minTimeCancellation
        if ($data['useOneDayBeforeCancellation']) {
            $this->settings['minTimeCancellationTime'] = null;
        }

        Storage::put('settings.json', json_encode($this->settings));

        Notification::make()
            ->title(__('resources.pages.settings.notifications.settings_saved_title'))
            ->success()
            ->send();
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make(__('resources.pages.settings.sections.main'))
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Section::make(__('resources.pages.settings.sections.employed_tutor_registration'))
                                ->schema([
                                    Select::make('employedTutorRegistrationDay')
                                        ->label(__('resources.pages.settings.fields.day'))
                                        ->options($this->getDays())
                                        ->required(),
                                    TimePicker::make('employedTutorRegistrationTime')
                                        ->label(__('resources.pages.settings.fields.time'))
                                        ->seconds(false)
                                        ->required(),
                                ])
                                ->columnSpan(1)
                                ->compact(),

                            Section::make(__('resources.pages.settings.sections.tutor_registration'))
                                ->schema([
                                    Select::make('tutorRegistrationDay')
                                        ->label(__('resources.pages.settings.fields.day'))
                                        ->options($this->getDays())
                                        ->required(),
                                    TimePicker::make('tutorRegistrationTime')
                                        ->label(__('resources.pages.settings.fields.time'))
                                        ->seconds(false)
                                        ->required(),
                                ])
                                ->columnSpan(1)
                                ->compact(),

                            Section::make(__('resources.pages.settings.sections.tutee_registration'))
                                ->schema([
                                    Select::make('tuteeRegistrationDay')
                                        ->label(__('resources.pages.settings.fields.day'))
                                        ->options($this->getDays())
                                        ->required(),
                                    TimePicker::make('tuteeRegistrationTime')
                                        ->label(__('resources.pages.settings.fields.time'))
                                        ->seconds(false)
                                        ->required(),
                                ])
                                ->columnSpan(1)
                                ->compact(),
                            Section::make(__('resources.pages.settings.sections.cancellation_delay'))
                                ->schema([
                                    Toggle::make('useOneDayBeforeCancellation')
                                        ->label(__('resources.pages.settings.fields.one_day_before'))
                                        ->reactive()
                                        ->inline(false)
                                        ->columnSpan('full'),

                                    TimePicker::make('minTimeCancellationTime')
                                        ->label(__('resources.pages.settings.fields.time_before'))
                                        ->seconds(false)
                                        ->required()
                                        ->hidden(fn (callable $get) => $get('useOneDayBeforeCancellation')),
                                ])
                                ->columnSpan(1)
                                ->compact(),
                            Section::make(__('resources.pages.settings.sections.max_student_per_tutor'))
                                ->schema([
                                    Forms\Components\TextInput::make('maxStudentFor1Tutor')
                                        ->label(__('resources.pages.settings.fields.max_student_for_1_tutor'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),
                                    Forms\Components\TextInput::make('maxStudentFor2Tutors')
                                        ->label(__('resources.pages.settings.fields.max_student_for_2_tutors'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),
                                ])
                                ->columnSpan(1)
                                ->compact(),
                        ]),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('save')
                            ->label(__('resources.pages.settings.buttons.save'))
                            ->action('saveSettings')
                            ->color('primary'),
                    ])
                ])
        ];
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(UV::query())
            ->heading(__('resources.pages.settings.sections.uv_catalog'))
            ->headerActions([
                TableAction::make('reset_uvs')
                    ->label(__('resources.pages.settings.buttons.reset_uvs'))
                    ->action(fn () => $this->resetUvs())
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-arrow-path'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('intitule')->label(__('resources.pages.settings.fields.intitule'))->searchable(),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading(__('resources.pages.settings.modals.edit_uv_title'))
                    ->form([
                        Forms\Components\TextInput::make('code')->label(__('resources.pages.settings.fields.code'))->required(),
                        Forms\Components\TextInput::make('intitule')->label(__('resources.pages.settings.fields.intitule'))->required(),
                    ]),
                DeleteAction::make(),
            ]);
    }

    public function resetUvs(): void
    {
        $response = Http::withHeaders([
            'x-api-key' => env('API_UTCRAWL_KEY'),
        ])->get(env('API_UTCRAWL'));

        if (!$response->ok()) {
            Notification::make()
                ->title(__('resources.pages.settings.notifications.uvs_update_failed_title'))
                ->danger()
                ->send();
            return;
        }

        $data = $response->json();
        UV::doesntHave('tutors')
            ->delete();

        foreach ($data as $code => $info) {
            if (!isset($info['Titre'])) {
                continue;
            }
            $titre = mb_convert_case(mb_strtolower($info['Titre'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            UV::firstOrCreate(
                ['code' => $code],
                ['intitule' => $titre]
            );
        }

        Notification::make()
            ->title(__('resources.pages.settings.notifications.uvs_updated_title'))
            ->success()
            ->send();
    }
}
