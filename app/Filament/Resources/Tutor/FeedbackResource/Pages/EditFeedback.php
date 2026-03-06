<?php

namespace App\Filament\Resources\Tutor\FeedbackResource\Pages;

use App\Filament\Resources\Tutor\FeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Page d'édition d'un feedback
 *
 * Cette page permet aux tutorés de modifier leurs feedbacks existants.
 * Des contrôles d'accès empêchent les utilisateurs de modifier les feedbacks
 * qui ne leur appartiennent pas.
 */
class EditFeedback extends EditRecord
{
    protected static string $resource = FeedbackResource::class;

    /**
     * Vérifie si un utilisateur peut éditer un feedback spécifique
     *
     * Limite l'édition aux feedbacks appartenant à l'utilisateur connecté
     *
     * @param mixed $record Le feedback à éditer
     * @return bool Vrai si l'utilisateur est autorisé à modifier ce feedback
     */
    public static function canEdit($record): bool
    {
        return Auth::id() === $record->tutee_id;
    }

    /**
     * Définit les actions disponibles dans l'en-tête
     *
     * Ajoute un bouton de suppression pour permettre aux tutorés
     * d'effacer leurs feedbacks
     *
     * @return array Tableau des actions d'en-tête
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
