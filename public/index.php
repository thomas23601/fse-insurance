<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Accueil';

// Tarifs indicatifs (SE-prop, moteur 8000$, 800h/2000h TBO)
$offers = [
    'flex'     => ['name' => 'Flex',     'price' => 369, 'franchise' => 1000, 'engagement' => 1,  'discount' => 0,   'highlight' => false],
    'confort'  => ['name' => 'Confort',  'price' => 332, 'franchise' => 500,  'engagement' => 3,  'discount' => 10,  'highlight' => true],
    'serenite' => ['name' => 'Sérénité', 'price' => 303, 'franchise' => 0,    'engagement' => 12, 'discount' => 18,  'highlight' => false],
];

include __DIR__ . '/../includes/header.php';
?>

<!-- Hero -->
<div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white py-20 px-4 text-center">
    <span class="bg-blue-900/50 text-blue-300 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-widest">Assurance virtuelle FSEconomy</span>
    <h1 class="text-4xl font-bold mt-4 mb-4">Protégez votre avion<br><span class="text-blue-400">contre les pannes FSE</span></h1>
    <p class="text-slate-400 max-w-xl mx-auto mb-8">Une panne moteur peut coûter plusieurs milliers de dollars in-game. Nos formules couvrent vos réparations — pour voler serein.</p>
    <a href="/devis.php" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-8 py-3 rounded-xl transition inline-block">Obtenir un devis gratuit</a>
</div>

<!-- KPIs -->
<div class="grid grid-cols-3 bg-white border-b border-slate-200">
    <div class="text-center py-6 px-4 border-r border-slate-100">
        <div class="text-3xl font-bold text-slate-900">3,69</div>
        <div class="text-xs text-slate-500 mt-1">pannes / an en moyenne (SE-prop)</div>
    </div>
    <div class="text-center py-6 px-4 border-r border-slate-100">
        <div class="text-3xl font-bold text-blue-600">0 $</div>
        <div class="text-xs text-slate-500 mt-1">reste à charge — offre Sérénité</div>
    </div>
    <div class="text-center py-6 px-4">
        <div class="text-3xl font-bold text-slate-900">3 000 $</div>
        <div class="text-xs text-slate-500 mt-1">plafond de remboursement / sinistre</div>
    </div>
</div>

<!-- Offres -->
<div class="max-w-5xl mx-auto py-16 px-4">
    <h2 class="text-2xl font-bold text-slate-900 text-center mb-2">Nos formules</h2>
    <p class="text-slate-500 text-sm text-center mb-10">Tarifs indicatifs — Cessna 172 SE-prop, moteur 8 000 $</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($offers as $key => $offer): ?>
        <div class="bg-white rounded-2xl border <?= $offer['highlight'] ? 'border-blue-500 shadow-lg shadow-blue-100' : 'border-slate-200' ?> overflow-hidden flex flex-col">
            <?php if ($offer['highlight']): ?>
                <div class="bg-blue-600 text-white text-xs font-bold text-center py-2 uppercase tracking-widest">Recommandé</div>
            <?php endif; ?>
            <div class="p-6 flex flex-col flex-1">
                <div class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-2"><?= $offer['name'] ?></div>
                <div class="text-4xl font-bold text-slate-900"><?= $offer['price'] ?> <span class="text-base font-normal text-slate-500">$/mois</span></div>
                <div class="text-xs text-slate-400 mb-6">Engagement <?= $offer['engagement'] ?> mois<?= $offer['discount'] > 0 ? ' · Remise ' . $offer['discount'] . ' %' : '' ?></div>
                <ul class="space-y-2 text-sm text-slate-600 flex-1 mb-6">
                    <li>✓ Franchise : <?= number_format($offer['franchise'], 0, ',', ' ') ?> $</li>
                    <li>✓ Plafond 3 000 $ / sinistre</li>
                    <?php if ($offer['discount'] > 0): ?>
                    <li>✓ Remise <?= $offer['discount'] ?> %</li>
                    <?php endif; ?>
                </ul>
                <a href="/devis.php" class="block text-center <?= $offer['highlight'] ? 'bg-blue-600 hover:bg-blue-500 text-white' : 'border border-blue-500 text-blue-600 hover:bg-blue-50' ?> font-semibold py-2.5 rounded-xl transition">
                    Choisir <?= $offer['name'] ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Comment ça marche -->
<div class="bg-slate-50 border-t border-slate-200 py-16 px-4">
    <div class="max-w-4xl mx-auto">
        <h2 class="text-2xl font-bold text-slate-900 text-center mb-10">Comment ça marche</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl font-bold text-blue-600 mx-auto mb-4">1</div>
                <h3 class="font-semibold text-slate-900 mb-2">Devis en 30 s</h3>
                <p class="text-sm text-slate-500">Saisissez l'immatriculation FSE — on récupère automatiquement les données de l'avion.</p>
            </div>
            <div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl font-bold text-blue-600 mx-auto mb-4">2</div>
                <h3 class="font-semibold text-slate-900 mb-2">Choisissez votre formule</h3>
                <p class="text-sm text-slate-500">Comparez franchise, remise et prime mensuelle. Acceptez d'un clic.</p>
            </div>
            <div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl font-bold text-blue-600 mx-auto mb-4">3</div>
                <h3 class="font-semibold text-slate-900 mb-2">Déclarez vos pannes</h3>
                <p class="text-sm text-slate-500">Sinistre in-game ? Déclarez depuis votre espace client. Indemnité calculée automatiquement.</p>
            </div>
        </div>
    </div>
</div>

<!-- CTA -->
<div class="bg-blue-600 py-16 px-4 text-center text-white">
    <h2 class="text-2xl font-bold mb-3">Prêt à voler l'esprit tranquille ?</h2>
    <p class="text-blue-200 mb-6">Devis gratuit, sans engagement. Résultat instantané.</p>
    <a href="/devis.php" class="bg-white text-blue-600 font-semibold px-8 py-3 rounded-xl inline-block hover:bg-blue-50 transition">Obtenir mon devis</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
