<?php

namespace App\Filament\Resources\Admin;

use App\Enums\Roles;
use App\Filament\Resources\Admin\ComptabiliteResource\Pages;
use App\Models\Comptabilite;
use App\Models\HeuresSupplementaires;
use App\Models\Semaine;
use App\Models\Semestre;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Resource de gestion de la comptabilité
 *
 * Cette ressource permet aux administrateurs de gérer la comptabilisation
 * des heures effectuées par les tuteurs employés.
 * Fonctionnalités :
 * - Affichage des heures par tuteur et par mois
 * - Filtrage par mois ou par statut de validation
 * - Visualisation détaillée des heures par semaine
 * - Ajout manuel d'heures supplémentaires
 * - Validation des heures pour la facturation
 * - Ajout de commentaires pour le BVE (Bureau de la Vie Étudiante)
 */
class ComptabiliteResource extends Resource
{
    protected static ?string $model = Comptabilite::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $label = 'Comptabilité';
    protected static ?string $pluralModelLabel = 'Comptabilité';
    protected static ?string $navigationGroup = 'Administration';

    /**
     * Stocke le mois filtré sélectionné actuellement
     */
    protected static ?string $selectedMonth = null;

    /**
     * Indique si on doit montrer uniquement les enregistrements non validés
     */
    protected static bool $showOnlyNonValides = false;

    /**
     * Vérifie si l'utilisateur peut accéder à cette ressource
     *
     * Seuls les administrateurs ont accès à la comptabilité
     *
     * @return bool Vrai si l'utilisateur a les droits d'accès
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && Auth::user()->role === Roles::Administrator->value;
    }

    /**
     * Définit le formulaire de base de la comptabilité
     *
     * @param Form $form Instance du formulaire
     * @return Form Formulaire configuré
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Hidden::make('fk_user'),
            Hidden::make('fk_semaine'),
            Textarea::make('commentaire_bve')
                ->label('Commentaire BVE')
                ->placeholder('Ajouter un commentaire pour le BVE')
                ->required()
        ]);
    }

    /**
     * Extrait la clé du mois à partir d'une semaine
     *
     * On utilise la date du samedi (dernier jour de la semaine)
     * comme référence pour déterminer le mois associé à une semaine.
     * Le format retourné est "AAAA-MM" (ex: "2023-09")
     *
     * @param Semaine $semaine La semaine dont on veut extraire le mois
     * @return string La clé du mois au format "AAAA-MM"
     */
    protected static function getMonthKeyFromSemaine(Semaine $semaine): string
    {
        return Carbon::parse($semaine->date_debut)->next(Carbon::SATURDAY)->format('Y-m');
    }

    /**
     * Définit la table d'affichage de la comptabilité
     *
     * Cette méthode complexe:
     * - Vérifie qu'un semestre actif existe
     * - Récupère tous les tuteurs employés ayant des heures
     * - Organise les données par mois
     * - Configure les filtres pour le mois et les validations
     * - Affiche un résumé des heures par tuteur
     * - Propose des actions pour modifier et valider les heures
     *
     * @param Table $table Instance de la table
     * @return Table Table configurée
     */
    public static function table(Table $table): Table
    {
        $semestreActif = Semestre::where('is_active', true)->first();

        if (!$semestreActif) {
            return $table
                ->query(User::query()->where('id', 0))
                ->columns([
                    TextColumn::make('id')->label('Pas de semestre actif')
                ]);
        }

        // Récupère toutes les semaines du semestre actif
        $semaines = Semaine::where('fk_semestre', $semestreActif->code)
            ->orderBy('numero')
            ->get();

        // Récupère tous les tuteurs employés ayant des heures
        $employedTutorIds = DB::table('comptabilite')
            ->whereIn('fk_semaine', $semaines->pluck('id'))
            ->pluck('fk_user')
            ->unique();

        $employedTutors = User::whereIn('id', $employedTutorIds)
            ->whereIn('role', [
                Roles::EmployedTutor->value,
                Roles::EmployedPrivilegedTutor->value
            ])
            ->orderBy('lastName')
            ->orderBy('firstName');

        // Groupe les semaines par mois
        $months = $semaines->groupBy(function ($semaine) {
            return self::getMonthKeyFromSemaine($semaine);
        });

        // Prépare les options de filtrage par mois
        $monthOptions = [];
        foreach ($months as $yearMonth => $semainesInMonth) {
            $monthLabel = ucfirst(Carbon::parse($yearMonth . '-01')->translatedFormat('F Y'));
            $monthOptions[$yearMonth] = $monthLabel;
        }

        // Détermine le mois par défaut (mois courant ou premier mois disponible)
        $defaultMonth = Carbon::now()->format('Y-m');
        if (!array_key_exists($defaultMonth, $monthOptions)) {
            $defaultMonth = array_key_first($monthOptions);
        }

        // Crée les groupes par mois pour l'affichage
        $monthGroups = [];
        foreach ($months as $yearMonth => $semainesInMonth) {
            $monthLabel = ucfirst(Carbon::parse($yearMonth . '-01')->translatedFormat('F Y'));

            $monthGroups[] = Tables\Grouping\Group::make($yearMonth)
                ->label($monthLabel)
                ->collapsible(true);
        }

        return $table
            ->query($employedTutors)
            ->filters([
                    Tables\Filters\Filter::make('non_valides')
                        ->label('Non validés')
                        ->query(function ($query) use ($semaines) {
                            self::$showOnlyNonValides = !self::$showOnlyNonValides;
                            $moisFiltre = self::$selectedMonth;
                            $relevantSemaines = $semaines;

                            if ($moisFiltre) {
                                $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                    return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                                });
                            }

                            $relevantSemaineIds = $relevantSemaines->pluck('id');
                            return $query->whereHas('comptabilites', function ($q) use ($relevantSemaineIds) {
                                $q->whereIn('fk_semaine', $relevantSemaineIds)
                                ->where('saisie', false);
                            });
                        })
                        ->default(),
                Tables\Filters\SelectFilter::make('month')
                    ->label('Mois')
                    ->options($monthOptions)
                    ->default($defaultMonth)
                    ->query(function ($query, array $data) use ($months) {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        self::$selectedMonth = $data['value'];
                        $semainesInMonth = $months[$data['value']] ?? collect();

                        return $query->whereHas('comptabilites', function ($q) use ($semainesInMonth) {
                            $q->whereIn('fk_semaine', $semainesInMonth->pluck('id'))
                              ->where('nb_heures', '>', 0);
                        });
                    }),
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        TextColumn::make('firstName')
                            ->label('Tuteur')
                            ->formatStateUsing(
                                fn ($state, User $record) =>
                                $record->firstName . ' ' . $record->lastName
                            )
                            ->weight('bold')
                            ->size('medium')
                            ->extraAttributes([
                                'class' => 'grow whitespace-nowrap',
                            ])
                            ->searchable(['firstName', 'lastName']),
                        IconColumn::make('valide')
                            ->label('Validé')
                            ->boolean()
                            ->size('xl')
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->getStateUsing(function (User $user, string $group = null) use ($semaines) {
                                $moisFiltre = $group ?? self::$selectedMonth;
                                $relevantSemaines = $semaines;
                                if ($moisFiltre) {
                                    $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                        return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                                    });
                                }

                                $comptabilites = Comptabilite::where('fk_user', $user->id)
                                    ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                                    ->get();

                                if ($comptabilites->isEmpty()) {
                                    return false;
                                }

                                return $comptabilites->every(fn ($compta) => $compta->saisie);
                            })
                    ]),
                    ViewColumn::make('semaines_heures')
                        ->label('Semaines et heures')
                        ->view('filament.tables.columns.semaines-heures')
                        ->extraAttributes(['class' => 'flex items-center gap-2'])
                        ->getStateUsing(function (User $user, string $group = null) use ($semaines) {
                            $moisFiltre = $group ?? self::$selectedMonth;

                            $relevantSemaines = $semaines;
                            if ($moisFiltre) {
                                $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                    return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                                });
                            }

                            $comptabilites = Comptabilite::where('fk_user', $user->id)
                                ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                                ->where('nb_heures', '>', 0);

                            if (self::$showOnlyNonValides) {
                                $comptabilites = $comptabilites->where('saisie', false);
                            }

                            $comptabilites = $comptabilites->get()->keyBy('fk_semaine');

                            $result = [];
                            foreach ($relevantSemaines as $semaine) {
                                $compta = $comptabilites->get($semaine->id);

                                $heuresSupplementaires = HeuresSupplementaires::where('fk_user', $user->id)
                                    ->where('fk_semaine', $semaine->numero)
                                    ->get();

                                if ($compta && $compta->nb_heures > 0) {
                                    $result[] = [
                                        'semaine' => $semaine,
                                        'heures' => $compta->nb_heures,
                                        'validated' => $compta->saisie,
                                        'heures_supp' => $heuresSupplementaires,
                                    ];
                                }
                            }

                            return $result;
                        }),
                    ViewColumn::make('total_heures')
                        ->label('Total')
                        ->view('filament.tables.columns.total-heures')
                        ->extraAttributes(['class' => 'flex items-center gap-2 font-bold text-primary-600'])
                        ->getStateUsing(function (User $user, string $group = null) use ($semaines) {
                            $moisFiltre = $group ?? self::$selectedMonth;

                            $relevantSemaines = $semaines;
                            if ($moisFiltre) {
                                $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                    return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                                });
                            }

                            $query = Comptabilite::where('fk_user', $user->id)
                                ->whereIn('fk_semaine', $relevantSemaines->pluck('id'));

                            if (self::$showOnlyNonValides) {
                                $query = $query->where('saisie', false);
                            }

                            return $query->sum('nb_heures');
                        })
                ])
            ])
            ->contentGrid([
                'sm' => 1,
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->actions([
                Action::make('modifier')
                    ->label('Modifier')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->button()
                    ->form(function (User $record, string $group = null) use ($semaines) {
                        $form = [];

                        $moisFiltre = $group ?? self::$selectedMonth;
                        $relevantSemaines = $semaines;
                        if ($moisFiltre) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                            });
                        }

                        $query = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0);

                        if (self::$showOnlyNonValides) {
                            $query = $query->where('saisie', false);
                        }

                        $comptabilites = $query->get()->keyBy('fk_semaine');

                        foreach ($relevantSemaines as $semaine) {
                            $comptabilite = $comptabilites->get($semaine->id);
                            $totalHeures = $comptabilite ? $comptabilite->nb_heures : 0;

                            if (self::$showOnlyNonValides && $comptabilite && $comptabilite->saisie) {
                                continue;
                            }

                            if ($totalHeures > 0) {
                                $heuresSupplementaires = HeuresSupplementaires::where('fk_user', $record->id)
                                    ->where('fk_semaine', $semaine->numero)
                                    ->get();

                                $heuresSupplementairesItems = [];
                                foreach ($heuresSupplementaires as $heureSup) {
                                    $heuresSupplementairesItems[] = [
                                        'nb_heures' => $heureSup->nb_heures,
                                        'commentaire' => $heureSup->commentaire,
                                    ];
                                }

                                $form[] = Section::make("Semaine {$semaine->numero}" . ($comptabilite && $comptabilite->saisie ? ' (Validée)' : ''))
                                ->schema([
                                    Grid::make()
                                        ->schema([
                                            TextInput::make("commentaire_bve_{$semaine->id}")
                                                ->label('Commentaire')
                                                ->default($comptabilite->commentaire_bve ?? '')
                                                ->placeholder('Ajouter un commentaire pour cette semaine')
                                                ->columnSpanFull(),

                                            Repeater::make("heures_supp_{$semaine->id}")
                                                ->label('Heures supplémentaires')
                                                ->schema([
                                                    Grid::make(2)
                                                        ->schema([
                                                            TextInput::make('nb_heures')
                                                                ->label("Nombre d'heures supplémentaires")
                                                                ->numeric()
                                                                ->minValue(0)
                                                                ->step(0.5)
                                                                ->required(),

                                                            TextInput::make('commentaire')
                                                                ->label('Justification')
                                                                ->placeholder('Justification des heures supplémentaires')
                                                                ->required(),
                                                        ])
                                                ])
                                                ->defaultItems(0)
                                                ->default($heuresSupplementairesItems ?? [])
                                                ->collapsible()
                                                ->collapsed()
                                                ->addActionLabel('Déclarer une nouvelle heure supplémentaire')
                                                ->itemLabel(
                                                    fn (array $state): ?string =>
                                                    isset($state['nb_heures']) ? "{$state['nb_heures']} heure(s) - {$state['commentaire']}" : null
                                                )
                                                ->columnSpanFull(),
                                        ])
                                        ->columnSpan(2)
                                ])
                                ->collapsible()
                                ->collapsed($comptabilite && $comptabilite->saisie);
                            }
                        }

                        return $form;
                    })
                    ->action(function (array $data, User $record) use ($semaines) {
                        foreach ($semaines as $semaine) {
                            if (isset($data["commentaire_bve_{$semaine->id}"]) || isset($data["heures_supp_{$semaine->id}"])) {
                                $comptabilite = Comptabilite::firstOrNew([
                                    'fk_user' => $record->id,
                                    'fk_semaine' => $semaine->id,
                                ]);

                                if (isset($data["commentaire_bve_{$semaine->id}"])) {
                                    $comptabilite->commentaire_bve = $data["commentaire_bve_{$semaine->id}"];
                                }

                                $oldHeuresSupp = HeuresSupplementaires::where('fk_user', $record->id)
                                    ->where('fk_semaine', $semaine->numero)
                                    ->get();

                                $oldTotalHeures = $oldHeuresSupp->sum('nb_heures');

                                HeuresSupplementaires::where('fk_user', $record->id)
                                    ->where('fk_semaine', $semaine->numero)
                                    ->delete();

                                $newTotalHeures = 0;

                                if (isset($data["heures_supp_{$semaine->id}"]) && is_array($data["heures_supp_{$semaine->id}"])) {
                                    foreach ($data["heures_supp_{$semaine->id}"] as $index => $heureSupp) {
                                        if (isset($heureSupp['nb_heures']) && isset($heureSupp['commentaire']) && floatval($heureSupp['nb_heures']) > 0) {
                                            HeuresSupplementaires::create([
                                                'fk_user' => $record->id,
                                                'fk_semaine' => $semaine->numero,
                                                'nb_heures' => floatval($heureSupp['nb_heures']),
                                                'commentaire' => $heureSupp['commentaire'],
                                            ]);

                                            $newTotalHeures += floatval($heureSupp['nb_heures']);
                                        }
                                    }
                                }

                                $heuresDiff = $newTotalHeures - $oldTotalHeures;

                                if ($heuresDiff != 0) {
                                    if (!$comptabilite->exists) {
                                        $comptabilite->nb_heures = max($heuresDiff, 0);
                                    } else {
                                        $comptabilite->nb_heures = max($comptabilite->nb_heures + $heuresDiff, 0);
                                    }
                                }

                                $comptabilite->save();
                            }
                        }
                    })
                    ->visible(function (User $record, string $group = null) use ($semaines) {
                        $moisFiltre = $group ?? self::$selectedMonth;
                        $relevantSemaines = $semaines;
                        if ($moisFiltre) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                            });
                        }

                        $query = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0);

                        if (self::$showOnlyNonValides) {
                            $query = $query->where('saisie', false);
                        }

                        return $query->exists();
                    }),
                Action::make('valider')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Valider les heures')
                    ->modalDescription(fn (User $record) => "Voulez-vous valider les heures de {$record->firstName} {$record->lastName} ?")
                    ->modalSubmitActionLabel('Oui, valider')
                    ->action(function (User $record, string $group = null) use ($semaines) {
                        $moisFiltre = $group ?? self::$selectedMonth;
                        $relevantSemaines = $semaines;
                        if ($moisFiltre) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                            });
                        }

                        $query = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0)
                            ->where('saisie', false);

                        $comptabilites = $query->get();

                        foreach ($comptabilites as $comptabilite) {
                            $comptabilite->saisie = true;
                            $comptabilite->save();
                        }
                    })
                    ->visible(function (User $record, string $group = null) use ($semaines) {
                        $moisFiltre = $group ?? self::$selectedMonth;
                        $relevantSemaines = $semaines;
                        if ($moisFiltre) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                            });
                        }

                        return Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0)
                            ->where('saisie', false)
                            ->exists();
                    }),

                Action::make('annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Annuler la validation des heures')
                    ->modalDescription(fn (User $record) => "Voulez-vous annuler la validation des heures de {$record->firstName} {$record->lastName} ?")
                    ->modalSubmitActionLabel('Oui, annuler')
                    ->action(function (User $record, string $group = null) use ($semaines) {
                        $moisFiltre = $group ?? self::$selectedMonth;
                        $relevantSemaines = $semaines;
                        if ($moisFiltre) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                            });
                        }

                        $query = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0);

                        if (self::$showOnlyNonValides) {
                            $query = $query->where('saisie', true);
                        } else {
                            $query = $query->where('saisie', true);
                        }

                        $comptabilites = $query->get();

                        foreach ($comptabilites as $comptabilite) {
                            $comptabilite->saisie = false;
                            $comptabilite->save();
                        }
                    })
                    ->visible(function (User $record, string $group = null) use ($semaines) {
                        if (self::$showOnlyNonValides) {
                            return false;
                        }

                        $moisFiltre = $group ?? self::$selectedMonth;
                        $relevantSemaines = $semaines;
                        if ($moisFiltre) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($moisFiltre) {
                                return self::getMonthKeyFromSemaine($semaine) === $moisFiltre;
                            });
                        }

                        $comptabilites = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0)
                            ->get();

                        return $comptabilites->isNotEmpty() &&
                               $comptabilites->every(fn ($compta) => $compta->saisie == true);
                    }),
            ])
            ->paginated(false)
            ->recordUrl(null);
    }

    /**
     * Détermine les mois auxquels l'utilisateur appartient en fonction de ses comptabilités
     *
     * Cette méthode filtre les comptabilités d'un utilisateur pour identifier les mois
     * où l'utilisateur a des heures enregistrées.
     *
     * @param User $user L'utilisateur dont on veut récupérer les mois d'activité
     * @param Collection $semaines Collection des semaines disponibles
     * @return array Liste des mois au format "AAAA-MM"
     */
    protected static function getUserMonths(User $user, Collection $semaines)
    {
        $comptabilites = Comptabilite::where('fk_user', $user->id)
            ->whereIn('fk_semaine', $semaines->pluck('id'))
            ->where('nb_heures', '>', 0)
            ->get();

        if ($comptabilites->isEmpty()) {
            return [];
        }

        $months = [];
        foreach ($comptabilites as $comptabilite) {
            $semaine = $semaines->firstWhere('id', $comptabilite->fk_semaine);
            if ($semaine) {
                $month = self::getMonthKeyFromSemaine($semaine);
                $months[] = $month;
            }
        }

        return array_unique($months);
    }

    /**
     * Définit les pages associées à cette ressource
     *
     * La comptabilité ne comprend qu'une page de liste,
     * qui intègre toutes les fonctionnalités de gestion.
     *
     * @return array Liste des pages et leurs routes
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComptabilite::route('/'),
        ];
    }
}
