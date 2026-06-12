<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Appel FSEconomy datafeed
function fetchFseAircraft(string $registration, string $fseKey): ?array {
    $key = $fseKey;
    $url = 'https://server.fseconomy.net/data?' . http_build_query([
        'servicekey' => $key,
        'format'     => 'xml',
        'query'      => 'aircraft',
        'search'     => 'key',
        'id'         => $registration,
    ]);

    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $xml = @file_get_contents($url, false, $ctx);
    if (!$xml) return null;

    $data = @simplexml_load_string($xml);
    if (!$data) return null;

    // Cherche l'avion dans la réponse
    $ac = null;
    if (isset($data->Aircraft)) {
        $ac = $data->Aircraft;
    } elseif (isset($data->AircraftItems->Aircraft)) {
        $ac = $data->AircraftItems->Aircraft;
        if (is_iterable($ac)) $ac = $ac[0];
    }
    if (!$ac) return null;

    // Détermine le type
    $makeModel = strtolower((string)($ac->MakeModel ?? $ac->makemodel ?? ''));
    $engines   = (int)($ac->Engines ?? 1);
    if (str_contains($makeModel, 'helicopter') || str_contains($makeModel, 'heli')) {
        $type = 'heli';
    } elseif (str_contains($makeModel, 'jet')) {
        $type = $engines > 1 ? 'ME-jet' : 'SE-jet';
    } elseif (str_contains($makeModel, 'turbo') || str_contains($makeModel, 'tbm') || str_contains($makeModel, 'pilatus')) {
        $type = $engines > 1 ? 'ME-turbo' : 'SE-turbo';
    } else {
        $type = $engines > 1 ? 'ME-prop' : 'SE-prop';
    }

    $tbo     = (float)($ac->TimeBetweenOverhauls ?? 2000);
    $airframe = (float)($ac->AirframeTime ?? 0);
    $sinceOvh = (float)($ac->TimeSinceLastOverhaul ?? $airframe);

    // Estimation prix moteur selon type
    $enginePrices = ['SE-prop'=>8000,'ME-prop'=>12000,'SE-turbo'=>25000,'ME-turbo'=>40000,'SE-jet'=>60000,'ME-jet'=>90000,'heli'=>30000];
    $enginePrice  = $enginePrices[$type] ?? 8000;

    return [
        'registration'  => strtoupper($registration),
        'model'         => (string)($ac->MakeModel ?? 'Inconnu'),
        'type'          => $type,
        'home_base'     => (string)($ac->Home ?? $ac->Location ?? 'N/A'),
        'engine_price'  => $enginePrice,
        'tbo_hours'     => $tbo ?: 2000,
        'current_hours' => $sinceOvh,
        'status'        => (string)($ac->NeedsRepair ?? '0') === '0' ? 'OK' : 'EN PANNE',
    ];
}

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

$result   = null;
$error    = '';

// Récupère la clé FSE : celle de l'utilisateur connecté ou la clé globale en fallback
$userFseKey = '';
if (isLoggedIn()) {
    $user = currentUser();
    $userFseKey = $user['fse_key'] ?? '';
    // Recharge depuis la DB si la session ne contient pas la clé
    if (!$userFseKey) {
        global $pdo;
        $row = $pdo->prepare('SELECT fse_key FROM users WHERE id = ?');
        $row->execute([$user['id']]);
        $userFseKey = $row->fetchColumn() ?: '';
    }
}
if (!$userFseKey) {
    $userFseKey = getenv('FSE_SERVICE_KEY') ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration'])) {
    $registration = strtoupper(trim($_POST['registration']));

    if (!$userFseKey) {
        $error = "Aucune clé FSEconomy configurée. Ajoutez votre clé dans votre profil.";
    } else {
        // Appel API FSEconomy avec la clé de l'utilisateur
        $aircraftData = fetchFseAircraft($registration, $userFseKey);
        if (!$aircraftData) {
            $error = "Immatriculation « $registration » introuvable sur FSEconomy.";
        } else {
            $offers = computeOffers($aircraftData['engine_price'], $aircraftData['tbo_hours'], $aircraftData['current_hours']);
            $result = ['aircraft' => $aircraftData, 'offers' => $offers];
        }
    }
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

    <?php if (isLoggedIn() && !$userFseKey): ?>
    <div class="bg-amber-50 border-l-4 border-amber-400 text-amber-800 px-4 py-3 rounded mb-6 text-sm">
        ⚠️ Vous n'avez pas de clé FSEconomy enregistrée. <a href="/profile.php" class="underline font-semibold">Ajoutez-la dans votre profil</a> pour obtenir des devis sur vos avions.
    </div>
    <?php endif; ?>

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
        <d