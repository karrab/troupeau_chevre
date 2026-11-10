<?php
use App\Controllers\AnimalController as AC;
$title = $a ? 'Modifier ' . $a['numero_identification'] : 'Nouvel animal';
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$cls = fn($k) => isset($errors[$k]) ? ' is-invalid' : '';
$val = fn($k) => e($v[$k] ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-<?= $a ? 'pencil' : 'plus-circle' ?>"></i> <?= e($title) ?></h1>
  <a href="<?= $a ? url('animaux/voir', ['id' => $a['id']]) : url('animaux') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<form method="post" class="card" novalidate>
  <?= csrf_field() ?>
  <div class="card-body">
    <h2 class="h6 text-success">Identification</h2>
    <div class="row g-3 mb-3">
      <div class="col-md-3"><label class="form-label">N° d'identification *</label>
        <input class="form-control<?= $cls('numero_identification') ?>" name="numero_identification" required maxlength="40" value="<?= $val('numero_identification') ?>" placeholder="Boucle / puce"><?= $err('numero_identification') ?></div>
      <div class="col-md-3"><label class="form-label">Nom</label><input class="form-control" name="nom" maxlength="80" value="<?= $val('nom') ?>"></div>
      <div class="col-md-2"><label class="form-label">Espèce *</label>
        <select class="form-select<?= $cls('espece_id') ?>" name="espece_id" id="espece_id" required>
          <?php foreach ($especes as $es): ?><option value="<?= $es['id'] ?>" <?= ($v['espece_id'] ?? '') == $es['id'] ? 'selected' : '' ?>><?= e($es['libelle']) ?></option><?php endforeach; ?>
        </select><?= $err('espece_id') ?></div>
      <div class="col-md-2"><label class="form-label">Race</label>
        <select class="form-select<?= $cls('race_id') ?>" name="race_id" data-depends="#espece_id"><option value="">—</option>
          <?php foreach ($races as $ra): if (!$ra['actif'] && ($v['race_id'] ?? null) != $ra['id']) continue; ?>
            <option value="<?= $ra['id'] ?>" data-espece="<?= $ra['espece_id'] ?>" <?= ($v['race_id'] ?? '') == $ra['id'] ? 'selected' : '' ?>><?= e($ra['libelle']) ?></option><?php endforeach; ?>
        </select><?= $err('race_id') ?></div>
      <div class="col-md-2"><label class="form-label">Couleur / robe</label><input class="form-control" name="couleur" maxlength="60" value="<?= $val('couleur') ?>"></div>
      <div class="col-md-2"><label class="form-label">Sexe *</label>
        <select class="form-select<?= $cls('sexe') ?>" name="sexe" id="sexe" required>
          <option value="F" <?= ($v['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Femelle</option>
          <option value="M" <?= ($v['sexe'] ?? '') === 'M' ? 'selected' : '' ?>>Mâle</option>
        </select><?= $err('sexe') ?></div>
      <div class="col-md-3"><label class="form-label">Catégorie</label>
        <select class="form-select<?= $cls('categorie_id') ?>" name="categorie_id" data-depends="#espece_id,#sexe">
          <option value="">Automatique (selon âge et sexe)</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" data-espece="<?= $c['espece_id'] ?>" data-sexe="<?= $c['sexe'] ?>" <?= ($v['categorie_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['libelle']) ?></option><?php endforeach; ?>
        </select><?= $err('categorie_id') ?></div>
      <div class="col-md-2 d-flex align-items-end"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="castre" value="1" id="castre" <?= !empty($v['castre']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="castre">Castré</label></div></div>
    </div>

    <h2 class="h6 text-success">Naissance & filiation</h2>
    <div class="row g-3 mb-3">
      <div class="col-md-3"><label class="form-label">Date de naissance *</label>
        <input type="date" class="form-control<?= $cls('date_naissance') ?>" name="date_naissance" required max="<?= date('Y-m-d') ?>" value="<?= $val('date_naissance') ?>"><?= $err('date_naissance') ?>
        <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="date_naissance_estimee" value="1" id="dne" <?= !empty($v['date_naissance_estimee']) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="dne">Date estimée</label></div></div>
      <div class="col-md-2"><label class="form-label">Poids naissance (kg)</label>
        <input type="number" step="0.01" min="0" max="20" class="form-control<?= $cls('poids_naissance') ?>" name="poids_naissance" value="<?= $val('poids_naissance') ?>"><?= $err('poids_naissance') ?></div>
      <div class="col-md-3"><label class="form-label">Mère</label>
        <input class="form-control<?= $cls('mere_id') ?>" data-autocomplete data-target="#mere_id" data-sexe="F" data-espece="#espece_id" value="<?= $val('mere_numero') ?>" placeholder="Tapez le n° de la mère" autocomplete="off">
        <input type="hidden" name="mere_id" id="mere_id" value="<?= $val('mere_id') ?>"><?= $err('mere_id') ?></div>
      <div class="col-md-3"><label class="form-label">Père</label>
        <input class="form-control<?= $cls('pere_id') ?>" data-autocomplete data-target="#pere_id" data-sexe="M" data-espece="#espece_id" value="<?= $val('pere_numero') ?>" placeholder="Tapez le n° du père" autocomplete="off">
        <input type="hidden" name="pere_id" id="pere_id" value="<?= $val('pere_id') ?>"><?= $err('pere_id') ?></div>
    </div>

    <h2 class="h6 text-success">Entrée</h2>
    <div class="row g-3 mb-3">
      <div class="col-md-3"><label class="form-label">Origine *</label>
        <select class="form-select<?= $cls('origine') ?>" name="origine"><?php foreach (AC::ORIGINES as $k => $l): ?><option value="<?= $k ?>" <?= ($v['origine'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('origine') ?></div>
      <div class="col-md-3"><label class="form-label">Date d'entrée</label>
        <input type="date" class="form-control<?= $cls('date_entree') ?>" name="date_entree" value="<?= $val('date_entree') ?>"><div class="form-text">Par défaut : date de naissance.</div><?= $err('date_entree') ?></div>
      <div class="col-md-3"><label class="form-label">Prix d'achat</label>
        <input type="number" step="0.01" min="0" class="form-control<?= $cls('prix_achat') ?>" name="prix_achat" value="<?= $val('prix_achat') ?>"><?= $err('prix_achat') ?></div>
    </div>

    <h2 class="h6 text-success">Statut & sortie</h2>
    <div class="row g-3 mb-3">
      <div class="col-md-3"><label class="form-label">Statut *</label>
        <select class="form-select<?= $cls('statut') ?>" name="statut" id="statut"><?php foreach (AC::STATUTS as $k => $l): ?><option value="<?= $k ?>" <?= ($v['statut'] ?? 'actif') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><?= $err('statut') ?></div>
      <div class="col-md-3 sortie"><label class="form-label">Date de sortie</label>
        <input type="date" class="form-control<?= $cls('date_sortie') ?>" name="date_sortie" value="<?= $val('date_sortie') ?>"><?= $err('date_sortie') ?></div>
      <div class="col-md-3 sortie"><label class="form-label">Motif / cause</label><input class="form-control" name="motif_sortie" maxlength="255" value="<?= $val('motif_sortie') ?>"></div>
      <div class="col-md-3 sortie"><label class="form-label">Prix de vente</label>
        <input type="number" step="0.01" min="0" class="form-control<?= $cls('prix_vente') ?>" name="prix_vente" value="<?= $val('prix_vente') ?>"><?= $err('prix_vente') ?></div>
    </div>
    <label class="form-label">Observations</label>
    <textarea class="form-control" name="observations" rows="3"><?= $val('observations') ?></textarea>
  </div>
  <div class="card-footer bg-white text-end">
    <a href="<?= url('animaux') ?>" class="btn btn-outline-secondary">Annuler</a>
    <button class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer</button>
  </div>
</form>
<script>
(function () {
  const st = document.getElementById('statut');
  const toggle = () => document.querySelectorAll('.sortie').forEach(el => el.classList.toggle('d-none', st.value === 'actif'));
  st.addEventListener('change', toggle); toggle();
})();
</script>
