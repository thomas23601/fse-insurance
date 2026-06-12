<?php
// Script d'initialisation de la base de données — à supprimer après utilisation
require_once __DIR__ . '/../includes/db.php';

$sql = file_get_contents(__DIR__ . '/../includes/setup.sql');
$pdo->exec($sql);

echo '<h2 style="font-family:sans-serif;color:green">✓ Base de données initialisée avec succès.</h2>';
echo '<p style="font-family:sans-serif"><strong>Supprimez ce fichier immédiatement.</strong> Accès : <a href="/">Accueil</a></p>';
