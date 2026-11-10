<?php
use App\Controllers\AnimalController as AC;
use App\Controllers\EvenementController as EC;
use App\Services\EvenementTypes;
use App\Services\Historique;
$title = 'Fiche ' . $a['numero_identification'];
$charts = true;
$types = EvenementTypes::all();
$supprime = (int) $a['supprime'] === 1;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h1 class="h4 mb-1"><i class="bi bi-tag"></i> <?= e($a['numero_identification']) ?> <?= $a['nom'] ? '<span class="text-muted">« ' . e($a['nom']) . ' »</span>' : '' ?></h1>
    <div><?= statut_badge($a['statut']) ?> <span class="badge text-bg-light border"><?= e($a['espece']) ?></span> <span class="badge text-bg-light border"><?= e($a['categorie']) ?></span>
      <?= $supprime ? '<span class="badge text-bg-dark"><i class="bi bi-trash"></i> Dans la corbeille</span>' : '' ?></div>
  </div>
  <div class="d-flex flex-wrap gap-2 d-print-none">
    <a class="btn btn-outline-secondary" href="<?= url('animaux') ?>"><i class="bi bi-arrow-left"></i> Liste</a>
    <?php if (!$supprime && can('animaux.modifier')): ?><a class="btn btn-primary" href="<?= url('animaux/modifier', ['id' => $a['id']]) ?>"><i class="bi bi-pencil"></i> Modifier</a><?php endif; ?>
    <?php if (can('animaux.exporter')): ?>
      <a class="btn btn-outline-secondary" target="_blank" href="<?= url('animaux/fiche-pdf', ['id' => $a['id'], 'mode' => 'imprimer']) ?>"><i class="bi bi-printer"></i> Imprimer</a>
      <a class="btn btn-outline-danger" href="<?= url('animaux/fiche-pdf', ['id' => $a['id']]) ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <?php endif; ?>
    <?php if (!$supprime && can('indices.calculer')): ?>
      <form method="post" action="<?= url('indices/calculer') ?>" class="d-inline"><?= csrf_field() ?>
        <input type="hidden" name="portee" value="animal"><input type="hidden" name="animal_id" value="<?= $a['id'] ?>"><input type="hidden" name="annee_animal" value="0">
        <button class="btn btn-outline-success"><i class="bi bi-calculator"></i> Calculer les indices</button></form>
    <?php endif; ?>
    <?php if (can('animaux.supprimer')): ?>
      <?php if ($supprime): ?>
        <form method="post" action="<?= url('animaux/restaurer') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>">
          <button class="btn btn-success"><i class="bi bi-arrow-counterclockwise"></i> Restaurer</button></form>
      <?php else: $action = url('animaux/supprimer'); $id = $a['id']; $message = 'Supprimer l\'animal ' . $a['numero_identification'] . ' ?'; $label = 'Supprimer'; $class = 'btn btn-outline-danger'; include __DIR__ . '/../partials/delete_button.php'; unset($label, $class); endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8"><div class="card h-100"><div class="card-body">
    <div class="row g-3 small">
      <?php foreach ([
        ['Espèce', e($a['espece'])], ['Race', e($a['race'] ?? '—')], ['Catégorie', e($a['categorie'])], ['Sexe', sexe_label($a['sexe']) . ($a['castre'] ? ' (castré)' : '')],
        ['Naissance', fdate($a['date_naissance']) . ($a['date_naissance_estimee'] ? ' <span class="text-muted">(estimée)</span>' : '')],
        ['Âge', e(age_label($a['date_naissance'], $a['date_sortie']))], ['Poids de naissance', $a['poids_naissance'] !== null ? fnum($a['poids_naissance'], 2) . ' kg' : '—'],
        ['Couleur / robe', e($a['couleur'] ?? '—')],
        ['Mère', $a['mere_id'] ? '<a href="' . url('animaux/voir', ['id' => $a['mere_id']]) . '">' . e($a['mere_numero'] . ' ' . $a['mere_nom']) . '</a>' : '—'],
        ['Père', $a['pere_id'] ? '<a href="' . url('animaux/voir', ['id' => $a['pere_id']]) . '">' . e($a['pere_numero'] . ' ' . $a['pere_nom']) . '</a>' : '—'],
        ['Origine', e(AC::ORIGINES[$a['origine']] ?? $a['origine'])], ['Entrée', fdate($a['date_entree'])],
        ['Prix d\'achat', $a['prix_achat'] !== null ? fnum($a['prix_achat']) : '—'],
        ['Sortie', $a['date_sortie'] ? fdate($a['date_sortie']) . ' — ' . e($a['motif_sortie']) : '—'],
        ['Prix de vente', $a['prix_vente'] !== null ? fnum($a['prix_vente']) : '—'],
        ['Saisi par', e($a['cree_par_nom'] ?? '—') . ' <span class="text-muted">' . fdate($a['created_at'], true) . '</span>'],
      ] as [$l, $val]): ?>
        <div class="col-6 col-md-3"><div class="text-muted"><?= $l ?></div><div class="fw-semibold"><?= $val ?></div></div>
      <?php endforeach; ?>
    </div>
    <?php if ($a['observations']): ?><hr><div class="small"><span class="text-muted">Observations :</span> <?= nl2br(e($a['observations'])) ?></div><?php endif; ?>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white fw-semibold">Courbe de poids</div>
    <div class="card-body"><?php if ($ev['pesee'] || $a['poids_naissance'] !== null): ?><canvas id="chartPoids" height="180"></canvas><?php else: ?><p class="text-muted small mb-0">Aucune pesée.</p><?php endif; ?></div></div></div>
</div>

<ul class="nav nav-tabs d-print-none" role="tablist">
  <?php foreach ($types as $code => $t): if ($t['sexe'] && $t['sexe'] !== $a['sexe']) continue; ?>
    <li class="nav-item"><button class="nav-link <?= $code === 'pesee' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $code ?>" type="button">
      <i class="bi <?= $t['icone'] ?>"></i> <?= e($t['titre']) ?> <span class="badge text-bg-secondary"><?= count($ev[$code]) ?></span></button></li>
  <?php endforeach; ?>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-descendants" type="button"><i class="bi bi-diagram-3"></i> Descendants <span class="badge text-bg-secondary"><?= count($descendants) ?></span></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-indices" type="button"><i class="bi bi-graph-up-arrow"></i> Indices</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-historique" type="button"><i class="bi bi-clock-history"></i> Historique</button></li>
</ul>
<div class="tab-content card border-top-0 rounded-top-0">
  <?php foreach ($types as $code => $t): if ($t['sexe'] && $t['sexe'] !== $a['sexe']) continue; ?>
  <div class="tab-pane fade <?= $code === 'pesee' ? 'show active' : '' ?> p-3" id="tab-<?= $code ?>">
    <div class="d-flex justify-content-between mb-2"><h2 class="h6 mb-0 d-none d-print-block"><?= e($t['titre']) ?></h2><span></span>
      <?php if (!$supprime && can('evenements.creer')): ?>
        <a class="btn btn-sm btn-success d-print-none" href="<?= url('evenements/creer', ['type' => $code, 'animal_id' => $a['id'], 'retour' => 'fiche']) ?>"><i class="bi bi-plus-lg"></i> <?= e($t['singulier']) ?></a>
      <?php endif; ?></div>
    <div class="table-responsive"><table class="table table-sm table-striped mb-0">
      <thead><tr><?php foreach ($t['fields'] as $fd): ?><th><?= e($fd['label']) ?></th><?php endforeach; ?><th class="d-print-none"></th></tr></thead>
      <tbody>
        <?php foreach ($ev[$code] as $row): ?>
          <tr><?php foreach ($t['fields'] as $k => $fd): ?><td><?= e(EC::display($fd, $row, $k)) ?></td><?php endforeach; ?>
            <td class="text-end text-nowrap d-print-none">
              <?php if (can('evenements.modifier')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('evenements/modifier', ['type' => $code, 'id' => $row['id'], 'retour' => 'fiche']) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
              <?php if (can('evenements.supprimer')) { $action = url('evenements/supprimer'); $id = $row['id']; $extra = ['type' => $code]; $message = 'Supprimer cet enregistrement (' . $t['singulier'] . ') ?'; include __DIR__ . '/../partials/delete_button.php'; unset($extra); } ?>
            </td></tr>
        <?php endforeach; if (!$ev[$code]): ?><tr><td colspan="<?= count($t['fields']) + 1 ?>" class="text-muted text-center">Aucun enregistrement.</td></tr><?php endif; ?>
      </tbody></table></div>
  </div>
  <?php endforeach; ?>

  <div class="tab-pane fade p-3" id="tab-descendants">
    <?php if (can('animaux.creer') && !$supprime): ?>
      <a class="btn btn-sm btn-success mb-2 d-print-none" href="<?= url('animaux/creer', [$a['sexe'] === 'F' ? 'mere_id' : 'pere_id' => $a['id'], 'espece_id' => $a['espece_id']]) ?>"><i class="bi bi-plus-lg"></i> Enregistrer un descendant</a>
    <?php endif; ?>
    <table class="table table-sm table-striped mb-0"><thead><tr><th>N°</th><th>Nom</th><th>Sexe</th><th>Naissance</th><th>Statut</th></tr></thead><tbody>
      <?php foreach ($descendants as $d): ?>
        <tr><td><a href="<?= url('animaux/voir', ['id' => $d['id']]) ?>"><?= e($d['numero_identification']) ?></a></td><td><?= e($d['nom']) ?></td><td><?= $d['sexe'] ?></td><td><?= fdate($d['date_naissance']) ?></td><td><?= statut_badge($d['statut']) ?></td></tr>
      <?php endforeach; if (!$descendants): ?><tr><td colspan="5" class="text-muted text-center">Aucun descendant enregistré.</td></tr><?php endif; ?>
    </tbody></table>
  </div>

  <div class="tab-pane fade p-3" id="tab-indices">
    <?php if (!$indices): ?><p class="text-muted mb-0">Aucun indice calculé pour cet animal.</p><?php else: ?>
    <table class="table table-sm mb-0"><thead><tr><th>Période</th><th>Indice</th><th class="text-end">Valeur</th><th>Niveau</th><th>Interprétation</th><th>Calculé le</th></tr></thead><tbody>
      <?php foreach ($indices as $i): ?>
        <tr><td><?= $i['annee'] ? (int) $i['annee'] : 'Carrière' ?></td><td><?= e($i['nom']) ?></td>
          <td class="text-end text-nowrap"><?= $i['valeur'] === null ? '—' : fnum($i['valeur'], (int) $i['decimales']) . ' ' . e($i['unite']) ?></td>
          <td><?= niveau_badge($i['niveau']) ?></td><td class="small"><?= e($i['interpretation']) ?></td><td class="small text-muted"><?= fdate($i['calcule_le'], true) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </div>

  <div class="tab-pane fade p-3" id="tab-historique">
    <div class="timeline">
      <?php foreach ($historique as $h): [$lib, $ic, $col] = Historique::TYPES[$h['type']] ?? [$h['type'], 'bi-dot', 'secondary']; ?>
        <div class="timeline-item tl-<?= $col ?>">
          <div class="small text-muted"><?= fdate($h['date_evenement']) ?> · <i class="bi <?= $ic ?>"></i> <?= e($lib) ?> · <?= e($h['utilisateur'] ?? 'système') ?> <span class="opacity-75">(saisi le <?= fdate($h['created_at'], true) ?>)</span></div>
          <div><?= e($h['titre']) ?></div>
          <?php $det = $h['details'] ? json_decode($h['details'], true) : null; if (!empty($det['apres'])): ?>
            <div class="small"><?php foreach ($det['apres'] as $k => $nv): ?>
              <span class="me-2"><span class="text-muted"><?= e(AC::LABELS[$k] ?? $k) ?> :</span> <del class="text-danger"><?= e(is_scalar($det['avant'][$k] ?? null) ? $det['avant'][$k] : '') ?></del> → <span class="text-success"><?= e(is_scalar($nv) ? $nv : '') ?></span></span>
            <?php endforeach; ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; if (!$historique): ?><p class="text-muted">Aucun historique.</p><?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const el = document.getElementById('chartPoids');
  if (!el) return;
  const pts = <?= json_encode(array_merge(
      $a['poids_naissance'] !== null ? [['x' => $a['date_naissance'], 'y' => (float) $a['poids_naissance']]] : [],
      array_map(fn($p) => ['x' => $p['date_pesee'], 'y' => (float) $p['poids_kg']], array_reverse($ev['pesee']))
  )) ?>;
  new Chart(el, { type: 'line', data: { labels: pts.map(p => p.x.split('-').reverse().join('/')), datasets: [{ label: 'Poids (kg)', data: pts.map(p => p.y), borderColor: '#2f6b3a', backgroundColor: 'rgba(47,107,58,.15)', fill: true, tension: .25 }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });
});
</script>
