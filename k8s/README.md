# Déploiement Kubernetes - Tutut

## Architecture

```
Cluster K8s (namespace: tutut)
│
├── StatefulSet: tutut-db (MySQL 8.0)
│   └── PVC: mysql-data-tutut-db-0 (10Gi)
│
├── Deployment: tutut-app (nginx + php-fpm via supervisord)
│   └── PVC: tutut-storage-pvc (5Gi → /var/www/html/storage)
│
├── Deployment: tutut-phpmyadmin
│
├── Ingress: tutut.stbo.fr → tutut-app:80
└── Ingress: pma.tutut.stbo.fr → tutut-phpmyadmin:80
```

## Fichiers

| Fichier                      | Rôle                                |
| ---------------------------- | ----------------------------------- |
| `namespace.yaml`             | Namespace `tutut`                   |
| `secret.yaml`                | Secrets DB + APP_KEY + OAuth        |
| `configmap.yaml`             | Variables d'env non-secrètes        |
| `pvc-storage.yaml`           | PVC pour `/storage` Laravel (5Gi)   |
| `statefulset-mysql.yaml`     | MySQL 8.0 + Service headless        |
| `deployment-phpmyadmin.yaml` | phpMyAdmin + Service                |
| `deployment-app.yaml`        | App Laravel + Service               |
| `ingress.yaml`               | Ingress app + phpMyAdmin            |
| `kustomization.yaml`         | Kustomize - applique tout d'un coup |

## Fichiers à la racine du projet

| Fichier                     | Rôle                                           |
| --------------------------- | ---------------------------------------------- |
| `Dockerfile.k8s`            | Dockerfile multi-stage (Vite build + PHP prod) |
| `k8s/nginx.k8s.conf`        | Config Nginx pour le conteneur K8s             |
| `k8s/supervisord.conf`      | Supervisord (nginx + php-fpm)                  |
| `k8s/laravel-entrypoint.sh` | Script de démarrage avec wait-for-mysql        |

## Prérequis

- `kubectl` configuré sur votre cluster
- `cert-manager` installé avec un ClusterIssuer `letsencrypt-prod`
- Un Ingress Controller (nginx recommandé)
- Une `storageClass` disponible (adapter `local-path` si besoin)

## Étapes de déploiement

### 1. Adapter les secrets

Éditer `k8s/secret.yaml` et remplacer les valeurs par vos vraies credentials :

```bash
# Gestion des secrets
kubectl create secret generic tutut-secrets \
  --from-literal=MYSQL_ROOT_PASSWORD=<mot-de-passe-root> \
  --from-literal=MYSQL_DATABASE=tutut \
  --from-literal=MYSQL_USER=tutut \
  --from-literal=MYSQL_PASSWORD=<mot-de-passe-tutut> \
  --from-literal=APP_KEY=<votre-app-key> \
  --from-literal=OAUTH_CLIENT_ID=<votre-client-id> \
  --from-literal=OAUTH_CLIENT_SECRET=<votre-client-secret> \
  --from-literal=API_UTCRAWL_KEY=<votre-cle-api> \
  -n tutut --dry-run=client -o yaml | kubectl apply -f -
```

### 2. Builder et pusher l'image Docker

```bash
docker build -f Dockerfile.k8s -t docker.io/mathisdlmr/tutut:latest .
docker push docker.io/mathisdlmr/tutut:latest
```

### 3. Adapter la storageClass

Vérifier les StorageClasses disponibles sur votre cluster :

```bash
kubectl get storageclass
```

Remplacer `local-path` par la bonne valeur dans :

- `k8s/pvc-storage.yaml`
- `k8s/statefulset-mysql.yaml` (dans `volumeClaimTemplates`)

### 4. Déployer tout en une commande (Kustomize)

```bash
kubectl apply -k k8s/
```

Ou fichier par fichier dans l'ordre :

```bash
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/secret.yaml
kubectl apply -f k8s/configmap.yaml
kubectl apply -f k8s/pvc-storage.yaml
kubectl apply -f k8s/statefulset-mysql.yaml
kubectl apply -f k8s/deployment-phpmyadmin.yaml
kubectl apply -f k8s/deployment-app.yaml
kubectl apply -f k8s/ingress.yaml
```

### 5. Vérifier le déploiement

```bash
# Suivre le démarrage
kubectl get pods -n tutut -w

# Logs de l'app
kubectl logs -n tutut deployment/tutut-app -f

# Logs MySQL
kubectl logs -n tutut statefulset/tutut-db -f
```

## Accès phpMyAdmin

⚠️ **phpMyAdmin est exposé sur `pma.tutut.stbo.fr`.**  
Il est fortement recommandé de restreindre l'accès avec des annotations nginx :

```yaml
# Dans ingress.yaml, décommenter :
nginx.ingress.kubernetes.io/whitelist-source-range: "votre-ip/32"
```

## Mise à jour de l'application

```bash
# Rebuild + push
docker build -f Dockerfile.k8s -t docker.io/mathisdlmr/tutut:latest .
docker push docker.io/mathisdlmr/tutut:latest

# Rolling update
kubectl rollout restart deployment/tutut-app -n tutut
kubectl rollout status deployment/tutut-app -n tutut
```
