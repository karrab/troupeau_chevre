<?php
/**
 * Configuration principale.
 * Ne modifiez pas ce fichier : créez config/config.local.php qui retourne
 * un tableau surchargeant les valeurs ci-dessous (voir config.local.example.php).
 */
$config = [
    'app' => [
        'name'     => 'Troupeau SI',
        'env'      => getenv('APP_ENV') ?: 'production',   // 'dev' affiche les erreurs
        'base_url' => getenv('APP_BASE_URL') ?: '',         // ex: '/troupeau/public'
        'timezone' => 'Africa/Casablanca',
        'locale'   => 'fr_FR',
        'session_name' => 'TROUPEAU_SID',
        'cache_prefix' => 'troupeau:',
    ],
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('DB_PORT') ?: 3306),
        'name'     => getenv('DB_NAME') ?: 'troupeau',
        'user'     => getenv('DB_USER') ?: 'root',
        'pass'     => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}
return $config;
