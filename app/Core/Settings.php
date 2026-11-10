<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Paramètres applicatifs (table `parametres`) mis en cache APCu.
 */
final class Settings
{
    public static function all(): array
    {
        return Cache::remember('parametres', function () {
            $rows = Database::all('SELECT cle, valeur, type FROM parametres');
            $out = [];
            foreach ($rows as $r) {
                $out[$r['cle']] = match ($r['type']) {
                    'entier'  => (int) $r['valeur'],
                    'decimal' => (float) $r['valeur'],
                    'booleen' => (bool) $r['valeur'],
                    default   => (string) $r['valeur'],
                };
            }
            return $out;
        }, 3600, 'ref');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }
}
