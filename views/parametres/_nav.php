<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link <?= ($_GET['r'] ?? '') === 'parametres' ? 'active' : '' ?>" href="<?= url('parametres') ?>"><i class="bi bi-gear"></i> Généraux</a></li>
  <li class="nav-item"><a class="nav-link <?= ($_GET['r'] ?? '') === 'parametres/races' ? 'active' : '' ?>" href="<?= url('parametres/races') ?>"><i class="bi bi-list-nested"></i> Espèces & races</a></li>
  <?php if (can('indices.voir')): ?><li class="nav-item"><a class="nav-link" href="<?= url('indices/formules') ?>"><i class="bi bi-sliders"></i> Formules des indices</a></li><?php endif; ?>
  <?php if (can('systeme.cache')): ?><li class="nav-item"><a class="nav-link <?= ($_GET['r'] ?? '') === 'parametres/systeme' ? 'active' : '' ?>" href="<?= url('parametres/systeme') ?>"><i class="bi bi-cpu"></i> Système & cache</a></li><?php endif; ?>
</ul>
