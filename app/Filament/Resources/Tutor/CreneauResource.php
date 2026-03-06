<?php

namespace App\Filament\Resources\Tutor;

use App\Enums\Roles;
use App\Filament\Resources\Tutor\CreneauResource\Pages;
use App\Models\Creneaux;
use App\Models\Semaine;
use Carbon\Carbon;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Resource de gestion des créneaux pour les tuteurs
 *
 * Cette ressource permet aux tuteurs de consulter et de s'inscrire
 * aux créneaux disponibles pour dispenser des séances de tutorat.
 * Fonctionnalités :
 * - Affichage des créneaux par jour et horaire
 * - Logique de "shotgun" permettant de réserver des créneaux selon son rôle
 * - Règles d'accès aux créneaux basées sur le type de tuteur (employé/bénévole)
 * - Visualisation des informations sur chaque créneau (salle, horaire, semaine)
 * - Affichage des UVs proposées par les tuteurs déjà inscrits
 */
class CreneauResource extends Resource
{
    protected static ?string $model = Creneaux::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Shotgun Créneaux';
    protected static ?string $pluralModelLabel = 'Créneaux';
    protected static ?string $navigationGroup = 'Tutorat';
    protected static ?int $navigationSort = 1;

    /**
     * Obtient le label de navigation pour la ressource
     *
     * @return string Le label traduit pour la navigation
     */
    public static function getNavigationLabel(): string
    {
        return __('resources.creneau.navigation_label');
    }

    /**
     * Obtient le label du modèle pour la ressource
     *
     * @return string Le label traduit pour le modèle
     */
    public static function getModelLabel(): string
    {
        return __('resources.creneau.label');
    }

    /**
     * Obtient le label pluriel du modèle pour la ressource
     *
     * @return string Le label pluriel traduit pour le modèle
     */
    public static function getPluralModelLabel(): string
    {
        return __('resources.creneau.plural_label');
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

    /**
     * Vérifie si l'utilisateur peut accéder à cette ressource
     *
     * Seuls les tuteurs (employés, privilégiés ou bénévoles) peuvent accéder
     *
     * @return bool Vrai si l'utilisateur a le droit d'accéder, faux sinon
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::EmployedTutor->value
            || Auth::user()->role === Roles::Tutor->value);
    }

    /**
     * Définit le schéma de formulaire (vide pour cette ressource)
     *
     * @param Form $form Le formulaire à configurer
     * @return Form Le formulaire configuré
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            //
        ]);
    }

    /**
     * Calcule l'attribut d'horaire complet pour un créneau
     *
     * @return string L'horaire formaté (début - fin)
     */
    public function getHoraireCompletAttribute(): string
    {
        return $this->start->format('H:i') . ' - ' . $this->end->format('H:i');
    }

    /**
     * Formate les codes d'UVs pour un affichage plus compact
     *
     * Regroupe les codes d'UVs par préfixe pour optimiser l'affichage.
     * Par exemple, "MT41, MT42, MT45" devient "MT41/42/45"
     *
     * @param Collection $codes Collection des codes d'UVs à formater
     * @return string Les codes formatés et regroupés
     */
    public static function formatGroupedUvs(Collection $codes): string
    {
        return $codes
            ->sort()
            ->groupBy(fn ($code) => substr($code, 0, 2))
            ->map(function ($group, $prefix) {
                $suffixes = $group->map(fn ($code) => substr($code, 2))->sort()->join('/');
                return $prefix . $suffixes;
            })
            ->values()
            ->join("\n");
    }

    /**
     * Balance les éléments horizontalement dans une ligne
     *
     * Cette méthode permet de réaliser un affichage de texte avec des éléments
     * qui doivent être affichés horizontalement, mais qui ne peuvent pas être
     * affichés tous en une seule ligne.
     *
     * @param array $items Tableau d'éléments à afficher
     * @param int $maxCharsPerLine Nombre de caractères maximum par ligne
     * @return array Tableau d'éléments répartis sur plusieurs lignes
     */
    public static function balanceHorizontally(array $items, int $maxCharsPerLine): array
    {
        $lines = [];
        $currentLine = [];
        $currentLength = 0;

        foreach ($items as $item) {
            $itemLength = strlen($item);

            if ($currentLength + $itemLength + count($currentLine) * 2 > $maxCharsPerLine) {
                // Si dépasse, on ferme la ligne et commence une nouvelle
                $lines[] = $currentLine;
                $currentLine = [];
                $currentLength = 0;
            }

            $currentLine[] = $item;
            $currentLength += $itemLength;
        }

        if (!empty($currentLine)) {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    /**
     * Récupère les paramètres de shotgun depuis le fichier de configuration
     *
     * Lit les paramètres de temps pour l'ouverture des inscriptions aux créneaux
     * selon le type de tuteur (employé ou bénévole)
     *
     * @return array Tableau associatif des paramètres de shotgun
     */
    protected static function getRegistrationSettings(): array
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
     * Détermine si la semaine suivante doit être affichée pour l'inscription
     *
     * Cette méthode vérifie, en fonction du rôle de l'utilisateur, si la date
     * d'inscription aux créneaux de la semaine suivante est déjà passée.
     *
     * @return bool Vrai si la semaine suivante doit être affichée
     */
    protected static function shouldShowNextWeek(): bool
    {
        $user = Auth::user();
        $settings = self::getRegistrationSettings();
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
     * Configure la table d'affichage des créneaux
     *
     * Cette méthode définit :
     * - Les filtres de requête pour afficher les créneaux des semaines appropriées
     * - L'organisation par groupes (jours/horaires)
     * - Les colonnes affichant les informations des créneaux
     * - Les actions permettant aux tuteurs de s'inscrire ou se désinscrire
     *
     * @param Table $table La table à configurer
     * @return Table La table configurée
     */
    public static function table(Table $table): Table
    {
        $userId = Auth::id();
        $showNextWeek = self::shouldShowNextWeek();

        $query = Creneaux::query()
            ->with([
                'tutor1.proposedUvs:code,code',
                'tutor2.proposedUvs:code,code',
                'semaine'
            ])
            ->orderBy('start');

        $currentWeek = Semaine::where('date_debut', '<=', Carbon::now())
            ->where('date_fin', '>=', Carbon::now())
            ->first();

        if ($currentWeek) {
            $nextWeek = Semaine::where('numero', $currentWeek->numero + 1)
                ->where('fk_semestre', $currentWeek->fk_semestre)
                ->first();

            if ($showNextWeek && $nextWeek) {
                $query->whereIn('fk_semaine', [$currentWeek->id, $nextWeek->id]);
            } else {
                $query->where('fk_semaine', $currentWeek->id);
            }
        }

        return $table
            ->query($query)
            ->groups([
                Tables\Grouping\Group::make('day_and_time')
                    ->label(__('resources.common.fields.jour_et_horaire'))
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(
                        fn (Creneaux $record) =>
                        ucfirst($record->start->translatedFormat('l d F Y')) . ' - ' .
                        $record->start->format('H:i') . ' à ' . $record->end->format('H:i')
                    )
                    ->getKeyFromRecordUsing(
                        fn (Creneaux $record) =>
                        $record->start->format('Y-m-d') . '_' . $record->start->format('H:i')
                    )
                    ->collapsible(true),
            ])
            ->defaultGroup('day_and_time')
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        TextColumn::make('fk_salle')
                            ->label(__('resources.common.fields.salle'))
                            ->icon('heroicon-o-map-pin')
                            ->color('gray'),

                        TextColumn::make('semaine.numero')
                            ->label(__('resources.common.fields.semaine'))
                            ->formatStateUsing(fn ($state) => __('resources.common.format.semaine_numero', ['number' => $state]))
                            ->icon('heroicon-o-calendar')
                            ->color('gray'),
                    ]),
                    Tables\Columns\Layout\Split::make([
                        TextColumn::make('tutor1.firstName')
                            ->label(__('resources.common.fields.tuteur1'))
                            ->icon('heroicon-o-user')
                            ->color('gray')
                            ->formatStateUsing(fn ($state, $record) => $state . ' ' .($record->tutor1->lastName)[0].'.')
                            ->placeholder(__('resources.common.placeholders.none')),

                        TextColumn::make('tutor2.firstName')
                            ->label(__('resources.common.fields.tuteur2'))
                            ->icon('heroicon-o-user')
                            ->color('gray')
                            ->formatStateUsing(fn ($state, $record) => $state . ' ' . ($record->tutor2->lastName)[0].'.')
                            ->placeholder(__('resources.common.placeholders.none')),
                    ]),

                    TextColumn::make('id')
                        ->label(__('resources.common.fields.uvs_proposees'))
                        ->formatStateUsing(function ($state, Creneaux $creneau) {
                            $uvs = collect();

                            foreach ([$creneau->tutor1, $creneau->tutor2] as $tutor) {
                                if ($tutor) {
                                    $tutor->loadMissing('proposedUvs');
                                    $uvs = $uvs->merge($tutor->proposedUvs->pluck('code'));
                                }
                            }

                            $grouped = self::formatGroupedUvs($uvs->unique());
                            $items = explode("\n", $grouped);

                            $lines = self::balanceHorizontally($items, 30); // 30 caractères max/ligne

                            return collect($lines)->map(function ($lineItems) {
                                return implode('&nbsp;&nbsp;', $lineItems);
                            })->implode('<br>');
                        })
                        ->icon('heroicon-o-academic-cap')
                        ->color('primary')
                        ->html(),
                ])
            ])
            ->contentGrid([
                'sm' => 2,
                'md' => 3,
                'lg' => 4,
                'xl' => 4,
            ])
            ->actions([
                Action::make('toggleShotgun1')
                    ->label(fn (Creneaux $record) => $record->tutor1_id === $userId ? __('resources.common.buttons.se_desinscrire') : __('resources.common.buttons.shotgun_1'))
                    ->color(fn (Creneaux $record) => $record->tutor1_id === $userId ? 'danger' : 'primary')
                    ->button()
                    ->visible(function (Creneaux $record) use ($userId) {
                        $hasConflict = Creneaux::where('start', $record->start)
                            ->where('id', '!=', $record->id)
                            ->where(function ($query) use ($userId) {
                                $query->where('tutor1_id', $userId)
                                    ->orWhere('tutor2_id', $userId);
                            })
                            ->exists();

                        return !$hasConflict
                            && (
                                ($record->tutor1_id === null && $record->tutor2_id !== $userId)
                                || $record->tutor1_id === $userId
                            );
                    })
                    ->action(function (Creneaux $record) use ($userId) {
                        if ($record->tutor1_id === $userId) {
                            $record->update(['tutor1_id' => null]);
                        } elseif (!$record->tutor1_id) {
                            $record->update(['tutor1_id' => $userId]);
                        }
                    }),

                Action::make('toggleShotgun2')
                    ->label(fn (Creneaux $record) => $record->tutor2_id === $userId ? __('resources.common.buttons.se_desinscrire') : __('resources.common.buttons.shotgun_2'))
                    ->color(fn (Creneaux $record) => $record->tutor2_id === $userId ? 'danger' : 'primary')
                    ->button()
                    ->visible(function (Creneaux $record) use ($userId) {
                        $hasConflict = \App\Models\Creneaux::where('start', $record->start)
                            ->where('id', '!=', $record->id)
                            ->where(function ($query) use ($userId) {
                                $query->where('tutor1_id', $userId)
                                    ->orWhere('tutor2_id', $userId);
                            })
                            ->exists();

                        return !$hasConflict
                            && (
                                ($record->tutor2_id === null && $record->tutor1_id !== $userId)
                                || $record->tutor2_id === $userId
                            );
                    })
                    ->action(function (Creneaux $record) use ($userId) {
                        if ($record->tutor2_id === $userId) {
                            $record->update(['tutor2_id' => null]);
                        } elseif (!$record->tutor2_id) {
                            $record->update(['tutor2_id' => $userId]);
                        }
                    }),
            ])
            ->paginated(false)
            ->recordUrl(null);
    }

    /**
     * Définit les pages disponibles pour cette ressource
     *
     * Cette ressource ne contient qu'une page d'index qui liste les créneaux
     * disponibles pour le shotgun.
     *
     * @return array Tableau associatif des pages
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreneau::route('/'),
        ];
    }
}
