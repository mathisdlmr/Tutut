# Architecture de l'application

## Structure des répertoires

```bash
app/
├── Enums/              # Énumérations (Roles, etc.)
├── Filament/           # Composants Filament
│   ├── Pages/          # Pages personnalisées
│   │   ├── Admin/      # Ressources accessibles aux administrateurs
│   │   ├── Tutor/      # Ressources accessibles aux tuteurs
│   │   └── Tutee/      # Ressources accessibles aux tutorés
│   └── Widgets/        # Widgets pour le tableau de bord
├── Http/               # Controllers et Middlewares
│   ├── Controllers/    # Contrôleurs Laravel
│   └── Middleware/     # Middlewares (ex: EnsureRgpdAccepted)
├── Models/             # Modèles Eloquent
└── Providers/          # Service providers
    └── Filament/       # Provider pour Filament
```

## Intégration de Filament

Filament est configuré via le `AdminPanelProvider` qui utilise une approche orientée panneaux (panel-based). Chaque panneau peut avoir ses propres :
- Ressources (CRUD)
- Pages personnalisées
- Widgets
- Thème et configuration visuelle

Exemple d'enregistrement de ressource dans `AdminPanelProvider.php` :

```php
->resources([
    AdminTuteursEmployesResource::class,
    AdminSemestreResource::class,
    AdminSemaineResource::class,
    ComptabiliteResource::class,
    SalleResource::class,
    // ...
])
```

## Gestion des autorisations

L'application utilise un système d'autorisation basé sur les rôles, implémenté via les méthodes `canAccess()` dans chaque ressource :

```php
public static function canAccess(): bool
{
    $user = Auth::user();
    return $user && (
        $user->role === Roles::Administrator->value ||
        $user->role === Roles::EmployedPrivilegedTutor->value
    );
}
```

## Middleware personnalisé

Un middleware RGPD vérifie que les utilisateurs ont accepté les conditions d'utilisation :

```php
public function handle(Request $request, Closure $next)
{
    if (Auth::check() && !Auth::user()->rgpd_accepted_at && !$request->routeIs('rgpd.*')) {
        return redirect()->route('rgpd.accept');
    }

    return $next($request);
}
```
