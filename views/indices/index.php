<?php
use App\Controllers\IndiceController as IC;
$title = 'Indices';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-graph-up-arrow"></i> Indices calculés</h1>
  <div class="d-flex gap-2">
    <?php $route = 'indices/pdf'; include __DIR__ . '/../partials/export_buttons.php'; ?>
    <a class="btn btn-outline-primary" href="<?= url('indices/formules') ?>"><i class="bi bi-sliders"></i> Formules</a>
    <?php if (can('indices.calculer')): ?><a class="btn btn-success" href="<?= url('indices/calculer') ?>"><i class="bi bi-calculator"></i> Calculer</a><?php endif; ?>
  </div>
</div>
<form class="card mb-3 d-print-none" method="get" action="<?= url() ?>"><div class="card-body"><div class="row g-2 align-items-end">
  <input type="hidden" name="r" value="indices">
  <div class="col-6 col-md-2"><label class="form-label small">Cible</label><select class="form-select" name="cible_type" onchange="this.form.submit()">
    <?php foreach (IC::CIBLES as $k => $l): ?><option value="<?= $k ?>" <?= $f['cible_type'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <?php if ($f['cible_type'] === 'espece'): ?>
  <div class="col-6 col-md-2"><label class="form-label small">Espèce</label><select class="form-select" name="cible_id"><option value="">Toutes</option>
    <?php foreach ($especes as $es): ?><option value="<?= $es['id'] ?>" <?= $f['cible_id'] == $es['id'] ? 'selected' : '' ?>><?= e($es['libelle']) ?></option><?php endforeach; ?></select></div>
  <?php elseif ($f['cible_type'] === 'race'): ?>
  <div class="col-6 col-md-2"><label class="form-label small">Race</label><select class="form-select" name="cible_id"><option value="">Toutes</option>
    <?php foreach ($races as $ra): ?><option value="<?= $ra['id'] ?>" <?= $f['cible_id'] == $ra['id'] ? 'selected' : '' ?>><?= e($ra['libelle']) ?></option><?php endforeach; ?></select></div>
  <?php elseif ($f['cible_type'] === 'animal'): ?>
  <div class="col-6 col-md-2"><label class="form-label small">N° animal</label><input class="form-control" name="animal" value="<?= e($f['animal']) ?>"></div>
  <?php endif; ?>
  <div class="col-6 col-md-2"><label class="form-label small">Année</label><select class="form-select" name="annee"><option value="">Toutes</option>
    <?php if ($f['cible_type'] === 'animal'): ?><option value="0" <?= $f['annee'] === 0 ? 'selected' : '' ?>>Carrière</option><?php endif; ?>
    <?php foreach ($annees as $an): ?><option value="<?= $an ?>" <?= $f['annee'] === $an ? 'selected' : '' ?>><?= $an ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-3"><label class="form-label small">Indice</label><select class="form-select" name="indicateur_id"><option value="">Tous</option>
    <?php foreach ($indicateurs as $i): if (($i['portee'] === 'animal') !== ($f['cible_type'] === 'animal')) continue; ?><option value="<?= $i['id'] ?>" <?= $f['indicateur_id'] == $i['id'] ? 'selected' : '' ?>><?= e($i['nom']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-1"><label class="form-label small">Niveau</label><select class="form-select" name="niveau"><option value="">Tous</option>
    <?php foreach (['bon' => 'Bon', 'moyen' => 'Moyen', 'faible' => 'Faible', 'na' => 'N/C'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['niveau'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrer</button></div>
</div></div></form>

<?php if ($synthese && $synthese['lignes']): ?>
<div class="card mb-3"><div class="card-header bg-white fw-semibold"><i class="bi bi-table"></i> Synthèse pluriannuelle — <?= e(IC::CIBLES[$f['cible_type']]) ?></div>
  <div class="table-responsive"><table class="table table-sm table-bordered mb-0 align-middle">
    <thead class="table-light"><tr><th>Indice</th><?php foreach ($synthese['annees'] as $an): ?><th class="text-center"><?= $an ?></th><?php endforeach; ?><th class="text-center">Tendance</th></tr></thead>
    <tbody>
    <?php foreach ($synthese['lignes'] as $l): $ind = $l['ind']; $prev = null; $trend = ''; ?>
      <tr><td><?= e($ind['nom']) ?> <small class="text-muted"><?= e($ind['unite']) ?></small></td>
        <?php foreach ($synthese['annees'] as $an): $c = $l['val'][$an] ?? null; ?>
          <td class="text-center cell-indice" title="<?= e($c['interpretation'] ?? '') ?>">
            <?php if ($c && $c['valeur'] !== null): ?><?= fnum($c['valeur'], (int) $ind['decimales']) ?> <?= niveau_badge($c['niveau']) ?>
              <?php if ($prev !== null) { $trend = $c['valeur'] > $prev ? 'up' : ($c['valeur'] < $prev ? 'down' : 'eq'); } $prev = (float) $c['valeur']; ?>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td class="text-center"><?= ['up' => '<i class="bi bi-arrow-up-right text-primary"></i>', 'down' => '<i class="bi bi-arrow-down-right text-primary"></i>', 'eq' => '<i class="bi bi-arrow-right"></i>'][$trend] ?? '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <div class="card-footer bg-white small text-muted">Survolez une valeur pour lire son interprétation. Les couleurs indiquent le niveau par rapport aux seuils paramétrés.</div>
</div>
<?php endif; ?>

<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Indice</th><th>Cible</th><th>Année</th><th class="text-end">Valeur</th><th>Niveau</th><th>Interprétation</th><th>Calculé le</th></tr></thead>
  <tbody>
  <?php foreach ($page['rows'] as $r): ?>
    <tr>
      <td><?= e($r['nom']) ?></td>
      <td><?= $r['cible_type'] === 'animal' ? '<a href="' . url('animaux/voir', ['id' => $r['cible_id'], 'onglet' => 'indices']) . '">' . e($r['cible_libelle']) . '</a>' : e($r['cible_libelle']) ?></td>
      <td><?= $r['annee'] ? (int) $r['annee'] : 'Carrière' ?></td>
      <td class="text-end text-nowrap"><?= $r['valeur'] === null ? '—' : fnum($r['valeur'], (int) $r['decimales']) . ' ' . e($r['unite']) ?></td>
      <td><?= niveau_badge($r['niveau']) ?></td>
      <td class="small"><?= e($r['interpretation']) ?>
        <a class="text-muted ms-1" data-bs-toggle="collapse" href="#v<?= $r['id'] ?>" title="Variables utilisées"><i class="bi bi-info-circle"></i></a>
        <div class="collapse" id="v<?= $r['id'] ?>"><code class="formula d-block"><?= e($r['formule']) ?></code>
          <?php foreach ((array) json_decode((string) $r['variables'], true) as $vk => $vv): ?><span class="badge text-bg-light border me-1"><?= e($vk) ?> = <?= e($vv === null ? 'null' : $vv) ?></span><?php endforeach; ?></div>
      </td>
      <td class="small text-muted text-nowrap"><?= fdate($r['calcule_le'], true) ?></td>
    </tr>
  <?php endforeach; if (!$page['rows']): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucun résultat. Lancez un calcul.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
