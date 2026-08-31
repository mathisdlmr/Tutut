# Structure de l'application

L'application est organisée autour du panel d'administration Filament, configuré dans `app/Providers/Filament/AdminPanelProvider.php`. Ce panel est accessible via l'URL `/tutut` et est sécurisé par authentification.

Le fichier `AdminPanelProvider.php` définit :
- La configuration visuelle (couleurs, nom de marque)
- Les ressources disponibles (classées par rôle)
- Les pages accessibles
- Les widgets du tableau de bord
- Les middlewares de sécurité (dont `EnsureRgpdAccepted` pour la conformité RGPD)

L'application utilise une architecture basée sur les ressources Filament, qui combine :
- Des modèles Eloquent (app/Models)
- Des ressources Filament (app/Filament/Resources)
- Des pages personnalisées (app/Filament/Pages)
- Des widgets (app/Filament/Widgets)

## Rôles utilisateurs

L'application définit les rôles suivants (définis dans `App\Enums\Roles`) :

- **Administrator** : Administrateurs avec accès complet à toutes les fonctionnalités
  - Peuvent gérer les utilisateurs, les semestres, les salles et toute la configuration
  - Ont accès aux statistiques et à la comptabilité globale
  - Peuvent envoyer des emails aux utilisateurs
  - Code d'implémentation:
    ```php
    case Administrator = 'admin';
    
    public function isAdministrator(): bool
    {
        return $this === self::Administrator;
    }
    ```
  
- **EmployedPrivilegedTutor** : Tuteurs employés avec privilèges supplémentaires
  - Mêmes privilèges que les tuteurs employés standard
  - Accès à certaines fonctionnalités d'administration comme les paramètres
  - Peuvent gérer le calendrier et certaines ressources administratives
  - Code d'implémentation:
    ```php
    case EmployedPrivilegedTutor = 'employedPrivilegedTutor';
    
    public function isEmployedPrivilegedTutor(): bool
    {
        return $this === self::EmployedPrivilegedTutor;
    }
    ```
  
- **EmployedTutor** : Tuteurs employés standard
  - Peuvent créer des créneaux plus tôt que les tuteurs non-employés
  - Ont accès à des statistiques personnelles et à leur comptabilité
  - Gèrent leurs UVs et leurs créneaux
  - Code d'implémentation:
    ```php
    case EmployedTutor = 'employedTutor';
    
    public function isEmployedTutor(): bool
    {
        return $this === self::EmployedTutor;
    }
    ```
  
- **Tutor** : Tuteurs non-employés
  - Peuvent créer des créneaux selon un calendrier défini par l'administration
  - Gèrent leurs UVs proposées et leurs créneaux
  - Accèdent aux feedback de leurs sessions
  - Code d'implémentation:
    ```php
    case Tutor = 'tutor';
    
    public function isTutor(): bool
    {
        return $this === self::Tutor;
    }
    ```
  
- **Tutee** : Tutorés (étudiants bénéficiant du tutorat)
  - Peuvent s'inscrire aux créneaux disponibles
  - Peuvent demander à devenir tuteur
  - Voient leur planning de tutorat
  - Code d'implémentation:
    ```php
    case Tutee = 'tutee';
    
    public function isTutee(): bool
    {
        return $this === self::Tutee;
    }
    ```

### Système de contrôle d'accès

Le contrôle d'accès basé sur les rôles est implémenté via la méthode `canAccess()` dans chaque ressource. Exemple pour une ressource réservée aux administrateurs :

```php
public static function canAccess(): bool
{
    $user = Auth::user();
    return $user && Auth::user()->role === Roles::Administrator->value;
}
```

Pour une ressource accessible à plusieurs rôles :

```php
public static function canAccess(): bool
{
    $user = Auth::user();
    return $user && (
        Auth::user()->role === Roles::EmployedPrivilegedTutor->value
        || Auth::user()->role === Roles::EmployedTutor->value
        || Auth::user()->role === Roles::Tutor->value
    );
}
```

### Vérification des rôles dans le code

L'enum `Roles` fournit des méthodes de vérification qui peuvent être utilisées dans tout le code :

```php
if (Auth::user()->role === Roles::Administrator->value) {
    // Actions réservées aux administrateurs
}
```

Ou en utilisant les méthodes de l'enum :

```php
$role = Roles::from(Auth::user()->role);
if ($role->isAdministrator() || $role->isEmployedPrivilegedTutor()) {
    // Actions réservées aux administrateurs et tuteurs employés privilégiés
}
```

## Fonctionnalités principales

L'application comprend trois grands groupes de fonctionnalités, accessibles selon le rôle de l'utilisateur connecté :

### 1. Administration (Admin)

Ressources accessibles aux administrateurs :
- **TuteursEmployesResource** : Gestion des tuteurs employés
  - Création et modification des comptes tuteurs
  - Attribution des rôles (admin, tuteur employé, etc.)
  - Gestion groupée de comptes via importation d'emails
  
- **SemestreResource** : Gestion des semestres universitaires
  - Création de nouveaux semestres (ex: "A25" pour automne 2025)
  - Définition des dates de début/fin et périodes d'examens
  - Activation du semestre courant
  
- **SemaineResource** : Gestion des semaines dans un semestre
  - Numérotation et paramétrage des semaines
  - Définition des périodes spéciales (examens, vacances)
  
- **ComptabiliteResource** : Gestion de la comptabilité
  - Suivi des heures effectuées par les tuteurs
  - Gestion de la paie et des heures supplémentaires
  - Génération de rapports
  
- **SalleResource** : Gestion des salles disponibles
  - Ajout et modification des salles
  - Configuration de la disponibilité

Pages spécifiques :
- **SettingsPage** : Configuration des paramètres de l'application
  - Définition des jours et heures d'accès aux créneaux selon le rôle
  - Configuration des règles d'annulation
  - Paramètres généraux du système
  
- **CalendarManager** : Gestion du calendrier
  - Création et modification d'événements
  - Définition de règles d'exception pour certaines dates
  - Vue globale de la planification
  
- **SendEmail** : Envoi d'emails aux utilisateurs
  - Communication avec des groupes d'utilisateurs
  - Modèles d'emails pour différentes occasions
  - Suivi des envois

### 2. Tuteurs (Tutor)

Ressources accessibles aux tuteurs :
- **CreneauResource** : Gestion des créneaux de tutorat ("Shotgun Créneaux")
  - Réservation de plages horaires selon disponibilités
  - Association avec un autre tuteur possible
  - Vue calendrier des créneaux disponibles et réservés
  
- **ComptabiliteTutorResource** : Suivi de leur comptabilité
  - Visualisation des heures effectuées
  - Suivi de la rémunération
  - Historique des sessions
  
- **FeedbackResource** : Gestion des retours sur les séances de tutorat
  - Création de rapports post-séance
  - Suivi des présences
  - Notes et commentaires sur les sessions
  
- **TutorApplicationResource** : Gestion des candidatures pour devenir tuteur
  - Suivi de l'état des candidatures
  - Processus d'approbation

Page spécifique :
- **TutorManageUvs** : Gestion des UVs proposées par le tuteur
  - Sélection des UVs dans lesquelles le tuteur se sent compétent
  - Mise à jour des compétences
  - Visibilité dans le système pour les tutorés

### 3. Tutorés (Tutee)

Ressources accessibles aux tutorés :
- **InscriptionCreneauResource** : Inscription aux créneaux de tutorat
  - Recherche de créneaux par UV, tuteur ou horaire
  - Inscription et désinscription selon les règles établies
  - Historique des séances suivies
  
- **BecomeTutorResource** : Demande pour devenir tuteur
  - Formulaire de candidature
  - Sélection des UVs maîtrisées
  - Suivi de la demande

## Widgets

L'application propose plusieurs widgets pour simplifier l'accès aux informations :
- **TutorCreneauxTableWidget** : Tableau des créneaux pour les tuteurs
  - Affiche les prochains créneaux avec tutorés inscrits
  - Détails sur les UVs demandées
  - Mise en évidence des créneaux du jour
  
- **TuteeCreneauxWidget** : Affichage des créneaux pour les tutorés
  - Liste des séances à venir
  - Rappels et notifications
  - Liens rapides pour la désinscription
  
- **AdminWidget** : Tableau de bord administratif
  - Statistiques d'utilisation
  - Alertes sur les événements importants
  - Vue synthétique de l'activité