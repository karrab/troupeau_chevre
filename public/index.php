<?php
declare(strict_types=1);

/**
 * Contrôleur frontal : toutes les requêtes passent par ce fichier.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

// Première utilisation : redirection vers l'installateur web
if (!\App\Services\Installer::isLocked()) {
    redirect(app_base() . '/install.php');
}

$routes = require ROOT_PATH . '/app/routes.php';
$route  = is_string($_GET['r'] ?? null) ? trim($_GET['r'], '/') : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if (!isset($routes[$route])) {
        http_response_code(404);
        View::render('partials/erreur', ['titre' => 'Page introuvable', 'message' => "La page demandée n'existe pas."]);
        exit;
    }
    [$class, $action, $perm, $methods] = $routes[$route];

    if (!in_array($method, $methods, true)) {
        http_response_code(405);
        exit('Méthode non autorisée');
    }
    if ($perm !== 'public') {
        if (!Auth::check()) {
            if ($method === 'GET') {
                $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? null;
            }
            redirect(url('auth/login'));
        }
        // Changement de mot de passe obligatoire
        if ((int) Auth::user()['doit_changer_mdp'] === 1 && !in_array($route, ['auth/profil', 'auth/logout'], true)) {
            flash('warning', 'Veuillez définir un nouveau mot de passe avant de continuer.');
            redirect(url('auth/profil'));
        }
        if ($perm !== null && !Auth::can($perm)) {
            http_response_code(403);
            View::render('partials/erreur', ['titre' => 'Accès refusé', 'message' => "Vous n'avez pas la permission requise ($perm)."]);
            exit;
        }
    }
    if ($method === 'POST' && !Csrf::verify()) {
        http_response_code(419);
        View::render('partials/erreur', ['titre' => 'Session expirée', 'message' => 'Jeton de sécurité invalide. Rechargez la page et réessayez.']);
        exit;
    }

    (new $class())->$action();
} catch (Throwable $e) {
    error_log('[' . date('c') . '] ' . $e);
    http_response_code(500);
    $msg = config('app.env') === 'dev' ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'Une erreur interne est survenue. Elle a été journalisée.';
    try {
        View::render('partials/erreur', ['titre' => 'Erreur', 'message' => $msg]);
    } catch (Throwable) {
        echo e($msg);
    }
}
