<?php

namespace App\Filament\Resources\Admin\SemestreResource\Pages;

use App\Filament\Resources\Admin\SemestreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Page d'édition d'un semestre
 *
 * Cette page permet de modifier les informations d'un semestre existant,
 * notamment son code, ses dates de début/fin, et les périodes d'examens.
 */
class EditSemestre extends EditRecord
{
    protected static string $resource = SemestreResource::class;

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
