<?php $title = 'Système & cache'; ?>
<h1 class="h4 mb-3"><i class="bi bi-cpu"></i> Système, performances & cache</h1>
<?php include __DIR__ . '/_nav.php'; ?>
<div class="row g-3">
  <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white fw-semibold">Environnement</div><ul class="list-group list-group-flush small">
    <li class="list-group-item d-flex justify-content-between"><span>PHP</span><b><?= e($php) ?></b></li>
    <li class="list-group-item d-flex justify-content-between"><span>MySQL / MariaDB</span><b><?= e($mysql) ?></b></li>
    <li class="list-group-item d-flex justify-content-between"><span>Dompdf (export PDF)</span><?= $dompdf ? '<span class="badge text-bg-success">installé</span>' : '<span class="badge text-bg-warning">absent — mode impression navigateur</span>' ?></li>
    <li class="list-group-item d-flex justify-content-between"><span>OPcache</span><?= $opcache && $opcache['opcache_enabled'] ? '<span class="badge text-bg-success">actif</span>' : '<span class="badge text-bg-danger">inactif</span>' ?></li>
    <li class="list-group-item d-flex justify-content-between"><span>APCu</span><?= $apcu ? '<span class="badge text-bg-success">actif</span>' : '<span class="badge text-bg-danger">inactif (cache de requête)</span>' ?></li>
  </ul></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white fw-semibold">OPcache</div><div class="card-body small">
    <?php if ($opcache): $m = $opcache['memory_usage']; $s = $opcache['opcache_statistics']; ?>
      <div>Mémoire utilisée : <b><?= fnum($m['used_memory'] / 1048576, 1) ?> Mo</b> / libre <?= fnum($m['free_memory'] / 1048576, 1) ?> Mo</div>
      <div>Scripts en cache : <b><?= (int) $s['num_cached_scripts'] ?></b></div>
      <div>Taux de succès : <b><?= fnum($s['opcache_hit_rate'], 2) ?> %</b></div>
      <div>Hits / Miss : <?= number_format($s['hits'], 0, ',', ' ') ?> / <?= number_format($s['misses'], 0, ',', ' ') ?></div>
      <div>JIT : <?= !empty($opcache['jit']['enabled']) ? 'activé' : 'désactivé' ?></div>
    <?php else: ?><p class="text-muted mb-0">OPcache indisponible pour ce SAPI. Activez <code>opcache.enable=1</code> (voir docs/php-performance.ini).</p><?php endif; ?>
  </div><div class="card-footer bg-white"><form method="post"><?= csrf_field() ?><button name="vider" value="opcache" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-repeat"></i> Réinitialiser OPcache</button></form></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white fw-semibold">APCu</div><div class="card-body small">
    <?php if ($apcu): $i = $apcu['info']; $hits = $i['num_hits']; $miss = $i['num_misses']; ?>
      <div>Entrées : <b><?= (int) $i['num_entries'] ?></b></div>
      <div>Mémoire : <b><?= fnum($i['mem_size'] / 1048576, 2) ?> Mo</b> / libre <?= fnum($apcu['sma']['avail_mem'] / 1048576, 1) ?> Mo</div>
      <div>Taux de succès : <b><?= $hits + $miss ? fnum($hits / ($hits + $miss) * 100, 1) : '0' ?> %</b> (<?= $hits ?> hits / <?= $miss ?> miss)</div>
      <div class="text-muted mt-2">Utilisé pour : permissions par rôle, paramètres, référentiels, formules compilées, compteurs de pagination, tableau de bord, limitation des tentatives de connexion.</div>
    <?php else: ?><p class="text-muted mb-0">APCu non chargé : <code>pecl install apcu</code> puis <code>extension=apcu</code>.</p><?php endif; ?>
  </div><div class="card-footer bg-white"><form method="post"><?= csrf_field() ?><button name="vider" value="apcu" class="btn btn-sm btn-outline-warning"><i class="bi bi-trash"></i> Vider APCu</button></form></div></div></div>
  <div class="col-12"><div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">Tables InnoDB
    <form method="post"><?= csrf_field() ?><button name="vider" value="analyze" class="btn btn-sm btn-outline-primary"><i class="bi bi-bar-chart"></i> ANALYZE TABLE (statistiques d'index)</button></form></div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Table</th><th>Moteur</th><th class="text-end">Lignes (approx.)</th><th class="text-end">Données (Mo)</th><th class="text-end">Index (Mo)</th></tr></thead><tbody>
      <?php foreach ($tables as $t): ?><tr><td><?= e($t['TABLE_NAME']) ?></td><td><?= e($t['ENGINE']) ?></td><td class="text-end"><?= number_format((int) $t['TABLE_ROWS'], 0, ',', ' ') ?></td><td class="text-end"><?= e($t['data_mb']) ?></td><td class="text-end"><?= e($t['index_mb']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
</div>
