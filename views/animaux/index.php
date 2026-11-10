<?php
use App\Controllers\AnimalController as AC;
$title = 'Animaux';
$advanced = $f['texte'] || $f['race_id'] || $f['categorie_id'] || $f['origine'] || $f['naissance_du'] || $f['naissance_au'] || $f['age_min'] !== null || $f['age_max'] !== null || $f['mere'] || $f['pere'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-tags"></i> Animaux <?= $f['corbeille'] ? '<span class="badge text-bg-dark">Corbeille</span>' : '' ?></h1>
  <div class="d-flex gap-2">
    <?php if (can('animaux.exporter')) { $route = 'animaux/pdf'; include __DIR__ . '/../partials/export_buttons.php'; } ?>
    <?php if (can('animaux.creer')): ?><a href="<?= url('animaux/creer') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Nouvel animal</a><?php endif; ?>
  </div>
</div>

<form class="card mb-3 d-print-none" method="get" action="<?= url() ?>">
  <input type="hidden" name="r" value="animaux">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-3"><input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="N° ou nom (commence par…)"></div>
      <div class="col-6 col-md-2"><select class="form-select" name="espece_id" id="f_espece"><option value="">Toutes espèces</option>
        <?php foreach ($especes as $es): ?><option value="<?= $es['id'] ?>" <?= $f['espece_id'] == $es['id'] ? 'selected' : '' ?>><?= e($es['libelle']) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-2"><select class="form-select" name="sexe"><option value="">Tous sexes</option>
        <option value="F" <?= $f['sexe'] === 'F' ? 'selected' : '' ?>>Femelles</option><option value="M" <?= $f['sexe'] === 'M' ? 'selected' : '' ?>>Mâles</option></select></div>
      <div class="col-6 col-md-2"><select class="form-select" name="statut">
        <option value="tous" <?= $f['statut'] === 'tous' ? 'selected' : '' ?>>Tous statuts</option>
        <?php foreach (AC::STATUTS as $k => $l): ?><option value="<?= $k ?>" <?= $f['statut'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-fill"><i class="bi bi-search"></i> Rechercher</button>
        <a class="btn btn-outline-secondary" href="<?= url('animaux') ?>" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>
      </div>
    </div>
    <details class="mt-2 filter-card" <?= $advanced ? 'open' : '' ?>>
      <summary class="small text-primary"><i class="bi bi-sliders"></i> Recherche avancée</summary>
      <div class="row g-2 mt-1">
        <div class="col-md-3"><label class="form-label small">Texte (nom, observations)</label><input class="form-control form-control-sm" name="texte" value="<?= e($f['texte']) ?>" placeholder="recherche plein texte"></div>
        <div class="col-6 col-md-3"><label class="form-label small">Race</label><select class="form-select form-select-sm" name="race_id" data-depends="#f_espece"><option value="">Toutes</option>
          <?php foreach ($races as $ra): ?><option value="<?= $ra['id'] ?>" data-espece="<?= $ra['espece_id'] ?>" <?= $f['race_id'] == $ra['id'] ? 'selected' : '' ?>><?= e($ra['libelle']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label small">Catégorie</label><select class="form-select form-select-sm" name="categorie_id" data-depends="#f_espece"><option value="">Toutes</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" data-espece="<?= $c['espece_id'] ?>" <?= $f['categorie_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['libelle']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label small">Origine</label><select class="form-select form-select-sm" name="origine"><option value="">Toutes</option>
          <?php foreach (AC::ORIGINES as $k => $l): ?><option value="<?= $k ?>" <?= $f['origine'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Né(e) du</label><input type="date" class="form-control form-control-sm" name="naissance_du" value="<?= e($f['naissance_du']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small">au</label><input type="date" class="form-control form-control-sm" name="naissance_au" value="<?= e($f['naissance_au']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small">Âge min (mois)</label><input type="number" min="0" class="form-control form-control-sm" name="age_min" value="<?= e($f['age_min']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small">Âge max (mois)</label><input type="number" min="0" class="form-control form-control-sm" name="age_max" value="<?= e($f['age_max']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small">N° mère</label><input class="form-control form-control-sm" name="mere" value="<?= e($f['mere']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small">N° père</label><input class="form-control form-control-sm" name="pere" value="<?= e($f['pere']) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small">Trier par</label><div class="input-group input-group-sm">
          <select class="form-select" name="tri"><?php foreach ($sorts as $k => $s): ?><option value="<?= $k ?>" <?= ($_GET['tri'] ?? 'id') === $k ? 'selected' : '' ?>><?= e($s[2]) ?></option><?php endforeach; ?></select>
          <select class="form-select" name="dir"><option value="desc">↓ Décroissant</option><option value="asc" <?= ($_GET['dir'] ?? '') === 'asc' ? 'selected' : '' ?>>↑ Croissant</option></select></div></div>
        <?php if (can('animaux.supprimer')): ?>
        <div class="col-6 col-md-3 d-flex align-items-end"><div class="form-check">
          <input class="form-check-input" type="checkbox" name="corbeille" value="1" id="corb" <?= $f['corbeille'] ? 'checked' : '' ?>>
          <label class="form-check-label small" for="corb"><i class="bi bi-trash"></i> Afficher la corbeille</label></div></div>
        <?php endif; ?>
      </div>
    </details>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr>
        <th>N° ident.</th><th>Nom</th><th>Espèce / race</th><th>Catégorie</th><th>Sexe</th><th>Naissance</th><th>Âge</th><th>Mère</th><th class="text-end">Poids</th><th>Statut</th><th class="text-end d-print-none">Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($page['rows'] as $a): ?>
        <tr>
          <td class="fw-semibold"><a href="<?= url('animaux/voir', ['id' => $a['id']]) ?>"><?= e($a['numero_identification']) ?></a></td>
          <td><?= e($a['nom']) ?></td>
          <td><?= e($a['espece']) ?><?= $a['race'] ? ' <small class="text-muted">' . e($a['race']) . '</small>' : '' ?></td>
          <td><?= e($a['categorie']) ?></td>
          <td><?= $a['sexe'] === 'F' ? '<i class="bi bi-gender-female text-danger" title="Femelle"></i>' : '<i class="bi bi-gender-male text-primary" title="Mâle"></i>' ?></td>
          <td><?= fdate($a['date_naissance']) ?></td>
          <td class="small"><?= e(age_label($a['date_naissance'], $a['date_sortie'])) ?></td>
          <td class="small"><?= e($a['mere_numero']) ?></td>
          <td class="text-end"><?= $a['dernier_poids'] !== null ? fnum($a['dernier_poids'], 1) . ' kg' : '—' ?></td>
          <td><?= statut_badge($a['statut']) ?></td>
          <td class="text-end text-nowrap d-print-none">
            <a class="btn btn-sm btn-outline-primary" href="<?= url('animaux/voir', ['id' => $a['id']]) ?>" title="Fiche"><i class="bi bi-eye"></i></a>
            <?php if ($a['supprime']): ?>
              <?php if (can('animaux.supprimer')): ?>
              <form method="post" action="<?= url('animaux/restaurer') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button class="btn btn-sm btn-outline-success" title="Restaurer"><i class="bi bi-arrow-counterclockwise"></i></button></form>
              <?php endif; ?>
            <?php else: ?>
              <?php if (can('animaux.modifier')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('animaux/modifier', ['id' => $a['id']]) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
              <?php if (can('animaux.exporter')): ?><a class="btn btn-sm btn-outline-danger" href="<?= url('animaux/fiche-pdf', ['id' => $a['id']]) ?>" title="Fiche PDF"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?>
              <?php if (can('animaux.supprimer')) { $action = url('animaux/supprimer'); $id = $a['id']; $message = 'Supprimer l\'animal ' . $a['numero_identification'] . ' ? (il sera placé dans la corbeille)'; include __DIR__ . '/../partials/delete_button.php'; } ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$page['rows']): ?>
        <tr><td colspan="11" class="text-center text-muted py-4">Aucun animal ne correspond aux critères.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
