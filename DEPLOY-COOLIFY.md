# Déploiement sur Coolify

Cette app (Laravel 11 + PostgreSQL) se déploie via le **Dockerfile** fourni.

## 1. Créer la ressource dans Coolify

1. **+ New Resource → Application → Public/Private Repository**
2. Sélectionner ce dépôt et la branche à déployer.
3. **Build Pack : `Dockerfile`** (Coolify détecte automatiquement le `Dockerfile`
   à la racine).
4. **Ports Exposes : `80`** (l'app écoute sur le port 80 via nginx).

## 2. Variables d'environnement (obligatoires)

À renseigner dans **Coolify → ton app → Environment Variables** :

```env
APP_NAME=TontineApp
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # voir étape 3
APP_URL=https://ton-domaine.tld

LOG_CHANNEL=stack
LOG_LEVEL=error

# Base PostgreSQL externe
DB_CONNECTION=pgsql
DB_HOST=<hote-postgres>
DB_PORT=5432
DB_DATABASE=<base>
DB_USERNAME=<user>
DB_PASSWORD=<motdepasse>

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync

# Mail (Brevo SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<login-brevo>
MAIL_PASSWORD=<cle-smtp-brevo>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tontine@ton-domaine.tld
MAIL_FROM_NAME=TontineApp

# Web Push (VAPID) — optionnel
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=https://ton-domaine.tld
```

> `SESSION_DRIVER=database` et `CACHE_STORE=database` nécessitent les tables
> correspondantes : elles sont créées par les migrations (lancées
> automatiquement au démarrage).

## 3. Générer l'`APP_KEY` (une seule fois)

En local : `php artisan key:generate --show`
Copier la valeur (`base64:...`) dans la variable `APP_KEY` de Coolify.

⚠️ Ne pas régénérer la clé après mise en production : cela invaliderait les
sessions et les données chiffrées.

## 4. Déployer

Cliquer sur **Deploy**. Au démarrage du conteneur, l'`entrypoint` :

- applique les **migrations** (`php artisan migrate --force`),
- crée le lien `storage`,
- reconstruit les caches **config** et **vues**.

> Les **routes ne sont volontairement PAS mises en cache** : `routes/web.php`
> contient des routes en *closures*, et `route:cache` planterait
> (`Unable to prepare route for serialization. Uses Closure`).

## 5. Base de données

La base PostgreSQL est **externe** : assure-toi qu'elle est joignable depuis le
réseau Coolify et que les variables `DB_*` sont correctes. Les migrations
s'exécutent automatiquement à chaque déploiement.

## Domaine & HTTPS

Renseigne le domaine dans Coolify (FQDN) ; le proxy Traefik de Coolify gère le
certificat Let's Encrypt automatiquement. Pense à mettre `APP_URL` sur l'URL
HTTPS finale.

## Tâches planifiées / file d'attente (optionnel)

`docker/supervisord.conf` contient des blocs commentés pour :

- **`queue`** : si tu passes `QUEUE_CONNECTION` de `sync` à `database`/`redis`.
- **`scheduler`** : pour clôturer automatiquement les tours expirés (une fois la
  tâche définie dans `routes/console.php`, cf. README).

Décommente le bloc voulu puis redéploie.
