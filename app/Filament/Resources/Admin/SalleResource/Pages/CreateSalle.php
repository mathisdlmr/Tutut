<?php

namespace App\Filament\Resources\Admin\SalleResource\Pages;

use App\Filament\Resources\Admin\SalleResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Page de création d'une salle
 *
 * Cette page permet de créer une nouvelle salle et de configurer:
 * - Son numéro
 * - Ses disponibilités par jour de la semaine (via cases à cocher)
 * - Ses horaires spécifiques pour les périodes d'examens
 */
class CreateSalle extends CreateRecord
{
    protected static string $resource = SalleResource::class;

    /**
     * Analyse et normalise un créneau horaire au format texte
     *
     * Convertit des formats comme "12h30-14h" en heures normalisées ["12:30:00", "14:00:00"]
     *
     * @param string $creneau Le créneau au format texte (ex: "12h30-14h")
     * @return array Tableau contenant les heures de début et fin normalisées
     */
    private function parseCreneau($creneau): array
    {
        [$debut, $fin] = explode('-', $creneau);

        $normalizeTime = function ($time) {
            $time = trim(str_replace('h', ':', $time));

            // Si "12" ou "12:" → "12:00:00"
            if (preg_match('/^\d{1,2}$/', $time) || preg_match('/^\d{1,2}:$/', $time)) {
                $time = rtrim($time, ':') . ':00:00';
            }

            // Si "12:30" → "12:30:00"
            if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
                $time .= ':00';
            }

            // Si déjà bien formaté, on garde
            return $time;
        };

        return [$normalizeTime($debut), $normalizeTime($fin)];
    }

    /**
     * Prépare les données du formulaire avant qu'il ne soit rempli
     *
     * Cette méthode transforme les disponibilités enregistrées en base de données
     * en structure adaptée pour l'affichage dans le formulaire.
     *
     * @param array $data Les données initiales
     * @return array Les données modifiées pour le formulaire
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load('disponibilites');

        $dispos = [];

        foreach ($this->record->disponibilites as $dispo) {
            $formatHeure = fn ($time) => \Carbon\Carbon::createFromFormat('H:i:s', $time)->format('H\hi');
            $creneauLabel = $formatHeure($dispo->debut) . '-' . $formatHeure($dispo->fin);
            $dispos[$dispo->jour][$creneauLabel] = true;
        }

        $data['dispos'] = $dispos;

        return $data;
    }

    /**
     * Exécuté après la création de la salle
     *
     * Enregistre les disponibilités configurées dans le formulaire:
     * - Traite les créneaux standards (cases à cocher) pour les jours normaux
     * - Traite séparément les créneaux pour les périodes d'examens (médians et finaux)
     */
    protected function afterCreate(): void
    {
        $this->record->disponibilites()->delete();

        $dispos = $this->form->getState()['dispos'] ?? [];

        foreach ($dispos as $jour => $creneaux) {
            // Traitement standard (checkboxes)
            foreach ($creneaux as $creneau => $isChecked) {
                if (in_array($jour, ['Médians', 'Finaux'])) {
                    // Skip Médians et Finaux ici, ils sont traités à part
                    continue;
                }

                if ($isChecked) {
                    [$debut, $fin] = $this->parseCreneau($creneau);

                    $this->record->disponibilites()->create([
                        'jour' => ucfirst($jour),
                        'debut' => $debut,
                        'fin' => $fin,
                    ]);
                }
            }
        }

        // Traitement spécifique pour Médians et Finaux
        foreach (['Médians', 'Finaux'] as $jour) {
            if (isset($dispos[$jour]['debut'], $dispos[$jour]['fin'])) {
                [$debut, $fin] = $this->parseCreneau($dispos[$jour]['debut'] . '-' . $dispos[$jour]['fin']);

                $this->record->disponibilites()->create([
                    'jour' => $jour,
                    'debut' => $debut,
                    'fin' => $fin,
                ]);
            }
        }
    }
}
