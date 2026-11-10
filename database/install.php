<?php
/**
 * Installation en ligne de commande :
 *   php database/install.php            -> crée la base (si besoin), le schéma et les données initiales
 *   php database/install.php --demo     -> + jeu de données de démonstration
 *
 * ATTENTION : le schéma supprime puis recrée les tables (DROP TABLE IF EXISTS).
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI uniquement.\n");
}
$config = require dirname(__DIR__) . '/config/config.php';
$db = $config['db'];

$pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']), $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$db['name']}`");

foreach (['schema.sql', 'seed.sql'] as $file) {
    echo "Exécution de $file...\n";
    $sql = file_get_contents(__DIR__ . '/' . $file);
    // Suppression des commentaires puis découpage sur les ';' de fin de ligne
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}
echo "Base « {$db['name']} » installée.\n";
echo "Connexion : admin / Admin@123 (changement obligatoire à la première connexion).\n";

if (in_array('--demo', $argv, true)) {
    require __DIR__ . '/demo.php';
}
