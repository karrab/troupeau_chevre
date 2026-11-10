<?php $title = $r ? 'Modifier le rôle' : 'Nouveau rôle'; $err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : ''; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0"><i class="bi bi-shield"></i> <?= e($title) ?></h1>
  <a class="btn btn-outline-secondary" href="<?= url('roles') ?>"><i class="bi bi-arrow-left"></i> Retour</a></div>
<form method="post" class="card"><?= csrf_field() ?><div class="card-body"><div class="row g-3">
  <div class="col-md-3"><label class="form-label">Code *</label><input class="form-control" name="code" value="<?= e($v['code'] ?? '') ?>" <?= !empty($r['systeme']) ? 'readonly' : '' ?> required><?= $err('code') ?></div>
  <div class="col-md-4"><label class="form-label">Libellé *</label><input class="form-control" name="libelle" value="<?= e($v['libelle'] ?? '') ?>" required><?= $err('libelle') ?></div>
  <div class="col-md-5"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($v['description'] ?? '') ?>"></div>
</div></div><div class="card-footer bg-white text-end"><button class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer</button></div></form>
