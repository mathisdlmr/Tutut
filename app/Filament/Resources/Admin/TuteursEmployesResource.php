<?php

namespace App\Filament\Resources\Admin;

use App\Enums\Roles;
use App\Filament\Resources\Admin\TuteursEmployesResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\MultiSelectFilter;
use Illuminate\Support\Facades\Auth;

/**
 * Resource de gestion des tuteurs employés
 *
 * Cette ressource permet aux administrateurs de gérer les tuteurs
 * ayant un statut d'employé ou privilégié dans le système.
 * Fonctionnalités :
 * - Ajout de nouveaux tuteurs par email
 * - Attribution des rôles (administrateur, tuteur employé, tuteur privilégié)
 * - Filtrage par rôle
 * - Promotion/rétrogradation des tuteurs employés
 * - Suppression de tuteurs (rétrogradation au statut de tutoré)
 * - Consultation des UVs proposées par les tuteurs
 */
class TuteursEmployesResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationGroup(): string
    {
        return __('resources.admin.navigation_group.administration');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && Auth::user()->role === Roles::Administrator->value;
    }

    public static function getModelLabel(): string
    {
        return __('resources.tuteurs_employes.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.tuteurs_employes.plural_label');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TagsInput::make('email')
                    ->label(__('resources.tuteurs_employes.fields.email'))
                    ->helperText('Entrer une ou plusieurs adresses email en appuyant sur Entrée entre chaque adresse.')
                    ->placeholder('email1@example.com, email2@example.com...')
                    ->required()
                    ->separator(','),
                Select::make('role')
                    ->label(__('resources.tuteurs_employes.fields.role'))
                    ->options([
                        Roles::Administrator->value => __('resources.tuteurs_employes.roles.administrator'),
                        Roles::EmployedPrivilegedTutor->value => __('resources.tuteurs_employes.roles.employed_privileged_tutor'),
                        Roles::EmployedTutor->value => __('resources.tuteurs_employes.roles.employed_tutor'),
                        Roles::Tutor->value => __('resources.tuteurs_employes.roles.tutor'),
                    ])
                    ->default(Roles::EmployedTutor->value)
                    ->required(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label(__('resources.tuteurs_employes.fields.email'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('resources.tuteurs_employes.fields.role'))
                    ->sortable()
                    ->formatStateUsing(fn ($state) => match($state) {
                        Roles::Administrator->value => __('resources.tuteurs_employes.roles.administrator'),
                        Roles::EmployedTutor->value => __('resources.tuteurs_employes.roles.employed_tutor'),
                        Roles::EmployedPrivilegedTutor->value => __('resources.tuteurs_employes.roles.employed_privileged_tutor'),
                        Roles::Tutor->value => __('resources.tuteurs_employes.roles.tutor'),
                        Roles::Tutee->value => __('resources.tuteurs_employes.roles.tutee'),
                        default => 'Inconnu',
                    }),
            ])
            ->filters([
                MultiSelectFilter::make('role')
                    ->label(__('resources.tuteurs_employes.fields.role'))
                    ->options([
                        Roles::Administrator->value => __('resources.tuteurs_employes.roles.administrator'),
                        Roles::EmployedTutor->value => __('resources.tuteurs_employes.roles.employed_tutor'),
                        Roles::EmployedPrivilegedTutor->value => __('resources.tuteurs_employes.roles.employed_privileged_tutor'),
                        Roles::Tutor->value => __('resources.tuteurs_employes.roles.tutor'),
                        Roles::Tutee->value => __('resources.tuteurs_employes.roles.tutee'),
                    ])
                    ->default([
                        Roles::EmployedTutor->value,
                        Roles::EmployedPrivilegedTutor->value,
                    ])
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->color('info'),
                Tables\Actions\DeleteAction::make()
                    ->label(__('resources.tuteurs_employes.actions.delete'))
                    ->visible(fn (User $record) => $record->role !== Roles::Tutee->value)
                    ->action(function (User $record) {
                        if ($record->role === Roles::Administrator->value) {
                            $adminCount = User::where('role', Roles::Administrator->value)->count();
                            if ($adminCount <= 1) {
                                Notification::make()
                                    ->title('Impossible')
                                    ->body('Cet utilisateur est le dernier administrateur de la plateforme.')
                                    ->danger()
                                    ->send();

                                return false;
                            }
                        }
                        $record->update(['role' => Roles::Tutee->value]);
                    }),
                Tables\Actions\Action::make('upgrade')
                    ->label(__('resources.tuteurs_employes.actions.upgrade'))
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->action(fn (User $record) => $record->update(['role' => Roles::EmployedPrivilegedTutor->value]))
                    ->visible(fn (User $record) => $record->role === Roles::EmployedTutor->value),
                Tables\Actions\Action::make('downgrade')
                    ->label(__('resources.tuteurs_employes.actions.downgrade'))
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->action(fn (User $record) => $record->update(['role' => Roles::EmployedTutor->value]))
                    ->visible(fn (User $record) => $record->role === Roles::EmployedPrivilegedTutor->value),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label(__('resources.tuteurs_employes.actions.delete')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTuteursEmployes::route('/'),
            'create' => Pages\CreateTuteursEmployes::route('/create'),
            'edit' => Pages\EditTuteursEmployes::route('/{record}/edit'),
            'uvs' => Pages\ViewUvsProposees::route('/uvs'),
        ];
    }
}
