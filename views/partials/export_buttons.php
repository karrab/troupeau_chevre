<?php /** Boutons imprimer / exporter PDF pour une liste. Variable : $route (route PDF) */ ?>
<div class="btn-group d-print-none">
  <a class="btn btn-outline-secondary" target="_blank" href="<?= e(url_with(['r' => $route, 'cursor' => null, 'mode' => 'imprimer'])) ?>" title="Aperçu imprimable (PDF dans le navigateur)"><i class="bi bi-printer"></i> Imprimer</a>
  <a class="btn btn-outline-danger" href="<?= e(url_with(['r' => $route, 'cursor' => null])) ?>" title="Télécharger la liste filtrée en PDF"><i class="bi bi-file-earmark-pdf"></i> Exporter PDF</a>
</div>
