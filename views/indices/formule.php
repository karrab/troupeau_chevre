<?php
$title = $ind ? 'Modifier ' . $ind['nom'] : 'Nouvel indice';
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$cls = fn($k) => isset($errors[$k]) ? ' is-invalid' : '';
$val = fn($k) => e($v[$k] ?? '');
$portee = $v['portee'] ?? 'annee';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-sliders"></i> <?= e($title) ?></h1>
  <a class="btn btn-outline-secondary" href="<?= url('indices/formules') ?>"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<div class="row g-3">
<div class="col-xl-8">
<form method="post" class="card" id="formIndice"><?= csrf_field() ?>
  <div class="card-body"><div class="row g-3">
    <div class="col-md-3"><label class="form-label">Code *</label><input class="form-control<?= $cls('code') ?>" name="code" value="<?= $val('code') ?>" required><?= $err('code') ?></div>
    <div class="col-md-5"><label class="form-label">Nom *</label><input class="form-control<?= $cls('nom') ?>" name="nom" value="<?= $val('nom') ?>" required><?= $err('nom') ?></div>
    <div class="col-md-4"><label class="form-label">Portée *</label><select class="form-select" name="portee" id="portee">
      <option value="annee" <?= $portee === 'annee' ? 'selected' : '' ?>>Par année (troupeau / espèce / race)</option>
      <option value="animal" <?= $portee === 'animal' ? 'selected' : '' ?>>Par animal</option></select></div>
    <div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= $val('description') ?>"></div>
    <div class="col-12"><label class="form-label">Formule *</label>
      <textarea class="form-control formula<?= $cls('formule') ?>" name="formule" id="formule" rows="3" required><?= $val('formule') ?></textarea><?= $err('formule') ?>
      <div class="form-text">Opérateurs : <code>+ - * / % ^ ( )</code>, comparaisons <code>&lt; &lt;= &gt; &gt;= == !=</code>, logiques <code>&amp;&amp; ||</code>. Cliquez une variable ou une fonction pour l'insérer. Une variable manquante ou une division par zéro rend l'indice « non calculable ».</div></div>
    <div class="col-md-3"><label class="form-label">Unité</label><input class="form-control" name="unite" value="<?= $val('unite') ?>" placeholder="%, g/j, L…"></div>
    <div class="col-md-2"><label class="form-label">Décimales</label><input type="number" min="0" max="4" class="form-control" name="decimales" value="<?= $val('decimales') ?>"></div>
    <div class="col-md-3 sexe-cible"><label class="form-label">Sexe ciblé</label><select class="form-select" name="sexe_cible">
      <option value="">Tous</option><option value="F" <?= ($v['sexe_cible'] ?? '') === 'F' ? 'selected' : '' ?>>Femelles</option><option value="M" <?= ($v['sexe_cible'] ?? '') === 'M' ? 'selected' : '' ?>>Mâles</option></select></div>
    <div class="col-md-2"><label class="form-label">Ordre</label><input type="number" class="form-control" name="ordre" value="<?= $val('ordre') ?>"></div>
    <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="actif" value="1" id="actif" <?= !empty($v['actif']) ? 'checked' : '' ?>><label class="form-check-label" for="actif">Actif</label></div></div>
  </div>
  <hr><h2 class="h6">Interprétation</h2>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Sens</label><select class="form-select" name="sens">
      <?php foreach (['haut' => 'Plus haut = meilleur', 'bas' => 'Plus bas = meilleur', 'intervalle' => 'Idéal dans l\'intervalle [bas ; haut]'] as $k => $l): ?><option value="<?= $k ?>" <?= ($v['sens'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Seuil bas</label><input class="form-control<?= $cls('seuil_bas') ?>" name="seuil_bas" value="<?= $val('seuil_bas') ?>"><?= $err('seuil_bas') ?></div>
    <div class="col-md-4"><label class="form-label">Seuil haut</label><input class="form-control" name="seuil_haut" value="<?= $val('seuil_haut') ?>"></div>
    <div class="col-12 small text-muted">« Plus haut = meilleur » : valeur ≥ seuil haut → <b>bon</b>, ≥ seuil bas → <b>moyen</b>, sinon <b>faible</b>. Inversement pour « plus bas ». Variables de texte : <code>{valeur}</code> <code>{unite}</code> <code>{nom}</code>.</div>
    <div class="col-md-4"><label class="form-label text-success">Texte si bon</label><textarea class="form-control" name="texte_bon" rows="2"><?= $val('texte_bon') ?></textarea></div>
    <div class="col-md-4"><label class="form-label text-warning">Texte si moyen</label><textarea class="form-control" name="texte_moyen" rows="2"><?= $val('texte_moyen') ?></textarea></div>
    <div class="col-md-4"><label class="form-label text-danger">Texte si faible</label><textarea class="form-control" name="texte_faible" rows="2"><?= $val('texte_faible') ?></textarea></div>
  </div></div>
  <div class="card-footer bg-white text-end"><button class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer</button></div>
</form>

<div class="card mt-3"><div class="card-header bg-white fw-semibold"><i class="bi bi-bug"></i> Tester la formule sur des données réelles</div>
  <div class="card-body"><div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small">Année</label><input type="number" class="form-control" id="t_annee" value="<?= date('Y') ?>"><div class="form-text test-animal">0 = carrière</div></div>
    <div class="col-md-3 test-annee"><label class="form-label small">Cible</label><select class="form-select" id="t_cible"><option value="troupeau:0">Troupeau</option>
      <?php foreach ($especes as $es): ?><option value="espece:<?= $es['id'] ?>"><?= e($es['libelle']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3 test-animal"><label class="form-label small">N° animal</label><input class="form-control" id="t_animal" data-autocomplete autocomplete="off"></div>
    <div class="col-md-3"><button type="button" class="btn btn-outline-primary w-100" id="btnTest"><i class="bi bi-play"></i> Tester</button></div>
  </div><div id="testResult" class="mt-3"></div></div></div>
</div>

<div class="col-xl-4">
  <div class="card mb-3"><div class="card-header bg-white fw-semibold">Variables disponibles</div>
    <div class="card-body small" style="max-height: 480px; overflow-y: auto">
      <div class="vars-annee"><?php foreach ($varsAnnee as $k => $d): ?><div class="mb-1"><span class="badge text-bg-light border var-chip" data-insert="<?= e($k) ?>"><?= e($k) ?></span> <span class="text-muted"><?= e($d) ?></span></div><?php endforeach; ?></div>
      <div class="vars-animal"><?php foreach ($varsAnimal as $k => $d): ?><div class="mb-1"><span class="badge text-bg-light border var-chip" data-insert="<?= e($k) ?>"><?= e($k) ?></span> <span class="text-muted"><?= e($d) ?></span></div><?php endforeach; ?></div>
    </div></div>
  <div class="card"><div class="card-header bg-white fw-semibold">Fonctions</div>
    <div class="card-body small"><?php foreach ($fonctions as $k => $d): ?><div class="mb-1"><span class="badge text-bg-primary var-chip" data-insert="<?= e($k) ?>()"><?= e($k) ?></span> <span class="text-muted"><?= e($d) ?></span></div><?php endforeach; ?></div></div>
</div>
</div>
<script>
(function () {
  const portee = document.getElementById('portee'), f = document.getElementById('formule');
  const sync = () => {
    const an = portee.value === 'animal';
    document.querySelectorAll('.vars-animal,.test-animal,.sexe-cible').forEach(e => e.classList.toggle('d-none', !an));
    document.querySelectorAll('.vars-annee,.test-annee').forEach(e => e.classList.toggle('d-none', an));
  };
  portee.addEventListener('change', sync); sync();
  document.querySelectorAll('[data-insert]').forEach(chip => chip.addEventListener('click', () => {
    const t = chip.dataset.insert, s = f.selectionStart ?? f.value.length;
    f.value = f.value.slice(0, s) + t + f.value.slice(f.selectionEnd ?? s);
    f.focus(); const pos = s + (t.endsWith('()') ? t.length - 1 : t.length); f.setSelectionRange(pos, pos);
  }));
  document.getElementById('btnTest').addEventListener('click', () => {
    const fd = new FormData(document.getElementById('formIndice'));
    fd.set('annee', document.getElementById('t_annee').value);
    const [ct, cid] = document.getElementById('t_cible').value.split(':');
    fd.set('cible_type', ct); fd.set('cible_id', cid);
    fd.set('animal', document.getElementById('t_animal').value);
    const out = document.getElementById('testResult');
    fetch(window.APP.baseUrl + '?r=indices/tester', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
      if (!d.ok) { out.innerHTML = '<div class="alert alert-danger mb-0"></div>'; out.firstChild.textContent = d.erreur; return; }
      const cls = { bon: 'success', moyen: 'warning', faible: 'danger', na: 'secondary' }[d.niveau];
      const vars = Object.entries(d.variables).map(([k, v]) => '<span class="badge text-bg-light border me-1">' + k + ' = ' + (v === null ? 'null' : v) + '</span>').join('');
      out.innerHTML = '<div class="alert alert-' + cls + ' mb-2"><b>Résultat : ' + (d.valeur === null ? 'non calculable' : (+d.valeur).toLocaleString('fr-FR', { maximumFractionDigits: 4 })) + '</b><br><span class="interp"></span></div>' + vars;
      out.querySelector('.interp').textContent = d.interpretation;
    });
  });
})();
</script>
