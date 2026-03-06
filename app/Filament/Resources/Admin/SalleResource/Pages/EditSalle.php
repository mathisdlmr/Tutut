<?php

namespace App\Filament\Resources\Admin\SalleResource\Pages;

use App\Filament\Resources\Admin\SalleResource;
use App\Models\Salle;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Page d'édition d'une salle
 *
 * Cette page permet de modifier les propriétés d'une salle existante:
 * - Son numéro
 * - Ses disponibilités par jour de la semaine
 * - Ses horaires spécifiques pour les périodes d'examens (médians et finaux)
 */
class EditSalle extends EditRecord
{
    protected static string $resource = SalleResource::class;

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
            // Remplace 'h' par ':' et nettoie
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
     * Cette méthode:
     * - Charge les disponibilités existantes de la salle
     * - Formate les créneaux horaires standards pour les cases à cocher
     * - Prépare spécifiquement les champs pour les périodes d'examens (médians/finaux)
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

            if (!in_array($dispo->jour, ['Médians', 'Finaux'])) {
                $dispos[$dispo->jour][$creneauLabel] = true;
            }
        }

        // Ajout pour Médians / Finaux
        foreach (['Médians', 'Finaux'] as $jour) {
            $creneaux = $this->record->disponibilites->where('jour', $jour)->first();
            if ($creneaux) {
                $dispos[$jour]['debut'] = \Carbon\Carbon::createFromFormat('H:i:s', $creneaux->debut)->format('H:i');
                $dispos[$jour]['fin'] = \Carbon\Carbon::createFromFormat('H:i:s', $creneaux->fin)->format('H:i');
            }
        }

        $data['dispos'] = $dispos;

        return $data;
    }

    /**
     * Exécuté après la sauvegarde des modifications
     *
     * Cette méthode:
     * - Supprime toutes les disponibilités existantes
     * - Enregistre les nouvelles disponibilités standards (jours normaux)
     * - Enregistre les disponibilités spéciales pour les périodes d'examens
     */
    protected function afterSave(): void
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
