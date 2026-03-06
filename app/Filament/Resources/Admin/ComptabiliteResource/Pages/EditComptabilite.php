<?php

namespace App\Filament\Resources\Admin\ComptabiliteResource\Pages;

use App\Filament\Resources\Admin\ComptabiliteResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Page d'édition d'une comptabilité
 *
 * Cette page permet théoriquement d'éditer directement un enregistrement de comptabilité,
 * mais en pratique, les modifications sont généralement effectuées via l'interface principale
 * de la ressource ComptabiliteResource, qui offre une expérience plus complète et contextuelle.
 */
class EditComptabilite extends EditRecord
{
    protected static string $resource = ComptabiliteResource::class;
}
