<?php

namespace App\Filament\Widgets;

use App\Enums\Roles;
use App\Models\Comptabilite;
use App\Models\Creneaux;
use App\Models\Inscription;
use App\Models\Semaine;
use App\Models\Semestre;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Widget de tableau de bord administrateur
 *
 * Ce widget affiche des statistiques clés pour les administrateurs concernant
 * l'utilisation de la plateforme.
 * Statistiques présentées :
 * - Moyenne de tutorés par soir
 * - Moyenne de tutorés par créneau
 * - Nombre total de tutorés actifs
 * - Créneaux ouverts par semaine
 * - Nombre de tuteurs bénévoles
 * - Total des heures effectuées
 * - UVs les plus demandées
 */
class AdminWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        $user = Auth::user();

        return in_array($user->role, [
            Roles::Administrator->value,
        ]);
    }

    protected function getStats(): array
    {
        $semestreActif = Semestre::where('is_active', true)->first();
        if (!$semestreActif) {
            return [];
        }

        $creneauxAvecInscriptions = Creneaux::withCount('inscriptions')
            ->whereBetween('start', [$semestreActif->debut, $semestreActif->fin])
            ->whereHas('inscriptions')
            ->get();

        $totalInscriptions = $creneauxAvecInscriptions->sum('inscriptions_count');
        $totalCreneauxAvecInscrits = $creneauxAvecInscriptions->count();

        $creneauxSoir = $creneauxAvecInscriptions->filter(function ($creneau) {
            return $creneau->start->format('H:i:s') > '18:00:00';
        });

        $moyenneParSoir = $creneauxSoir
            ->groupBy(fn ($creneau) => $creneau->start->format('Y-m-d'))
            ->map(fn ($dayCreneaux) => $dayCreneaux->sum('inscriptions_count'))
            ->avg();

        $moyenneParCreneau = $totalCreneauxAvecInscrits > 0 ? $totalInscriptions / $totalCreneauxAvecInscrits : 0;

        $nbTutorésUniques = Inscription::whereHas('creneau', function ($query) use ($semestreActif) {
            $query->whereBetween('start', [$semestreActif->debut, $semestreActif->fin]);
        })->distinct('tutee_id')->count();

        $creneauxParSemaine = Creneaux::where('tutor1_id', '!=', null)
            ->orWhere('tutor2_id', '!=', null)
            ->get()
            ->groupBy(fn ($creneau) => optional($creneau->semaine)->numero)
            ->map(fn ($group) => $group->count());

        $nbTuteursBénévoles = User::where('role', 'tutor')->count();

        $semaineIds = Semaine::where('fk_semestre', $semestreActif->code)->pluck('id');

        $heuresTotales = Comptabilite::whereIn('fk_semaine', $semaineIds)
            ->whereHas('user', function ($q) {
                $q->whereIn('role', ['tutor', 'employedTutor', 'employedPrivilegedTutor']);
            })
            ->sum('nb_heures');

        $topUVs = Inscription::whereHas('creneau', function ($query) use ($semestreActif) {
            $query->whereBetween('start', [$semestreActif->debut, $semestreActif->fin]);
        })->get()
        ->flatMap(fn ($inscription) => json_decode($inscription->enseignements_souhaites ?? '[]'))
        ->filter()
        ->countBy()
        ->sortDesc()
        ->take(5)
        ->map(fn ($count, $code) => "$code ($count)")
        ->implode(', ');

        return [
            Stat::make(__('resources.widgets.admin.stats.avg_tutees_per_night'), number_format($moyenneParSoir, 1)),
            Stat::make(__('resources.widgets.admin.stats.avg_tutees_per_slot'), number_format($moyenneParCreneau, 1)),
            Stat::make(__('resources.widgets.admin.stats.total_active_tutees'), $nbTutorésUniques),
            Stat::make(__('resources.widgets.admin.stats.open_slots_per_week'), $creneauxParSemaine->avg() ? round($creneauxParSemaine->avg(), 2) : 0),
            Stat::make(__('resources.widgets.admin.stats.volunteer_tutors'), $nbTuteursBénévoles),
            Stat::make(__('resources.widgets.admin.stats.total_hours'), round($heuresTotales, 1) . 'h'),
            Stat::make(__('resources.widgets.admin.stats.most_requested_courses'), $topUVs ?: __('resources.common.placeholders.none')),
        ];
    }
}
