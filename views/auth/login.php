<div class="container d-flex align-items-center justify-content-center min-vh-100">
  <div class="card login-card w-100 shadow">
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <i class="bi bi-flower1 display-5 text-success"></i>
        <h1 class="h4 mt-2"><?= e(config('app.name')) ?></h1>
        <p class="text-muted small mb-0">Gestion de troupeau caprin & ovin</p>
      </div>
      <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= url('auth/login') ?>" autocomplete="on">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="login">Identifiant</label>
          <input class="form-control" id="login" name="login" required autofocus value="<?= e($_POST['login'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label" for="password">Mot de passe</label>
          <input class="form-control" type="password" id="password" name="password" required>
        </div>
        <button class="btn btn-success w-100"><i class="bi bi-box-arrow-in-right"></i> Se connecter</button>
      </form>
    </div>
  </div>
</div>
