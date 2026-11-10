<?php
/**
 * Installation en ligne de commande (alternative à l'installateur web public/install.php) :
 *   php database/install.php            -> crée la base, le schéma et les données initiales
 *   php database/install.php --demo     -> + troupeau de démonstration
 *
 * ATTENTION : les tables existantes sont supprimées puis recréées.
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI uniquement. Utilisez l'installateur web : public/install.php\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Cache;
use App\Services\DemoData;
use App\Services\Installer;

$db = config('db');
Installer::install(Installer::connect($db), $db['name']);
Cache::clearAll();
echo "Base « {$db['name']} » installée.\n";
if (in_array('--demo', $argv, true)) {
    echo DemoData::generer() . "\n";
}
Installer::lock();
echo "Connexion : admin / Admin@123 (changement obligatoire à la première connexion).\n";
