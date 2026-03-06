<?php

namespace App\Filament\Resources\Admin\SemaineResource\Pages;

use App\Filament\Resources\Admin\SemaineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Page de liste des semaines
 *
 * Cette page affiche toutes les semaines du semestre actif,
 * avec leur numéro, dates de début/fin, et statut (vacances ou non).
 * Elle permet également de créer une nouvelle semaine manuellement.
 */
class ListSemaines extends ListRecords
{
    protected static string $resource = SemaineResource::class;

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
