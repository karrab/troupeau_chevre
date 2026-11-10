<?php
/** @var array $page résultat de KeysetPaginator::paginate */
$n = $page['limit'];
?>
<nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2 d-print-none" aria-label="Pagination">
  <div class="small text-muted">
    <?= count($page['rows']) ?> ligne(s) affichée(s)<?= isset($total) ? ' sur ' . number_format($total, 0, ',', ' ') : '' ?>
    · pagination keyset
  </div>
  <div class="d-flex align-items-center gap-2">
    <select class="form-select form-select-sm w-auto" onchange="location.href=this.value" aria-label="Lignes par page">
      <?php foreach ([10, 25, 50, 100] as $opt): ?>
        <option value="<?= e(url_with(['n' => $opt, 'cursor' => null])) ?>" <?= $opt === $n ? 'selected' : '' ?>><?= $opt ?> / page</option>
      <?php endforeach; ?>
    </select>
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item"><a class="page-link" href="<?= e(url_with(['cursor' => null])) ?>" title="Début"><i class="bi bi-chevron-double-left"></i></a></li>
      <li class="page-item <?= $page['has_prev'] ? '' : 'disabled' ?>"><a class="page-link" href="<?= $page['has_prev'] ? e(url_with(['cursor' => $page['prev']])) : '#' ?>"><i class="bi bi-chevron-left"></i> Précédent</a></li>
      <li class="page-item <?= $page['has_next'] ? '' : 'disabled' ?>"><a class="page-link" href="<?= $page['has_next'] ? e(url_with(['cursor' => $page['next']])) : '#' ?>">Suivant <i class="bi bi-chevron-right"></i></a></li>
    </ul>
  </div>
</nav>
