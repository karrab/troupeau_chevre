<?php
use App\Controllers\EvenementController as EC;
$title = $t['titre'];
$stField = $f['sous_type_champ'] ?? null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><i class="bi <?= $t['icone'] ?>"></i> <?= e($t['titre']) ?></h1>
  <div class="d-flex gap-2">
    <?php if (can('animaux.exporter')) { $route = 'evenements/pdf'; include __DIR__ . '/../partials/export_buttons.php'; } ?>
    <?php if (can('evenements.creer')): ?><a class="btn btn-success" href="<?= url('evenements/creer', ['type' => $t['code']]) ?>"><i class="bi bi-plus-lg"></i> <?= e($t['singulier']) ?></a><?php endif; ?>
  </div>
</div>
<ul class="nav nav-pills mb-3 d-print-none">
  <?php foreach ($types as $code => $ty): ?>
    <li class="nav-item"><a class="nav-link <?= $code === $t['code'] ? 'active' : '' ?>" href="<?= url('evenements', ['type' => $code]) ?>"><i class="bi <?= $ty['icone'] ?>"></i> <?= e($ty['titre']) ?></a></li>
  <?php endforeach; ?>
</ul>
<form class="card mb-3 d-print-none" method="get" action="<?= url() ?>"><div class="card-body"><div class="row g-2 align-items-end">
  <input type="hidden" name="r" value="evenements"><input type="hidden" name="type" value="<?= e($t['code']) ?>">
  <div class="col-6 col-md-2"><label class="form-label small">Du</label><input type="date" class="form-control" name="du" value="<?= e($f['du']) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small">Au</label><input type="date" class="form-control" name="au" value="<?= e($f['au']) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small">N° animal</label><input class="form-control" name="animal" value="<?= e($f['animal']) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small">Espèce</label><select class="form-select" name="espece_id"><option value="">Toutes</option>
    <?php foreach ($especes as $es): ?><option value="<?= $es['id'] ?>" <?= $f['espece_id'] == $es['id'] ? 'selected' : '' ?>><?= e($es['libelle']) ?></option><?php endforeach; ?></select></div>
  <?php if ($stField): ?>
  <div class="col-6 col-md-2"><label class="form-label small"><?= e($t['fields'][$stField]['label']) ?></label><select class="form-select" name="sous_type"><option value="">Tous</option>
    <?php foreach ($t['fields'][$stField]['options'] as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['sous_type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-search"></i> Filtrer</button>
    <a class="btn btn-outline-secondary" href="<?= url('evenements', ['type' => $t['code']]) ?>"><i class="bi bi-x-lg"></i></a></div>
</div></div></form>

<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Animal</th><?php foreach ($t['fields'] as $fd): ?><th><?= e($fd['label']) ?></th><?php endforeach; ?><th class="d-print-none"></th></tr></thead>
  <tbody>
  <?php foreach ($page['rows'] as $row): ?>
    <tr>
      <td><a href="<?= url('animaux/voir', ['id' => $row['animal_id'], 'onglet' => $t['code']]) ?>" class="fw-semibold"><?= e($row['numero_identification']) ?></a> <small class="text-muted"><?= e($row['animal_nom']) ?></small></td>
      <?php foreach ($t['fields'] as $k => $fd): ?><td><?= e(EC::display($fd, $row, $k)) ?></td><?php endforeach; ?>
      <td class="text-end text-nowrap d-print-none">
        <?php if (can('evenements.modifier')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('evenements/modifier', ['type' => $t['code'], 'id' => $row['id']]) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
        <?php if (can('evenements.supprimer')) { $action = url('evenements/supprimer'); $id = $row['id']; $extra = ['type' => $t['code']]; $message = 'Supprimer cet enregistrement ?'; include __DIR__ . '/../partials/delete_button.php'; } ?>
      </td>
    </tr>
  <?php endforeach; if (!$page['rows']): ?><tr><td colspan="<?= count($t['fields']) + 2 ?>" class="text-center text-muted py-4">Aucun enregistrement.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
