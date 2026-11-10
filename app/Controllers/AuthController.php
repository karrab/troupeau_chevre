<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

final class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            redirect(url());
        }
        $error = null;
        if ($this->isPost()) {
            [$ok, $error] = Auth::attempt(input_str('login', 60), (string) ($_POST['password'] ?? ''));
            if ($ok) {
                $to = $_SESSION['intended'] ?? url();
                unset($_SESSION['intended']);
                redirect(is_string($to) && str_starts_with($to, '/') ? $to : url());
            }
        }
        View::render('auth/login', ['error' => $error], 'layout/blank');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect(url('auth/login'));
    }

    public function profil(): void
    {
        $u = Auth::user();
        $errors = [];
        if ($this->isPost()) {
            $nom = input_str('nom_complet', 150);
            $email = input_str('email', 190);
            $actuel = (string) ($_POST['mdp_actuel'] ?? '');
            $nouveau = (string) ($_POST['mdp_nouveau'] ?? '');
            $confirm = (string) ($_POST['mdp_confirmation'] ?? '');
            $hash = Database::value('SELECT mot_de_passe FROM utilisateurs WHERE id = ?', [$u['id']]);

            if ($nom === '') $errors[] = 'Le nom est obligatoire.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
            if ($email !== '' && Database::value('SELECT id FROM utilisateurs WHERE email = ? AND id <> ?', [$email, $u['id']])) {
                $errors[] = 'Cet email est déjà utilisé.';
            }
            $changePwd = $nouveau !== '' || (int) $u['doit_changer_mdp'] === 1;
            if ($changePwd) {
                if (!password_verify($actuel, (string) $hash)) $errors[] = 'Mot de passe actuel incorrect.';
                if (($m = UtilisateurController::passwordError($nouveau)) !== null) $errors[] = $m;
                if ($nouveau !== $confirm) $errors[] = 'La confirmation ne correspond pas.';
                if ($nouveau !== '' && password_verify($nouveau, (string) $hash)) $errors[] = "Le nouveau mot de passe doit être différent de l'ancien.";
            }
            if (!$errors) {
                $data = ['nom_complet' => $nom, 'email' => $email ?: null];
                if ($changePwd) {
                    $data['mot_de_passe'] = password_hash($nouveau, PASSWORD_DEFAULT);
                    $data['doit_changer_mdp'] = 0;
                }
                Database::update('utilisateurs', $data, (int) $u['id']);
                Audit::log('modification', 'utilisateur', (int) $u['id'], 'Mise à jour du profil' . ($changePwd ? ' et du mot de passe' : ''));
                Auth::refresh();
                flash('success', 'Profil mis à jour.');
                redirect(url('auth/profil'));
            }
        }
        $this->render('auth/profil', ['u' => $u, 'errors' => $errors]);
    }
}
