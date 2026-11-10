<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentification par session + contrôle des permissions (RBAC).
 * Les permissions d'un rôle sont mises en cache APCu (groupe "rbac").
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function startSession(string $name): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
        // Expiration après 2 h d'inactivité
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 7200) {
            session_unset();
            session_regenerate_id(true);
        }
        $_SESSION['last_activity'] = time();
    }

    public static function attempt(string $login, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $rlKey = 'login:' . sha1(strtolower($login) . '|' . $ip);
        if (Cache::hit($rlKey, 900) > 8) {
            return [false, 'Trop de tentatives. Réessayez dans 15 minutes.'];
        }
        $u = Database::one('SELECT id, login, mot_de_passe, actif FROM utilisateurs WHERE login = ?', [$login]);
        if (!$u || !password_verify($password, $u['mot_de_passe'])) {
            Audit::log('connexion_echec', 'utilisateur', $u['id'] ?? null, "Échec de connexion pour « $login »", null, null, $u['id'] ?? null, $login);
            return [false, 'Identifiant ou mot de passe incorrect.'];
        }
        if (!(int) $u['actif']) {
            return [false, 'Ce compte est désactivé.'];
        }
        if (password_needs_rehash($u['mot_de_passe'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        Database::run('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?', [$u['id']]);
        Cache::delete('rl:' . $rlKey);
        self::$loaded = false;
        Audit::log('connexion', 'utilisateur', (int) $u['id'], 'Connexion réussie');
        return [true, null];
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('deconnexion', 'utilisateur', self::id(), 'Déconnexion');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            self::$user = null;
            $uid = $_SESSION['uid'] ?? null;
            if ($uid) {
                $u = Database::one(
                    'SELECT u.id, u.login, u.nom_complet, u.email, u.role_id, u.actif, u.doit_changer_mdp, r.libelle AS role_libelle, r.code AS role_code
                       FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
                    [$uid]
                );
                if ($u && (int) $u['actif']) {
                    $u['permissions'] = self::rolePermissions((int) $u['role_id']);
                    self::$user = $u;
                } else {
                    unset($_SESSION['uid']);
                }
            }
        }
        return self::$user;
    }

    public static function rolePermissions(int $roleId): array
    {
        return Cache::remember('role:' . $roleId, function () use ($roleId) {
            $rows = Database::all(
                'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
                [$roleId]
            );
            return array_fill_keys(array_column($rows, 'code'), true);
        }, 600, 'rbac');
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if ($u['role_code'] === 'admin') {
            return true;
        }
        return isset($u['permissions'][$permission]);
    }

    public static function refresh(): void
    {
        self::$loaded = false;
    }
}
