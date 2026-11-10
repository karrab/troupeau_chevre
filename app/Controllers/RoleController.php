<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Database;

final class RoleController extends Controller
{
    /** Matrice rôles × permissions (modifiable en une fois). */
    public function index(): void
    {
        $roles = Database::all('SELECT r.*, (SELECT COUNT(*) FROM utilisateurs u WHERE u.role_id = r.id) nb_utilisateurs FROM roles r ORDER BY r.id');
        $perms = Database::all('SELECT * FROM permissions ORDER BY module, id');
        $assign = [];
        foreach (Database::all('SELECT role_id, permission_id FROM role_permissions') as $rp) {
            $assign[$rp['role_id']][$rp['permission_id']] = true;
        }
        if ($this->isPost()) {
            $posted = (array) ($_POST['perm'] ?? []);
            Database::transaction(function () use ($roles, $perms, $posted, $assign) {
                foreach ($roles as $r) {
                    if ($r['code'] === 'admin') continue; // l'administrateur a toujours tous les droits
                    $new = array_map('intval', array_keys((array) ($posted[$r['id']] ?? [])));
                    $new = array_values(array_intersect($new, array_map('intval', array_column($perms, 'id'))));
                    $old = array_keys($assign[$r['id']] ?? []);
                    sort($new);
                    sort($old);
                    if ($new === $old) continue;
                    Database::run('DELETE FROM role_permissions WHERE role_id = ?', [$r['id']]);
                    foreach ($new as $pid) {
                        Database::run('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$r['id'], $pid]);
                    }
                    $codes = fn($ids) => array_values(array_column(array_filter($perms, fn($p) => in_array((int) $p['id'], $ids, true)), 'code'));
                    Audit::log('modification', 'role', (int) $r['id'], 'Permissions du rôle ' . $r['libelle'],
                        ['permissions' => $codes(array_map('intval', $old))], ['permissions' => $codes($new)]);
                }
            });
            Cache::flushGroup('rbac');
            flash('success', 'Permissions enregistrées.');
            redirect(url('roles'));
        }
        $modules = [];
        foreach ($perms as $p) $modules[$p['module']][] = $p;
        $this->render('roles/index', ['roles' => $roles, 'modules' => $modules, 'assign' => $assign]);
    }

    public function form(): void
    {
        $id = input_int('id');
        $r = $id ? Database::one('SELECT * FROM roles WHERE id = ?', [$id]) : null;
        $errors = [];
        $v = $r ?? [];
        if ($this->isPost()) {
            $d = ['code' => strtolower(input_str('code', 50)), 'libelle' => input_str('libelle', 100), 'description' => input_str('description', 255) ?: null];
            if ($r && $r['systeme']) $d['code'] = $r['code'];
            if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $d['code'])) $errors['code'] = 'Code : minuscules, chiffres et _.';
            elseif (Database::value('SELECT id FROM roles WHERE code = ? AND id <> ?', [$d['code'], $id ?? 0])) $errors['code'] = 'Code déjà utilisé.';
            if ($d['libelle'] === '') $errors['libelle'] = 'Libellé obligatoire.';
            if (!$errors) {
                if ($r) {
                    [$a, $b] = Audit::diff($r, $d);
                    Database::update('roles', $d, (int) $r['id']);
                    Audit::log('modification', 'role', (int) $r['id'], 'Modification du rôle ' . $d['libelle'], $a, $b);
                } else {
                    $id = Database::insert('roles', $d);
                    Audit::log('creation', 'role', $id, 'Création du rôle ' . $d['libelle'], null, $d);
                }
                Cache::flushGroup('ref');
                flash('success', 'Rôle enregistré. Attribuez-lui ses permissions dans la matrice.');
                redirect(url('roles'));
            }
            $v = array_merge($v, $d);
        }
        $this->render('roles/form', ['r' => $r, 'v' => $v, 'errors' => $errors]);
    }

    public function delete(): void
    {
        $r = Database::one('SELECT * FROM roles WHERE id = ?', [(int) input_int('id', 0)]);
        if (!$r) redirect(url('roles'));
        if ($r['systeme'] || Database::value('SELECT COUNT(*) FROM utilisateurs WHERE role_id = ?', [$r['id']])) {
            flash('danger', 'Ce rôle est protégé ou attribué à des utilisateurs.');
        } else {
            Database::run('DELETE FROM roles WHERE id = ?', [$r['id']]);
            Audit::log('suppression', 'role', (int) $r['id'], 'Suppression du rôle ' . $r['libelle'], $r, null);
            Cache::flushGroup('ref');
            Cache::flushGroup('rbac');
            flash('success', 'Rôle supprimé.');
        }
        redirect(url('roles'));
    }
}
