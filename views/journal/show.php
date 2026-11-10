<?php
$title = 'Trace #' . $j['id'];
$avant = $j['anciennes_valeurs'] ? json_decode($j['anciennes_valeurs'], true) : [];
$apres = $j['nouvelles_valeurs'] ? json_decode($j['nouvelles_valeurs'], true) : [];
$keys = array_unique(array_merge(array_keys((array) $avant), array_keys((array) $apres)));
$fmt = fn($x) => is_array($x) ? json_encode($x, JSON_UNESCAPED_UNICODE) : (string) ($x ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0"><i class="bi bi-journal-text"></i> <?= e($title) ?></h1>
  <div><button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
  <a class="btn btn-outline-secondary" href="<?= url('journal') ?>"><i class="bi bi-arrow-left"></i> Retour</a></div></div>
<div class="card mb-3"><div class="card-body row g-3 small">
  <?php foreach (['Date' => fdate($j['created_at'], true), 'Utilisateur' => $j['login'], 'Action' => $j['action'], 'Objet' => $j['entite'] . ($j['entite_id'] ? ' #' . $j['entite_id'] : ''),
                  'Adresse IP' => $j['ip'], 'Navigateur' => $j['user_agent'], 'Description' => $j['description']] as $l => $val): ?>
    <div class="col-md-<?= in_array($l, ['Navigateur', 'Description'], true) ? 6 : 3 ?>"><div class="text-muted"><?= $l ?></div><div class="fw-semibold"><?= e($val) ?></div></div>
  <?php endforeach; ?>
</div></div>
<?php if ($keys): ?>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead><tbody>
  <?php foreach ($keys as $k): ?><tr><td><code><?= e($k) ?></code></td><td class="text-danger"><?= e($fmt($avant[$k] ?? null)) ?></td><td class="text-success"><?= e($fmt($apres[$k] ?? null)) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
