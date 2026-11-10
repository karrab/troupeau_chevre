<?php $title = 'Rôles & permissions'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-shield-lock"></i> Rôles & permissions</h1>
  <a class="btn btn-success" href="<?= url('roles/form') ?>"><i class="bi bi-plus-lg"></i> Nouveau rôle</a>
</div>
<div class="row g-3 mb-3">
  <?php foreach ($roles as $r): ?>
  <div class="col-md-3"><div class="card h-100"><div class="card-body">
    <h2 class="h6 mb-1"><?= e($r['libelle']) ?> <?= $r['systeme'] ? '<i class="bi bi-lock-fill text-muted" title="Rôle système"></i>' : '' ?></h2>
    <div class="small text-muted mb-2"><?= e($r['description']) ?></div>
    <div class="small"><?= (int) $r['nb_utilisateurs'] ?> utilisateur(s)</div>
  </div><div class="card-footer bg-white text-end">
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('roles/form', ['id' => $r['id']]) ?>"><i class="bi bi-pencil"></i></a>
    <?php if (!$r['systeme']) { $action = url('roles/supprimer'); $id = $r['id']; $message = 'Supprimer le rôle ' . $r['libelle'] . ' ?'; include __DIR__ . '/../partials/delete_button.php'; } ?>
  </div></div></div>
  <?php endforeach; ?>
</div>
<form method="post" class="card"><?= csrf_field() ?>
  <div class="card-header bg-white fw-semibold">Matrice des permissions</div>
  <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
    <thead class="table-light"><tr><th>Permission</th><?php foreach ($roles as $r): ?><th class="text-center"><?= e($r['libelle']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($modules as $mod => $perms): ?>
      <tr class="table-group-divider"><td colspan="<?= count($roles) + 1 ?>" class="fw-semibold text-uppercase small text-success"><?= e($mod) ?></td></tr>
      <?php foreach ($perms as $p): ?>
        <tr><td><?= e($p['libelle']) ?> <code class="small text-muted"><?= e($p['code']) ?></code></td>
          <?php foreach ($roles as $r): $admin = $r['code'] === 'admin'; ?>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="perm[<?= $r['id'] ?>][<?= $p['id'] ?>]" value="1"
              <?= $admin || isset($assign[$r['id']][$p['id']]) ? 'checked' : '' ?> <?= $admin ? 'disabled title="L\'administrateur a tous les droits"' : '' ?>></td>
          <?php endforeach; ?></tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody></table></div>
  <div class="card-footer bg-white text-end"><button class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer les permissions</button></div>
</form>
