<?php

namespace App\Filament\Resources\Admin;

use App\Enums\Roles;
use App\Filament\Resources\Admin\SemestreResource\Pages;
use App\Models\Semestre;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Resource de gestion des semestres
 *
 * Cette ressource permet aux administrateurs et tuteurs privilégiés
 * de gérer les semestres académiques.
 * Fonctionnalités :
 * - Création et édition des semestres (code, dates)
 * - Configuration des périodes d'examens (médians et finaux)
 * - Activation/désactivation des semestres
 * - Visualisation du semestre actif
 * - Tri par date de fin pour montrer les semestres les plus récents en premier
 */
class SemestreResource extends Resource
{
    protected static ?string $model = Semestre::class;
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('resources.admin.semestre.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.admin.semestre.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('resources.admin.navigation_group.gestion');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::Administrator->value ||
               Auth::user()->role === Roles::EmployedPrivilegedTutor->value);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label(__('resources.admin.semestre.fields.code'))
                        ->required()
                        ->maxLength(3)
                        ->placeholder('A25')
                        ->columnSpan(1),

                    Forms\Components\DatePicker::make('debut')
                        ->label(__('resources.admin.semestre.fields.debut'))
                        ->required()
                        ->columnSpan(1)
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, $state, $get) {
                            $fin = $get('fin');
                            if ($fin && $state >= $fin) {
                                $set('fin', null);
                            }
                        }),

                    Forms\Components\DatePicker::make('fin')
                        ->label(__('resources.admin.semestre.fields.fin'))
                        ->required()
                        ->columnSpan(1)
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, $state, $get) {
                            $debut = $get('debut');
                            if ($debut && $state <= $debut) {
                                $set('fin', null);
                            }
                        }),
                ]),

            Forms\Components\Grid::make(2)
                ->schema([
                    DatePicker::make('debut_medians')
                        ->label(__('resources.admin.semestre.fields.debut_medians')),
                    DatePicker::make('fin_medians')
                        ->label(__('resources.admin.semestre.fields.fin_medians')),
                    DatePicker::make('debut_finaux')
                        ->label(__('resources.admin.semestre.fields.debut_finaux')),
                    DatePicker::make('fin_finaux')
                        ->label(__('resources.admin.semestre.fields.fin_finaux')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('resources.admin.semestre.fields.code'))
                    ->searchable(),
                TextColumn::make('is_active')
                    ->label(__('resources.admin.semestre.fields.is_active'))
                    ->formatStateUsing(fn (bool $state) => $state ? __('resources.admin.semestre.values.oui') : __('resources.admin.semestre.values.non'))
                    ->badge()
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
                TextColumn::make('debut')
                    ->label(__('resources.admin.semestre.fields.debut'))
                    ->formatStateUsing(fn (string $state) => Carbon::parse($state)->locale('fr')->translatedFormat('l d F Y')),
                TextColumn::make('fin')
                    ->label(__('resources.admin.semestre.fields.fin'))
                    ->formatStateUsing(fn (string $state) => Carbon::parse($state)->locale('fr')->translatedFormat('l d F Y')),
            ])
            ->defaultSort('fin', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('activer')
                    ->label(__('resources.admin.semestre.actions.activer'))
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->color('success')
                    ->visible(fn ($record) => !$record->is_active)
                    ->action(function (Semestre $record) {
                        Semestre::setActive($record);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSemestres::route('/'),
            'create' => Pages\CreateSemestre::route('/create'),
            'edit' => Pages\EditSemestre::route('/{record}/edit'),
        ];
    }
}
