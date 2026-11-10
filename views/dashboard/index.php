<?php $title = 'Tableau de bord'; $charts = true; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-speedometer"></i> Tableau de bord</h1>
  <?php if (can('animaux.creer')): ?><a href="<?= url('animaux/creer') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Nouvel animal</a><?php endif; ?>
</div>
<div class="row g-3 mb-3">
  <?php foreach ([
      ['Animaux actifs', $totaux['total'] ?? 0, 'bi-tags', 'success'],
      ['Caprins', $totaux['caprins'] ?? 0, 'bi-flower1', 'primary'],
      ['Ovins', $totaux['ovins'] ?? 0, 'bi-cloud', 'info'],
      ['Femelles / Mâles', (int) ($totaux['femelles'] ?? 0) . ' / ' . (int) ($totaux['males'] ?? 0), 'bi-gender-ambiguous', 'warning'],
      ['Nés vivants ' . date('Y'), $mouvements['naissances'] ?? 0, 'bi-stars', 'success'],
      ['Morts / Ventes ' . date('Y'), (int) ($mouvements['morts'] ?? 0) . ' / ' . (int) ($mouvements['ventes'] ?? 0), 'bi-arrow-left-right', 'danger'],
  ] as [$l, $val, $ic, $col]): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="text-muted small"><i class="bi <?= $ic ?> text-<?= $col ?>"></i> <?= e($l) ?></div>
      <div class="display-6"><?= e((string) $val) ?></div>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card h-100"><div class="card-header bg-white fw-semibold">Naissances des 12 derniers mois</div>
      <div class="card-body"><canvas id="chartNaissances" height="110"></canvas></div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-header bg-white fw-semibold">Effectif par catégorie</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($parCategorie as $c): ?>
          <li class="list-group-item d-flex justify-content-between"><span><?= e($c['categorie']) ?> <small class="text-muted"><?= e($c['espece']) ?></small></span><span class="badge text-bg-success rounded-pill"><?= (int) $c['n'] ?></span></li>
        <?php endforeach; if (!$parCategorie): ?><li class="list-group-item text-muted">Aucun animal.</li><?php endif; ?>
      </ul></div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-heart"></i> Mises bas prévues (30 jours)</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($mbPrevues as $m): ?>
          <li class="list-group-item d-flex justify-content-between">
            <a href="<?= url('animaux/voir', ['id' => $m['animal_id']]) ?>"><?= e($m['numero_identification']) ?> <?= e($m['nom']) ?></a>
            <span class="<?= $m['date_prevue_mise_bas'] < date('Y-m-d') ? 'text-danger fw-semibold' : '' ?>"><?= fdate($m['date_prevue_mise_bas']) ?></span>
          </li>
        <?php endforeach; if (!$mbPrevues): ?><li class="list-group-item text-muted">Aucune mise bas prévue.</li><?php endif; ?>
      </ul></div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header bg-white fw-semibold d-flex justify-content-between">
      <span><i class="bi bi-graph-up-arrow"></i> Derniers indices du troupeau <?= $indices ? '(' . (int) $indices[0]['annee'] . ')' : '' ?></span>
      <?php if (can('indices.voir')): ?><a href="<?= url('indices') ?>" class="small">Tout voir</a><?php endif; ?></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($indices as $i): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center" title="<?= e($i['interpretation']) ?>">
            <span><?= e($i['nom']) ?></span>
            <span><?= $i['valeur'] === null ? '—' : fnum($i['valeur'], (int) $i['decimales']) . ' ' . e($i['unite']) ?> <?= niveau_badge($i['niveau']) ?></span>
          </li>
        <?php endforeach; if (!$indices): ?><li class="list-group-item text-muted">Aucun indice calculé. <?php if (can('indices.calculer')): ?><a href="<?= url('indices/calculer') ?>">Lancer un calcul</a><?php endif; ?></li><?php endif; ?>
      </ul></div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const data = <?= json_encode($evolution) ?>;
  new Chart(document.getElementById('chartNaissances'), {
    type: 'bar',
    data: { labels: data.map(d => d.mois), datasets: [
      { label: 'Nés', data: data.map(d => d.nes), backgroundColor: '#9fc5a8' },
      { label: 'Nés vivants', data: data.map(d => d.vivants), backgroundColor: '#2f6b3a' } ] },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
  });
});
</script>
