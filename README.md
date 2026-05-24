
# TontineApp — Application de gestion de tontines avec enchères

Application Laravel 11 + PostgreSQL pour gérer des tontines avec un système d'enchères à date limite, rôles admin/participant, et notifications email via Brevo.

## Fonctionnalités

- **Rôles** : Admin (gestion complète) / Participant (enchères seulement)
- **Enchères** : plafond configurable (défaut 15%), le plus offrant gagne
- **Tirage au sort** : si plusieurs au plafond ou si aucune enchère
- **Compte à rebours** temps réel avant clôture
- **Notifications email** : ouverture de tour, résultats, invitations (SMTP Brevo)
- **Invitations** : par email (lien 7 jours) ou ajout direct par l'admin
- **Historique** des tours et enchères

---

## Installation

### Prérequis
- PHP 8.2+
- PostgreSQL
- Composer
- Node.js (pour Vite/assets, optionnel si vous utilisez CDN Tailwind)

### 1. Cloner et installer

```bash
# Copier les fichiers dans votre projet Laravel existant ou créer un nouveau :
composer create-project laravel/laravel tontine
cd tontine

# Copier tous les fichiers de ce projet par-dessus
```

### 2. Installer les dépendances Laravel Breeze (auth)

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
```

### 3. Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Éditer `.env` :
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tontine
DB_USERNAME=votre_user
DB_PASSWORD=votre_mdp

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=votre-login@brevo.com
MAIL_PASSWORD=VOTRE_CLE_SMTP_BREVO
MAIL_FROM_ADDRESS=tontine@votredomaine.com
MAIL_FROM_NAME="TontineApp"
```

### 4. Base de données

```bash
php artisan migrate
php artisan db:seed  # Crée des données de démo
```

### 5. Lancer

```bash
php artisan serve
```

---

## Comptes de démo (après seeder)

| Rôle        | Email                        | Mot de passe |
|-------------|------------------------------|--------------|
| Admin       | admin@tontine.local          | password     |
| Participant | alice.dupont@exemple.com     | password     |
| Participant | bob.martin@exemple.com       | password     |
| Participant | claire.lebrun@exemple.com    | password     |

---

## Configuration SMTP Brevo

1. Connectez-vous à [Brevo](https://app.brevo.com)
2. Allez dans **Paramètres → Clés API → SMTP & API**
3. Récupérez le login SMTP et générez une clé SMTP
4. Renseignez dans `.env` :
   - `MAIL_USERNAME` = votre login Brevo
   - `MAIL_PASSWORD` = la clé SMTP générée

---

## Structure des enchères

```
Tour ouvert
    └── Participants enchérissent (0% à bid_cap%)
    └── Clôture automatique à bid_closes_at
    └── Admin déclenche le tirage

Résolution :
    Si des enchères existent :
        → Plus haute enchère gagne
        → Si plusieurs au plafond → tirage au sort parmi eux
    Si aucune enchère :
        → Tirage au sort parmi les participants n'ayant pas encore gagné
        → Si tous ont gagné → tirage parmi tous
```

---

## Déploiement production

```bash
APP_ENV=production
APP_DEBUG=false

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pour clôturer automatiquement les tours expirés, ajouter une tâche cron :

```bash
# Dans routes/console.php
Schedule::call(function () {
    Round::where('status', 'open')
         ->where('bid_closes_at', '<', now())
         ->update(['status' => 'closed']);
})->everyMinute();
```

Crontab serveur :
```
* * * * * cd /chemin/vers/projet && php artisan schedule:run >> /dev/null 2>&1
```
