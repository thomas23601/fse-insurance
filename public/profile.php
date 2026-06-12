<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$user    = currentUser();
$success = '';
$error   = '';

// Charge la clé FSE depuis la DB
$stmt = $pdo->prepare('SELECT fse_key FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$currentFseKey = $stmt->fetchColumn() ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newKey = trim($_POST['fse_key'] ?? '');

    $pdo->prepare('UPDATE users SET fse_key = ? WHERE id = ?')
        ->execute([$newKey ?: null, $user['id']]);

    // Met à jour la session
    $_SESSION['user']['fse_key'] = $newKey ?: null;
    $currentFseKey = $newKey;
    $success = 'Clé FSEconomy enregistrée.';
}

$pageTitle = 'Mon profil';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-lg mx-auto py-16 px-4">
    <h1 class="text-2xl font-bold text-slate-900 mb-8">Mon profil</h1>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-6 text-sm"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Infos compte -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-6">
        <h2 class="font-semibold text-slate-900 mb-4">Informations du compte</h2>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-500">Nom</span>
                <span class="font-medium text-slate-900"><?= htmlspecialchars($user['name']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Email</span>
                <span class="font-medium text-slate-900"><?= htmlspecialchars($user['email']) ?></span>
            </div>
        </div>
    </div>

    <!-- Clé FSEconomy -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <h2 class="font-semibold text-slate-900 mb-1">Clé personnelle FSEconomy</h2>
        <p class="text-sm text-slate-500 mb-5">Permet d'interroger les données de vos avions pour générer des devis précis. Trouvable dans votre profil FSEconomy sous <em>Personal Key</em>.</p>
        <form method="POST" class="space-y-4">
            <div>
                <input type="text" name="fse_key"
                       value="<?= htmlspecialchars($currentFseKey) ?>"
                       placeholder="ex: 16A8314454ABB51F"
                       class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-6 py-3 rounded-xl transition text-sm">
                Enregistrer
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
