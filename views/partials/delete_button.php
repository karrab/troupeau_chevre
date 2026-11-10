<?php
/** Bouton de suppression sécurisé (POST + CSRF + confirmation). Variables : $action, $id, $message, $extra (array), $label, $class */
?>
<form method="post" action="<?= e($action) ?>" class="d-inline" data-confirm="<?= e($message ?? 'Confirmer la suppression ?') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <?php foreach (($extra ?? []) as $k => $val): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($val) ?>"><?php endforeach; ?>
  <button type="submit" class="<?= e($class ?? 'btn btn-sm btn-outline-danger') ?>" title="Supprimer"><i class="bi bi-trash"></i><?= isset($label) ? ' ' . e($label) : '' ?></button>
</form>
