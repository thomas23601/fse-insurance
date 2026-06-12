<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Moteur de tarification
function computeOffers(float $enginePrice, float $tboHours, float $currentHours): array {
    $ratio = $currentHours / $tboHours;
    if ($ratio >= 1.0)      $ageFactor = 1.40;
    elseif ($ratio >= 0.90) $ageFactor = 1.25;
    elseif ($ratio >= 0.80) $ageFactor = 1.12;
    elseif ($ratio >= 0.60) $ageFactor = 1.00;
    elseif ($ratio >= 0.30) $ageFactor = 0.95;
    else                    $ageFactor = 0.90;

    $failuresPerYear = 3.69; // SE-prop
    $avgIndemnity    = $enginePrice * 0.40;
    $expectedLoss    = $failuresPerYear * $avgIndemnity * $ageFactor;
    $loadingFactor   = 0.30;
    $expenseRatio    = 0.20;

    $base = $expectedLoss * (1 + $loadingFactor) / (1 - $expenseRatio) / 12;

    return [
        'flex'     => ['name' => 'Flex',     'price' => round($base, 2),               'franchise' => 1000, 'engagement' => 1,  'discount' => 0],
        'confort'  => ['name' => 'Confort',  'price' => round($base * 0.90, 2),         'franchise' => 500,  'engagement' => 3,  'discount' => 10],
        'serenite' => ['name' => 'Sérénité', 'price' => round($base * 0.82, 2),         'franchise' => 0,    'engagement' => 12, 'discount' => 18],
    ];
}

$result = null;
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration'])) {
    $registration = strtoupper(trim($_POST['registration']));

    // Simulation données FSE (en production : appel XML datafeed)
    $aircraftData = [
        'registration' => $registration,
        'model'        => 'Cessna 172',
        'type'         => 'SE-prop',
        'home_base'    => 'LFPG',
        'engine_price' => 8000,
        'tbo_hours'    => 2000,
        'current_hours'=> 842,
        'status'       => 'OK',
    ];

    $offers = computeOffers($aircraftData['engine_price'], $aircraftData['tbo_hours'], $aircraftData['current_hours']);
    $result = ['aircraft' => $aircraftData, 'offers' => $offers];
}

// Sauvegarde devis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_offer'])) {
    requireLogin();
    $offer    = $_POST['offer_type'];
    $reg      = strtoupper(trim($_POST['registration']));
    $model    = $_POST['model'] ?? '';
    $price    = (float)$_POST['price'];
    $franchise = (float)$_POST['franchise'];
    $engagement = (int)$_POST['engagement'];
    $expires  = date('Y-m-d', strtotime('+30 days'));

    $stmt = $pdo->prepare('INSERT INTO quotes (user_id, registration, model, offer_type, monthly_premium, franchise, engagement_months, expires_at) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$_SESSION['user_id'], $reg, $model, $offer, $price, $franchise, $engagement, $expires]);

    header('Location: /dashboard.php?tab=quotes&saved=1');
    exit;
}

$pageTitle = 'Devis';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto py-12 px-4">
    <h1 class="text-2xl font-bold text-slate-900 mb-2">Obtenir un devis</h1>
    <p class="text-slate-500 text-sm mb-8">Saisissez l'immatriculation FSE de votre avion pour voir vos options.</p>

    <form method="POST" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8">
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Immatriculation FSE</label>
        <div class="flex gap-3">
            <input type="text" name="registration" value="<?= htmlspecialchars($_POST['registration'] ?? '') ?>"
                   placeholder="ex: N12345 ou F-ABCD" required
                   style="text-transform:uppercase"
                   class="flex-1 border border-slate-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-6 py-3 rounded-xl transition">
                Calculer →
            </button>
        </div>
    </form>

    <?php if ($result): ?>
    <!-- Résultat avion -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 mb-6 flex items-center gap-4 shadow-sm">
        <div class="text-3xl">✈️</div>
        <div class="flex-1">
            <div class="font-semibold text-slate-900"><?= htmlspecialchars($result['aircraft']['registration']) ?> — <?= htmlspecialchars($result['aircraft']['model']) ?></div>
            <div class="text-sm text-slate-500"><?= $result['aircraft']['type'] ?> · Base : <?= $result['aircraft']['home_base'] ?> · <?= $result['aircraft']['current_hours'] ?> h cellule</div>
        </div>
        <span class="bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full">OK</span>
    </div>

    <!-- Cartes offres -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        <?php foreach ($result['offers'] as $key => $offer): ?>
        <div class="bg-white border-2 <?= $key === 'confort' ? 'border-blue-500' : 'border-slate-200' ?> rounded-2xl overflow-hidden">
            <?php if ($key === 'confort'): ?>
                <div class="bg-blue-600 text-white text-xs font-bold text-center py-2 uppercase tracking-widest">Recommandé</div>
            <?php endif; ?>
            <div class="p-5">
                <div class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-1"><?= $offer['name'] ?></div>
                <div class="text-3xl font-bold text-slate-900 mb-1"><?= number_format($offer['price'], 0) ?> <span class="text-sm font-normal text-slate-500">$/mois</span></div>
                <div class="text-xs text-slate-400 mb-4">Engagement <?= $offer['engagement'] ?> mois<?= $offer['discount'] > 0 ? ' · -' . $offer['discount'] . ' %' : '' ?></div>
                <div class="text-sm text-slate-600 space-y-1 mb-5">
                    <div>Franchise : <strong><?= number_format($offer['franchise'], 0) ?> $</strong></div>
                    <div>Plafond : <strong>3 000 $</strong></div>
                </div>
                <?php if (isLoggedIn()): ?>
                <form method="POST">
                    <input type="hidden" name="save_offer" value="1">
                    <input type="hidden" name="registration" value="<?= htmlspecialchars($result['aircraft']['registration']) ?>">
                    <input type="hidden" name="model" value="<?= htmlspecialchars($result['aircraft']['model']) ?>">
                    <input type="hidden" name="offer_type" value="<?= $key ?>">
                    <input type="hidden" name="price" value="<?= $offer['price'] ?>">
                    <input type="hidden" name="franchise" value="<?= $offer['franchise'] ?>">
                    <input type="hidden" name="engagement" value="<?= $offer['engagement'] ?>">
                    <button type="submit" class="w-full <?= $key === 'confort' ? 'bg-blue-600 hover:bg-blue-500 text-white' : 'border border-blue-500 text-blue-600 hover:bg-blue-50' ?> font-semibold py-2.5 rounded-xl transition text-sm">
                        Enregistrer ce devis
                    </button>
                </form>
                <?php else: ?>
                <a href="/login.php" class="block text-center border border-slate-300 text-slate-500 hover:bg-slate-50 font-medium py-2.5 rounded-xl transition text-sm">
                    Connexion pour sauvegarder
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
