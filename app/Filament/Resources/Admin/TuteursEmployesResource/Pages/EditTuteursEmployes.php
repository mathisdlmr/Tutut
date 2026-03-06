<?php

namespace App\Filament\Resources\Admin\TuteursEmployesResource\Pages;

use App\Filament\Resources\Admin\TuteursEmployesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Page d'édition des tuteurs employés
 *
 * Cette page permet de modifier les informations d'un tuteur employé existant,
 * notamment son rôle dans le système.
 */
class EditTuteursEmployes extends EditRecord
{
    protected static string $resource = TuteursEmployesResource::class;

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
