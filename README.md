# Application Web Tut'ut

Heyyyy ! 

Bienvenue sur le repo de l'application utilisée par Tut'ut !

Si tu es ici, c'est probablement parce que tu as été missionné.e par Madame Hédou pour t'occupe de la maintenance de l'application sur le semestre, ou alors pour y ajouter des nouvelles features.

Je vais essayer de te présenter un peu le projet, et te donner les clés en main pour que tu puisses prendre le projet en main le plus vite possible.

## Petit historique

Cette application a été développée en 2025 par moi-même, Mathis DELMAERE, dans le cadre d'une TX avec Madame Hédou et Monsieur Bonnet (aka Stéphane le meilleur prof de l'UTC).

Une fois la TX terminée, le projet a connu une longue période de péripéties pour le déploiement. Grosso modo le SiMDE refusait d'héberger l'application, et la DSI aussi... Après quelques négociations avec la DSI, le déploiement a été autorisé, mais aucune infra n'était disponible pour faire tourner l'application pour l'instant. Finalement, après 1 an de travaux à la DSI, une Infra sur OpenShift a été mise en place, et l'application a pu être déployée.

Ainsi, l'application tourne désormais sur le serveur OpenShift de l'UTC, et est accessible à cette adresse : https://tutut.utc.fr

Pour l'authentification, on utilise aussi les services de l'UTC : le CAS.

## Stack technique

L'aplication en elle-même repose sur les technologies suivantes :
- **Laravel** : Framework PHP qui fait tourner le code et la logique métier (accès à la base de données, manipulation des utilisateurs, etc.)
- **Filament** : Framework PHP qui propose des interfaces graphiques modernes (formulaires, tableaux, etc.)

> "Mais qu'est-ce que c'est qu'un Framework ?"
> 
> Pour faire simple, un framework c'est comme une librairie qui te donne accès à des fonctionnalités prédéfinies, qui te permettent de construire des applications web.
> Typiquement, au lieu de se connecter à la base de données à la main, en PHP, Laravel embarque sa propre connexion à la base de données. Il ne suffit donc qu'à référencer les paramètres de la connexion (hôte, mot de passe, etc.) pour que ça tourne.

Pour rentrer un peu plus dans le détail, l'application est ensuite déployée sur les serveurs de la DSI de l'UTC, et utilise les services suivants :
- **PostgreSQL** : Base de données
- **Redis** : Cache
- **OpenShift** : Plateforme de déploiement

## La Doc

J'avais plein d'infos à te faire passer, donc j'ai découpé toute la doc en plusieurs fichiers qui sont accessibles dans `docs/`.

Question pratique, je te conseille de lire les fichiers dans cet ordre : 
1. [Laravel](docs/laravel.md)
2. [Architecture](docs/architecture.md)
3. [Structure](docs/structure.md)
4. [Base de données](docs/base-de-donnes.md)
5. [Flux de fonctionnement](docs/flux-de-fonctionnement.md)
6. [Fonctionnalités spéciales](docs/fonctionnalités-speciales.md)
7. [Exemples d'utilisation typique](docs/exemple-utilisation-typique.md)
8. [Déploiement](docs/deploiement.md)
