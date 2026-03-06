<?php

namespace App\Filament\Resources\Admin\TuteursEmployesResource\Pages;

use App\Filament\Resources\Admin\TuteursEmployesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Page de liste des tuteurs employés
 *
 * Cette page affiche tous les tuteurs ayant le statut employé ou privilégié.
 * Elle permet de visualiser, filtrer, et effectuer des actions sur les tuteurs.
 */
class ListTuteursEmployes extends ListRecords
{
    protected static string $resource = TuteursEmployesResource::class;

    /**
     * Définit les actions disponibles dans l'en-tête de la page
     *
     * @return array Liste des actions disponibles (ici uniquement l'action de création)
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
            Actions\Action::make('view_uvs')
                ->label('Voir les UVs proposées')
                ->icon('heroicon-o-academic-cap')
                ->color('info')
                ->url('/admin/tuteurs-employes/uvs')
        ];
    }
}
