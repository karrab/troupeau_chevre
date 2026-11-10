<?php
/**
 * Préchargement OPcache (opcache.preload) : compile les classes de l'application
 * une seule fois au démarrage de PHP-FPM.
 */
$root = dirname(__DIR__);
foreach (['app/Core', 'app/Services', 'app/Controllers'] as $dir) {
    foreach (glob("$root/$dir/*.php") as $file) {
        opcache_compile_file($file);
    }
}
opcache_compile_file("$root/app/helpers.php");
