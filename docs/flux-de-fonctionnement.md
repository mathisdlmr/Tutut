# Flux de fonctionnement

## Configuration initiale

1. Un administrateur configure les semestres, semaines et paramètres.
   - Création d'un nouveau semestre (ex: "A25") avec dates de début/fin
   - Définition des semaines avec leurs spécificités (examens, vacances)
   - Configuration des paramètres d'inscription dans `SettingsPage`

## Cycle de réservation des créneaux

2. Les tuteurs employés et non-employés peuvent réserver des créneaux selon des règles de priorité.
   - Les tuteurs employés ont accès aux réservations en début de semaine (ex: lundi à 16h)
   - Les tuteurs non-employés peuvent réserver plus tard (ex: vendredi à 16h)
   - Système de double tuteur possible pour certains créneaux
   - La plateforme vérifie les conflits d'horaire et de salle

## Cycle d'inscription des tutorés

3. Les tutorés peuvent s'inscrire aux créneaux disponibles.
   - Inscription à partir d'un jour/heure défini dans les paramètres
   - Sélection des UVs pour lesquelles ils souhaitent du tutorat
   - Système de recherche par tuteur, UV ou horaire
   - Possibilité d'annulation selon règles définies (délai minimum)

## Déroulement et suivi des séances

4. Les tuteurs dispensent les sessions et remplissent les retours (feedback).
   - Vérification des présences
   - Indication des UVs effectivement traitées
   - Commentaires sur le déroulement
   - Rapport de problèmes éventuels

## Gestion administrative

5. La comptabilité est gérée automatiquement en fonction des sessions effectuées.
   - Comptabilisation des heures par tuteur
   - Calcul de la rémunération
   - Gestion des validations administratives
   - Génération de rapports pour le service financier