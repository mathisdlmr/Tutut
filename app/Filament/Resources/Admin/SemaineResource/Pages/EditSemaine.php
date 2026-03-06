<?php

namespace App\Filament\Resources\Admin\SemaineResource\Pages;

use App\Filament\Resources\Admin\SemaineResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Page d'édition d'une semaine
 *
 * Cette page permet de modifier les propriétés d'une semaine existante:
 * - Son numéro
 * - Son semestre associé
 * - Son statut (vacances ou non)
 * - Ses dates de début et fin
 */
class EditSemaine extends EditRecord
{
    protected static string $resource = SemaineResource::class;

    /**
     * Définit les actions disponibles dans l'en-tête de la page d'édition
     *
     * @return array Liste des actions disponibles (ici uniquement l'action de suppression)
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
