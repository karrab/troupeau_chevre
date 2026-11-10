<?php
declare(strict_types=1);

/**
 * Installateur web : http://localhost/troupeau/  (ou .../public/install.php)
 * - teste la connexion MySQL, crée la base, les tables et les données initiales ;
 * - écrit config/config.local.php ;
 * - se verrouille ensuite (storage/installed.lock) : il ne peut plus être relancé
 *   tant que ce fichier n'est pas supprimé manuellement.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Cache;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\DemoData;
use App\Services\Installer;

$locked = Installer::isLocked();
$errors = [];
$done = null;
$cur = config('db');
$v = [
    'host' => $_POST['host'] ?? $cur['host'], 'port' => $_POST['port'] ?? $cur['port'], 'name' => $_POST['name'] ?? $cur['name'],
    'user' => $_POST['user'] ?? $cur['user'], 'pass' => $_POST['pass'] ?? '',
];
$needOverwrite = false;

if (!$locked && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify()) {
        $errors[] = 'Jeton de sécurité invalide, rechargez la page.';
    } else {
        $db = array_map(fn($x) => trim((string) $x), $v);
        $db['pass'] = (string) ($_POST['pass'] ?? '');
        $db['port'] = (int) $db['port'] ?: 3306;
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db['name'])) {
            $errors[] = 'Nom de base invalide (lettres, chiffres et _ uniquement).';
        }
        if (!$errors) {
            try {
                $pdo = Installer::connect($db);
                if (Installer::databaseHasTables($pdo, $db['name']) && empty($_POST['ecraser'])) {
                    $needOverwrite = true;
                    $errors[] = "La base « {$db['name']} » contient déjà une installation. Cochez « Écraser » pour tout réinstaller (les données seront perdues).";
                } else {
                    @set_time_limit(600);
                    Installer::install($pdo, $db['name']);
                    Cache::clearAll();
                    $msgs = ["Base « {$db['name']} » créée avec ses tables et données initiales."];
                    if (!Installer::writeConfig($db)) {
                        $msgs[] = "⚠ Impossible d'écrire config/config.local.php : créez-le manuellement à partir de config/config.local.example.php.";
                    } else {
                        $msgs[] = 'Fichier config/config.local.php écrit.';
                    }
                    if (!empty($_POST['demo'])) {
                        Database::configure($db + ['charset' => 'utf8mb4']);
                        $msgs[] = DemoData::generer();
                    }
                    Installer::lock();
                    $done = $msgs;
                }
            } catch (\PDOException $e) {
                $errors[] = 'Erreur MySQL : ' . $e->getMessage();
            } catch (\Throwable $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}
$checks = [
    ['PHP ≥ 8.1', version_compare(PHP_VERSION, '8.1', '>='), PHP_VERSION, true],
    ['Extension pdo_mysql', extension_loaded('pdo_mysql'), '', true],
    ['Extension mbstring', extension_loaded('mbstring'), '', true],
    ['Dossier config/ accessible en écriture', is_writable(dirname(__DIR__) . '/config'), '', true],
    ['Dossier storage/ accessible en écriture', is_writable(dirname(__DIR__) . '/storage'), '', true],
    ['OPcache (recommandé)', function_exists('opcache_get_status'), '', false],
    ['APCu (recommandé)', function_exists('apcu_enabled') && apcu_enabled(), '', false],
    ['Dompdf / composer install (export PDF)', is_file(dirname(__DIR__) . '/vendor/autoload.php'), '', false],
];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation — <?= e(config('app.name')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="container py-4" style="max-width: 760px">
  <h1 class="h3 mb-3">Installation de <?= e(config('app.name')) ?></h1>

  <?php if ($done): ?>
    <div class="alert alert-success"><b>Installation terminée.</b><ul class="mb-0"><?php foreach ($done as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
    <div class="card"><div class="card-body">
      <p>Connectez-vous avec <b>admin</b> / <b>Admin@123</b> : un nouveau mot de passe vous sera demandé.</p>
      <a class="btn btn-success" href="<?= e(url('auth/login')) ?>">Aller à la connexion</a>
    </div></div>
  <?php elseif ($locked): ?>
    <div class="alert alert-warning">L'application est déjà installée. Pour réinstaller, supprimez le fichier
      <code>storage/installed.lock</code> sur le serveur puis rechargez cette page.</div>
    <a class="btn btn-success" href="<?= e(url()) ?>">Ouvrir l'application</a>
  <?php else: ?>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Vérification du serveur</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($checks as [$l, $ok, $info, $req]): ?>
        <li class="list-group-item d-flex justify-content-between"><span><?= e($l) ?> <small class="text-muted"><?= e($info) ?></small></span>
          <span class="badge text-bg-<?= $ok ? 'success' : ($req ? 'danger' : 'warning') ?>"><?= $ok ? 'OK' : ($req ? 'Manquant' : 'Absent') ?></span></li>
      <?php endforeach; ?>
      </ul></div>

    <?php foreach ($errors as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>

    <form method="post" class="card"><?= Csrf::field() ?>
      <div class="card-header bg-white fw-semibold">Base de données MySQL / MariaDB</div>
      <div class="card-body row g-3">
        <div class="col-8"><label class="form-label">Serveur</label><input class="form-control" name="host" value="<?= e($v['host']) ?>" required></div>
        <div class="col-4"><label class="form-label">Port</label><input class="form-control" name="port" type="number" value="<?= e($v['port']) ?>" required></div>
        <div class="col-12"><label class="form-label">Nom de la base (créée si elle n'existe pas)</label><input class="form-control" name="name" value="<?= e($v['name']) ?>" required></div>
        <div class="col-6"><label class="form-label">Utilisateur</label><input class="form-control" name="user" value="<?= e($v['user']) ?>" required></div>
        <div class="col-6"><label class="form-label">Mot de passe</label><input class="form-control" name="pass" type="password" autocomplete="off">
          <div class="form-text">Sous WAMP / XAMPP / Laragon : souvent <code>root</code> sans mot de passe.</div></div>
        <div class="col-12">
          <div class="form-check"><input class="form-check-input" type="checkbox" name="demo" value="1" id="demo" <?= !empty($_POST['demo']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="demo">Ajouter un troupeau de démonstration (~800 animaux sur 4 ans)</label></div>
          <?php if ($needOverwrite): ?>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="ecraser" value="1" id="ecraser">
            <label class="form-check-label text-danger" for="ecraser">Écraser l'installation existante (toutes les données seront supprimées)</label></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-footer bg-white text-end"><button class="btn btn-success">Installer</button></div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
