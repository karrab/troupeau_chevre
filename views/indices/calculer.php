<?php $title = 'Calcul des indices'; $an = (int) date('Y'); ?>
<h1 class="h4 mb-3"><i class="bi bi-calculator"></i> Calculer et enregistrer les indices</h1>
<div class="row g-3">
  <div class="col-lg-6">
    <form method="post" class="card h-100"><?= csrf_field() ?><input type="hidden" name="portee" value="annee">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar3"></i> Indices par année</div>
      <div class="card-body">
        <p class="small text-muted">Fertilité, prolificité, mortalité, productivité numérique… calculés pour chaque année et chaque niveau choisi.</p>
        <div class="row g-2 mb-3">
          <div class="col"><label class="form-label">De l'année</label><select class="form-select" name="annee_de"><?php foreach ($annees as $a): ?><option <?= $a === $an - 2 ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?></select></div>
          <div class="col"><label class="form-label">À l'année</label><select class="form-select" name="annee_a"><?php foreach ($annees as $a): ?><option <?= $a === $an ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?></select></div>
        </div>
        <label class="form-label">Niveaux d'agrégation</label>
        <?php foreach (['troupeau' => 'Troupeau entier', 'espece' => 'Par espèce (caprin / ovin)', 'race' => 'Par race'] as $k => $l): ?>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="niveaux[]" value="<?= $k ?>" id="n_<?= $k ?>" <?= $k !== 'race' ? 'checked' : '' ?>><label class="form-check-label" for="n_<?= $k ?>"><?= $l ?></label></div>
        <?php endforeach; ?>
      </div>
      <div class="card-footer bg-white text-end"><button class="btn btn-success"><i class="bi bi-play-fill"></i> Lancer le calcul</button></div>
    </form>
  </div>
  <div class="col-lg-6">
    <form method="post" class="card h-100"><?= csrf_field() ?><input type="hidden" name="portee" value="animal">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-tag"></i> Indices par animal</div>
      <div class="card-body">
        <p class="small text-muted">GMQ, prolificité individuelle, intervalle entre mises bas, production laitière… Calcul par lots de 500 animaux.</p>
        <div class="mb-3"><label class="form-label">Période</label><select class="form-select" name="annee_animal">
          <option value="0">Carrière complète</option><?php foreach ($annees as $a): ?><option value="<?= $a ?>"><?= $a ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Espèce</label><select class="form-select" name="espece_id"><option value="">Toutes</option>
          <?php foreach ($especes as $es): ?><option value="<?= $es['id'] ?>"><?= e($es['libelle']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Un seul animal (optionnel)</label>
          <input class="form-control" data-autocomplete data-target="#calc_animal" placeholder="N° de l'animal" autocomplete="off"><input type="hidden" name="animal_id" id="calc_animal"></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="tous" value="1" id="tous"><label class="form-check-label" for="tous">Inclure les animaux sortis (vendus, morts, réformés)</label></div>
      </div>
      <div class="card-footer bg-white text-end"><button class="btn btn-success"><i class="bi bi-play-fill"></i> Lancer le calcul</button></div>
    </form>
  </div>
</div>
<div class="alert alert-info mt-3 small mb-0"><i class="bi bi-info-circle"></i> Chaque résultat est enregistré avec la formule et les valeurs des variables utilisées : un nouveau calcul met à jour le résultat existant (même indice, même cible, même année). Les formules et seuils se modifient dans <a href="<?= url('indices/formules') ?>">Paramétrage des formules</a>.</div>
