<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$userId = $_SESSION['user_id'];
$tab    = $_GET['tab'] ?? 'contracts';

// Activation contrat depuis un devis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_quote'])) {
    $quoteId = (int)$_POST['quote_id'];
    $stmt = $pdo->prepare('SELECT * FROM quotes WHERE id = ? AND user_id = ? AND status = "sent"');
    $stmt->execute([$quoteId, $userId]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($quote) {
        $start = date('Y-m-d');
        $next  = date('Y-m-d', strtotime('+1 month'));
        $pdo->prepare('INSERT INTO contracts (user_id, quote_id, registration, model, offer_type, monthly_premium, franchise, engagement_months, start_date, next_billing_date) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$userId, $quoteId, $quote['registration'], $quote['model'], $quote['offer_type'], $quote['monthly_premium'], $quote['franchise'], $quote['engagement_months'], $start, $next]);
        $pdo->prepare('UPDATE quotes SET status = "accepted" WHERE id = ?')->execute([$quoteId]);
        header('Location: /dashboard.php?tab=contracts&activated=1');
        exit;
    }
}

// Déclaration sinistre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['declare_claim'])) {
    $contractId  = (int)$_POST['contract_id'];
    $eventDate   = $_POST['event_date'];
    $eventType   = $_POST['event_type'];
    $repairCost  = (float)$_POST['repair_cost'];
    $description = trim($_POST['description'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? AND user_id = ? AND status = "active"');
    $stmt->execute([$contractId, $userId]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($contract) {
        $cap       = 3000;
        $franchise = $contract['franchise'];
        $indemnity = max(0, min($repairCost, $cap) - $franchise);

        $pdo->prepare('INSERT INTO claims (user_id, contract_id, event_date, event_type, description, repair_cost, indemnity, franchise_applied) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$userId, $contractId, $eventDate, $eventType, $description, $repairCost, $indemnity, $franchise]);
        header('Location: /dashboard.php?tab=claims&declared=1');
        exit;
    }
}

// Données
$contracts = $pdo->prepare('SELECT * FROM contracts WHERE user_id = ? ORDER BY created_at DESC');
$contracts->execute([$userId]);
$contracts = $contracts->fetchAll(PDO::FETCH_ASSOC);

$quotes = $pdo->prepare('SELECT * FROM quotes WHERE user_id = ? ORDER BY created_at DESC');
$quotes->execute([$userId]);
$quotes = $quotes->fetchAll(PDO::FETCH_ASSOC);

$claims = $pdo->prepare('SELECT c.*, ct.registration, ct.offer_type FROM claims c JOIN contracts ct ON c.contract_id = ct.id WHERE c.user_id = ? ORDER BY c.created_at DESC');
$claims->execute([$userId]);
$claims = $claims->fetchAll(PDO::FETCH_ASSOC);

$activeContracts = array_filter($contracts, fn($c) => $c['status'] === 'active');

$pageTitle = 'Espace client';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto py-12 px-4">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Espace client</h1>
        <span class="text-sm text-slate-500">Bonjour, <?= htmlspecialchars(currentUser()['name']) ?></span>
    </div>

    <?php if (isset($_GET['saved'])): ?><div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-6 text-sm">Devis enregistré.</div><?php endif; ?>
    <?php if (isset($_GET['activated'])): ?><div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-6 text-sm">Contrat activé avec succès.</div><?php endif; ?>
    <?php if (isset($_GET['declared'])): ?><div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-6 text-sm">Sinistre déclaré.</div><?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-1 bg-slate-100 p-1 rounded-xl mb-8 w-fit">
        <?php foreach (['contracts' => 'Contrats', 'quotes' => 'Devis', 'claims' => 'Sinistres'] as $t => $label): ?>
        <a href="?tab=<?= $t ?>" class="px-5 py-2 rounded-lg text-sm font-medium transition <?= $tab === $t ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- CONTRATS -->
    <?php if ($tab === 'contracts'): ?>
    <?php if (empty($contracts)): ?>
        <div class="text-center py-16 text-slate-400">
            <p class="mb-4">Aucun contrat actif.</p>
            <a href="/devis.php" class="text-blue-500 underline">Obtenir un devis</a>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($contracts as $c): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <div class="font-semibold text-slate-900"><?= htmlspecialchars($c['registration']) ?> — <?= htmlspecialchars($c['model'] ?? '') ?></div>
                        <div class="text-sm text-slate-500"><?= ucfirst($c['offer_type']) ?> · <?= number_format($c['monthly_premium'], 2) ?> $/mois · Franchise <?= number_format($c['franchise'], 0) ?> $</div>
                    </div>
                    <span class="text-xs font-semibold px-3 py-1 rounded-full <?= $c['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?>">
                        <?= ucfirst($c['status']) ?>
                    </span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
                    <div class="bg-slate-50 rounded-xl p-3"><div class="text-xs text-slate-400 mb-1">Engagement</div><div class="font-semibold"><?= $c['engagement_months'] ?> mois</div></div>
                    <div class="bg-slate-50 rounded-xl p-3"><div class="text-xs text-slate-400 mb-1">Début</div><div class="font-semibold"><?= date('d/m/Y', strtotime($c['start_date'])) ?></div></div>
                    <div class="bg-slate-50 rounded-xl p-3"><div class="text-xs text-slate-400 mb-1">Prochaine éch.</div><div class="font-semibold"><?= date('d/m/Y', strtotime($c['next_billing_date'])) ?></div></div>
                    <div class="bg-slate-50 rounded-xl p-3"><div class="text-xs text-slate-400 mb-1">Plafond</div><div class="font-semibold">3 000 $</div></div>
                </div>
                <?php if ($c['status'] === 'active'): ?>
                <button onclick="document.getElementById('claim-form-<?= $c['id'] ?>').classList.toggle('hidden')"
                        class="bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium px-5 py-2 rounded-xl transition">
                    Déclarer un sinistre
                </button>
                <div id="claim-form-<?= $c['id'] ?>" class="hidden mt-4 border-t border-slate-100 pt-4">
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="declare_claim" value="1">
                        <input type="hidden" name="contract_id" value="<?= $c['id'] ?>">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Date de la panne</label>
                                <input type="date" name="event_date" max="<?= date('Y-m-d') ?>" required
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                                <select name="event_type" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="engine_failure">Panne moteur</option>
                                    <option value="airframe_damage">Dommages cellule</option>
                                    <option value="avionics_failure">Avionique</option>
                                    <option value="landing_gear">Train d'atterrissage</option>
                                    <option value="prop_damage">Hélice</option>
                                    <option value="other">Autre</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Coût de réparation ($)</label>
                            <input type="number" name="repair_cost" min="0.01" step="0.01" required
                                   placeholder="ex: 8500"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Description (optionnel)</label>
                            <textarea name="description" rows="2" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium px-5 py-2 rounded-xl transition">
                            Soumettre la déclaration
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- DEVIS -->
    <?php elseif ($tab === 'quotes'): ?>
    <?php if (empty($quotes)): ?>
        <div class="text-center py-16 text-slate-400">
            <p class="mb-4">Aucun devis.</p>
            <a href="/devis.php" class="text-blue-500 underline">Obtenir mon premier devis</a>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($quotes as $q): ?>
            <div class="bg-white border border-slate-200 rounded-2xl px-6 py-4 shadow-sm flex items-center justify-between">
                <div>
                    <div class="font-semibold text-slate-900"><?= htmlspecialchars($q['registration']) ?> — <?= ucfirst($q['offer_type']) ?></div>
                    <div class="text-sm text-slate-500"><?= number_format($q['monthly_premium'], 2) ?> $/mois · Franchise <?= number_format($q['franchise'], 0) ?> $ · <?= $q['engagement_months'] ?> mois</div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400"><?= date('d/m/Y', strtotime($q['created_at'])) ?></span>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= $q['status'] === 'sent' ? 'bg-blue-100 text-blue-700' : ($q['status'] === 'accepted' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500') ?>">
                        <?= ucfirst($q['status']) ?>
                    </span>
                    <?php if ($q['status'] === 'sent'): ?>
                    <form method="POST">
                        <input type="hidden" name="activate_quote" value="1">
                        <input type="hidden" name="quote_id" value="<?= $q['id'] ?>">
                        <button type="submit" class="text-xs bg-green-600 hover:bg-green-500 text-white font-medium px-3 py-1.5 rounded-lg transition">
                            Activer
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- SINISTRES -->
    <?php elseif ($tab === 'claims'): ?>
    <?php if (empty($claims)): ?>
        <div class="text-center py-16 text-slate-400">Aucun sinistre déclaré.</div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($claims as $cl): ?>
            <div class="bg-white border border-slate-200 rounded-2xl px-6 py-4 shadow-sm flex items-center justify-between">
                <div>
                    <div class="font-semibold text-slate-900"><?= htmlspecialchars($cl['registration']) ?> · <?= date('d/m/Y', strtotime($cl['event_date'])) ?></div>
                    <div class="text-sm text-slate-500"><?= str_replace('_', ' ', $cl['event_type']) ?> · Réparation : <?= number_format($cl['repair_cost'], 0) ?> $</div>
                    <?php if ($cl['indemnity'] !== null): ?>
                    <div class="text-sm text-green-700 font-medium mt-0.5">Indemnité : <?= number_format($cl['indemnity'], 0) ?> $</div>
                    <?php endif; ?>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= match($cl['status']) {
                    'pending'  => 'bg-amber-100 text-amber-700',
                    'approved' => 'bg-blue-100 text-blue-700',
                    'paid'     => 'bg-green-100 text-green-700',
                    'rejected' => 'bg-red-100 text-red-600',
                    default    => 'bg-slate-100 text-slate-500'
                } ?>">
                    <?= ucfirst($cl['status']) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
