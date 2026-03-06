<?php

namespace App\Filament\Resources\Tutor\ComptabiliteTutorResource\Pages;

use App\Filament\Resources\Tutor\ComptabiliteTutorResource;
use App\Models\Creneaux;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Page de création/modification de la comptabilité pour les tuteurs
 *
 * Cette page permet aux tuteurs employés de :
 * - Marquer les créneaux comme comptabilisés ou non
 * - Ajouter des heures supplémentaires avec justification
 * - Sauvegarder l'ensemble des modifications pour la validation
 */
class CreateTutorComptabilite extends CreateRecord
{
    protected static string $resource = ComptabiliteTutorResource::class;

    /**
     * Définit les actions du formulaire en bas de page
     *
     * Ajoute deux boutons :
     * - Annuler : pour revenir à la liste sans sauvegarder
     * - Enregistrer : pour confirmer les heures et sauvegarder
     *
     * @return array Tableau des actions du formulaire
     */
    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('cancel')
                ->label(__('resources.comptabilite_tutor.actions.cancel'))
                ->url($this->previousUrl ?? static::getResource()::getUrl())
                ->color('gray'),

            \Filament\Actions\Action::make('save')
                ->label(__('resources.comptabilite_tutor.actions.save'))
                ->action('create')
                ->color('primary'),
        ];
    }

    /**
     * Définit les actions disponibles dans l'en-tête de la page
     *
     * Ajoute deux boutons identiques à ceux du bas du formulaire :
     * - Annuler : pour revenir à la liste sans sauvegarder
     * - Enregistrer : pour confirmer les heures et sauvegarder
     *
     * @return array Tableau des actions d'en-tête
     */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('cancel')
                ->label(__('resources.comptabilite_tutor.actions.cancel'))
                ->url($this->previousUrl ?? static::getResource()::getUrl())
                ->color('gray'),

            \Filament\Actions\Action::make('save')
                ->label(__('resources.comptabilite_tutor.actions.save'))
                ->action('create')
                ->color('primary'),
        ];
    }

    /**
     * Obtient le titre de la page
     *
     * @return string Le titre traduit de la page
     */
    public function getTitle(): string
    {
        return __('resources.comptabilite_tutor.actions.confirm_hours');
    }

    /**
     * Obtient le sous-titre de la page
     *
     * Affiche un rappel concernant la sauvegarde des données
     *
     * @return string|Htmlable|null Le sous-titre traduit de la page
     */
    public function getSubheading(): string|Htmlable|null
    {
        $user = Auth::user();
        return __('resources.comptabilite_tutor.subheadings.save_reminder');
    }

    /**
     * Active ou désactive la comptabilisation d'un créneau
     *
     * Cette méthode permet à un tuteur de marquer un créneau comme comptabilisé
     * ou non, en fonction de son rôle dans ce créneau (tuteur 1 ou 2).
     * Elle est appelée depuis la vue via des boutons de bascule.
     *
     * @param int $creneauId L'identifiant du créneau à modifier
     * @param bool $value La valeur à définir (true = comptabilisé, false = non comptabilisé)
     * @throws \Illuminate\Auth\Access\AuthorizationException Si l'utilisateur n'est pas autorisé
     */
    public function toggleCreneauCompted($creneauId, $value)
    {
        $user = Auth::user();
        $creneau = Creneaux::findOrFail($creneauId);

        if ($creneau->tutor1_id === $user->id) {
            $creneau->tutor1_compted = $value;
        } elseif ($creneau->tutor2_id === $user->id) {
            $creneau->tutor2_compted = $value;
        } else {
            abort(403, 'Non autorisé');
        }

        $creneau->save();
    }

    /**
     * Crée ou met à jour les enregistrements de comptabilité
     *
     * Cette méthode est appelée lors de la soumission du formulaire et :
     * - Calcule le nombre total d'heures pour chaque semaine
     * - Enregistre ou met à jour les enregistrements de comptabilité
     * - Gère les heures supplémentaires avec leurs justifications
     * - Affiche une notification de confirmation
     *
     * @param bool $another Indique si une autre création doit être lancée après celle-ci (non utilisé)
     */
    public function create(bool $another = false): void
    {
        $user = Auth::user();

        $creneaux = Creneaux::where(function ($q) use ($user) {
            $q->where('tutor1_id', $user->id)
              ->where('tutor1_compted', true)
              ->orWhere('tutor2_id', $user->id)
              ->where('tutor2_compted', true);
        })
            ->whereHas('inscriptions')
            ->get();

        $creneauxParSemaine = $creneaux->groupBy('fk_semaine');
        $formState = $this->form->getState();
        $semestreActif = \App\Models\Semestre::where('is_active', true)->first();
        $semaines = \App\Models\Semaine::where('fk_semestre', $semestreActif->code)->get();

        foreach ($semaines as $semaine) {
            $heuresSupp = collect($formState["heures_supplementaires_{$semaine->id}"] ?? []);
            $creneaux = $creneauxParSemaine[$semaine->id] ?? collect();

            $totalMinutes = $creneaux->sum(fn ($creneau) => $creneau->start->diffInMinutes($creneau->end));
            $heuresSuppTotal = $heuresSupp->sum('nb_heures') ?? 0;
            $nb_heures = ($totalMinutes / 60) + $heuresSuppTotal;

            if ($nb_heures > 0) {
                \App\Models\Comptabilite::updateOrCreate(
                    [
                        'fk_user' => $user->id,
                        'fk_semaine' => $semaine->id,
                    ],
                    [
                        'nb_heures' => $nb_heures,
                    ]
                );
            }

            \App\Models\HeuresSupplementaires::where('fk_user', $user->id)
                ->where('fk_semaine', $semaine->id)
                ->delete();

            foreach ($heuresSupp as $heureSupp) {
                \App\Models\HeuresSupplementaires::create([
                    'fk_user' => $user->id,
                    'fk_semaine' => $semaine->id,
                    'nb_heures' => $heureSupp['nb_heures'],
                    'commentaire' => $heureSupp['commentaire'],
                ]);
            }
        }

        Notification::make()
            ->title(__('resources.comptabilite_tutor.notifications.hours_updated'))
            ->success()
            ->send();

        $this->redirect(ComptabiliteTutorResource::getUrl('index'));
    }
}
