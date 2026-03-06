<?php

namespace App\Filament\Resources\Admin;

use App\Enums\Roles;
use App\Filament\Resources\Admin\SemaineResource\Pages;
use App\Models\CalendarOverride;
use App\Models\Creneaux;
use App\Models\DispoSalle;
use App\Models\Semaine;
use App\Models\Semestre;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Resource de gestion des semaines
 *
 * Cette ressource permet aux administrateurs et tuteurs privilégiés
 * de gérer les semaines du semestre et les créneaux associés.
 * Fonctionnalités :
 * - Création et édition des semaines (numéro, dates)
 * - Marquage des semaines de vacances
 * - Génération automatique de créneaux pour chaque semaine
 * - Création automatique de la semaine suivante
 * - Filtrage pour afficher uniquement les semaines à venir
 */
class SemaineResource extends Resource
{
    protected static ?string $model = Semaine::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    /**
     * Définit le label singulier de la ressource
     *
     * @return string Label traduit pour "semaine"
     */
    public static function getModelLabel(): string
    {
        return __('resources.admin.semaine.label');
    }

    /**
     * Définit le label pluriel de la ressource
     *
     * @return string Label traduit pour "semaines"
     */
    public static function getPluralModelLabel(): string
    {
        return __('resources.admin.semaine.plural_label');
    }

    /**
     * Définit le groupe de navigation dans le menu latéral
     *
     * @return string Groupe "Gestion" traduit
     */
    public static function getNavigationGroup(): string
    {
        return __('resources.admin.navigation_group.gestion');
    }

    protected static ?int $navigationSort = 1;

    /**
     * Vérifie si l'utilisateur peut accéder à cette ressource
     *
     * Seuls les administrateurs et tuteurs privilégiés y ont accès
     *
     * @return bool Vrai si l'utilisateur a les droits d'accès
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::Administrator->value ||
               Auth::user()->role === Roles::EmployedPrivilegedTutor->value);
    }

    /**
     * Définit le formulaire de création/édition d'une semaine
     *
     * Comprend:
     * - Numéro de semaine
     * - Semestre associé
     * - Indicateur de vacances
     * - Dates de début et fin
     *
     * @param Form $form Instance du formulaire
     * @return Form Formulaire configuré
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('numero')
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Select::make('fk_semestre')
                        ->relationship('semestre', 'code')
                        ->required()
                        ->columnSpan(1),

                    Toggle::make('is_vacances')
                        ->label(__('resources.admin.semaine.fields.is_vacances'))
                        ->helperText(__('resources.admin.semaine.fields.helperText_is_vacances'))
                        ->columnSpan(1),
                ]),

            Forms\Components\Grid::make(2)
                ->schema([
                    DatePicker::make('date_debut')
                        ->label(__('resources.admin.semaine.fields.date_debut'))
                        ->required()
                        ->columnSpan(1),

                    DatePicker::make('date_fin')
                        ->label(__('resources.admin.semaine.fields.date_fin'))
                        ->required()
                        ->columnSpan(1),
                ]),
        ]);
    }

    /**
     * Définit la table d'affichage des semaines
     *
     * Comprend:
     * - Action spéciale pour créer automatiquement la semaine suivante
     * - Colonnes (numéro, semestre, dates, statut vacances)
     * - Filtrage par semaines futures
     * - Actions (édition, suppression, génération de créneaux, marquer comme vacances)
     *
     * @param Table $table Instance de la table
     * @return Table Table configurée
     */
    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('Créer la prochaine semaine')
                    ->label(__('resources.admin.semaine.actions.creer_prochaine'))
                    ->action(function () {
                        $semestre = Semestre::where('is_active', true)->first();

                        if (!$semestre) {
                            Notification::make()
                                ->title('Erreur de création de semaine')
                                ->body('Aucun semestre actif n\'a été trouvé.')
                                ->danger()
                                ->send();
                            return false;
                        }

                        $semaines = Semaine::where('fk_semestre', $semestre->code)
                            ->orderByDesc('numero')
                            ->get();

                        $dernierNumero = 0;
                        foreach ($semaines as $semaine) {
                            if ($semaine->numero !== 'X') {
                                $dernierNumero = is_numeric($semaine->numero) ? intval($semaine->numero) : 0;
                                break;
                            }
                        }

                        $lastWeek = Semaine::where('fk_semestre', $semestre->code)
                            ->orderByDesc('numero')
                            ->first();

                        if ($lastWeek && $lastWeek->date_fin) {
                            $date_debut = \Carbon\Carbon::parse($lastWeek->date_fin)->addDay();
                        } else {
                            $date_debut = \Carbon\Carbon::parse($semestre->debut);
                        }

                        $date_fin = $date_debut->copy()->addDays(6);

                        if ($date_fin->gt($semestre->fin)) {
                            Notification::make()
                                ->title('Erreur de création de semaine')
                                ->body('La semaine n\'a pas pu être créée car elle dépasse la fin du semestre.')
                                ->danger()
                                ->send();
                            return false;
                        }

                        Semaine::create([
                            'numero' => $dernierNumero + 1,
                            'fk_semestre' => $semestre->code,
                            'date_debut' => $date_debut,
                            'date_fin' => $date_fin,
                            'is_vacances' => false,
                        ]);
                    })
                    ->color('primary')
                    ->icon('heroicon-o-plus'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label(__('resources.admin.semaine.fields.numero')),
                Tables\Columns\TextColumn::make('semestre.code')
                    ->label(__('resources.admin.semaine.fields.fk_semestre')),
                Tables\Columns\TextColumn::make('date_debut')
                    ->label(__('resources.admin.semaine.fields.date_debut'))
                    ->formatStateUsing(fn (string $state) => Carbon::parse($state)->locale('fr')->translatedFormat('d F Y')),
                Tables\Columns\TextColumn::make('date_fin')
                    ->label(__('resources.admin.semaine.fields.date_fin'))
                    ->formatStateUsing(fn (string $state) => Carbon::parse($state)->locale('fr')->translatedFormat('d F Y')),
                Tables\Columns\TextColumn::make('is_vacances')
                    ->label(__('resources.admin.semaine.fields.is_vacances'))
                    ->formatStateUsing(fn (bool $state) => $state ? __('resources.admin.semestre.values.oui') : __('resources.admin.semestre.values.non')),
            ])
            ->query(
                fn () =>
                Semaine::query()
                    ->when(
                        $semestre = Semestre::where('is_active', true)->first(),
                        fn ($query) => $query->where('fk_semestre', $semestre->code)
                    )
            )
           ->filters([
                Tables\Filters\Filter::make('future')
                    ->label(__('resources.admin.semaine.filters.future'))
                    ->query(fn (Builder $query) => $query->where('date_fin', '>', now()))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Action::make('générerCréneaux')
                    ->label(__('resources.admin.semaine.actions.generer_creneaux'))
                    ->action(fn (Semaine $record) => static::genererCreneaux($record))
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-plus-circle'),
                Action::make('vacances')
                    ->label(__('resources.admin.semaine.actions.vacances'))
                    ->icon('heroicon-o-sun')
                    ->requiresConfirmation()
                    ->action(function (Semaine $record) {
                        if (!$record->is_vacances) {
                            $record->update(['is_vacances' => true, 'numero' => 'X']);
                        } else {
                            $semestre = Semestre::where('code', $record->fk_semestre)->first();
                            $previousWeek = Semaine::where('fk_semestre', $semestre->code)
                                ->where('date_fin', '<', $record->date_debut)
                                ->orderByDesc('date_fin')
                                ->first();
                            $newNumero = $previousWeek ? $previousWeek->numero + 1 : 1;
                            $record->update(['is_vacances' => false, 'numero' => $newNumero]);
                        }
                    })
            ]);
    }

    /**
     * Génère automatiquement des créneaux pour une semaine donnée
     *
     * Cette méthode:
     * - Supprime les créneaux existants pour cette semaine
     * - Ne fait rien si c'est une semaine de vacances
     * - Détermine les horaires standards pour chaque jour
     * - Gère les horaires spéciaux pour les périodes d'examens (médians/finaux)
     * - Prend en compte les overrides du calendrier (jours fériés, etc.)
     * - Crée des créneaux pour chaque salle disponible à ces horaires
     *
     * @param Semaine $semaine La semaine pour laquelle générer les créneaux
     * @return void
     */
    public static function genererCreneaux(Semaine $semaine): void
    {
        if ($semaine->is_vacances) {
            Notification::make()
                ->title(__('resources.admin.semaine.messages.pas_generation_vacances'))
                ->danger()
                ->send();
            return;
        }

        Creneaux::where('fk_semaine', $semaine->id)->delete();

        // Définition des horaires standards pour chaque jour
        $horairesStandards = [
            'Lundi' => ['12:30', '18:40', '19:40'],
            'Mardi' => ['12:30', '18:40', '19:40'],
            'Mercredi' => ['12:30', '18:40', '19:40'],
            'Jeudi' => ['12:30', '18:40', '19:40'],
            'Vendredi' => ['12:30', '18:40', '19:40'],
            'Samedi' => ['10:30'],
        ];

        // Horaires spéciaux pour les périodes d'examens avec leur durée en minutes
        $horairesSpeciaux = [
            '08:00' => 120,
            '10:00' => 120,
            '12:30' => 90,
            '14:30' => 120,
            '16:30' => 120,
            '18:40' => 60,
            '19:40' => 60,
        ];

        // Mapping des jours français vers anglais pour Carbon
        $joursMap = [
            'Lundi' => 'Monday',
            'Mardi' => 'Tuesday',
            'Mercredi' => 'Wednesday',
            'Jeudi' => 'Thursday',
            'Vendredi' => 'Friday',
            'Samedi' => 'Saturday',
        ];

        // Durées standards pour chaque horaire en minutes
        $duréesStandards = [
            '12:30' => 90,
            '18:40' => 60,
            '19:40' => 60,
            '10:30' => 90,
        ];

        $semestre = Semestre::where('code', $semaine->fk_semestre)->first();
        $semestreStart = Carbon::parse($semestre->debut)->startOfWeek();
        $baseDate = $semestreStart->copy()->addWeeks($semaine->numero - 1);

        $jours = array_keys($joursMap);

        foreach ($jours as $jour) {
            $jourIndex = array_search($jour, array_keys($joursMap));
            $dateDuJour = $baseDate->copy()->addDays($jourIndex);

            // Vérifier s'il y a un override pour cette date
            $override = CalendarOverride::where('date', Carbon::parse($dateDuJour))->first();
            if ($override && $override->is_holiday) {
                continue; // Jour férié, pas de créneaux
            }

            $jourLabel = $jour;
            if ($override && $override->day_template) {
                $jourLabel = $override->day_template;
            }

            $horairesDuJour = $horairesStandards[$jour] ?? [];
            $durées = $duréesStandards;

            // Déterminer si on est en période d'examen
            if ($semestre) {
                if (
                    $semestre->debut_medians && $semestre->fin_medians &&
                    $dateDuJour->between($semestre->debut_medians, $semestre->fin_medians)
                ) {
                    $jourLabel = 'Médians';
                    $horairesDuJour = array_keys($horairesSpeciaux);
                    $durées = $horairesSpeciaux;
                } elseif (
                    $semestre->debut_finaux && $semestre->fin_finaux &&
                    $dateDuJour->between($semestre->debut_finaux, $semestre->fin_finaux)
                ) {
                    $jourLabel = 'Finaux';
                    $horairesDuJour = array_keys($horairesSpeciaux);
                    $durées = $horairesSpeciaux;
                }
            }

            // Appliquer l'override s'il existe
            if ($override && $override->day_template) {
                $jourLabel = $override->day_template;
                if (in_array($jourLabel, array_keys($horairesStandards))) {
                    $horairesDuJour = $horairesStandards[$jourLabel] ?? [];
                    $durées = $duréesStandards;
                }
            }

            // Récupérer les salles disponibles ce jour-là
            $dispos = DispoSalle::where('jour', $jourLabel)->get();

            // Créer les créneaux pour chaque salle disponible
            foreach ($dispos as $dispo) {
                $salleNumero = $dispo->fk_salle;

                foreach ($horairesDuJour as $heure) {
                    $durée = $durées[$heure];
                    $startTime = Carbon::parse($heure);
                    $endTime = $startTime->copy()->addMinutes($durée);

                    // Vérifier que le créneau est inclus dans les disponibilités de la salle
                    if (
                        $startTime->format('H:i:s') >= $dispo->debut &&
                        $endTime->format('H:i:s') <= $dispo->fin
                    ) {
                        $start = $dateDuJour->copy()->setTimeFromTimeString($heure);
                        $end = $start->copy()->addMinutes($durée);

                        Creneaux::create([
                            'tutor1_id' => null,
                            'tutor2_id' => null,
                            'fk_semaine' => $semaine->id,
                            'fk_salle' => $salleNumero,
                            'start' => $start,
                            'end' => $end,
                            'day_and_time' => $start->format('Y-m-d') . '_' . $start->format('H:i'),
                        ]);
                    }
                }
            }
        }

        Notification::make()
            ->title(__('resources.admin.semaine.messages.creneaux_generes', ['numero' => $semaine->numero]))
            ->success()
            ->send();
    }

    /**
     * Définit les pages associées à cette ressource
     *
     * @return array Liste des pages et leurs routes
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSemaines::route('/'),
            'edit' => Pages\EditSemaine::route('/{record}/edit'),
        ];
    }
}
