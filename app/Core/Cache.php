<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cache applicatif APCu (mémoire partagée), avec repli sur un cache
 * de requête si l'extension n'est pas disponible.
 *
 * Invalidation par "groupes" : chaque groupe possède un numéro de version
 * stocké dans APCu ; incrémenter la version invalide instantanément
 * toutes les clés du groupe sans avoir à les parcourir.
 */
final class Cache
{
    private static string $prefix = 'app:';
    private static int $defaultTtl = 300;
    private static array $local = [];
    private static ?bool $enabled = null;

    public static function configure(string $prefix, int $ttl = 300): void
    {
        self::$prefix = $prefix;
        self::$defaultTtl = $ttl;
    }

    public static function enabled(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = function_exists('apcu_enabled') && apcu_enabled();
        }
        return self::$enabled;
    }

    private static function key(string $key, ?string $group = null): string
    {
        if ($group !== null) {
            $key = $group . ':v' . self::groupVersion($group) . ':' . $key;
        }
        return self::$prefix . $key;
    }

    private static function groupVersion(string $group): int
    {
        $k = self::$prefix . 'grp:' . $group;
        if (self::enabled()) {
            $v = apcu_fetch($k, $ok);
            if (!$ok) {
                $v = 1;
                apcu_add($k, $v);
            }
            return (int) $v;
        }
        return (int) (self::$local[$k] ?? 1);
    }

    public static function get(string $key, ?string $group = null, mixed $default = null): mixed
    {
        $k = self::key($key, $group);
        if (self::enabled()) {
            $v = apcu_fetch($k, $ok);
            return $ok ? $v : $default;
        }
        return array_key_exists($k, self::$local) ? self::$local[$k] : $default;
    }

    public static function set(string $key, mixed $value, ?int $ttl = null, ?string $group = null): void
    {
        $k = self::key($key, $group);
        if (self::enabled()) {
            apcu_store($k, $value, $ttl ?? self::$defaultTtl);
        } else {
            self::$local[$k] = $value;
        }
    }

    public static function delete(string $key, ?string $group = null): void
    {
        $k = self::key($key, $group);
        if (self::enabled()) {
            apcu_delete($k);
        }
        unset(self::$local[$k]);
    }

    /** Renvoie la valeur en cache ou la calcule puis la stocke. */
    public static function remember(string $key, callable $producer, ?int $ttl = null, ?string $group = null): mixed
    {
        $k = self::key($key, $group);
        if (self::enabled()) {
            $v = apcu_fetch($k, $ok);
            if ($ok) {
                return $v;
            }
        } elseif (array_key_exists($k, self::$local)) {
            return self::$local[$k];
        }
        $v = $producer();
        self::set($key, $v, $ttl, $group);
        return $v;
    }

    /** Invalide toutes les clés d'un groupe. */
    public static function flushGroup(string $group): void
    {
        $k = self::$prefix . 'grp:' . $group;
        if (self::enabled()) {
            if (apcu_inc($k) === false) {
                apcu_store($k, 2);
            }
        } else {
            self::$local[$k] = (self::$local[$k] ?? 1) + 1;
        }
    }

    /** Limitation de débit simple (ex: tentatives de connexion). */
    public static function hit(string $key, int $window): int
    {
        $k = self::$prefix . 'rl:' . $key;
        if (self::enabled()) {
            apcu_add($k, 0, $window);
            return (int) apcu_inc($k);
        }
        self::$local[$k] = (self::$local[$k] ?? 0) + 1;
        return self::$local[$k];
    }

    public static function clearAll(): bool
    {
        self::$local = [];
        return self::enabled() ? apcu_clear_cache() : true;
    }
}
