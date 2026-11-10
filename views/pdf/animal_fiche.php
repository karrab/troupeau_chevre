<?php
use App\Controllers\AnimalController as AC;
use App\Controllers\EvenementController as EC;
use App\Services\EvenementTypes;
use App\Services\Historique;
$titre = 'Fiche animal ' . $a['numero_identification'];
?>
<h1>Fiche animal : <?= e($a['numero_identification']) ?><?= $a['nom'] ? ' « ' . e($a['nom']) . ' »' : '' ?></h1>
<table class="info">
  <tr><td class="l">Espèce</td><td><?= e($a['espece']) ?></td><td class="l">Race</td><td><?= e($a['race'] ?? '—') ?></td></tr>
  <tr><td class="l">Catégorie</td><td><?= e($a['categorie']) ?></td><td class="l">Sexe</td><td><?= $a['sexe'] === 'F' ? 'Femelle' : 'Mâle' ?><?= $a['castre'] ? ' (castré)' : '' ?></td></tr>
  <tr><td class="l">Date de naissance</td><td><?= fdate($a['date_naissance']) ?><?= $a['date_naissance_estimee'] ? ' (estimée)' : '' ?></td><td class="l">Âge</td><td><?= e(age_label($a['date_naissance'], $a['date_sortie'])) ?></td></tr>
  <tr><td class="l">Poids de naissance</td><td><?= $a['poids_naissance'] !== null ? fnum($a['poids_naissance']) . ' kg' : '—' ?></td><td class="l">Couleur / robe</td><td><?= e($a['couleur'] ?? '—') ?></td></tr>
  <tr><td class="l">Mère</td><td><?= e(trim(($a['mere_numero'] ?? '') . ' ' . ($a['mere_nom'] ?? ''))) ?: '—' ?></td><td class="l">Père</td><td><?= e(trim(($a['pere_numero'] ?? '') . ' ' . ($a['pere_nom'] ?? ''))) ?: '—' ?></td></tr>
  <tr><td class="l">Origine</td><td><?= e(AC::ORIGINES[$a['origine']] ?? '') ?></td><td class="l">Date d'entrée</td><td><?= fdate($a['date_entree']) ?></td></tr>
  <tr><td class="l">Statut</td><td><?= e(AC::STATUTS[$a['statut']] ?? '') ?></td><td class="l">Sortie</td><td><?= $a['date_sortie'] ? fdate($a['date_sortie']) . ' — ' . e($a['motif_sortie']) : '—' ?></td></tr>
  <?php if ($a['observations']): ?><tr><td class="l">Observations</td><td colspan="3"><?= nl2br(e($a['observations'])) ?></td></tr><?php endif; ?>
</table>

<?php foreach (EvenementTypes::all() as $code => $t): if (!$ev[$code]) continue; ?>
  <h2><?= e($t['titre']) ?> (<?= count($ev[$code]) ?>)</h2>
  <table class="data"><thead><tr><?php foreach ($t['fields'] as $fd): ?><th><?= e($fd['label']) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach ($ev[$code] as $row): ?><tr><?php foreach ($t['fields'] as $k => $fd): ?><td><?= e(EC::display($fd, $row, $k)) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table>
<?php endforeach; ?>

<?php if ($descendants): ?>
  <h2>Descendants (<?= count($descendants) ?>)</h2>
  <table class="data"><thead><tr><th>N°</th><th>Nom</th><th>Sexe</th><th>Naissance</th><th>Statut</th></tr></thead><tbody>
    <?php foreach ($descendants as $d): ?><tr><td><?= e($d['numero_identification']) ?></td><td><?= e($d['nom']) ?></td><td><?= $d['sexe'] ?></td><td><?= fdate($d['date_naissance']) ?></td><td><?= e(AC::STATUTS[$d['statut']] ?? '') ?></td></tr><?php endforeach; ?>
  </tbody></table>
<?php endif; ?>

<?php if ($indices): ?>
  <h2>Indices</h2>
  <table class="data"><thead><tr><th>Période</th><th>Indice</th><th>Valeur</th><th>Niveau</th><th>Interprétation</th></tr></thead><tbody>
    <?php foreach ($indices as $i): ?><tr><td><?= $i['annee'] ?: 'Carrière' ?></td><td><?= e($i['nom']) ?></td>
      <td><?= $i['valeur'] === null ? '—' : fnum($i['valeur'], (int) $i['decimales']) . ' ' . e($i['unite']) ?></td>
      <td><span class="badge b-<?= e($i['niveau']) ?>"><?= ['bon' => 'Bon', 'moyen' => 'Moyen', 'faible' => 'Faible', 'na' => 'N/C'][$i['niveau']] ?></span></td>
      <td><?= e($i['interpretation']) ?></td></tr><?php endforeach; ?>
  </tbody></table>
<?php endif; ?>

<?php if ($historique): ?>
  <h2>Historique</h2>
  <table class="data"><thead><tr><th>Date</th><th>Type</th><th>Événement</th><th>Par</th></tr></thead><tbody>
    <?php foreach (array_slice($historique, 0, 100) as $h): ?><tr><td><?= fdate($h['date_evenement']) ?></td><td><?= e(Historique::TYPES[$h['type']][0] ?? $h['type']) ?></td><td><?= e($h['titre']) ?></td><td><?= e($h['utilisateur'] ?? '') ?></td></tr><?php endforeach; ?>
  </tbody></table>
<?php endif; ?>
