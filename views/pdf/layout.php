<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($titre ?? 'Document') ?></title>
<style>
  @page { margin: 18mm 12mm 16mm 12mm; size: A4 <?= $orientation === 'landscape' ? 'landscape' : 'portrait' ?>; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; color: #222; }
  .entete { border-bottom: 2px solid #2f6b3a; padding-bottom: 6px; margin-bottom: 10px; }
  .entete table { width: 100%; }
  .entete .org { font-size: 13pt; font-weight: bold; color: #2f6b3a; }
  .entete .meta { text-align: right; font-size: 8pt; color: #666; }
  h1 { font-size: 14pt; margin: 4px 0 8px; }
  h2 { font-size: 11pt; margin: 14px 0 6px; color: #2f6b3a; border-bottom: 1px solid #cfdccf; padding-bottom: 2px; }
  table.data { width: 100%; border-collapse: collapse; }
  table.data th { background: #2f6b3a; color: #fff; font-size: 8pt; text-align: left; padding: 4px; }
  table.data td { border-bottom: 1px solid #ddd; padding: 3px 4px; font-size: 8pt; vertical-align: top; }
  table.data tr:nth-child(even) td { background: #f4f7f3; }
  table.info { width: 100%; border-collapse: collapse; }
  table.info td { padding: 3px 4px; border-bottom: 1px solid #eee; }
  table.info td.l { color: #666; width: 22%; }
  .filtres { font-size: 8pt; color: #555; margin-bottom: 6px; }
  .badge { padding: 1px 5px; border-radius: 3px; font-size: 7.5pt; color: #fff; }
  .b-bon { background: #198754; } .b-moyen { background: #c99400; } .b-faible { background: #dc3545; } .b-na { background: #6c757d; }
  .muted { color: #777; }
  .pied { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #888; }
  .no-print { margin: 10px 0; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>
<?php if ($print_mode): ?>
  <div class="no-print"><button onclick="window.print()">🖨 Imprimer / Enregistrer en PDF</button>
  <small class="muted">(Dompdf n'est pas installé : utilisez « Enregistrer au format PDF » dans la boîte d'impression)</small></div>
<?php endif; ?>
<div class="entete"><table><tr>
  <td><div class="org"><?= e($exploitation) ?></div><div class="muted"><?= e($adresse) ?> <?= $telephone ? '· Tél. ' . e($telephone) : '' ?></div></td>
  <td class="meta">Édité le <?= e($genere_le) ?><br>par <?= e(\App\Core\Auth::user()['nom_complet'] ?? '') ?></td>
</tr></table></div>
<div class="pied"><?= e(config('app.name')) ?> — <?= e($exploitation) ?></div>
<?= $body ?>
<?php if ($print_mode): ?><script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script><?php endif; ?>
</body>
</html>
