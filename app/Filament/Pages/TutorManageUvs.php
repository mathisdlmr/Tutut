<?php

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Models\User;
use App\Models\UV;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Page de gestion des UVs pour les tuteurs
 *
 * Cette page permet aux tuteurs de gérer les UVs (unités de valeur) qu'ils proposent d'enseigner.
 * Fonctionnalités:
 * - Sélection d'UVs existantes depuis le catalogue
 * - Proposition de nouvelles UVs (pour les tuteurs privilégiés)
 * - Gestion des langues d'enseignement proposées par le tuteur
 * - Visualisation et suppression des UVs déjà sélectionnées
 */
class TutorManageUvs extends Page implements Forms\Contracts\HasForms, Tables\Contracts\HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static string $view = 'filament.pages.tutor-manage-uvs';
    protected static ?int $navigationSort = 3;

    public array $languagesForm = [];
    public $selected_codes;
    public $code;
    public $intitule;

    // Propriété réactive pour l'état du bouton
    public $canSaveUv = false;

    public function getTitle(): string
    {
        return __('resources.pages.tutor_manage_uvs.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.pages.tutor_manage_uvs.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('resources.pages.tutor_manage_uvs.navigation_group');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::EmployedTutor->value
            || Auth::user()->role === Roles::Tutor->value);
    }

    public function getLanguagesFormComponentProperty(): Form
    {
        return $this->makeForm()
            ->schema([
                Forms\Components\CheckboxList::make('languages')
                    ->label(__('resources.pages.tutor_manage_uvs.fields.languages'))
                    ->options([
                        'en' => __('resources.pages.tutor_manage_uvs.languages.en'),
                        'es' => __('resources.pages.tutor_manage_uvs.languages.es'),
                        'zh' => __('resources.pages.tutor_manage_uvs.languages.zh'),
                        'de' => __('resources.pages.tutor_manage_uvs.languages.de'),
                        'ar' => __('resources.pages.tutor_manage_uvs.languages.ar'),
                        'ru' => __('resources.pages.tutor_manage_uvs.languages.ru'),
                        'ja' => __('resources.pages.tutor_manage_uvs.languages.ja'),
                        'it' => __('resources.pages.tutor_manage_uvs.languages.it'),
                    ])
                    ->columns(2)
            ])
            ->statePath('languagesForm');
    }

    public function mount(): void
    {
        $this->languagesForm = [
            'languages' => Auth::user()->languages ?? [],
        ];
        $this->form->fill([
            'languages' => $this->languagesForm['languages'],
        ]);

        $this->updateCanSaveUv();
    }

    public function formLanguagesForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\CheckboxList::make('languages')
                    ->label(__('resources.pages.tutor_manage_uvs.fields.languages'))
                    ->options([
                        'en' => __('resources.pages.tutor_manage_uvs.languages.en'),
                        'es' => __('resources.pages.tutor_manage_uvs.languages.es'),
                        'zh' => __('resources.pages.tutor_manage_uvs.languages.zh'),
                        'de' => __('resources.pages.tutor_manage_uvs.languages.de'),
                        'ar' => __('resources.pages.tutor_manage_uvs.languages.ar'),
                        'ru' => __('resources.pages.tutor_manage_uvs.languages.ru'),
                        'ja' => __('resources.pages.tutor_manage_uvs.languages.ja'),
                        'it' => __('resources.pages.tutor_manage_uvs.languages.it'),
                    ])
                    ->columns(2)
                    ->default(Auth::user()->languages ?? [])
                    ->reactive()
            ])
            ->statePath('languagesForm');
    }

    public function updateLanguages(): void
    {
        $data = $this->languagesFormComponent->getState();
        Auth::user()->update([
            'languages' => $data['languages'] ?? [],
        ]);

        Notification::make()
            ->title(__('resources.pages.tutor_manage_uvs.notifications.languages_updated_title'))
            ->success()
            ->body(__('resources.pages.tutor_manage_uvs.notifications.languages_updated_body'))
            ->send();
    }

    public function form(Form $form): Form
    {
        $tutors = User::where('role', Roles::EmployedPrivilegedTutor->value)
                    ->get()
                    ->map(fn ($user) => "{$user->firstName} {$user->lastName}")
                    ->join(' ou ');

        return $form->schema([
            Forms\Components\Section::make(__('resources.pages.tutor_manage_uvs.sections.propose_uv.title'))
                ->description(trans('resources.pages.tutor_manage_uvs.sections.propose_uv.description', ['tutors' => $tutors]))
                ->schema([
                    Forms\Components\Select::make('selected_codes')
                        ->label(__('resources.pages.tutor_manage_uvs.fields.selected_codes'))
                        ->options(
                            \App\Models\UV::whereNotIn('code', Auth::user()->proposedUvs()->pluck('code'))
                            ->get()
                            ->mapWithKeys(fn ($uv) => [$uv->code => "{$uv->code} - {$uv->intitule}"])
                        )
                        ->searchable()
                        ->multiple()
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->updateCanSaveUv())
                        ->requiredWithout(['code', 'intitule']),
                ]),

            Forms\Components\Section::make(__('resources.pages.tutor_manage_uvs.sections.create_new_uv.title'))
                ->description(__('resources.pages.tutor_manage_uvs.sections.create_new_uv.description'))
                ->schema([
                    Forms\Components\TextInput::make('code')
                    ->label(__('resources.pages.tutor_manage_uvs.fields.code'))
                    ->maxLength(10)
                    ->reactive()
                    ->afterStateUpdated(fn () => $this->updateCanSaveUv())
                    ->requiredWithout('selected_codes'),

                    Forms\Components\TextInput::make('intitule')
                    ->label(__('resources.pages.tutor_manage_uvs.fields.intitule'))
                    ->maxLength(255)
                    ->reactive()
                    ->afterStateUpdated(fn () => $this->updateCanSaveUv())
                    ->requiredWithout('selected_codes'),
                ])
                ->columns(2)
                ->visible(fn () => Auth::user()->role === Roles::EmployedPrivilegedTutor->value),
        ])->statePath('');
    }

    public function updateCanSaveUv(): void
    {
        $this->canSaveUv = (!empty($this->selected_codes) && is_array($this->selected_codes)) ||
                          (!empty($this->code) && !empty($this->intitule));
    }

    public function updated($property): void
    {
        if (in_array($property, ['selected_codes', 'code', 'intitule'])) {
            $this->updateCanSaveUv();
        }
    }

    public function createUv()
    {
        $data = $this->form->getState();

        if (!empty($data['selected_codes']) && is_array($data['selected_codes'])) {
            Auth::user()->proposedUvs()->syncWithoutDetaching($data['selected_codes']);

            Notification::make()
                ->title(__('resources.pages.tutor_manage_uvs.notifications.uvs_added_title'))
                ->success()
                ->body(__('resources.pages.tutor_manage_uvs.notifications.uvs_added_body'))
                ->send();
        } elseif (!empty($data['code']) && !empty($data['intitule'])) {
            $uv = UV::firstOrCreate(
                ['code' => $data['code']],
                ['intitule' => $data['intitule']]
            );

            Auth::user()->proposedUvs()->syncWithoutDetaching([$uv->code]);

            Notification::make()
                ->title(__('resources.pages.tutor_manage_uvs.notifications.uvs_created_title'))
                ->success()
                ->body(__('resources.pages.tutor_manage_uvs.notifications.uvs_created_body'))
                ->send();
        }

        $this->reset(['selected_codes', 'code', 'intitule']);
        $this->form->fill();
        $this->updateCanSaveUv();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Auth::user()->proposedUvs()->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('intitule'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->action(function (UV $record) {
                        Auth::user()->proposedUvs()->detach($record->code);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
