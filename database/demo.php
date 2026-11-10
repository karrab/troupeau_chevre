<?php
/**
 * Génère un troupeau de démonstration :  php database/demo.php [nombre_de_meres=120]
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI uniquement.\n");
}
require_once dirname(__DIR__) . '/app/bootstrap.php';

$n = isset($argv[1]) && ctype_digit($argv[1]) ? (int) $argv[1] : 120;
echo App\Services\DemoData::generer($n) . "\n";
