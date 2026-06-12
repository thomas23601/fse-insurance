<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

// Vérification rôle admin
if ((currentUser()['role'] ?? '') !== 'admin') {
    header('Location: /dashboard.php');
    exit;
}

$tab = $_GET['tab'] ?? 'stats';

// Actions admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_claim'])) {
        $pdo->prepare('UPDATE claims SET status = "approved" WHERE id = ?')->execute([(int)$_POST['claim_id']]);
        header('Location: /admin.php?tab=claims&done=approved');
        exit;
    }
    if (isset($_POST['reject_claim'])) {
        $pdo->prepare('UPDATE claims SET status = "rejected" WHERE id = ?')->execute([(int)$_POST['claim_id']]);
        header('Location: /admin.php?tab=claims&done=rejected');
        exit;
    }
    if (isset($_POST['pay_claim'])) {
        $pdo->prepare('UPDATE claims SET status = "paid" WHERE id = ?')->execute([(int)$_POST['claim_id']]);
        header('Location: /admin.php?tab=claims&done=paid');
        exit;
    }
}

// ── Stats globales ──────────────────────────────────────────────────────────
$stats = [];

$stats['users']     = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "client"')->fetchColumn();
$stats['contracts'] = $pdo->query('SELECT COUNT(*) FROM contracts WHERE status = "active"')->fetchColumn();
$stats['quotes']    = $pdo->query('SELECT COUNT(*) FROM quotes')->fetchColumn();
$stats['claims']    = $pdo->query('SELECT COUNT(*) FROM claims')->fetchColumn();

$stats['mrr'] = $pdo->query('SELECT COALESCE(SUM(monthly_premium),0) FROM contracts WHERE status = "active"')->fetchColumn();
$stats['indemnities_paid'] = $pdo->query('SELECT COALESCE(SUM(indemnity),0) FROM claims WHERE status IN ("approved","paid")')->fetchColumn();
$stats['pending_claims']   = $pdo->query('SELECT COUNT(*) FROM claims WHERE status = "pending"')->fetchColumn();

$mrr  = (float)$stats['mrr'];
$indem = (float)$stats['indemnities_paid'];
$margin = $mrr > 0 ? round((($mrr - $indem / 12) / $mrr) * 100, 1) : 0;

// ── Données par onglet ──────────────────────────────────────────────────────
if ($tab === 'claims') {
    $claims = $pdo->query('
        SELECT cl.*, u.name AS user_name, u.email AS user_email, ct.registration, ct.offer_type, ct.franchise
        FROM claims cl
        JOIN users u ON cl.user_id = u.id
        JOIN contracts ct ON cl.contract_id = ct.id
        ORDER BY cl.created_at DESC
    ')->fetchAll(PDO::FETCH_ASSOC);
}

if ($tab === 'contracts') {
    $contracts = $pdo->query('
        SELECT ct.*, u.name AS user_name, u.email AS user_email
        FROM contracts ct
        JOIN users u ON ct.user_id = u.id
        ORDER BY ct.created_at DESC
    ')->fetchAll(PDO::FETCH_ASSOC);
}

if ($tab === 'users') {
    $users = $pdo->query('
        SELECT u.*,
               COUNT(DISTINCT ct.id) AS nb_contracts,
               COUNT(DISTINCT cl.id) AS nb_claims
        FROM users u
        LEFT JOIN contracts ct ON u.id = ct.user_id
        LEFT JOIN claims cl ON u.id = cl.user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ')->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Administration';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-6xl mx-auto py-10 px-4">

    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Administration</h1>
        <span class="bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 rounded-full">Admin</span>
    </div>

    <?php if (isset($_GET['done'])): ?>
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-6 text-sm">
        Action effectuée : <?= htmlspecialchars($_GET['done']) ?>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-1 bg-slate-100 p-1 rounded-xl mb-8 w-fit">
        <?php foreach (['stats' => 'Tableau de bord', 'claims' => 'Sinistres', 'contracts' => 'Contrats', 'users' => 'Utilisateurs'] as $t => $label): ?>
        <a href="?tab=<?= $t ?>" class="px-5 py-2 rounded-lg text-sm font-medium transition <?= $tab === $t ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
            <?= $label ?>
            <?php if ($t === 'claims' && $stats['pending_claims'] > 0): ?>
                <span class="ml-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?= $stats['pending_claims'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── STATS ── -->
    <?php if ($tab === 'stats'): ?>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php
        $kpis = [
            ['label' => 'Clients', 'value' => $stats['users'], 'sub' => 'comptes actifs', 'color' => 'blue'],
            ['label' => 'Contrats actifs', 'value' => $stats['contracts'], 'sub' => 'en cours', 'color' => 'green'],
            ['label' => 'MRR', 'value' => number_format($mrr, 0) . ' $', 'sub' => 'primes / mois', 'color' => 'indigo'],
            ['label' => 'Sinistres', 'value' => $stats['claims'], 'sub' => $stats['pending_claims'] . ' en attente', 'color' => 'amber'],
        ];
        foreach ($kpis as $kpi):
        ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-2"><?= $kpi['label'] ?></div>
            <div class="text-3xl font-bold text-slate-900"><?= $kpi['value'] ?></div>
            <div class="text-xs text-slate-500 mt-1"><?= $kpi['sub'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Bilan financier -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-6">
        <h2 class="font-bold text-slate-900 mb-5">Bilan financier</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600"><?= number_format($mrr, 0) ?> $</div>
                <div class="text-sm text-slate-500 mt-1">Primes encaissées / mois</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-red-500"><?= number_format($indem, 0) ?> $</div>
                <div class="text-sm text-slate-500 mt-1">Indemnités versées (cumul)</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold <?= $margin >= 30 ? 'text-blue-600' : 'text-amber-500' ?>"><?= $margin ?> %</div>
                <div class="text-sm text-slate-500 mt-1">Marge brute estimée</div>
            </div>
        </div>
    </div>

    <!-- Répartition sinistres par statut -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <h2 class="font-bold text-slate-900 mb-4">Sinistres par statut</h2>
        <?php
        $claimStats = $pdo->query('SELECT status, COUNT(*) as n FROM claims GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
        $statusColors = ['pending' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-blue-100 text-blue-700', 'paid' => 'bg-green-100 text-green-700', 'rejected' => 'bg-red-100 text-red-600'];
        ?>
        <div class="flex flex-wrap gap-3">
            <?php foreach ($claimStats as $cs): ?>
            <div class="flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200">
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $statusColors[$cs['status']] ?? 'bg-slate-100 text-slate-500' ?>"><?= ucfirst($cs['status']) ?></span>
                <span class="text-lg font-bold text-slate-900"><?= $cs['n'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($claimStats)): ?>
            <p class="text-slate-400 text-sm">Aucun sinistre.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── SINISTRES ── -->
    <?php elseif ($tab === 'claims'): ?>

    <?php if (empty($claims)): ?>
        <div class="text-center py-16 text-slate-400">Aucun sinistre déclaré.</div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($claims as $cl): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <div class="font-semibold text-slate-900"><?= htmlspecialchars($cl['registration']) ?> · <?= date('d/m/Y', strtotime($cl['event_date'])) ?></div>
                    <div class="text-sm text-slate-500"><?= htmlspecialchars($cl['user_name']) ?> (<?= htmlspecialchars($cl['user_email']) ?>)</div>
                    <div class="text-sm text-slate-500 mt-0.5"><?= str_replace('_', ' ', $cl['event_type']) ?> · Réparation déclarée : <strong><?= number_format($cl['repair_cost'], 0) ?> $</strong></div>
                    <?php if ($cl['description']): ?>
                    <div class="text-sm text-slate-400 mt-1 italic"><?= htmlspecialchars($cl['description']) ?></div>
                    <?php endif; ?>
                </div>
                <span class="text-xs font-semibold px-3 py-1 rounded-full <?= match($cl['status']) {
                    'pending'  => 'bg-amber-100 text-amber-700',
                    'approved' => 'bg-blue-100 text-blue-700',
                    'paid'     => 'bg-green-100 text-green-700',
                    'rejected' => 'bg-red-100 text-red-600',
                    default    => 'bg-slate-100 text-slate-500'
                } ?>">
                    <?= ucfirst($cl['status']) ?>
                </span>
            </div>
            <div class="flex items-center gap-3 text-sm mb-3">
                <span class="bg-slate-50 px-3 py-1 rounded-lg text-slate-600">Franchise : <strong><?= number_format($cl['franchise'], 0) ?> $</strong></span>
                <span class="bg-slate-50 px-3 py-1 rounded-lg text-slate-600">Plafond : <strong>3 000 $</strong></span>
                <span class="bg-green-50 px-3 py-1 rounded-lg text-green-700">Indemnité calculée : <strong><?= number_format($cl['indemnity'] ?? 0, 0) ?> $</strong></span>
            </div>
            <?php if ($cl['status'] === 'pending'): ?>
            <div class="flex gap-2">
                <form method="POST" class="inline">
                    <input type="hidden" name="claim_id" value="<?= $cl['id'] ?>">
                    <button name="approve_claim" type="submit" class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">Approuver</button>
                </form>
                <form method="POST" class="inline">
                    <input type="hidden" name="claim_id" value="<?= $cl['id'] ?>">
                    <button name="reject_claim" type="submit" class="border border-red-300 text-red-600 hover:bg-red-50 text-xs font-semibold px-4 py-2 rounded-lg transition">Rejeter</button>
                </form>
            </div>
            <?php elseif ($cl['status'] === 'approved'): ?>
            <form method="POST" class="inline">
                <input type="hidden" name="claim_id" value="<?= $cl['id'] ?>">
                <button name="pay_claim" type="submit" class="bg-green-600 hover:bg-green-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">Marquer comme payé</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── CONTRATS ── -->
    <?php elseif ($tab === 'contracts'): ?>

    <?php if (empty($contracts)): ?>
        <div class="text-center py-16 text-slate-400">Aucun contrat.</div>
    <?php else: ?>
    <div class="space-y-2">
        <?php foreach ($contracts as $c): ?>
        <div class="bg-white border border-slate-200 rounded-2xl px-6 py-4 shadow-sm flex items-center justify-between">
            <div>
                <div class="font-semibold text-slate-900"><?= htmlspecialchars($c['registration']) ?> — <?= ucfirst($c['offer_type']) ?></div>
                <div class="text-sm text-slate-500"><?= htmlspecialchars($c['user_name']) ?> · <?= number_format($c['monthly_premium'], 2) ?> $/mois · Franchise <?= number_format($c['franchise'], 0) ?> $</div>
                <div class="text-xs text-slate-400 mt-0.5">Depuis le <?= date('d/m/Y', strtotime($c['start_date'])) ?> · Prochaine éch. <?= date('d/m/Y', strtotime($c['next_billing_date'])) ?></div>
            </div>
            <span class="text-xs font-semibold px-3 py-1 rounded-full <?= $c['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?>">
                <?= ucfirst($c['status']) ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── UTILISATEURS ── -->
    <?php elseif ($tab === 'users'): ?>

    <?php if (empty($users)): ?>
        <div class="text-center py-16 text-slate-400">Aucun utilisateur.</div>
    <?php else: ?>
    <div class="space-y-2">
        <?php foreach ($users as $u): ?>
        <div class="bg-white border border-slate-200 rounded-2xl px-6 py-4 shadow-sm flex items-center justify-between">
            <div>
                <div class="font-semibold text-slate-900"><?= htmlspecialchars($u['name']) ?></div>
                <div class="text-sm text-slate-500"><?= htmlspecialchars($u['email']) ?> · Inscrit le <?= date('d/m/Y', strtotime($u['created_at'])) ?></div>
            </div>
            <div class="flex items-center gap-3">
                <span class="bg-blue-50 text-blue-700 text-xs px-3 py-1 rounded-full"><?= $u['nb_contracts'] ?> contrat<?= $u['nb_contracts'] > 1 ? 's' : '' ?></span>
                <span class="bg-amber-50 text-amber-700 text-xs px-3 py-1 rounded-full"><?= $u['nb_claims'] ?> sinistre<?= $u['nb_claims'] > 1 ? 's' : '' ?></span>
                <span class="text-xs font-semibold px-3 py-1 rounded-full <?= $u['role'] === 'admin' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500' ?>">
                    <?= ucfirst($u['role']) ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
