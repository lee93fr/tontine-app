#!/bin/sh
set -e

cd /var/www/html

echo "[entrypoint] Préparation de l'application Laravel…"

# Permissions sur les dossiers inscriptibles
chown -R www-data:www-data storage bootstrap/cache || true

# APP_KEY : obligatoire. On NE génère pas une clé aléatoire à chaque déploiement
# (cela invaliderait sessions et données chiffrées). On avertit simplement.
if [ -z "${APP_KEY}" ]; then
    echo "[entrypoint] ⚠️  APP_KEY n'est pas définie ! Générez-la une fois avec"
    echo "             'php artisan key:generate --show' puis renseignez-la"
    echo "             dans les variables d'environnement Coolify."
fi

# Migrations (base PostgreSQL externe). --force = pas de confirmation en prod.
echo "[entrypoint] Exécution des migrations…"
php artisan migrate --force --no-interaction || \
    echo "[entrypoint] ⚠️  Migrations en échec (DB joignable ? variables DB_* correctes ?)"

# Lien symbolique storage -> public/storage (idempotent)
php artisan storage:link --no-interaction 2>/dev/null || true

# Caches : on cache config + vues, MAIS PAS les routes.
# routes/web.php contient des routes en closures -> route:cache planterait
# ("Unable to prepare route for serialization. Uses Closure").
echo "[entrypoint] Reconstruction des caches (config + vues)…"
php artisan config:clear --no-interaction || true
php artisan config:cache --no-interaction || true
php artisan view:cache --no-interaction || true

echo "[entrypoint] Prêt. Démarrage des services."

exec "$@"
