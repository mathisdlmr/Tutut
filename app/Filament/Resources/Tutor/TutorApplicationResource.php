<?php

namespace App\Filament\Resources\Tutor;

use App\Enums\Roles;
use App\Models\BecomeTutor;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Resource de gestion des candidatures de tuteurs
 *
 * Cette ressource permet aux tuteurs employés et administrateurs d'examiner
 * et de traiter les demandes des tutorés souhaitant devenir tuteurs.
 * Fonctionnalités :
 * - Liste des candidatures en attente avec filtrage par semestre
 * - Affichage détaillé de chaque candidature (infos personnelles, UVs, motivation)
 * - Acceptation des candidatures (promotion au rôle de tuteur)
 * - Rejet des candidatures avec marquage approprié
 * - Recherche par nom ou prénom du candidat
 */
class TutorApplicationResource extends Resource
{
    protected static ?string $model = BecomeTutor::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 6;

    /**
     * Obtient le label du modèle pour la ressource
     *
     * @return string Le label traduit pour le modèle
     */
    public static function getModelLabel(): string
    {
        return __('resources.tutor_application.label');
    }

    /**
     * Obtient le label pluriel du modèle pour la ressource
     *
     * @return string Le label pluriel traduit pour le modèle
     */
    public static function getPluralModelLabel(): string
    {
        return __('resources.tutor_application.plural_label');
    }

    /**
     * Obtient le groupe de navigation pour la ressource
     *
     * @return string Le groupe de navigation traduit
     */
    public static function getNavigationGroup(): string
    {
        return __('resources.tutor_application.navigation_group');
    }

    /**
     * Vérifie si l'utilisateur peut accéder à cette ressource
     *
     * Seuls les tuteurs employés et les administrateurs peuvent voir
     * et traiter les candidatures
     *
     * @return bool Vrai si l'utilisateur a le droit d'accéder, faux sinon
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::EmployedTutor->value
            || Auth::user()->role === Roles::Administrator->value);
    }

    /**
     * Configure la table d'affichage des candidatures
     *
     * Cette méthode définit :
     * - Les colonnes affichant les informations des candidats
     * - Les filtres pour trier les candidatures
     * - Les actions pour voir, accepter ou rejeter une candidature
     *
     * @param Table $table La table à configurer
     * @return Table La table configurée
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.firstName')
                    ->label(__('resources.tutor_application.fields.firstname'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.lastName')
                    ->label(__('resources.tutor_application.fields.lastname'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('semester')
                    ->label(__('resources.tutor_application.fields.semester'))
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('resources.tutor_application.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'pending' => __('resources.tutor_application.status.pending'),
                        'rejected' => __('resources.tutor_application.status.rejected'),
                    })
                    ->color(fn ($state) => match($state) {
                        'pending' => 'warning',
                        'rejected' => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('semester')
                    ->label(__('resources.tutor_application.filters.semester'))
                    ->options(function () {
                        return BecomeTutor::select('semester')
                            ->where('status', 'pending')
                            ->distinct()
                            ->pluck('semester', 'semester');
                    }),
                Tables\Filters\Filter::make('pending')
                    ->label(__('resources.tutor_application.filters.pending'))
                    ->query(fn (Builder $query) => $query->where('status', 'pending'))
                    ->default(),
            ])
            ->actions([
                Action::make('view')
                    ->label(__('resources.tutor_application.actions.view.label'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(__('resources.tutor_application.actions.view.modal_heading'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('resources.tutor_application.actions.view.modal_cancel_label'))
                    ->modalContent(function (BecomeTutor $record) {
                        return Infolist::make()
                            ->record($record)
                            ->schema([
                                Components\Section::make(__('resources.tutor_application.sections.personal_info'))
                                    ->schema([
                                        Components\Grid::make(3)
                                            ->schema([
                                                Components\TextEntry::make('user')
                                                    ->label(__('resources.tutor_application.fields.lastname'))
                                                    ->formatStateUsing(fn ($state) => $state['firstName'].' '.$state['lastName']),
                                                Components\TextEntry::make('user.email')
                                                    ->label(__('resources.common.fields.email')),
                                                Components\TextEntry::make('semester')
                                                    ->label(__('resources.tutor_application.fields.semester')),
                                            ]),
                                    ]),
                                Components\Section::make(__('resources.tutor_application.sections.uvs'))
                                    ->schema([
                                        Components\Grid::make(4)
                                            ->schema(function () use ($record) {
                                                return collect($record->UVs ?? [])
                                                    ->map(fn ($uv) => Components\TextEntry::make($uv['code'])
                                                        ->label(false)
                                                        ->default($uv['code'].' - '.$uv['intitule']))
                                                    ->toArray();
                                            }),
                                    ]),
                                Components\Section::make(__('resources.tutor_application.sections.motivation'))
                                    ->schema([
                                        Components\TextEntry::make('motivation')
                                            ->label(false),
                                    ]),
                            ]);
                    })
                    ->modalActions(function (BecomeTutor $record) {
                        return $record->status == 'pending'
                        ? [
                            Action::make('accept')
                                ->label(__('resources.tutor_application.actions.accept.label'))
                                ->color('success')
                                ->icon('heroicon-o-check-circle')
                                ->action(function (BecomeTutor $record) {
                                    $user = $record->user;
                                    $user->role = Roles::Tutor;
                                    $user->save();
                                    $record->delete();

                                    Notification::make()
                                        ->title(__('resources.tutor_application.actions.accept.notification_title'))
                                        ->success()
                                        ->send();

                                    return redirect(request()->header('Referer'));
                                })
                                ->requiresConfirmation()
                                ->modalHeading(__('resources.tutor_application.actions.accept.modal_heading'))
                                ->modalDescription(__('resources.tutor_application.actions.accept.modal_description')),

                            Action::make('reject')
                                ->label(__('resources.tutor_application.actions.reject.label'))
                                ->color('danger')
                                ->icon('heroicon-o-x-circle')
                                ->action(function (BecomeTutor $record) {
                                    $record->update(['status' => 'rejected']);

                                    Notification::make()
                                        ->title(__('resources.tutor_application.actions.reject.notification_title'))
                                        ->danger()
                                        ->send();

                                    return redirect(request()->header('Referer'));
                                })
                                ->requiresConfirmation()
                                ->modalHeading(__('resources.tutor_application.actions.reject.modal_heading'))
                                ->modalDescription(__('resources.tutor_application.actions.reject.modal_description')),
                        ]
                        : [];
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Définit les relations du modèle
     *
     * Aucune relation spécifique n'est définie pour cette ressource
     *
     * @return array Tableau vide car pas de relations particulières
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Définit les pages disponibles pour cette ressource
     *
     * Cette ressource ne contient qu'une page d'index qui liste
     * les candidatures de tuteurs.
     *
     * @return array Tableau associatif des pages
     */
    public static function getPages(): array
    {
        return [
            'index' => TutorApplicationResource\Pages\ListTutorApplications::route('/'),
        ];
    }
}
