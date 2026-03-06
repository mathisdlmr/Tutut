<?php

namespace App\Filament\Resources\Tutor\CreneauResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Tutor\CreneauResource;
use App\Models\Creneaux;
use App\Models\Semaine;
use Carbon\Carbon;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Page de liste des créneaux pour les tuteurs
 *
 * Cette page affiche les créneaux disponibles pour le shotgun des tuteurs.
 * Elle organise l'affichage en onglets par semaine et gère la logique
 * d'affichage des créneaux selon les règles de temps du shotgun.
 */
class ListCreneau extends ListRecords
{
    protected static string $resource = CreneauResource::class;

    /**
     * Définit les actions d'en-tête (vides pour cette ressource)
     *
     * @return array Tableau d'actions
     */
    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    /**
     * Détermine si la semaine suivante doit être affichée pour l'inscription
     *
     * Cette méthode vérifie, en fonction du rôle de l'utilisateur, si la date
     * d'inscription aux créneaux de la semaine suivante est déjà passée.
     * Permet aux tuteurs employés de réserver les créneaux à l'avance.
     *
     * @return bool Vrai si la semaine suivante doit être affichée
     */
    protected function shouldShowNextWeek(): bool
    {
        $user = Auth::user();
        $settings = $this->getRegistrationSettings();
        $now = Carbon::now();

        if ($user->role === Roles::Tutor->value) {
            $day = $settings['tutorRegistrationDay'] ?? 'friday';
            $time = $settings['tutorRegistrationTime'] ?? '16:00';
        } else {
            $day = $settings['employedTutorRegistrationDay'] ?? 'monday';
            $time = $settings['employedTutorRegistrationTime'] ?? '16:00';
        }

        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        $dayNumber = $dayMap[strtolower($day)] ?? 1;

        $registrationDate = Carbon::now()->startOfWeek()->addDays($dayNumber);

        $timeParts = explode(':', $time);
        $registrationDate->hour(intval($timeParts[0] ?? 0));
        $registrationDate->minute(intval($timeParts[1] ?? 0));
        $registrationDate->second(0);

        // Si on est après la date/heure d'inscription en fct du role, montrer la semaine suivante aussi
        return $now->greaterThanOrEqualTo($registrationDate);
    }

    /**
     * Récupère les paramètres de shotgun depuis le fichier de configuration
     *
     * Lit les paramètres de temps pour l'ouverture des inscriptions aux créneaux
     * selon le type de tuteur (employé ou bénévole)
     *
     * @return array Tableau associatif des paramètres de shotgun
     */
    protected function getRegistrationSettings(): array
    {
        $settingsPath = Storage::path('settings.json');
        if (file_exists($settingsPath)) {
            $settings = json_decode(file_get_contents($settingsPath), true);
            return $settings;
        }

        return [   // Valeurs par défaut si le fichier n'existe pas
            'employedTutorRegistrationDay' => 'monday',
            'employedTutorRegistrationTime' => '16:00',
            'tutorRegistrationDay' => 'friday',
            'tutorRegistrationTime' => '16:00',
        ];
    }

    /**
     * Définit les onglets pour la liste des créneaux
     *
     * Crée des onglets pour la semaine actuelle et éventuellement la semaine suivante
     * si la période de shotgun pour celle-ci est ouverte pour le rôle de l'utilisateur.
     * Chaque onglet affiche les créneaux d'une semaine spécifique.
     *
     * @return array Tableau d'onglets configurés
     */
    public function getTabs(): array
    {
        $userId = Auth::id();
        $showNextWeek = $this->shouldShowNextWeek();

        $currentWeek = Semaine::where('date_debut', '<=', Carbon::now())
            ->where('date_fin', '>=', Carbon::now())
            ->first();

        $tabs = [];

        if ($currentWeek) {
            $tabs["semaine-{$currentWeek->id}"] = Tab::make(__('resources.inscription_creneau.semaine_actuelle')." ({$currentWeek->numero})")
                ->badge(fn () => Creneaux::where('fk_semaine', $currentWeek->id)->count())
                ->modifyQueryUsing(function (Builder $query) use ($currentWeek) {
                    return $query->where('fk_semaine', $currentWeek->id);
                });

            if ($showNextWeek) {
                $nextWeek = Semaine::where('numero', $currentWeek->numero + 1)
                    ->where('fk_semestre', $currentWeek->fk_semestre)
                    ->first();

                if ($nextWeek) {
                    $tabs["semaine-{$nextWeek->id}"] = Tab::make(__('resources.inscription_creneau.semaine_prochaine')." ({$nextWeek->numero})")
                        ->badge(fn () => Creneaux::where('fk_semaine', $nextWeek->id)->count())
                        ->modifyQueryUsing(function (Builder $query) use ($nextWeek) {
                            return $query->where('fk_semaine', $nextWeek->id);
                        });
                }
            }
        }

        return $tabs;
    }
}
