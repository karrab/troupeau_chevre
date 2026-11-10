<?php
use App\Core\Auth;
use App\Core\Settings;
$u = Auth::user();
$r = $_GET['r'] ?? '';
$active = fn(string $prefix) => ($prefix === '' ? $r === '' || $r === 'dashboard' : str_starts_with($r, $prefix)) ? 'active' : '';
$menu = [
    ['', 'bi-speedometer', 'Tableau de bord', null],
    ['animaux', 'bi-tags', 'Animaux', 'animaux.voir'],
    ['evenements', 'bi-calendar-event', 'Événements', 'evenements.voir'],
    ['indices', 'bi-graph-up-arrow', 'Indices', 'indices.voir'],
];
$admin = [
    ['utilisateurs', 'bi-people', 'Utilisateurs', 'utilisateurs.gerer'],
    ['roles', 'bi-shield-lock', 'Rôles & permissions', 'roles.gerer'],
    ['journal', 'bi-journal-text', 'Journal des traces', 'journal.voir'],
    ['parametres', 'bi-gear', 'Paramètres', 'parametres.gerer'],
];
?><!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
<title><?= e(($title ?? '') ? $title . ' — ' : '') ?><?= e(config('app.name')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top app-navbar d-print-none">
  <div class="container-fluid">
    <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu">
      <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand fw-semibold" href="<?= url() ?>"><i class="bi bi-flower1 me-1"></i><?= e(config('app.name')) ?></a>
    <span class="navbar-text small text-white-50 d-none d-md-inline"><?= e(Settings::get('nom_exploitation')) ?></span>
    <div class="ms-auto d-flex align-items-center gap-2">
      <?php if (can('animaux.voir')): ?>
      <form class="d-none d-md-flex" action="<?= url('animaux') ?>" method="get" role="search">
        <input type="hidden" name="r" value="animaux"><input type="hidden" name="statut" value="tous">
        <input class="form-control form-control-sm" type="search" name="q" placeholder="N° ou nom d'animal…" aria-label="Rechercher">
      </form>
      <?php endif; ?>
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline"><?= e($u['nom_complet']) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text small text-muted"><?= e($u['role_libelle']) ?></span></li>
          <li><a class="dropdown-item" href="<?= url('auth/profil') ?>"><i class="bi bi-person-gear me-1"></i> Mon profil</a></li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <form method="post" action="<?= url('auth/logout') ?>"><?= csrf_field() ?>
              <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-1"></i> Déconnexion</button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<div class="container-fluid">
  <div class="row">
    <aside class="col-lg-2 p-0 d-print-none">
      <div class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="sidebar">
        <div class="offcanvas-header"><h5 class="offcanvas-title">Menu</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button></div>
        <div class="offcanvas-body d-block p-2">
          <ul class="nav nav-pills flex-column gap-1">
            <?php foreach ($menu as [$route, $icon, $label, $perm]): if ($perm && !can($perm)) continue; ?>
              <li class="nav-item"><a class="nav-link <?= $active($route) ?>" href="<?= url($route) ?>"><i class="bi <?= $icon ?> me-2"></i><?= $label ?></a></li>
            <?php endforeach; ?>
          </ul>
          <?php $vis = array_filter($admin, fn($m) => can($m[3])); if ($vis): ?>
          <div class="text-uppercase small text-muted fw-semibold mt-4 mb-1 px-3">Administration</div>
          <ul class="nav nav-pills flex-column gap-1">
            <?php foreach ($vis as [$route, $icon, $label, $perm]): ?>
              <li class="nav-item"><a class="nav-link <?= $active($route) ?>" href="<?= url($route) ?>"><i class="bi <?= $icon ?> me-2"></i><?= $label ?></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
      </div>
    </aside>
    <main class="col-lg-10 px-3 px-md-4 py-3">
      <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?> alert-dismissible fade show d-print-none" role="alert">
          <?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>

<!-- Modale de confirmation de suppression -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger"></i> Confirmation</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="confirmModalText">Confirmer la suppression ?</div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
      <button type="button" class="btn btn-danger" id="confirmModalOk">Confirmer</button>
    </div>
  </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($charts)): ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script><?php endif; ?>
<script>window.APP = { baseUrl: <?= json_encode(url()) ?> };</script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
