<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'FSEInsurance') ?> — Assurance virtuelle FSEconomy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

<header class="bg-slate-900 text-white shadow-lg">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="/" class="font-bold text-xl tracking-tight">
            FSE<span class="text-blue-400">Insurance</span>
        </a>
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-300">
            <a href="/" class="hover:text-white transition">Accueil</a>
            <a href="/devis.php" class="hover:text-white transition">Devis</a>
            <?php if (isLoggedIn()): ?>
                <a href="/dashboard.php" class="hover:text-white transition">Espace client</a>
            <?php endif; ?>
        </nav>
        <div class="flex items-center gap-3">
            <?php if (isLoggedIn()): ?>
                <span class="text-sm text-slate-400"><?= htmlspecialchars(currentUser()['name']) ?></span>
                <a href="/logout.php" class="text-sm text-slate-300 hover:text-white transition">Déconnexion</a>
            <?php else: ?>
                <a href="/login.php" class="text-sm text-slate-300 hover:text-white transition">Connexion</a>
                <a href="/register.php" class="text-sm bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg transition font-medium">S'inscrire</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="flex-1">
