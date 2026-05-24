<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation expirée — TontineApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
        <div class="text-5xl mb-4">⏰</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Invitation invalide</h1>
        <p class="text-gray-500 text-sm mb-6">
            Ce lien d'invitation a expiré ou a déjà été utilisé.<br>
            Contactez l'administrateur pour recevoir une nouvelle invitation.
        </p>
        <a href="{{ route('login') }}"
           class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl font-medium text-sm transition">
            Se connecter
        </a>
    </div>
</body>
</html>
