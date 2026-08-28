## Retour OIDC (CAS UTC)

Authentification via le CAS UTC en OIDC (`https://cas.utc.fr/cas/oidc`, voir le well-known pour plus d'infos `/.well-known/openid-configuration`). 
Le callback (`AuthController::callback`) lit les claims suivants sur l'userinfo_endpoint (`oidcProfile`) :
- `sub` : identifiant unique CAS (non stocké pour l'instant)
- `email` : obligatoire car utilisé comme primary key du user
- `given_name` -> `users.firstName`
- `family_name` -> `users.lastName`
- `preferred_username`, `name` : disponibles mais non utilisés

Scopes demandés : `openid profile email`.
