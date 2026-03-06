<?php

namespace App\Filament\Resources\Tutor\FeedbackResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Tutor\FeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

/**
 * Page de liste des feedbacks
 *
 * Cette page affiche la liste des feedbacks soumis par les utilisateurs.
 * Le comportement varie selon le rôle de l'utilisateur :
 * - Les tutorés ne voient que leurs propres feedbacks
 * - Les tuteurs voient tous les feedbacks
 */
class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    /**
     * Personnalise le titre de la page en fonction du rôle de l'utilisateur
     *
     * @return string Le titre traduit approprié
     */
    public function getTitle(): string
    {
        return Auth::user()->role === Roles::Tutee->value ? __('resources.feedback.fields.text') : __('resources.feedback.plural_label');
    }

    /**
     * Définit les actions disponibles dans l'en-tête
     *
     * Les tutorés ont accès au bouton de création d'un nouveau feedback,
     * tandis que les tuteurs n'ont pas d'actions spécifiques.
     *
     * @return array Tableau des actions d'en-tête
     */
    protected function getHeaderActions(): array
    {
        if (Auth::user()->role === Roles::Tutee->value) {
            return [
                Actions\CreateAction::make()
            ];
        } else {
            return [];
        }
    }
}
