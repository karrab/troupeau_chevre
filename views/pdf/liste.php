<h1><?= e($titre) ?></h1>
<?php if ($filtres): ?><div class="filtres"><b>Filtres :</b>
  <?php foreach ($filtres as $k => $v): ?><?= e($k) ?> = <?= e($v) ?> ; <?php endforeach; ?></div><?php endif; ?>
<div class="filtres"><?= count($lignes) ?> ligne(s)</div>
<table class="data">
  <thead><tr><?php foreach ($colonnes as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($lignes as $l): ?><tr><?php foreach ($l as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="<?= count($colonnes) ?>">Aucune donnée.</td></tr><?php endif; ?>
  </tbody>
</table>
