<?php

namespace App\Filament\Resources\Admin\ComptabiliteResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Admin\ComptabiliteResource;
use App\Models\Comptabilite;
use App\Models\Semaine;
use App\Models\Semestre;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

/**
 * Page de liste de la comptabilité
 *
 * Cette page affiche la liste des tuteurs avec leurs heures,
 * permet de modifier et valider les heures, et d'exporter
 * les données au format CSV.
 */
class ListComptabilite extends ListRecords
{
    protected static string $resource = ComptabiliteResource::class;

    /**
     * Définit les actions disponibles dans l'en-tête de la page
     *
     * @return array Actions disponibles (ici uniquement l'export CSV)
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Exporter CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    // Récupère le semestre actif
                    $semestreActif = Semestre::where('is_active', true)->first();

                    if (!$semestreActif) {
                        $this->notify('danger', 'Aucun semestre actif trouvé.');
                        return;
                    }

                    // Récupère toutes les semaines du semestre
                    $semaines = Semaine::where('fk_semestre', $semestreActif->code)
                        ->orderBy('numero')
                        ->get();

                    // Récupère tous les tuteurs employés ayant des heures
                    $employedTutorIds = DB::table('comptabilite')
                        ->whereIn('fk_semaine', $semaines->pluck('numero'))
                        ->pluck('fk_user')
                        ->merge(
                            DB::table('heures_supplementaires')
                                ->whereIn('fk_semaine', $semaines->pluck('numero'))
                                ->pluck('fk_user')
                        )
                        ->unique();

                    $employedTutors = User::whereIn('id', $employedTutorIds)
                        ->whereIn('role', [
                            Roles::EmployedTutor->value,
                            Roles::EmployedPrivilegedTutor->value
                        ])
                        ->orderBy('lastName')
                        ->orderBy('firstName')
                        ->get();

                    // Préparation des données CSV
                    $csvData = [];

                    // En-têtes du fichier CSV
                    $header = ['Nom', 'Prénom', 'Email'];
                    foreach ($semaines as $semaine) {
                        $header[] = "Semaine {$semaine->numero}";
                    }
                    $header[] = 'Total';

                    $csvData[] = $header;

                    // Données pour chaque tuteur
                    foreach ($employedTutors as $tutor) {
                        $row = [
                            $tutor->lastName,
                            $tutor->firstName,
                            $tutor->email,
                        ];

                        $total = 0;

                        // Récupération des heures pour chaque semaine
                        foreach ($semaines as $semaine) {
                            $comptabilite = Comptabilite::where('fk_user', $tutor->id)
                                ->where('fk_semaine', $semaine->numero)
                                ->first();

                            $heuresSemaine = ($comptabilite ? $comptabilite->nb_heures : 0);
                            $total += $heuresSemaine;

                            $row[] = $heuresSemaine;
                        }

                        $row[] = $total;
                        $csvData[] = $row;
                    }

                    // Création du contenu CSV
                    $csvContent = '';
                    foreach ($csvData as $row) {
                        $escapedRow = array_map(function ($value) {
                            return '"' . str_replace('"', '""', $value) . '"';
                        }, $row);

                        $csvContent .= implode(',', $escapedRow) . "\n";
                    }

                    // Ajout du BOM UTF-8 pour Excel
                    $csvContent = chr(0xEF) . chr(0xBB) . chr(0xBF) . $csvContent;
                    $filename = "comptabilite_tuteurs_{$semestreActif->code}.csv";

                    // Retourne le fichier en téléchargement
                    return response()->streamDownload(function () use ($csvContent) {
                        echo $csvContent;
                    }, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })
        ];
    }
}
