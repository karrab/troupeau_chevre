<?php
$title = ($row ? 'Modifier — ' : 'Nouveau — ') . $t['singulier'];
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$cls = fn($k) => isset($errors[$k]) ? ' is-invalid' : '';
$retour = input_str('retour');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><i class="bi <?= $t['icone'] ?>"></i> <?= e($title) ?></h1>
  <a class="btn btn-outline-secondary" href="<?= $retour === 'fiche' && $animal ? url('animaux/voir', ['id' => $animal['id'], 'onglet' => $t['code']]) : url('evenements', ['type' => $t['code']]) ?>"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<form method="post" class="card" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="retour" value="<?= e($retour) ?>">
  <div class="card-body"><div class="row g-3">
    <div class="col-md-4"><label class="form-label">Animal *<?= $t['sexe'] ? ' <small class="text-muted">(' . ($t['sexe'] === 'F' ? 'femelle' : 'mâle') . ')</small>' : '' ?></label>
      <input class="form-control<?= $cls('animal_id') ?>" data-autocomplete data-target="#animal_id" <?= $t['sexe'] ? 'data-sexe="' . $t['sexe'] . '"' : '' ?>
             value="<?= e($animal['numero_identification'] ?? '') ?>" placeholder="Tapez le n° de l'animal" autocomplete="off" <?= $row ? 'readonly' : '' ?>>
      <input type="hidden" name="animal_id" id="animal_id" value="<?= e($v['animal_id'] ?? '') ?>"><?= $err('animal_id') ?></div>
    <?php foreach ($t['fields'] as $k => $fd): $val = $v[$k] ?? ''; $req = !empty($fd['required']); ?>
      <div class="col-md-4">
        <label class="form-label" for="f_<?= $k ?>"><?= e($fd['label']) ?><?= $req ? ' *' : '' ?></label>
        <?php if ($fd['type'] === 'select'): ?>
          <select class="form-select<?= $cls($k) ?>" name="<?= $k ?>" id="f_<?= $k ?>" <?= $req ? 'required' : '' ?>>
            <?php if (!$req): ?><option value="">—</option><?php endif; ?>
            <?php foreach ($fd['options'] as $ok => $ol): ?><option value="<?= e($ok) ?>" <?= (string) $val === (string) $ok ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?>
          </select>
        <?php elseif ($fd['type'] === 'animal'): ?>
          <input class="form-control<?= $cls($k) ?>" data-autocomplete data-target="#f_<?= $k ?>" data-sexe="<?= e($fd['sexe'] ?? '') ?>" value="<?= e($labels[$k] ?? '') ?>" placeholder="N° de l'animal" autocomplete="off">
          <input type="hidden" name="<?= $k ?>" id="f_<?= $k ?>" value="<?= e($val) ?>">
        <?php else: ?>
          <input class="form-control<?= $cls($k) ?>" id="f_<?= $k ?>" name="<?= $k ?>" value="<?= e($val) ?>" <?= $req ? 'required' : '' ?>
            type="<?= $fd['type'] === 'date' ? 'date' : (in_array($fd['type'], ['int', 'decimal'], true) ? 'number' : 'text') ?>"
            <?= $fd['type'] === 'decimal' ? 'step="0.01"' : '' ?> <?= isset($fd['min']) ? 'min="' . $fd['min'] . '"' : '' ?> <?= isset($fd['max']) && $fd['type'] !== 'text' ? 'max="' . $fd['max'] . '"' : '' ?>
            <?= $fd['type'] === 'text' ? 'maxlength="' . ($fd['max'] ?? 255) . '"' : '' ?>>
        <?php endif; ?>
        <?php if (!empty($fd['help'])): ?><div class="form-text"><?= e($fd['help']) ?></div><?php endif; ?>
        <?= $err($k) ?>
      </div>
    <?php endforeach; ?>
  </div></div>
  <div class="card-footer bg-white d-flex justify-content-end gap-2">
    <?php if (!$row): ?><button class="btn btn-outline-success" name="encore" value="1"><i class="bi bi-plus-lg"></i> Enregistrer et saisir un autre</button><?php endif; ?>
    <button class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer</button>
  </div>
</form>
