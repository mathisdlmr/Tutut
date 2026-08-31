# Déploiement de l'application avec la DSI de l'UTC

## OpenShift

Concernant le déploiement, tout a été fait sur l'OpenShift de la DSI. Pour accéder à la console, utilise ce lien : https://console.openshift.dsi.utc.fr/k8s/cluster/projects/tiers-tutut. Si tu n'arrives pas à te connecter, il faudrait sûrement que tu demandes à la DSI (ou directement à Rémy Huet) de t'ajouter les droits.

Concrètement, OpenShift est un Kubernetes-like, mais avec pas mal de features supplémentaires. Si jamais tu as des choses à modifier dedans, tu devrais pouvoir tout faire depuis l'interface web (manipulation sur le code backend, changement de creds, manipulation de la base de données, etc.).

Si tu veux une vue d'ensemble plus globale de l'architecture, tu trouveras dans [ce repo](https://gitlab.utc.fr/dsiweb/projets-tiers/tutut.utc.fr) les manifests qui ont été utilisés pour la création de l'application.

## Base de données

Pour l'instant la DB n'est accessible que par `psql` sur OpenShift, mais à terme ça serait cool de déployer un PgAdmin ou un Adminer pour avoir une interface plus facile à prendre en main.

Pour manipuler la DB, il suffit donc 
1. d'aller sur le projet OpenShift Tut'ut (voir plus haut), 
2. descendre en bas de la page et cliquer (à gauche) sur `<un-nombre> Pods`
3. cliquer sur l'une des `tutut-postgres`
4. aller dans `Terminal`
5. taper `psql -h tutut-postgres-rw -p 5432 -U app -d app`

Si jamais la connexion est refusée, il faut vérifier la configuration de la Postgre. Pour ça, retourner sur le projet `tiers-tutut` > `<un-nombre> Secrets` > `tutut-postgres-app`, puis scroller jusqu'à trouver les bonnes valeurs

Ensuite, il ne reste qu'à taper des commandes psql classiques : 
* `\l` pour lister les tables
* `\d` pour lister les colonnes
* `UPDATE users SET role = 'admin' WHERE email = '<ton-mail>';` ou `INSERT INTO users (email, role, created_at, updated_at) VALUES ('<ton-mail>', 'admin', NOW(), NOW());` pour te mettre admin
...

## Authentification

L'authentification est basée sur le protocole CAS UTC, avec des endpoints OpenID Connect.

Ce service est accessible sur l'url `https://cas.utc.fr/cas/oidc`, et un well-known avec toutes les informations sur la configuration est disponible à l'url `/.well-known/openid-configuration`.

Le callback de notre Controller d'authentification (`AuthController::callback`) lit ensuite les claims suivants sur l'userinfo_endpoint (`oidcProfile`) :
- `sub` : identifiant unique CAS (non stocké pour l'instant)
- `email` -> `users.email`
- `given_name` -> `users.firstName`
- `family_name` -> `users.lastName`
- `preferred_username`, `name` : disponibles mais non utilisés

On obtient tout ça grâce aux scopes suivants : `openid profile email`.
