<?php

namespace App\Filament\Resources\Tutor\TutorApplicationResource\Pages;

use App\Filament\Resources\Tutor\TutorApplicationResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Page de liste des candidatures pour devenir tuteur
 *
 * Cette page affiche toutes les candidatures de tutorés souhaitant
 * devenir tuteurs. Elle est accessible aux tuteurs employés et administrateurs
 * pour évaluer et traiter ces demandes.
 */
class ListTutorApplications extends ListRecords
{
    protected static string $resource = TutorApplicationResource::class;

    /**
     * Définit les actions d'en-tête (vides pour cette ressource)
     *
     * Aucune action d'en-tête n'est nécessaire car on ne peut pas
     * créer de candidatures manuellement depuis cette page.
     *
     * @return array Tableau d'actions (vide)
     */
    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
