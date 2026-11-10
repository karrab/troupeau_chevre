<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\KeysetPaginator;
use App\Core\Ref;
use App\Services\PdfExporter;

final class UtilisateurController extends Controller
{
    public static function passwordError(string $p): ?string
    {
        if (mb_strlen($p) < 8 || !preg_match('/[A-Z]/', $p) || !preg_match('/[a-z]/', $p) || !preg_match('/\d/', $p)) {
            return 'Le mot de passe doit contenir au moins 8 caractères dont une majuscule, une minuscule et un chiffre.';
        }
        return null;
    }

    private function query(): array
    {
        $f = ['q' => input_str('q', 60), 'role_id' => input_int('role_id'), 'actif' => input_str('actif')];
        $w = [];
        $p = [];
        if ($f['q'] !== '') {
            $w[] = '(u.login LIKE :q1 OR u.nom_complet LIKE :q2 OR u.email LIKE :q3)';
            $p['q1'] = $p['q2'] = $p['q3'] = addcslashes($f['q'], '%_\\') . '%';
        }
        if ($f['role_id']) { $w[] = 'u.role_id = :r'; $p['r'] = $f['role_id']; }
        if ($f['actif'] === '1' || $f['actif'] === '0') { $w[] = 'u.actif = :a'; $p['a'] = (int) $f['actif']; }
        return [[
            'select' => 'u.id, u.login, u.nom_complet, u.email, u.actif, u.derniere_connexion, u.created_at, r.libelle role',
            'from' => 'utilisateurs u JOIN roles r ON r.id = u.role_id', 'where' => $w, 'params' => $p,
            'sort' => ['col' => 'u.nom_complet', 'key' => 'nom_complet', 'dir' => 'ASC'], 'id' => ['col' => 'u.id', 'key' => 'id'],
        ], $f];
    }

    public function index(): void
    {
        [$q, $f] = $this->query();
        $q['limit'] = $this->pageSize();
        $q['cursor'] = input_str('cursor', 500) ?: null;
        $this->render('utilisateurs/index', ['page' => KeysetPaginator::paginate($q), 'f' => $f, 'roles' => Ref::roles()]);
    }

    public function exportPdf(): void
    {
        [$q, $f] = $this->query();
        $rows = (function () use ($q) {
            foreach (KeysetPaginator::iterate($q, $this->maxExport()) as $r) {
                yield [$r['login'], $r['nom_complet'], $r['email'], $r['role'], $r['actif'] ? 'Actif' : 'Désactivé', fdate($r['derniere_connexion'], true)];
            }
        })();
        PdfExporter::liste('Utilisateurs', ['Identifiant', 'Nom', 'Email', 'Rôle', 'État', 'Dernière connexion'], $rows, ['Recherche' => $f['q']], 'portrait', input_str('mode') !== 'imprimer');
    }

    public function form(): void
    {
        $id = input_int('id');
        $u = $id ? Database::one('SELECT * FROM utilisateurs WHERE id = ?', [$id]) : null;
        if ($id && !$u) {
            flash('danger', 'Utilisateur introuvable.');
            redirect(url('utilisateurs'));
        }
        $errors = [];
        $v = $u ?? ['actif' => 1, 'role_id' => 4, 'doit_changer_mdp' => 1];
        if ($this->isPost()) {
            $d = [
                'login' => input_str('login', 60), 'nom_complet' => input_str('nom_complet', 150), 'email' => input_str('email', 190) ?: null,
                'role_id' => (int) input_int('role_id', 0), 'actif' => input_int('actif') ? 1 : 0, 'doit_changer_mdp' => input_int('doit_changer_mdp') ? 1 : 0,
            ];
            $pwd = (string) ($_POST['mot_de_passe'] ?? '');
            if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $d['login'])) $errors['login'] = 'Identifiant : 3 à 60 caractères (lettres, chiffres, . _ -).';
            elseif (Database::value('SELECT id FROM utilisateurs WHERE login = ? AND id <> ?', [$d['login'], $id ?? 0])) $errors['login'] = 'Identifiant déjà utilisé.';
            if ($d['nom_complet'] === '') $errors['nom_complet'] = 'Nom obligatoire.';
            if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email invalide.';
            elseif ($d['email'] && Database::value('SELECT id FROM utilisateurs WHERE email = ? AND id <> ?', [$d['email'], $id ?? 0])) $errors['email'] = 'Email déjà utilisé.';
            if (!isset(Ref::roles()[$d['role_id']])) $errors['role_id'] = 'Rôle invalide.';
            if (!$u || $pwd !== '') {
                if ($m = self::passwordError($pwd)) $errors['mot_de_passe'] = $m;
            }
            if ($u && (int) $u['id'] === Auth::id() && (!$d['actif'] || $d['role_id'] !== (int) $u['role_id'])) {
                $errors['actif'] = 'Vous ne pouvez pas désactiver votre propre compte ni changer votre rôle.';
            }
            if (!$errors) {
                if ($pwd !== '') $d['mot_de_passe'] = password_hash($pwd, PASSWORD_DEFAULT);
                if ($u) {
                    [$avant, $apres] = Audit::diff($u, $d);
                    unset($avant['mot_de_passe'], $apres['mot_de_passe']);
                    Database::update('utilisateurs', $d, (int) $u['id']);
                    Audit::log('modification', 'utilisateur', (int) $u['id'], 'Modification de l\'utilisateur ' . $d['login'] . ($pwd !== '' ? ' (mot de passe réinitialisé)' : ''), $avant, $apres);
                } else {
                    $id = Database::insert('utilisateurs', $d);
                    $log = $d;
                    unset($log['mot_de_passe']);
                    Audit::log('creation', 'utilisateur', $id, 'Création de l\'utilisateur ' . $d['login'], null, $log);
                }
                flash('success', 'Utilisateur enregistré.');
                redirect(url('utilisateurs'));
            }
            $v = array_merge($v, $d);
        }
        $this->render('utilisateurs/form', ['u' => $u, 'v' => $v, 'errors' => $errors, 'roles' => Ref::roles()]);
    }

    public function delete(): void
    {
        $id = (int) input_int('id', 0);
        $u = Database::one('SELECT id, login FROM utilisateurs WHERE id = ?', [$id]);
        if (!$u) redirect(url('utilisateurs'));
        if ($id === Auth::id()) {
            flash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            redirect(url('utilisateurs'));
        }
        // Les traces restent attachées au login ; on désactive si l'utilisateur a des données liées
        $lie = Database::value('SELECT 1 FROM animaux WHERE cree_par = ? OR modifie_par = ? LIMIT 1', [$id, $id]);
        if ($lie) {
            Database::run('UPDATE utilisateurs SET actif = 0 WHERE id = ?', [$id]);
            Audit::log('desactivation', 'utilisateur', $id, "Désactivation de {$u['login']} (données liées)");
            flash('warning', "L'utilisateur a saisi des données : il a été désactivé plutôt que supprimé.");
        } else {
            Database::run('DELETE FROM utilisateurs WHERE id = ?', [$id]);
            Audit::log('suppression', 'utilisateur', $id, "Suppression de l'utilisateur {$u['login']}");
            flash('success', 'Utilisateur supprimé.');
        }
        redirect(url('utilisateurs'));
    }
}
