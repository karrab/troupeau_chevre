<?php
$title = $u ? 'Modifier ' . $u['login'] : 'Nouvel utilisateur';
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$val = fn($k) => e($v[$k] ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-person"></i> <?= e($title) ?></h1>
  <a class="btn btn-outline-secondary" href="<?= url('utilisateurs') ?>"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<form method="post" class="card"><?= csrf_field() ?><div class="card-body"><div class="row g-3">
  <div class="col-md-4"><label class="form-label">Identifiant *</label><input class="form-control" name="login" value="<?= $val('login') ?>" required><?= $err('login') ?></div>
  <div class="col-md-4"><label class="form-label">Nom complet *</label><input class="form-control" name="nom_complet" value="<?= $val('nom_complet') ?>" required><?= $err('nom_complet') ?></div>
  <div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= $val('email') ?>"><?= $err('email') ?></div>
  <div class="col-md-4"><label class="form-label">Rôle *</label><select class="form-select" name="role_id"><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>" <?= ($v['role_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= e($r['libelle']) ?></option><?php endforeach; ?></select><?= $err('role_id') ?></div>
  <div class="col-md-4"><label class="form-label">Mot de passe <?= $u ? '(laisser vide pour conserver)' : '*' ?></label><input type="password" class="form-control" name="mot_de_passe" autocomplete="new-password"><?= $err('mot_de_passe') ?></div>
  <div class="col-md-4 d-flex flex-column justify-content-end">
    <div class="form-check"><input class="form-check-input" type="checkbox" name="actif" value="1" id="actif" <?= !empty($v['actif']) ? 'checked' : '' ?>><label class="form-check-label" for="actif">Compte actif</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="doit_changer_mdp" value="1" id="dcm" <?= !empty($v['doit_changer_mdp']) ? 'checked' : '' ?>><label class="form-check-label" for="dcm">Changement de mot de passe à la prochaine connexion</label></div>
    <?= $err('actif') ?>
  </div>
</div></div><div class="card-footer bg-white text-end"><button class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer</button></div></form>
