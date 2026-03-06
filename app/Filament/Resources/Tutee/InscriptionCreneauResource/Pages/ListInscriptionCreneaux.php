<?php

namespace App\Filament\Resources\Tutee\InscriptionCreneauResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Tutee\InscriptionCreneauResource;
use App\Models\Creneaux;
use App\Models\Semaine;
use App\Models\Semestre;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Page de liste des créneaux disponibles pour inscription
 *
 * Cette page affiche les créneaux de tutorat auxquels les tutorés
 * peuvent s'inscrire, organisés en onglets par semaine et avec
 * des règles d'accès basées sur la date d'ouverture des inscriptions.
 */
class ListInscriptionCreneaux extends ListRecords
{
    protected static string $resource = InscriptionCreneauResource::class;

    /**
     * Définit les actions d'en-tête (vides pour cette ressource)
     *
     * @return array Tableau d'actions
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label(__('resources.common.buttons.export_excel'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->button()
                ->visible(fn () => Auth::user()->role === Roles::Administrator->value)
                ->action(function () {
                    return self::exportExcel();
                })
        ];
    }

    /**
     * Récupère les paramètres d'inscription depuis le fichier de configuration
     *
     * Lit les paramètres concernant la date et l'heure d'ouverture des
     * inscriptions pour les tutorés.
     *
     * @return array Tableau associatif des paramètres d'inscription
     */
    protected function getRegistrationSettings(): array
    {
        $settingsPath = Storage::path('settings.json');
        if (file_exists($settingsPath)) {
            $settings = json_decode(file_get_contents($settingsPath), true);
            return $settings;
        }

        return [   // Valeurs par défaut si le fichier n'existe pas
            'tuteeRegistrationDay' => 'sunday',
            'tuteeRegistrationTime' => '16:00',
        ];
    }

    /**
     * Détermine si la semaine actuelle et la semaine suivante doivent être affichées
     *
     * Cette méthode vérifie, en fonction des paramètres de configuration,
     * si la date/heure actuelle permet aux tutorés de voir les créneaux
     * de la semaine suivante.
     *
     * @return bool Vrai si les semaines actuelle et suivante doivent être affichées
     */
    protected function shouldShowCurrentAndNextWeek(): bool
    {
        $settings = $this->getRegistrationSettings();

        $registrationDay = $settings['tuteeRegistrationDay'] ?? 'sunday';
        $registrationTime = $settings['tuteeRegistrationTime'] ?? '16:00';

        $now = Carbon::now();
        $currentDayOfWeek = strtolower($now->englishDayOfWeek);

        if ($currentDayOfWeek === strtolower($registrationDay)) {  // Si on est le jour de changement, on vérifie l'heure
            list($hour, $minute) = explode(':', $registrationTime);
            $registrationDateTime = Carbon::now()->setTime((int)$hour, (int)$minute, 0);
            return $now->greaterThanOrEqualTo($registrationDateTime);
        } else {   // On détermine si on est après le jour d'inscription
            $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $registrationDayIndex = array_search(strtolower($registrationDay), $daysOfWeek);
            $currentDayIndex = array_search($currentDayOfWeek, $daysOfWeek);

            return ($currentDayIndex > $registrationDayIndex);
        }
    }

    /**
     * Définit les onglets pour la liste des créneaux d'inscription
     *
     * Crée des onglets pour la semaine actuelle et éventuellement la semaine suivante
     * si la période d'inscription pour celle-ci est ouverte.
     * Chaque onglet affiche les créneaux d'une semaine spécifique, avec :
     * - Le numéro de semaine
     * - Un badge indiquant le nombre de créneaux disponibles
     * - Filtrage pour n'afficher que les créneaux pertinents (pas terminés, avec tuteurs)
     *
     * @return array Tableau d'onglets configurés
     */
    public function getTabs(): array
    {
        if (Auth::user()->role === Roles::Administrator->value) {
            $semestreId = Semestre::where('is_active', true)->first()?->code;

            $weeks = $semestreId
                ? Semaine::where('fk_semestre', $semestreId)
                    ->orderBy('numero', 'desc')
                    ->get()
                : collect();

            $tabs = [];
            foreach ($weeks as $week) {
                $tabs["semaine-{$week->id}"] = Tab::make(__('resources.inscription_creneau.semaine')." {$week->numero}")
                    ->badge(fn () => Creneaux::where('fk_semaine', $week->id)
                        ->where('end', '>', Carbon::now())
                        ->where(function ($query) {
                            $query->whereNotNull('tutor1_id')
                                ->orWhereNotNull('tutor2_id');
                        })
                        ->count())
                    ->modifyQueryUsing(function (Builder $query) use ($week) {
                        return $query->where('fk_semaine', $week->id)
                            ->where('end', '>', Carbon::now())
                            ->where(function ($query) {
                                $query->whereNotNull('tutor1_id')
                                    ->orWhereNotNull('tutor2_id');
                            });
                    });
            }
            return $tabs;
        }

        $showNextWeek = $this->shouldShowCurrentAndNextWeek();

        $currentWeek = Semaine::where('date_debut', '<=', Carbon::now())
            ->where('date_fin', '>=', Carbon::now())
            ->first();

        $tabs = [];

        if ($currentWeek) {
            $tabs["semaine-{$currentWeek->id}"] = Tab::make(__('resources.inscription_creneau.semaine_actuelle')." ({$currentWeek->numero})")
                ->badge(fn () => Creneaux::where('fk_semaine', $currentWeek->id)
                    ->where('end', '>', Carbon::now())
                    ->where(function ($query) {
                        $query->whereNotNull('tutor1_id')
                            ->orWhereNotNull('tutor2_id');
                    })
                    ->count())
                ->modifyQueryUsing(function (Builder $query) use ($currentWeek) {
                    return $query->where('fk_semaine', $currentWeek->id)
                        ->where('end', '>', Carbon::now())
                        ->where(function ($query) {
                            $query->whereNotNull('tutor1_id')
                                ->orWhereNotNull('tutor2_id');
                        });
                });

            if ($showNextWeek) {
                $nextWeek = Semaine::where('numero', $currentWeek->numero + 1)
                    ->where('fk_semestre', $currentWeek->fk_semestre)
                    ->first();

                if ($nextWeek) {
                    $tabs["semaine-{$nextWeek->id}"] = Tab::make(__('resources.inscription_creneau.semaine_prochaine')." ({$nextWeek->numero})")
                        ->badge(fn () => Creneaux::where('fk_semaine', $nextWeek->id)
                            ->where(function ($query) {
                                $query->whereNotNull('tutor1_id')
                                    ->orWhereNotNull('tutor2_id');
                            })
                            ->count())
                        ->modifyQueryUsing(function (Builder $query) use ($nextWeek) {
                            return $query->where('fk_semaine', $nextWeek->id)
                                ->where(function ($query) {
                                    $query->whereNotNull('tutor1_id')
                                        ->orWhereNotNull('tutor2_id');
                                });
                        });
                }
            }
        }

        return $tabs;
    }

    /**
     * Génère un export Excel des créneaux et inscriptions
     *
     * Cette méthode crée un fichier Excel structuré par semaine, avec :
     * - Un onglet distinct pour chaque semaine du semestre actif
     * - Regroupement des créneaux par jour et horaire
     * - Affichage détaillé des informations pour chaque créneau :
     *   - Tuteurs assignés et leurs UVs
     *   - Salle et horaire
     *   - Liste des tutorés inscrits avec leurs UVs demandées
     * - Formatage avancé pour une meilleure lisibilité (couleurs, styles, etc.)
     *
     * Accessible uniquement aux administrateurs depuis le bouton d'export
     *
     * @return StreamedResponse Réponse HTTP contenant le fichier Excel en téléchargement
     */
    public static function exportExcel()
    {
        $activeSemester = Semestre::getActive();
        if (!$activeSemester) {
            return response()->json(['error' => 'Aucun semestre actif trouvé'], 404);
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Créneaux du Semestre')
            ->setDescription('Export des créneaux du semestre actif');

        $semaines = Semaine::where('fk_semestre', $activeSemester->code)
            ->orderBy('date_debut')
            ->get();

        $spreadsheet->removeSheetByIndex(0);

        foreach ($semaines as $semaine) {
            $weekNumber = $semaine->numero_semaine ?? ($semaine->id - $semaines->first()->id + 1);
            $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, "Semaine $weekNumber");
            $spreadsheet->addSheet($sheet);
            $spreadsheet->setActiveSheetIndexByName("Semaine $weekNumber");

            // Premier Creneau
            $sheet->getColumnDimension('A')->setWidth(25);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(20);
            $sheet->getColumnDimension('D')->setWidth(30);

            $sheet->getColumnDimension('E')->setWidth(5); // Separateur

            // Second Creneau
            $sheet->getColumnDimension('F')->setWidth(25);
            $sheet->getColumnDimension('G')->setWidth(25);
            $sheet->getColumnDimension('H')->setWidth(20);
            $sheet->getColumnDimension('I')->setWidth(30);

            $sheet->getColumnDimension('J')->setWidth(5); // Separateur

            // Troisieme creneau
            $sheet->getColumnDimension('K')->setWidth(25);
            $sheet->getColumnDimension('L')->setWidth(25);
            $sheet->getColumnDimension('M')->setWidth(20);
            $sheet->getColumnDimension('N')->setWidth(30);

            $creneaux = Creneaux::with([
                    'tutor1.proposedUvs',
                    'tutor2.proposedUvs',
                    'inscriptions.tutee',
                    'semaine'
                ])
                ->where('fk_semaine', $semaine->id)
                ->whereHas('inscriptions')
                ->orderBy('start')
                ->get();

            $creneauxByDay = $creneaux->groupBy(function ($creneau) {
                return $creneau->start->format('Y-m-d');
            });

            $rowIndex = 1;

            // Titre d'onglet'
            $sheet->setCellValue('A' . $rowIndex, "Créneaux de la Semaine $weekNumber");
            $sheet->mergeCells('A' . $rowIndex . ':N' . $rowIndex);
            $sheet->getStyle('A' . $rowIndex)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $rowIndex += 2;

            foreach ($creneauxByDay as $day => $dayCreneaux) {
                // Header de feuille
                $dayHeader = ucfirst(Carbon::parse($day)->translatedFormat('l d F Y'));
                $sheet->setCellValue('A' . $rowIndex, $dayHeader);
                $sheet->mergeCells('A' . $rowIndex . ':N' . $rowIndex);
                $sheet->getStyle('A' . $rowIndex)->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A' . $rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
                $rowIndex++;

                $creneauxByTime = $dayCreneaux->groupBy(function ($creneau) {
                    return $creneau->start->format('H:i');
                });
                foreach ($creneauxByTime as $time => $timeCreneaux) {
                    // header heure
                    $firstCreneau = $timeCreneaux->first();
                    $timeHeader = $firstCreneau->start->format('H:i') . ' à ' . $firstCreneau->end->format('H:i');
                    $sheet->setCellValue('A' . $rowIndex, $timeHeader);
                    $sheet->mergeCells('A' . $rowIndex . ':N' . $rowIndex);
                    $sheet->getStyle('A' . $rowIndex)->getFont()->setItalic(true);
                    $sheet->getStyle('A' . $rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                    $rowIndex++;

                    $chunkedCreneaux = array_chunk($timeCreneaux->all(), 3);
                    foreach ($chunkedCreneaux as $creneauxGroup) {
                        $startRow = $rowIndex;

                        $maxHeaderRows = 0;
                        $maxTuteeRows = 0;
                        $tuteeStartRows = [];

                        // On met les infos pour chaque creneau (s'il y a des infos à mettre)
                        foreach ($creneauxGroup as $index => $creneau) {
                            $headerRows = 3;
                            if ($creneau->tutor1 && $creneau->tutor1->proposedUvs->count() > 0) {
                                $headerRows++;
                            }

                            if ($creneau->tutor2) {
                                $headerRows++;
                                if ($creneau->tutor2->proposedUvs->count() > 0) {
                                    $headerRows++;
                                }
                            }

                            $maxHeaderRows = max($maxHeaderRows, $headerRows);

                            $tuteeRows = max(1, count($creneau->inscriptions));
                            $maxTuteeRows = max($maxTuteeRows, $tuteeRows);

                            $tuteeStartRows[$index] = $headerRows;
                        }

                        foreach ($creneauxGroup as $index => $creneau) {
                            $colOffset = $index * 5;
                            $localRowIndex = $startRow;

                            // Header salle
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'Salle: ' . $creneau->fk_salle);
                            $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                            $sheet->getStyle(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex)->getFont()->setBold(true);
                            $localRowIndex++;

                            // Tutor 1
                            $tutor1Name = $creneau->tutor1 ? ($creneau->tutor1->firstName . ' ' . $creneau->tutor1->lastName) : '-';
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'Tuteur 1: ' . $tutor1Name);
                            $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                            $sheet->getStyle(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex)
                                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2EFDA');
                            $localRowIndex++;

                            // Tutor 1 UVs
                            if ($creneau->tutor1 && $creneau->tutor1->proposedUvs->count() > 0) {
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'UVs proposées:');

                                $uvs = $creneau->tutor1->proposedUvs->pluck('code')->sort()->implode(', ');
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex, $uvs);
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                $localRowIndex++;
                            }

                            // Tutor 2
                            if ($creneau->tutor2) {
                                $tutor2Name = $creneau->tutor2->firstName . ' ' . $creneau->tutor2->lastName;
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'Tuteur 2: ' . $tutor2Name);
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                $sheet->getStyle(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex)
                                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2EFDA');
                                $localRowIndex++;

                                // Tutor 2 UVs
                                if ($creneau->tutor2->proposedUvs->count() > 0) {
                                    $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'UVs proposées:');

                                    $uvs = $creneau->tutor2->proposedUvs->pluck('code')->sort()->implode(', ');
                                    $sheet->setCellValue(Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex, $uvs);
                                    $sheet->mergeCells(Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                    $localRowIndex++;
                                }
                            }

                            // Cellules vides pour s'alligner
                            while ($localRowIndex < $startRow + $maxHeaderRows) {
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, '');
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                $localRowIndex++;
                            }

                            // Header pour les tutee
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'Tutorés inscrits:');
                            $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                            $sheet->getStyle(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex)->getFont()->setBold(true);
                            $localRowIndex++;

                            // Liste tous les tutee
                            $tuteeRowsWritten = 0;
                            foreach ($creneau->inscriptions as $inscription) {
                                $tutee = $inscription->tutee;
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, $tutee->firstName . ' ' . $tutee->lastName);
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex);

                                // UVs du Tutee
                                $uvsSouhaites = collect(json_decode($inscription->enseignements_souhaites ?? '[]'))->sort()->implode(', ');
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(3 + $colOffset) . $localRowIndex, $uvsSouhaites);
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(3 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);

                                $localRowIndex++;
                                $tuteeRowsWritten++;
                            }

                            // Rangées vides pour s'alligner
                            while ($tuteeRowsWritten < $maxTuteeRows) {
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, '');
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                $localRowIndex++;
                                $tuteeRowsWritten++;
                            }
                        }

                        $totalHeight = $maxHeaderRows + 1 + $maxTuteeRows;
                        $rowIndex = $startRow + $totalHeight;

                        if (!empty($creneauxGroup)) {
                            $borderStyle = [
                                'borders' => [
                                    'outline' => [
                                        'borderStyle' => Border::BORDER_MEDIUM,
                                        'color' => ['rgb' => '000000'],
                                    ],
                                ],
                            ];

                            foreach ($creneauxGroup as $index => $creneau) {
                                $colStart = Coordinate::stringFromColumnIndex(1 + $index * 5);
                                $colEnd = Coordinate::stringFromColumnIndex(4 + $index * 5);
                                $sheet->getStyle($colStart . $startRow . ':' . $colEnd . ($rowIndex - 1))->applyFromArray($borderStyle);
                            }
                        }
                        $rowIndex++;
                    }
                }
                $rowIndex++;
            }
        }

        // Def première feuille active
        if ($spreadsheet->getSheetCount() > 0) {
            $spreadsheet->setActiveSheetIndex(0);
        }

        // Réponse Excel
        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="creneaux_semestre.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
