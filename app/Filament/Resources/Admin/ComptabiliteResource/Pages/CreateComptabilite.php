<?php

namespace App\Filament\Resources\Admin\ComptabiliteResource\Pages;

use App\Filament\Resources\Admin\ComptabiliteResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Page de création d'une comptabilité
 *
 * Cette page permet théoriquement de créer un enregistrement de comptabilité manuellement,
 * mais en pratique, les enregistrements sont généralement créés automatiquement par le système
 * ou via l'interface principale de la ressource ComptabiliteResource.
 */
class CreateComptabilite extends CreateRecord
{
    protected static string $resource = ComptabiliteResource::class;
}
