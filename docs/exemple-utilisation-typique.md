# Exemples d'utilisation typique

## Workflow Administrateur

### 1. Création d'un nouveau semestre

```php
// Dans AdminSemestreResource.php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            TextInput::make('code')
                ->label(__('resources.semestre.fields.code'))
                ->required()
                ->maxLength(3)
                ->placeholder('A25')
                ->helperText(__('resources.semestre.helpers.code')),
            DatePicker::make('debut')
                ->label(__('resources.semestre.fields.debut'))
                ->required(),
            DatePicker::make('fin')
                ->label(__('resources.semestre.fields.fin'))
                ->required()
                ->after('debut'),
            DatePicker::make('debut_medians')
                ->label(__('resources.semestre.fields.debut_medians')),
            DatePicker::make('fin_medians')
                ->label(__('resources.semestre.fields.fin_medians'))
                ->after('debut_medians'),
            DatePicker::make('debut_finaux')
                ->label(__('resources.semestre.fields.debut_finaux')),
            DatePicker::make('fin_finaux')
                ->label(__('resources.semestre.fields.fin_finaux')),
            Toggle::make('is_active')
                ->label(__('resources.semestre.fields.is_active'))
                ->default(false)
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set, $record) {
                    if ($state && $record) {
                        // Désactiver les autres semestres
                        Semestre::where('code', '!=', $record->code)
                            ->update(['is_active' => false]);
                    }
                }),
        ]);
}
```

Exemple de création d'un semestre :
```
Semestre A25 : 1er septembre 2025 au 15 janvier 2026
Médians : 20 octobre 2025 au 25 octobre 2025
Finaux : 5 janvier 2026 au 15 janvier 2026
```

### 2. Configuration des paramètres d'inscription

Interface utilisée dans `SettingsPage.php` :

```php
protected function getFormSchema(): array
{
    return [
        Section::make(__('resources.pages.settings.sections.main'))
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Section::make(__('resources.pages.settings.sections.employed_tutor_registration'))
                            ->schema([
                                Select::make('employedTutorRegistrationDay')
                                    ->label(__('resources.pages.settings.fields.day'))
                                    ->options($this->getDays())
                                    ->required(),
                                TimePicker::make('employedTutorRegistrationTime')
                                    ->label(__('resources.pages.settings.fields.time'))
                                    ->seconds(false)
                                    ->required(),
                            ]),
                        // Autres sections similaires pour tutors et tutees
                    ]),
            ])
    ];
}
```

Valeurs typiques configurées :
```
Tuteurs employés : lundi 16h00
Tuteurs : vendredi 16h00
Tutorés : samedi 10h00
Délai d'annulation : la veille
```

### 3. Définition des salles et capacités

Interface de gestion des salles dans `SalleResource.php` :

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            TextInput::make('numero')
                ->label(__('resources.salle.fields.numero'))
                ->required()
                ->maxLength(10),
            TextInput::make('capacity')
                ->label(__('resources.salle.fields.capacity'))
                ->required()
                ->numeric()
                ->default(20)
                ->minValue(1)
                ->maxValue(100),
        ]);
}
```

Exemple de configuration de salles :
```
Salle A101 : 20 places
Salle B202 : 15 places
```

## Workflow Tuteur

### 1. Réservation de créneaux

Logique de vérification d'accès aux créneaux :

```php
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

    // Suite de la configuration de la table...
}
```

Processus de réservation d'un créneau :
```php
public function createCreneau($data)
{
    // Vérification des conflits
    $existingCreneau = Creneaux::where('tutor1_id', Auth::id())
        ->where('fk_semaine', $data['semaine'])
        ->where(function ($query) use ($data) {
            $start = Carbon::parse($data['date'] . ' ' . $data['start_time']);
            $end = Carbon::parse($data['date'] . ' ' . $data['end_time']);
            
            $query->where(function ($q) use ($start, $end) {
                $q->where('start', '<=', $start)
                  ->where('end', '>', $start);
            })->orWhere(function ($q) use ($start, $end) {
                $q->where('start', '<', $end)
                  ->where('end', '>=', $end);
            });
        })
        ->first();
    
    if ($existingCreneau) {
        Notification::make()
            ->title(__('resources.creneau.notifications.conflict'))
            ->danger()
            ->send();
        return;
    }
    
    // Création du créneau
    $start = Carbon::parse($data['date'] . ' ' . $data['start_time']);
    $end = Carbon::parse($data['date'] . ' ' . $data['end_time']);
    
    Creneaux::create([
        'tutor1_id' => Auth::id(),
        'fk_semaine' => $data['semaine'],
        'fk_salle' => $data['salle'],
        'start' => $start,
        'end' => $end,
    ]);
    
    Notification::make()
        ->title(__('resources.creneau.notifications.created'))
        ->success()
        ->send();
}
```

### 2. Gestion des UVs proposées

Interface dans `TutorManageUvs.php` :

```php
public function getFormSchema(): array
{
    return [
        CheckboxList::make('selected_uvs')
            ->label(__('resources.pages.tutor_manage_uvs.fields.uvs'))
            ->options(function () {
                return UV::orderBy('code')
                    ->pluck('name', 'code')
                    ->map(function ($name, $code) {
                        return $code . ' - ' . $name;
                    });
            })
            ->columns(3)
            ->default(function () {
                return Auth::user()->proposedUvs->pluck('code')->toArray();
            }),
    ];
}

public function save()
{
    $user = Auth::user();
    $selectedUvs = $this->selected_uvs;
    
    // Supprimer toutes les associations existantes
    DB::table('tutor_propose')
        ->where('fk_user', $user->id)
        ->delete();
    
    // Créer les nouvelles associations
    foreach ($selectedUvs as $uvCode) {
        DB::table('tutor_propose')->insert([
            'fk_user' => $user->id,
            'fk_code' => $uvCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    Notification::make()
        ->title(__('resources.pages.tutor_manage_uvs.notifications.saved'))
        ->success()
        ->send();
}
```

Exemple de sélection d'UVs par un tuteur :
```
MT11 - Mathématiques
MA11 - Algèbre linéaire
LC01 - Anglais
```

### 3. Consultation des créneaux avec inscrits

Widget tuteur montrant les créneaux avec inscriptions :

```php
class TutorCreneauxTableWidget extends BaseWidget
{
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
    
    // Colonnes et configuration de la table...
}
```

## Workflow Tutoré

### 1. Inscription à un créneau

Processus d'inscription dans `InscriptionCreneauResource` :

```php
public function create($data)
{
    $user = Auth::user();
    $creneau = Creneaux::findOrFail($data['creneau_id']);
    
    // Vérifier si l'utilisateur est déjà inscrit
    $existingInscription = Inscription::where('tutee_id', $user->id)
        ->where('creneau_id', $creneau->id)
        ->first();
    
    if ($existingInscription) {
        Notification::make()
            ->title(__('resources.inscription.notifications.already_registered'))
            ->warning()
            ->send();
        return;
    }
    
    // Vérifier les délais d'annulation
    $canRegister = $this->checkRegistrationTime($creneau);
    if (!$canRegister['can_register']) {
        Notification::make()
            ->title($canRegister['message'])
            ->warning()
            ->send();
        return;
    }
    
    // Créer l'inscription
    Inscription::create([
        'tutee_id' => $user->id,
        'creneau_id' => $creneau->id,
        'enseignements_souhaites' => json_encode($data['enseignements_souhaites']),
    ]);
    
    Notification::make()
        ->title(__('resources.inscription.notifications.registered'))
        ->success()
        ->send();
}
```

Exemple d'inscription :
```
Inscription au créneau de Mardi 14h-16h avec tuteur Jean Dupont
UVs demandées : MT11, MA11
```

### 2. Vérification des règles d'annulation

Logique de vérification pour les annulations :

```php
protected function checkCancellationTime(Creneaux $creneau): array
{
    $settings = $this->getSettings();
    $now = Carbon::now();
    
    // Si on utilise la règle simple de "la veille"
    if ($settings['useOneDayBeforeCancellation'] ?? false) {
        $limitDate = Carbon::parse($creneau->start)->subDay()->endOfDay();
        
        if ($now->greaterThan($limitDate)) {
            return [
                'can_cancel' => false,
                'message' => __('resources.inscription.messages.too_late_to_cancel_one_day'),
            ];
        }
        
        return ['can_cancel' => true];
    }
    
    // Sinon, utiliser la règle personnalisée
    $day = $settings['minTimeCancellationDay'] ?? 'friday';
    $time = $settings['minTimeCancellationTime'] ?? '16:00';
    
    // Calculer la date limite selon les paramètres
    $dayMap = [
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2,
        'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6,
    ];
    
    $dayNumber = $dayMap[strtolower($day)] ?? 5;
    $timeParts = explode(':', $time);
    
    $limitDate = Carbon::parse($creneau->start)
        ->startOfWeek()
        ->addDays($dayNumber)
        ->setHour(intval($timeParts[0] ?? 16))
        ->setMinute(intval($timeParts[1] ?? 0))
        ->setSecond(0);
    
    if ($now->greaterThan($limitDate)) {
        return [
            'can_cancel' => false,
            'message' => __('resources.inscription.messages.too_late_to_cancel_custom', [
                'day' => __("resources.pages.settings.days.{$day}"),
                'time' => $time,
            ]),
        ];
    }
    
    return ['can_cancel' => true];
}
```

## Workflow de suivi

### 1. Visualisation des inscriptions

Widget tuteur montrant les UVs demandées :

```php
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
```

### 2. Création de feedback après séance

Interface dans `FeedbackResource` :

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Select::make('creneau_id')
                ->label(__('resources.feedback.fields.creneau'))
                ->options(function () {
                    $user = Auth::user();
                    return Creneaux::where(function ($query) use ($user) {
                            $query->where('tutor1_id', $user->id)
                                  ->orWhere('tutor2_id', $user->id);
                        })
                        ->where('end', '<', now())
                        ->with(['semaine'])
                        ->orderBy('start', 'desc')
                        ->get()
                        ->mapWithKeys(function ($creneau) {
                            $date = Carbon::parse($creneau->start)->format('d/m/Y H:i');
                            return [$creneau->id => "Créneau du {$date} (Semaine {$creneau->semaine->numero})"];
                        });
                })
                ->required()
                ->searchable(),
            Textarea::make('content')
                ->label(__('resources.feedback.fields.content'))
                ->required()
                ->columnSpan('full'),
            CheckboxList::make('present_students')
                ->label(__('resources.feedback.fields.present_students'))
                ->options(function (callable $get) {
                    $creneauId = $get('creneau_id');
                    if (!$creneauId) return [];
                    
                    return Inscription::where('creneau_id', $creneauId)
                        ->with('tutee')
                        ->get()
                        ->mapWithKeys(function ($inscription) {
                            $tutee = $inscription->tutee;
                            return [$inscription->tutee_id => "{$tutee->firstName} {$tutee->lastName}"];
                        });
                })
                ->columns(2),
        ]);
}
```

### 3. Gestion de la comptabilité

Traitement automatique dans `ComptabiliteResource` :

```php
public function processCreneaux()
{
    $unprocessedCreneaux = Creneaux::where(function ($query) {
            $query->whereNull('tutor1_compted')
                  ->orWhereNull('tutor2_compted');
        })
        ->where('end', '<', now())
        ->get();
    
    $count = 0;
    
    foreach ($unprocessedCreneaux as $creneau) {
        $duration = Carbon::parse($creneau->start)->diffInHours(Carbon::parse($creneau->end));
        
        if ($creneau->tutor1_id && $creneau->tutor1_compted === null) {
            Comptabilite::create([
                'fk_user' => $creneau->tutor1_id,
                'fk_creneau' => $creneau->id,
                'date' => $creneau->start,
                'heures' => $duration,
            ]);
            
            $creneau->tutor1_compted = true;
            $count++;
        }
        
        if ($creneau->tutor2_id && $creneau->tutor2_compted === null) {
            Comptabilite::create([
                'fk_user' => $creneau->tutor2_id,
                'fk_creneau' => $creneau->id,
                'date' => $creneau->start,
                'heures' => $duration,
            ]);
            
            $creneau->tutor2_compted = true;
            $count++;
        }
        
        $creneau->save();
    }
    
    Notification::make()
        ->title(__('resources.comptabilite.notifications.processed', ['count' => $count]))
        ->success()
        ->send();
}
```

Exemples de statistiques générées :
```
Jean Dupont : 8h effectuées cette semaine
Total du mois : 24h
Total des heures supplémentaires : 2h
```