<?php $title = 'Journal des traces'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-journal-text"></i> Journal des traces</h1>
  <?php $route = 'journal/pdf'; include __DIR__ . '/../partials/export_buttons.php'; ?>
</div>
<form class="card mb-3 d-print-none" method="get" action="<?= url() ?>"><div class="card-body"><div class="row g-2 align-items-end">
  <input type="hidden" name="r" value="journal">
  <div class="col-6 col-md-2"><label class="form-label small">Du</label><input type="date" class="form-control" name="du" value="<?= e($f['du']) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small">Au</label><input type="date" class="form-control" name="au" value="<?= e($f['au']) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small">Utilisateur</label><select class="form-select" name="utilisateur_id"><option value="">Tous</option>
    <?php foreach ($utilisateurs as $u): ?><option value="<?= $u['id'] ?>" <?= $f['utilisateur_id'] == $u['id'] ? 'selected' : '' ?>><?= e($u['nom_complet']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small">Action</label><select class="form-select" name="action"><option value="">Toutes</option>
    <?php foreach ($actions as $a): ?><option <?= $f['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small">Objet</label><select class="form-select" name="entite"><option value="">Tous</option>
    <?php foreach ($entites as $a): ?><option <?= $f['entite'] === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small">Texte</label><input class="form-control" name="q" value="<?= e($f['q']) ?>"></div>
  <div class="col-12 col-md-2 ms-auto"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrer</button></div>
</div></div></form>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead class="table-light"><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Description</th><th>IP</th><th></th></tr></thead><tbody>
  <?php
  $col = ['creation' => 'success', 'modification' => 'primary', 'suppression' => 'danger', 'connexion' => 'info', 'connexion_echec' => 'warning', 'export_pdf' => 'secondary'];
  foreach ($page['rows'] as $j): ?>
    <tr><td class="text-nowrap small"><?= fdate($j['created_at'], true) ?></td><td><?= e($j['login']) ?></td>
      <td><span class="badge text-bg-<?= $col[$j['action']] ?? 'light border' ?>"><?= e($j['action']) ?></span></td>
      <td class="small"><?= e($j['entite']) ?><?= $j['entite_id'] ? ' #' . (int) $j['entite_id'] : '' ?></td>
      <td class="small"><?= e($j['description']) ?></td><td class="small text-muted"><?= e($j['ip']) ?></td>
      <td><a class="btn btn-sm btn-outline-secondary" href="<?= url('journal/voir', ['id' => $j['id']]) ?>"><i class="bi bi-eye"></i></a></td></tr>
  <?php endforeach; if (!$page['rows']): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucune trace.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
