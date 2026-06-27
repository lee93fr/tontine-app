<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
        // Webhooks externes (DocuSeal) — exemptés du token CSRF
        $middleware->validateCsrfTokens(except: [
            'api/docuseal/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Connexion DB impossible → message explicite plutôt qu'un 500 générique.
        // On répond en HTML pur pour éviter toute dépendance à la session ou au cache DB.
        $exceptions->render(function (QueryException $e, \Illuminate\Http\Request $request) {
            if (str_starts_with($e->getSqlState() ?? '', '08')) {
                return dbUnavailableResponse();
            }
        });

        $exceptions->render(function (\PDOException $e, \Illuminate\Http\Request $request) {
            // Codes PDO courants pour connexion refusée : 2002 (MySQL), 7 (PostgreSQL)
            if (in_array((string) $e->getCode(), ['2002', '7', '08006', '08001', '08004'], true)
                || str_starts_with((string) $e->getCode(), '08')) {
                return dbUnavailableResponse();
            }
        });
    })->create();

function dbUnavailableResponse(): \Illuminate\Http\Response
{
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service indisponible — TontineApp</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f0f0ff;display:flex;align-items:center;
             justify-content:center;min-height:100vh;margin:0}
        .card{background:#fff;border-radius:1rem;box-shadow:0 4px 24px #0001;padding:2.5rem;
              max-width:420px;text-align:center}
        h1{font-size:1.25rem;color:#1e1b4b;margin:.5rem 0}
        p{color:#6b7280;font-size:.9rem;margin:.5rem 0 1.5rem}
        a{background:#4f46e5;color:#fff;padding:.5rem 1.5rem;border-radius:.5rem;
          text-decoration:none;font-size:.9rem}
        a:hover{background:#4338ca}
    </style>
</head>
<body>
    <div class="card">
        <div style="font-size:3rem">🔌</div>
        <h1>Base de données inaccessible</h1>
        <p>La connexion à la base de données a échoué.<br>
           Veuillez réessayer dans quelques instants.</p>
        <a href="javascript:location.reload()">Réessayer</a>
    </div>
</body>
</html>
HTML;

    return response($html, 503, ['Content-Type' => 'text/html; charset=utf-8']);
}
