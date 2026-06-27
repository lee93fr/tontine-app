<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service temporairement indisponible — TontineApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
        <div class="text-5xl mb-4">🔌</div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Base de données inaccessible</h1>
        <p class="text-gray-500 text-sm mb-6">
            La connexion à la base de données a échoué.<br>
            Veuillez réessayer dans quelques instants ou contacter l'administrateur.
        </p>
        <a href="{{ url()->current() }}"
           class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
            Réessayer
        </a>
    </div>
</body>
</html>
