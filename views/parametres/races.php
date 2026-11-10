<?php $title = 'Espèces & races'; ?>
<h1 class="h4 mb-3"><i class="bi bi-list-nested"></i> Espèces, catégories & races</h1>
<?php include __DIR__ . '/_nav.php'; ?>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-semibold">Races</div>
    <div class="card-body border-bottom"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="ajouter">
      <div class="col-md-4"><select class="form-select" name="espece_id"><?php foreach ($especes as $es): ?><option value="<?= $es['id'] ?>"><?= e($es['libelle']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-5"><input class="form-control" name="libelle" placeholder="Nouvelle race" required maxlength="80"></div>
      <div class="col-md-3"><button class="btn btn-success w-100"><i class="bi bi-plus-lg"></i> Ajouter</button></div></form></div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>Espèce</th><th>Race</th><th>Animaux</th><th>État</th><th></th></tr></thead><tbody>
      <?php foreach ($races as $r): ?><tr class="<?= $r['actif'] ? '' : 'text-muted' ?>"><td><?= e($r['espece']) ?></td><td><?= e($r['libelle']) ?></td><td><?= (int) $r['nb'] ?></td>
        <td><?= $r['actif'] ? 'Active' : 'Inactive' ?></td><td class="text-end text-nowrap">
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="basculer"><input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-sm btn-outline-secondary" title="Activer / désactiver"><i class="bi bi-toggle-<?= $r['actif'] ? 'on' : 'off' ?>"></i></button></form>
          <?php $action = url('parametres/races'); $id = $r['id']; $extra = ['action' => 'supprimer']; $message = 'Supprimer la race ' . $r['libelle'] . ' ?'; include __DIR__ . '/../partials/delete_button.php'; ?>
        </td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
  <div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">Catégories (détermination automatique)</div>
    <table class="table table-sm mb-0"><thead><tr><th>Espèce</th><th>Catégorie</th><th>Sexe</th><th>Adulte</th><th>Castré</th></tr></thead><tbody>
      <?php foreach ($categories as $c): ?><tr><td><?= e($especes[$c['espece_id']]['libelle'] ?? '') ?></td><td><?= e($c['libelle']) ?></td><td><?= $c['sexe'] ?></td><td><?= $c['adulte'] ? 'Oui' : 'Non' ?></td><td><?= $c['castre'] ? 'Oui' : '' ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <div class="card-footer bg-white small text-muted">Un animal devient adulte à l'âge défini dans les paramètres (<?= (int) \App\Core\Settings::get('age_adulte_mois') ?> mois).</div></div></div>
</div>
