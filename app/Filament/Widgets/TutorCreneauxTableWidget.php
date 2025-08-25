<?php

namespace App\Filament\Widgets;

use App\Models\Creneaux;
use App\Enums\Roles;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

/**
 * Widget de visualisation des créneaux pour un tuteur
 * 
 * Ce widget affiche les créneaux à venir assignés au tuteur connecté.
 * Informations affichées pour chaque créneau :
 * - Date et jour
 * - Horaire
 * - Salle
 * - Tuteurs assignés (co-tuteurs)
 * - Nombre d'inscrits
 * - UVs demandées par les tutorés
 * 
 * Les créneaux sont regroupés par jour et horaire pour une meilleure organisation.
 */
class TutorCreneauxTableWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Prochains créneaux en tuteur.ice';

    public static function canView(): bool
    {
        $user = Auth::user();
        return in_array($user->role, [
            Roles::Tutor->value,
            Roles::EmployedTutor->value,
            Roles::EmployedPrivilegedTutor->value,
        ]);
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = Auth::user();

        return Creneaux::query()
            ->with(['tutor1.proposedUvs', 'tutor2.proposedUvs', 'salle', 'semaine', 'inscriptions'])
            ->where('end', '>=', now())
            ->where(function ($query) use ($user) {
                $query->where('tutor1_id', $user->id)
                      ->orWhere('tutor2_id', $user->id);
            })
            ->whereHas('inscriptions')
            ->orderBy('start');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                TextColumn::make('start')
                    ->label(__('resources.widgets.tutor_creneaux.columns.day'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->formatStateUsing(fn($state, $record) =>
                        ucfirst($record->start->translatedFormat('l d F Y'))
                    ),
                Split::make([
                    TextColumn::make('start')
                        ->label(__('resources.widgets.tutor_creneaux.columns.schedule'))
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->formatStateUsing(fn($state, $record) =>
                            $record->start->format('H:i') . ' - ' . $record->end->format('H:i')
                        ),
                    TextColumn::make('salle.numero')
                        ->label(__('resources.widgets.tutor_creneaux.columns.room'))
                        ->icon('heroicon-o-map-pin')
                        ->color('gray'),
                ]),
                Split::make([
                    TextColumn::make('tutor1.firstName')
                        ->label(__('resources.widgets.tutor_creneaux.columns.tutor1'))
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->placeholder(__('resources.common.placeholders.none'))
                        ->formatStateUsing(fn($state, $record) => $state . ' ' .($record->tutor1->lastName)[0].'.'),
                    TextColumn::make('tutor2.firstName')
                        ->label(__('resources.widgets.tutor_creneaux.columns.tutor2'))
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->placeholder(__('resources.common.placeholders.none'))
                        ->formatStateUsing(fn($state, $record) => $state . ' ' .($record->tutor2->lastName)[0].'.'),
                ]),
                TextColumn::make('inscriptions_count')
                    ->label(__('resources.widgets.tutor_creneaux.columns.registrations_count'))
                    ->counts('inscriptions')
                    ->icon('heroicon-o-users')
                    ->color('success'),

                TextColumn::make('id')
                    ->label(__('resources.widgets.tutor_creneaux.columns.requested_courses'))
                    ->formatStateUsing(function ($state, Creneaux $creneau) {
                        $uvs = $creneau->inscriptions
                            ->flatMap(fn($inscription) => json_decode($inscription->enseignements_souhaites ?? '[]'))
                            ->filter()
                            ->unique()
                            ->sort()
                            ->values();

                        return $uvs->implode(', ') ?: __('resources.common.placeholders.none');
                    })
                    ->icon('heroicon-o-academic-cap')
                    ->color('primary'),
            ]),
        ];
    }

    protected function getTableContentGrid(): ?array
    {
        return [
            'sm' => 2,
            'md' => 3,
            'lg' => 4,
            'xl' => 4,
        ];
    }

    protected function getTableGroups(): array
    {
        return [
            Tables\Grouping\Group::make('day')
                ->label(__('resources.widgets.tutor_creneaux.columns.day'))
                ->getTitleFromRecordUsing(fn(Creneaux $record) =>
                    ucfirst($record->start->translatedFormat('l d F Y'))
                )
                ->collapsible(false),
        ];
    }

    protected function getDefaultTableGroup(): ?string
    {
        return 'day';
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
}
