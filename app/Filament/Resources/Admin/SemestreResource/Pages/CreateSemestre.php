<?php

namespace App\Filament\Resources\Admin\SemestreResource\Pages;

use App\Filament\Resources\Admin\SemestreResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Page de création d'un semestre
 *
 * Cette page permet de créer un nouveau semestre en définissant son code,
 * ses dates de début et fin, ainsi que les périodes d'examens (médians et finaux).
 * Par défaut, un nouveau semestre est créé en statut inactif.
 */
class CreateSemestre extends CreateRecord
{
    protected static string $resource = SemestreResource::class;
}
