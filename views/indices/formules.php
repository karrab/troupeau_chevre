<?php $title = 'Formules des indices'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-sliders"></i> Paramétrage des indices et formules</h1>
  <?php if (can('indices.formules')): ?><div class="btn-group">
    <a class="btn btn-success" href="<?= url('indices/formule', ['portee' => 'annee']) ?>"><i class="bi bi-plus-lg"></i> Indice annuel</a>
    <a class="btn btn-outline-success" href="<?= url('indices/formule', ['portee' => 'animal']) ?>"><i class="bi bi-plus-lg"></i> Indice par animal</a></div><?php endif; ?>
</div>
<?php foreach (['annee' => 'Indices par année (troupeau / espèce / race)', 'animal' => 'Indices par animal'] as $portee => $titre): ?>
<div class="card mb-3"><div class="card-header bg-white fw-semibold"><?= $titre ?></div>
  <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
    <thead class="table-light"><tr><th>Code</th><th>Nom</th><th>Formule</th><th>Unité</th><th>Sens</th><th>Seuils (bas / haut)</th><th>État</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($indicateurs as $i): if ($i['portee'] !== $portee) continue; ?>
      <tr class="<?= $i['actif'] ? '' : 'text-muted' ?>">
        <td><code><?= e($i['code']) ?></code></td>
        <td><?= e($i['nom']) ?><?= $i['sexe_cible'] ? ' <span class="badge text-bg-light border">' . ($i['sexe_cible'] === 'F' ? 'femelles' : 'mâles') . '</span>' : '' ?></td>
        <td><code class="formula"><?= e($i['formule']) ?></code></td>
        <td><?= e($i['unite']) ?></td>
        <td><?= ['haut' => '↑ plus haut = mieux', 'bas' => '↓ plus bas = mieux', 'intervalle' => '↔ dans l\'intervalle'][$i['sens']] ?></td>
        <td class="text-nowrap"><?= $i['seuil_bas'] !== null ? fnum($i['seuil_bas'], 2) : '—' ?> / <?= $i['seuil_haut'] !== null ? fnum($i['seuil_haut'], 2) : '—' ?></td>
        <td><?= $i['actif'] ? '<span class="badge text-bg-success">Actif</span>' : '<span class="badge text-bg-secondary">Inactif</span>' ?></td>
        <td class="text-end text-nowrap"><?php if (can('indices.formules')): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= url('indices/formule', ['id' => $i['id']]) ?>" title="Modifier la formule"><i class="bi bi-pencil"></i></a>
          <?php $action = url('indices/supprimer'); $id = $i['id']; $message = 'Supprimer l\'indice ' . $i['nom'] . ' et tous ses résultats enregistrés ?'; include __DIR__ . '/../partials/delete_button.php'; ?>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
<?php endforeach; ?>
<div class="card"><div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history"></i> Historique des modifications de formules</div>
  <div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Date</th><th>Indice</th><th>Ancienne formule</th><th>Nouvelle formule</th><th>Par</th></tr></thead><tbody>
    <?php foreach ($historique as $h): ?>
      <tr><td class="small text-nowrap"><?= fdate($h['modifie_le'], true) ?></td><td><?= e($h['nom']) ?></td>
        <td><code class="formula text-danger"><?= e($h['ancienne_formule'] ?? '(création)') ?></code></td><td><code class="formula text-success"><?= e($h['nouvelle_formule']) ?></code></td><td class="small"><?= e($h['nom_complet']) ?></td></tr>
    <?php endforeach; if (!$historique): ?><tr><td colspan="5" class="text-muted text-center">Aucune modification.</td></tr><?php endif; ?>
    </tbody></table></div></div>
