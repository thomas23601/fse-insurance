<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login($pdo, $email, $password)) {
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = 'Email ou mot de passe incorrect.';
    }
}

$pageTitle = 'Connexion';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-md mx-auto py-16 px-4">
    <h1 class="text-2xl font-bold text-slate-900 mb-8">Connexion</h1>
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-6 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" class="bg-white border border-slate-200 rounded-2xl p-8 space-y-5 shadow-sm">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required
                   class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Mot de passe</label>
            <input type="password" name="password" required
                   class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 rounded-xl transition">
            Se connecter
        </button>
        <p class="text-center text-sm text-slate-500">
            Pas encore de compte ? <a href="/register.php" class="text-blue-600 underline">S'inscrire</a>
        </p>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
