<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirmation'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit faire au moins 8 caractères.';
    } else {
        // Vérifie si l'email existe déjà
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, $hash]);
            $userId = $pdo->lastInsertId();

            $_SESSION['user_id'] = $userId;
            $_SESSION['user']    = ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'client'];

            header('Location: /dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Inscription';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-md mx-auto py-16 px-4">
    <h1 class="text-2xl font-bold text-slate-900 mb-8">Créer un compte</h1>
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-6 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" class="bg-white border border-slate-200 rounded-2xl p-8 space-y-5 shadow-sm">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Nom</label>
            <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required
                   class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
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
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirmer le mot de passe</label>
            <input type="password" name="password_confirmation" required
                   class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 rounded-xl transition">
            Créer mon compte
        </button>
        <p class="text-center text-sm text-slate-500">
            Déjà un compte ? <a href="/login.php" class="text-blue-600 underline">Se connecter</a>
        </p>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
