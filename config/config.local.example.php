<?php
// Copier ce fichier en config.local.php et adapter.
return [
    'app' => [
        'env'      => 'production',
        'base_url' => '',            // sous-dossier si l'application n'est pas à la racine, ex: '/troupeau'
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'troupeau',
        'user' => 'troupeau_user',
        'pass' => 'mot_de_passe_fort',
    ],
];
