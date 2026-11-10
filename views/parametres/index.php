<?php $title = 'Paramètres'; $noms = ['general' => 'Exploitation', 'affichage' => 'Affichage & exports', 'zootechnie' => 'Zootechnie (utilisés par les indices)', 'systeme' => 'Système']; ?>
<h1 class="h4 mb-3"><i class="bi bi-gear"></i> Paramètres</h1>
<?php include __DIR__ . '/_nav.php'; ?>
<form method="post"><?= csrf_field() ?>
  <div class="row g-3">
  <?php foreach ($groupes as $g => $params): ?>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header bg-white fw-semibold"><?= e($noms[$g] ?? $g) ?></div><div class="card-body">
      <?php foreach ($params as $p): ?>
        <div class="mb-3"><label class="form-label" for="p_<?= e($p['cle']) ?>"><?= e($p['libelle']) ?></label>
          <?php if ($p['type'] === 'booleen'): ?>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="p_<?= e($p['cle']) ?>" value="1" <?= $p['valeur'] ? 'checked' : '' ?>></div>
          <?php else: ?>
            <input class="form-control" id="p_<?= e($p['cle']) ?>" name="p_<?= e($p['cle']) ?>" value="<?= e($p['valeur']) ?>" <?= $p['type'] === 'entier' ? 'type="number" min="0"' : '' ?>>
          <?php endif; ?>
          <?php if ($p['aide']): ?><div class="form-text"><?= e($p['aide']) ?></div><?php endif; ?></div>
      <?php endforeach; ?>
    </div></div></div>
  <?php endforeach; ?>
  </div>
  <div class="text-end mt-3"><button class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer</button></div>
</form>
