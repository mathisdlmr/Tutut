## Fonctionnalités spéciales

## Système de paramètres

L'application utilise un système de paramètres stockés dans un fichier JSON (`settings.json`) qui définit notamment :

- Les jours et heures d'ouverture des inscriptions pour chaque type d'utilisateur:
  ```json
  {
    "employedTutorRegistrationDay": "monday",
    "employedTutorRegistrationTime": "16:00",
    "tutorRegistrationDay": "friday",
    "tutorRegistrationTime": "16:00",
    "tuteeRegistrationDay": "saturday",
    "tuteeRegistrationTime": "10:00"
  }
  ```

- Les règles de délai d'annulation, avec deux options:
  1. Utilisation de "la veille" comme règle simple (`useOneDayBeforeCancellation: true`)
  2. Définition d'un jour et heure spécifique (`minTimeCancellationDay` et `minTimeCancellationTime`)

Ces paramètres sont gérés via l'interface `SettingsPage` et sont stockés dans un fichier JSON pour une persistance simple.

### Implémentation technique

La classe `SettingsPage` gère ces paramètres avec des méthodes dédiées :

```php
protected function loadSettings(): void
{
    if (Storage::exists('settings.json')) {
        $this->settings = json_decode(Storage::get('settings.json'), true) ?: $this->settings;
    }
}

public function saveSettings(): void
{
    $data = $this->form->getState();
    
    foreach ($data as $key => $value) {
        $this->settings[$key] = $value;
    }
    
    // Si on utilise "la veille", on vide les champs minTimeCancellation
    if ($data['useOneDayBeforeCancellation']) {
        $this->settings['minTimeCancellationDay'] = null;
        $this->settings['minTimeCancellationTime'] = null;
    }
    
    Storage::put('settings.json', json_encode($this->settings));
    
    Notification::make()
        ->title(__('resources.pages.settings.notifications.settings_saved_title'))
        ->success()
        ->send();
}
```

### Utilisation des paramètres

Les paramètres sont utilisés dans tout le système, par exemple pour déterminer si un tuteur peut voir les créneaux de la semaine suivante :

```php
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
```

## Gestion multilingue

L'application supporte le français et l'anglais via le plugin FilamentLanguageSwitch, permettant aux utilisateurs de changer la langue de l'interface.

Configuration dans `AdminPanelProvider.php`:
```php
LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
    $switch
        ->locales(['fr', 'en'])
        ->displayLocale('fr')
        ->labels([
            'fr' => 'Français',
            'en' => 'English',
        ])
        ->renderHook('panels::topbar.end');
});
```

Les traductions sont stockées dans les fichiers de ressources linguistiques de Laravel.

### Structure des fichiers de traduction

```
resources/
└── lang/
    ├── en/
    │   ├── auth.php
    │   ├── pagination.php
    │   ├── passwords.php
    │   ├── resources.php     # Traductions spécifiques à l'application
    │   └── validation.php
    └── fr/
        ├── auth.php
        ├── pagination.php
        ├── passwords.php
        ├── resources.php     # Traductions spécifiques à l'application
        └── validation.php
```

Exemple de fichier de traduction `resources.php` :

```php
// resources/lang/fr/resources.php
return [
    'common' => [
        'fields' => [
            'jour_et_horaire' => 'Jour et horaire',
            'salle' => 'Salle',
            'semaine' => 'Semaine',
            'tuteur1' => 'Tuteur 1',
            'tuteur2' => 'Tuteur 2',
            'uvs_proposees' => 'UVs proposées',
        ],
        'format' => [
            'semaine_numero' => 'Semaine :number',
        ],
        'placeholders' => [
            'none' => 'Aucun',
        ],
    ],
    'creneau' => [
        'navigation_label' => 'Shotgun Créneaux',
        'label' => 'Créneau',
        'plural_label' => 'Créneaux',
    ],
    // ...
];
```

### Utilisation des traductions

Dans le code, les traductions sont utilisées via la fonction `__()` :

```php
public static function getNavigationLabel(): string
{
    return __('resources.creneau.navigation_label');
}

public static function getModelLabel(): string
{
    return __('resources.creneau.label');
}
```

## Système de calendrier

La gestion du calendrier permet de définir des périodes spéciales via `CalendarManager` :
- Création d'exceptions pour certaines dates (jours fériés, événements)
- Visualisation du calendrier complet du semestre
- Modification de la disponibilité des salles pour des jours spécifiques
- Personnalisation des plages horaires disponibles

Le système s'appuie sur la table `calendar_overrides` pour stocker les exceptions aux règles générales.

### Structure de la table des exceptions

```php
Schema::create('calendar_overrides', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->enum('type', ['holiday', 'special_day', 'room_unavailable']);
    $table->string('title');
    $table->text('description')->nullable();
    $table->foreignId('salle_id')->nullable()->constrained('salles', 'numero');
    $table->timestamps();
});
```

### Interface de gestion du calendrier

La page `CalendarManager` offre une interface visuelle pour gérer ces exceptions :

```php
public function getFormSchema(): array
{
    return [
        DatePicker::make('date')
            ->label(__('resources.pages.calendar.date'))
            ->required(),
        Select::make('type')
            ->options([
                'holiday' => __('resources.pages.calendar.types.holiday'),
                'special_day' => __('resources.pages.calendar.types.special_day'),
                'room_unavailable' => __('resources.pages.calendar.types.room_unavailable'),
            ])
            ->reactive()
            ->required(),
        TextInput::make('title')
            ->label(__('resources.pages.calendar.title'))
            ->required(),
        Textarea::make('description')
            ->label(__('resources.pages.calendar.description')),
        Select::make('salle_id')
            ->label(__('resources.pages.calendar.room'))
            ->options(Salle::pluck('numero', 'numero'))
            ->visible(fn (callable $get) => $get('type') === 'room_unavailable'),
    ];
}
```

### Utilisation des exceptions dans le système

Avant de créer un créneau, le système vérifie les exceptions :

```php
public static function checkAvailability($date, $salle_id)
{
    // Vérifier si la date est un jour férié ou spécial
    $holiday = CalendarOverride::where('date', $date->format('Y-m-d'))
        ->whereIn('type', ['holiday', 'special_day'])
        ->first();
        
    if ($holiday) {
        return [
            'available' => false,
            'reason' => $holiday->title,
        ];
    }
    
    // Vérifier si la salle est indisponible ce jour
    $roomUnavailable = CalendarOverride::where('date', $date->format('Y-m-d'))
        ->where('type', 'room_unavailable')
        ->where('salle_id', $salle_id)
        ->first();
        
    if ($roomUnavailable) {
        return [
            'available' => false,
            'reason' => 'Salle indisponible: ' . $roomUnavailable->title,
        ];
    }
    
    return ['available' => true];
}
```

## Protection RGPD

L'application inclut un middleware `EnsureRgpdAccepted` qui vérifie que l'utilisateur a accepté les conditions RGPD avant d'accéder au système.

Les dates d'acceptation sont stockées dans le champ `rgpd_accepted_at` de la table `users`.

### Implémentation du middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRgpdAccepted
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && !Auth::user()->rgpd_accepted_at && !$request->routeIs('rgpd.*')) {
            return redirect()->route('rgpd.accept');
        }

        return $next($request);
    }
}
```

### Enregistrement dans AdminPanelProvider.php

```php
->middleware([
    // Autres middlewares...
    EnsureRgpdAccepted::class,
])
```

### Page d'acceptation RGPD

Une page dédiée permet aux utilisateurs d'accepter les conditions :

```php
public function acceptRgpd()
{
    $user = Auth::user();
    $user->rgpd_accepted_at = now();
    $user->save();
    
    return redirect()->intended(config('filament.home_url'));
}
```

