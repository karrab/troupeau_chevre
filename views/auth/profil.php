<?php $title = 'Mon profil'; ?>
<h1 class="h4 mb-3"><i class="bi bi-person-gear"></i> Mon profil</h1>
<?php if ((int) $u['doit_changer_mdp'] === 1): ?>
  <div class="alert alert-warning"><i class="bi bi-shield-exclamation"></i> Pour des raisons de sécurité, vous devez définir un nouveau mot de passe.</div>
<?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card"><div class="card-body">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Identifiant</label><input class="form-control" value="<?= e($u['login']) ?>" disabled></div>
    <div class="col-md-4"><label class="form-label">Nom complet *</label><input class="form-control" name="nom_complet" required value="<?= e($_POST['nom_complet'] ?? $u['nom_complet']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? $u['email']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Rôle</label><input class="form-control" value="<?= e($u['role_libelle']) ?>" disabled></div>
  </div>
  <hr>
  <h2 class="h6">Changer le mot de passe</h2>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Mot de passe actuel</label><input class="form-control" type="password" name="mdp_actuel" autocomplete="current-password"></div>
    <div class="col-md-4"><label class="form-label">Nouveau mot de passe</label><input class="form-control" type="password" name="mdp_nouveau" autocomplete="new-password">
      <div class="form-text">8 caractères min., avec majuscule, minuscule et chiffre.</div></div>
    <div class="col-md-4"><label class="form-label">Confirmation</label><input class="form-control" type="password" name="mdp_confirmation" autocomplete="new-password"></div>
  </div>
</div>
<div class="card-footer bg-white text-end"><button class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer</button></div>
</form>
