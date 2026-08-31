# Base de données

## Schéma détaillé

### Modèle Entité-Relation

Le schéma de la base de données s'articule autour de plusieurs entités principales :

1. **Users** : Utilisateurs du système (tuteurs et tutorés)
2. **Semestres** : Périodes académiques
3. **Semaines** : Sous-divisions des semestres
4. **Salles** : Lieux physiques des séances
5. **Créneaux** : Plages horaires des séances de tutorat
6. **Inscriptions** : Liens entre tutorés et créneaux
7. **UVs** : Unités de valeur/matières enseignées

### Diagramme de la base de données

![Diagramme Base de données](BDD.png)

## Tables principales

### users
- `id` : Identifiant unique
- `email` : Email de l'utilisateur (unique)
- `firstName` : Prénom
- `lastName` : Nom de famille
- `role` : Rôle de l'utilisateur (enum des valeurs définies dans `App\Enums\Roles`)
- `languages` : Langues maîtrisées (JSON)
- `rgpd_accepted_at` : Date d'acceptation RGPD
- Relations:
  - `proposedUvs()`: UVs proposées par un tuteur
  - `heuresSupplementaires()`: Heures supplémentaires du tuteur
  - `comptabilites()`: Entrées de comptabilité associées
  - `becomeTutorRequest()`: Demande pour devenir tuteur

**Implémentation du modèle User :**
```php
class User extends Authenticatable implements HasName
{
    use HasFactory;

    protected $fillable = ['email', 'firstName', 'lastName', 'role', 'languages', 'rgpd_accepted_at'];

    protected $casts = [
        'languages' => 'array',
    ];

    public function getFilamentName(): string
    {
        return ($this->firstName." ".$this->lastName);
    }

    public function proposedUvs()
    {
        return $this->belongsToMany(UV::class, 'tutor_propose', 'fk_user', 'fk_code');
    }     

    public function heuresSupplementaires()
    {
        return $this->hasMany(HeuresSupplementaires::class);
    }    

    public function comptabilites()
    {
        return $this->hasMany(Comptabilite::class, 'fk_user');
    }

    public function becomeTutorRequest()
    {
        return $this->hasOne(BecomeTutor::class, 'fk_user');
    }
}
```

### semestres
- `code` : Code du semestre (ex: "A25" pour Automne 2025), clé primaire
- `is_active` : Indicateur si le semestre est actif
- `debut` et `fin` : Dates de début et fin du semestre
- `debut_medians` et `fin_medians` : Période des examens médians
- `debut_finaux` et `fin_finaux` : Période des examens finaux
- Relations:
  - `semaines()`: Semaines associées au semestre

**Implémentation de la migration :**
```php
Schema::create('semestres', function (Blueprint $table) {
    $table->string('code', 3)->primary(); // ex: "A25"
    $table->boolean('is_active')->default(false);
    $table->date('debut');
    $table->date('fin');
    $table->date('debut_medians')->nullable();
    $table->date('fin_medians')->nullable();
    $table->date('debut_finaux')->nullable();
    $table->date('fin_finaux')->nullable();
    $table->timestamps();
});
```

### semaines
- `id` : Identifiant unique
- `numero` : Numéro de la semaine dans le semestre
- `fk_semestre` : Lien vers le semestre parent
- `date_debut` et `date_fin` : Dates de début et fin de la semaine
- Relations:
  - `semestre()`: Semestre auquel appartient la semaine
  - `creneaux()`: Créneaux planifiés durant cette semaine

**Implémentation de la migration :**
```php
Schema::create('semaines', function (Blueprint $table) {
    $table->id();
    $table->integer('numero');
    $table->string('fk_semestre', 3);
    $table->date('date_debut');
    $table->date('date_fin');
    $table->foreign('fk_semestre')->references('code')->on('semestres');
    $table->timestamps();
});
```

### creneaux
- `id` : Identifiant unique
- `tutor1_id` et `tutor2_id` : Références aux tuteurs assignés (nullable)
- `tutor1_compted` et `tutor2_compted` : Indicateurs de comptabilisation pour la paie
- `fk_semaine` : Semaine du créneau
- `fk_salle` : Salle attribuée
- `start` et `end` : Horaires de début et fin
- Relations:
  - `tutor1()` et `tutor2()`: Tuteurs assignés
  - `semaine()`: Semaine associée
  - `salle()`: Salle associée
  - `inscriptions()`: Inscriptions de tutorés à ce créneau

**Implémentation de la migration :**
```php
Schema::create('creneaux', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tutor1_id')->nullable()->constrained('users')->onDelete('cascade');
    $table->foreignId('tutor2_id')->nullable()->constrained('users')->onDelete('cascade');
    $table->boolean('tutor1_compted')->nullable(); // null = non traité, true/false sinon
    $table->boolean('tutor2_compted')->nullable();
    $table->foreignId('fk_semaine')->constrained('semaines', 'id')->onDelete('cascade');
    $table->foreignId('fk_salle')->constrained('salles', 'numero');
    $table->dateTime('start');
    $table->dateTime('end');
    $table->timestamps();
});
```

### inscription
- `id` : Identifiant unique
- `tutee_id` : Référence au tutoré inscrit
- `creneau_id` : Référence au créneau choisi
- `enseignements_souhaites` : Liste des UVs demandées (JSON)
- Relations:
  - `tutee()`: Tutoré inscrit
  - `creneau()`: Créneau associé

**Implémentation de la migration :**
```php
Schema::create('inscription', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tutee_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('creneau_id')->constrained('creneaux')->onDelete('cascade');
    $table->json('enseignements_souhaites');
    $table->timestamps();
});
```

### uvs
- `code` : Code de l'UV (ex: "MT11"), clé primaire
- `name` : Nom complet de l'UV
- Relations:
  - `tutors()`: Tuteurs proposant cette UV via la table pivot `tutor_propose`

**Implémentation de la migration :**
```php
Schema::create('uvs', function (Blueprint $table) {
    $table->string('code', 10)->primary();
    $table->string('name', 100);
    $table->timestamps();
});

Schema::create('tutor_propose', function (Blueprint $table) {
    $table->foreignId('fk_user')->constrained('users')->onDelete('cascade');
    $table->string('fk_code', 10);
    $table->foreign('fk_code')->references('code')->on('uvs')->onDelete('cascade');
    $table->primary(['fk_user', 'fk_code']);
});
```

### salles
- `numero` : Numéro de la salle, clé primaire
- `capacity` : Capacité de la salle
- Relations:
  - `creneaux()`: Créneaux planifiés dans cette salle
  - `dispoSalles()`: Disponibilités de la salle

**Implémentation de la migration :**
```php
Schema::create('salles', function (Blueprint $table) {
    $table->id('numero');
    $table->integer('capacity')->default(20);
    $table->timestamps();
});
```

### feedback
- `id` : Identifiant unique
- `creneau_id` : Référence au créneau concerné
- `content` : Contenu du feedback
- Relations:
  - `creneau()`: Créneau associé

**Implémentation de la migration :**
```php
Schema::create('feedback', function (Blueprint $table) {
    $table->id();
    $table->foreignId('creneau_id')->constrained('creneaux')->onDelete('cascade');
    $table->text('content');
    $table->timestamps();
});
```

## Indexation et optimisation

Pour optimiser les requêtes fréquentes, plusieurs index sont créés :

```php
$table->index('role'); // Pour filtrer les utilisateurs par rôle
$table->index('start'); // Pour les recherches par date/heure
$table->index(['tutor1_id', 'tutor2_id']); // Pour les requêtes de créneaux par tuteur
$table->index('fk_semaine'); // Pour filtrer par semaine
```

### Gestion des transactions

Pour les opérations critiques comme la création de créneaux ou l'inscription, l'application utilise des transactions pour garantir l'intégrité des données :

```php
DB::transaction(function () use ($data) {
    // Création du créneau
    $creneau = Creneaux::create([
        'tutor1_id' => Auth::id(),
        'fk_semaine' => $data['semaine'],
        'fk_salle' => $data['salle'],
        'start' => $start,
        'end' => $end,
    ]);
    
    // Autres opérations dépendantes
});
```