<?php

namespace App\Filament\Resources\Tutor;

use App\Enums\Roles;
use App\Filament\Resources\Tutor\ComptabiliteTutorResource\Pages;
use App\Models\Comptabilite;
use App\Models\Creneaux;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Resource de comptabilité pour les tuteurs employés
 *
 * Cette ressource permet aux tuteurs employés de gérer et suivre
 * leurs heures de tutorat pour la comptabilisation.
 * Fonctionnalités :
 * - Affichage des créneaux par semaine
 * - Marquage des créneaux comme comptabilisés
 * - Ajout d'heures supplémentaires avec justification
 * - Filtrage pour afficher uniquement les créneaux non comptabilisés
 * - Tableau récapitulatif des heures par semaine
 * - Statut de validation par l'administration
 */
class ComptabiliteTutorResource extends Resource
{
    protected static ?string $model = Comptabilite::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    /**
     * Obtient le label du modèle pour la ressource
     *
     * @return string Le label traduit pour le modèle
     */
    public static function getModelLabel(): string
    {
        return __('resources.comptabilite_tutor.label');
    }

    /**
     * Obtient le label pluriel du modèle pour la ressource
     *
     * @return string Le label pluriel traduit pour le modèle
     */
    public static function getPluralModelLabel(): string
    {
        return __('resources.comptabilite_tutor.plural_label');
    }

    /**
     * Obtient le groupe de navigation pour la ressource
     *
     * @return string Le groupe de navigation traduit
     */
    public static function getNavigationGroup(): string
    {
        return __('resources.common.navigation.tutorat');
    }

    protected static ?int $navigationSort = 2;

    /**
     * Vérifie si l'utilisateur peut accéder à cette ressource
     *
     * Seuls les tuteurs employés (privilégiés ou non) peuvent accéder
     * à cette ressource de comptabilité.
     *
     * @return bool Vrai si l'utilisateur a le droit d'accéder, faux sinon
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::EmployedTutor->value);
    }

    /**
     * Active ou désactive la comptabilisation d'un créneau
     *
     * Cette méthode permet à un tuteur de marquer un créneau comme comptabilisé
     * ou non, en fonction de son rôle dans ce créneau (tuteur 1 ou 2).
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
     * Configure le formulaire de comptabilité des heures
     *
     * Ce formulaire permet aux tuteurs employés de :
     * - Filtrer pour voir uniquement les créneaux non comptabilisés
     * - Voir et gérer leurs créneaux par semaine
     * - Ajouter des heures supplémentaires avec justification
     * - Marquer les créneaux comme comptabilisés
     *
     * @param Form $form Le formulaire à configurer
     * @return Form Le formulaire configuré
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('filter_uncounted')
                    ->label(__('resources.comptabilite_tutor.fields.only_unaccounted'))
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('refresh_key', now()))
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('refresh_key'),

                Forms\Components\Group::make()->schema(function (Forms\Get $get) {
                    $user = Auth::user();
                    $semestreActif = \App\Models\Semestre::where('is_active', true)->first();

                    if (!$semestreActif) {
                        return [];
                    }

                    $filterUncounted = $get('filter_uncounted') ?? false;

                    $semainesQuery = \App\Models\Semaine::where('fk_semestre', $semestreActif->code);
                    $semainesQuery->whereNotIn('id', function ($query) use ($user) {  // N'afficher que les semaines pas encore saisies
                        $query->select('fk_semaine')
                            ->from('comptabilite')
                            ->where('fk_user', $user->id)
                            ->where('saisie', true);
                    });
                    $semaines = $semainesQuery->orderByDesc('numero')->get();

                    $allCreneaux = \App\Models\Creneaux::with(['salle', 'inscriptions', 'semaine'])
                        ->where(function ($q) use ($user) {
                            $q->where('tutor1_id', $user->id)
                                ->orWhere('tutor2_id', $user->id);
                        })
                        ->whereHas('inscriptions')
                        ->get()
                        ->groupBy('fk_semaine');

                    $semaines = $semaines->filter(function ($semaine) use ($user, $allCreneaux) {    // Et on filtre sur les semaines qui ont des créneaux
                        $hasCreneaux = $allCreneaux->has($semaine->id);

                        $hasHeuresSupp = \App\Models\HeuresSupplementaires::where('fk_user', $user->id)
                            ->where('fk_semaine', $semaine->id)
                            ->exists();

                        return $hasCreneaux || $hasHeuresSupp;
                    });

                    return $semaines->map(function ($semaine) use ($allCreneaux, $user, $filterUncounted) {
                        $creneaux = $allCreneaux[$semaine->id] ?? collect();

                        if ($filterUncounted) {
                            $creneaux = $creneaux->filter(function ($creneau) use ($user) {
                                $key = $creneau->tutor1_id === $user->id ? 'tutor1_compted' : 'tutor2_compted';
                                return $creneau->$key === null;
                            });
                        }

                        $heuresSupp = \App\Models\HeuresSupplementaires::where('fk_user', $user->id)
                            ->where('fk_semaine', $semaine->id)
                            ->get()
                            ->map(fn ($heure) => [
                                'nb_heures' => $heure->nb_heures,
                                'commentaire' => $heure->commentaire,
                            ])
                            ->toArray();

                        return Forms\Components\Group::make([
                            Forms\Components\Section::make(__('resources.common.format.semaine_numero', ['number' => $semaine->numero]) . " — du {$semaine->date_debut->format('d/m')} au {$semaine->date_fin->format('d/m')}")
                                ->schema([
                                    $creneaux->isEmpty()
                                    ? Forms\Components\View::make('filament.components.empty-states.no-creneaux')
                                        ->columnSpanFull()
                                    : Forms\Components\Grid::make(3)
                                        ->schema(
                                            $creneaux->map(function ($creneau) use ($user) {
                                                $tutorKey = $creneau->tutor1_id === $user->id ? 'tutor1_compted' : 'tutor2_compted';
                                                $isCounted = $creneau->$tutorKey;
                                                return Forms\Components\View::make('filament.components.form.slot-creneau')
                                                    ->viewData([
                                                        'creneau' => $creneau,
                                                        'isCounted' => $isCounted,
                                                    ])
                                                    ->columnSpan(1);
                                            })->toArray()
                                        ),

                                    Forms\Components\Repeater::make("heures_supplementaires_{$semaine->id}")
                                        ->label(__('resources.comptabilite_tutor.fields.heures_supp'))
                                        ->schema([
                                            Grid::make(2)
                                                ->schema([
                                                    TextInput::make('nb_heures')
                                                        ->label(__('resources.comptabilite_tutor.fields.duree'))
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->step(0.5)
                                                        ->default('')
                                                        ->required(),
                                                    TextInput::make('commentaire')
                                                        ->label('Justification')
                                                        ->placeholder('Justification des heures supplémentaires')
                                                        ->required(),
                                                ])
                                        ])
                                        ->default($heuresSupp ?? [])
                                        ->collapsible()
                                        ->collapsed()
                                        ->itemLabel(
                                            fn (array $state): ?string =>
                                            isset($state['nb_heures']) ? "{$state['nb_heures']} heure(s) - {$state['commentaire']}" : null
                                        )
                                        ->columnSpanFull()
                                        ->visible(fn () => !$filterUncounted)
                                ])
                        ])->columnSpanFull();
                    })->toArray();
                })->columnSpanFull()
            ]);
    }

    /**
     * Configure la table d'affichage récapitulative des comptabilités
     *
     * Cette table affiche :
     * - Les semaines
     * - Le nombre d'heures comptabilisées par semaine
     * - Les commentaires du BVE (Bureau de la Vie Étudiante)
     * - Le statut de saisie de la comptabilité
     *
     * @param Table $table La table à configurer
     * @return Table La table configurée
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('semaine.numero')
                    ->label(__('resources.comptabilite_tutor.fields.semaine'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('nb_heures')
                    ->label(__('resources.comptabilite_tutor.fields.heures_comptabilisees')),
                Tables\Columns\TextColumn::make('commentaire_bve')
                    ->limit(50),
                Tables\Columns\IconColumn::make('saisie')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
            ])
            ->defaultSort('semaine.numero', 'asc')
            ->modifyQueryUsing(function ($query) {
                $user = Auth::user();
                return $query->where('fk_user', $user->id);
            })
            ->filters([])
            ->paginated(false)
            ->recordUrl(null);
    }

    /**
     * Définit les relations du modèle
     *
     * Aucune relation spécifique n'est définie pour cette ressource
     *
     * @return array Tableau vide car pas de relations particulières
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Définit les pages disponibles pour cette ressource
     *
     * Cette ressource contient deux pages :
     * - la liste des comptabilités (index)
     * - la création/édition d'une comptabilité
     *
     * @return array Tableau associatif des pages
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTutorComptabilites::route('/'),
            'create' => Pages\CreateTutorComptabilite::route('/create'),
        ];
    }
}
