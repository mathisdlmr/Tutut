<?php

namespace App\Filament\Resources\Tutor\ComptabiliteTutorResource\Pages;

use App\Filament\Resources\Tutor\ComptabiliteTutorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Page de liste des comptabilités pour les tuteurs employés
 *
 * Cette page affiche la récapitulation des heures comptabilisées
 * pour les tuteurs employés, organisées par semaine.
 * Elle permet également d'accéder à la page de création/modification
 * de la comptabilité.
 */
class ListTutorComptabilites extends ListRecords
{
    protected static string $resource = ComptabiliteTutorResource::class;

    /**
     * Définit les actions disponibles dans l'en-tête de la page
     *
     * Ajoute un bouton permettant de confirmer les heures de la semaine,
     * redirigeant vers le formulaire de création/édition de comptabilité.
     *
     * @return array Tableau des actions d'en-tête
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('resources.comptabilite_tutor.actions.confirm_hours'))
        ];
    }
}
