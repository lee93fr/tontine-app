# Mise à jour de sécurité — 17 novembre 2026

Laravel 12.73.0, dépendances PHP compatibles avec PHP 8.3 et chaîne Vite 7 actualisés. Image de compilation Node 22 pour Vite 7.

Validation : construction des assets réussie, Composer audit sans avis de sécurité et npm audit --omit=dev sans vulnérabilité. 59 tests : 6 échecs déjà présents avant migration, aucun nouveau cas en échec (exécution isolée avec PostgreSQL).

Les avis picomatch GHSA-fc5q-39xc-vjpf encore signalés concernent uniquement les dépendances de compilation ; aucun correctif n’était disponible lors du contrôle.

Avant déploiement : dump PostgreSQL au format custom et archive complète de storage sauvegardés et vérifiés sur le serveur dans le répertoire de sauvegarde de sécurité du 17 novembre 2026. Aucun changement de schéma applicatif dans cette mise à jour.

Références :
- https://laravel.com/docs/12.x/upgrade
- https://v7.vite.dev/guide/migration.html
- https://github.com/barryvdh/laravel-dompdf/releases/tag/v3.0.0
