<?php

namespace App\Filament\Resources\Tutor\FeedbackResource\Pages;

use App\Filament\Resources\Tutor\FeedbackResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Page de création d'un feedback
 *
 * Cette page permet aux tutorés de soumettre un nouveau feedback.
 * Elle gère le formulaire de création et la redirection après soumission.
 */
class CreateFeedback extends CreateRecord
{
    protected static string $resource = FeedbackResource::class;

    /**
     * Définit l'URL de redirection après la création d'un feedback
     *
     * Redirige l'utilisateur vers la liste des feedbacks après soumission
     *
     * @return string L'URL de redirection
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Force l'auteur du feedback côté serveur (jamais pris depuis le client)
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tutee_id'] = Auth::id();
        return $data;
    }

    /**
     * Supprime le bouton de création multiple
     *
     * Cette méthode désactive le bouton "Créer et en créer un autre"
     * pour simplifier l'interface utilisateur
     *
     * @return bool Toujours faux pour masquer le bouton
     */
    protected function hasCreateAnotherButton(): bool
    {
        return false;
    }
}
