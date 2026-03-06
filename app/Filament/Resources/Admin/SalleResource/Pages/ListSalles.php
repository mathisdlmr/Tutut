<?php

namespace App\Filament\Resources\Admin\SalleResource\Pages;

use App\Filament\Resources\Admin\SalleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Page de liste des salles
 *
 * Cette page affiche toutes les salles configurées dans le système,
 * avec leurs numéros et disponibilités par jour/créneau.
 */
class ListSalles extends ListRecords
{
    protected static string $resource = SalleResource::class;

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
