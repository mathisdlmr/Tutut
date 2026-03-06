<?php

namespace App\Filament\Resources\Tutor;

use App\Enums\Roles;
use App\Filament\Resources\Tutor\FeedbackResource\Pages;
use App\Models\Feedback;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Resource de gestion des feedbacks
 *
 * Cette ressource permet aux utilisateurs (tuteurs et tutorés) de
 * soumettre et consulter des feedbacks sur le service de tutorat.
 * Fonctionnalités :
 * - Soumission de feedbacks textuels
 * - Affichage des feedbacks par date (les plus récents en premier)
 * - Modification et suppression de ses propres feedbacks
 * - Affichage différent selon le rôle (tuteur peut voir tous les feedbacks,
 *   tutoré ne voit que les siens)
 */
class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';
    protected static ?int $navigationSort = 2;

    /**
     * Obtient le label du modèle selon le rôle de l'utilisateur
     *
     * Le libellé est différent pour les tutorés et les tuteurs afin
     * de mieux correspondre à leur contexte d'utilisation.
     *
     * @return string Le label traduit et contextualisé
     */
    public static function getLabel(): string
    {
        $user = Auth::user();
        return ($user && Auth::user()->role === Roles::Tutee->value)
            ? __('resources.feedback.tutee_label')
            : __('resources.feedback.label');
    }

    /**
     * Configure le formulaire de création/édition des feedbacks
     *
     * Le formulaire inclut un champ caché pour l'ID du tutoré et un
     * champ texte pour le contenu du feedback.
     *
     * @param Form $form Le formulaire à configurer
     * @return Form Le formulaire configuré
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('tutee_id')
                    ->default(Auth::id()),
                Forms\Components\Textarea::make('text')
                    ->required()
                    ->label(__('resources.feedback.fields.text')),
            ]);
    }

    /**
     * Configure la table d'affichage des feedbacks
     *
     * Cette méthode définit :
     * - Les colonnes affichant les feedbacks
     * - Les actions pour éditer ou supprimer ses propres feedbacks
     * - La logique de filtrage pour que les tutorés ne voient que leurs feedbacks
     *
     * @param Table $table La table à configurer
     * @return Table La table configurée
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('text')
                    ->label('')
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => Auth::id() === $record->tutee_id),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => Auth::id() === $record->tutee_id)
                    ->action(function (Feedback $record) {
                        $record->delete();
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('resources.feedback.actions.delete.modal_heading'))
                    ->modalSubheading(__('resources.feedback.actions.delete.modal_subheading'))
                    ->modalButton(__('resources.feedback.actions.delete.modal_button')),
            ])
            ->bulkActions([
                //
            ])
            ->modifyQueryUsing(
                fn ($query) => $query->when(Auth::user()->role === Roles::Tutee->value, fn ($query) => $query->where('tutee_id', Auth::id()))
            )
        ->paginated(false)
        ->defaultSort('created_at', 'desc')
        ->recordUrl(null);
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
     * Détermine si l'utilisateur peut créer un feedback
     *
     * Les administrateurs ne peuvent pas créer de feedbacks,
     * contrairement aux tuteurs et tutorés.
     *
     * @return bool Vrai si l'utilisateur peut créer un feedback
     */
    public static function canCreate(): bool
    {
        return Auth::user()->role !== Roles::Administrator->value;
    }

    /**
     * Définit les pages disponibles pour cette ressource
     *
     * Cette ressource contient trois pages :
     * - liste des feedbacks (index)
     * - création d'un nouveau feedback
     * - édition d'un feedback existant
     *
     * @return array Tableau associatif des pages
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedback::route('/'),
            'create' => Pages\CreateFeedback::route('/create'),
            'edit' => Pages\EditFeedback::route('/{record}/edit'),
        ];
    }
}
