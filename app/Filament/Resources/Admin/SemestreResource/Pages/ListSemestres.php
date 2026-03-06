<?php

namespace App\Filament\Resources\Admin\SemestreResource\Pages;

use App\Filament\Resources\Admin\SemestreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Page de liste des semestres
 *
 * Cette page affiche tous les semestres configurés dans le système,
 * avec leur statut (actif/inactif) et leurs dates de début et fin.
 * Elle permet également d'activer un semestre particulier.
 */
class ListSemestres extends ListRecords
{
    protected static string $resource = SemestreResource::class;

    /**
     * Définit les actions disponibles dans l'en-tête de la page
     *
     * @return array Liste des actions disponibles (ici uniquement l'action de création)
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
