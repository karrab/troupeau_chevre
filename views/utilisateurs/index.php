<?php $title = 'Utilisateurs'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-people"></i> Utilisateurs</h1>
  <div class="d-flex gap-2"><?php $route = 'utilisateurs/pdf'; include __DIR__ . '/../partials/export_buttons.php'; ?>
    <a class="btn btn-success" href="<?= url('utilisateurs/form') ?>"><i class="bi bi-person-plus"></i> Nouvel utilisateur</a></div>
</div>
<form class="card mb-3 d-print-none" method="get" action="<?= url() ?>"><div class="card-body"><div class="row g-2">
  <input type="hidden" name="r" value="utilisateurs">
  <div class="col-md-4"><input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Identifiant, nom ou email"></div>
  <div class="col-md-3"><select class="form-select" name="role_id"><option value="">Tous les rôles</option><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>" <?= $f['role_id'] == $r['id'] ? 'selected' : '' ?>><?= e($r['libelle']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select class="form-select" name="actif"><option value="">Tous</option><option value="1" <?= $f['actif'] === '1' ? 'selected' : '' ?>>Actifs</option><option value="0" <?= $f['actif'] === '0' ? 'selected' : '' ?>>Désactivés</option></select></div>
  <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
</div></div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Identifiant</th><th>Nom</th><th>Email</th><th>Rôle</th><th>État</th><th>Dernière connexion</th><th></th></tr></thead><tbody>
  <?php foreach ($page['rows'] as $u): ?>
    <tr><td class="fw-semibold"><?= e($u['login']) ?></td><td><?= e($u['nom_complet']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['role']) ?></td>
      <td><?= $u['actif'] ? '<span class="badge text-bg-success">Actif</span>' : '<span class="badge text-bg-secondary">Désactivé</span>' ?></td>
      <td class="small"><?= fdate($u['derniere_connexion'], true) ?: '—' ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('utilisateurs/form', ['id' => $u['id']]) ?>"><i class="bi bi-pencil"></i></a>
        <a class="btn btn-sm btn-outline-info" href="<?= url('journal', ['utilisateur_id' => $u['id']]) ?>" title="Traces"><i class="bi bi-journal-text"></i></a>
        <?php $action = url('utilisateurs/supprimer'); $id = $u['id']; $message = 'Supprimer l\'utilisateur ' . $u['login'] . ' ?'; include __DIR__ . '/../partials/delete_button.php'; ?>
      </td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
