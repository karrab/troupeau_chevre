<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Référentiels (espèces, races, catégories, rôles) mis en cache APCu, groupe "ref".
 * Toute modification d'un référentiel appelle Cache::flushGroup('ref').
 */
final class Ref
{
    public static function especes(): array
    {
        return Cache::remember('especes', fn() => array_column(
            Database::all('SELECT id, code, libelle FROM especes ORDER BY id'), null, 'id'
        ), 3600, 'ref');
    }

    public static function races(?int $especeId = null): array
    {
        $all = Cache::remember('races', fn() => array_column(
            Database::all('SELECT id, espece_id, libelle, actif FROM races ORDER BY espece_id, libelle'), null, 'id'
        ), 3600, 'ref');
        return $especeId ? array_filter($all, fn($r) => (int) $r['espece_id'] === $especeId) : $all;
    }

    public static function categories(?int $especeId = null): array
    {
        $all = Cache::remember('categories', fn() => array_column(
            Database::all('SELECT id, espece_id, code, libelle, sexe, adulte, castre FROM categories ORDER BY espece_id, sexe DESC, adulte DESC, id'), null, 'id'
        ), 3600, 'ref');
        return $especeId ? array_filter($all, fn($c) => (int) $c['espece_id'] === $especeId) : $all;
    }

    public static function roles(): array
    {
        return Cache::remember('roles', fn() => array_column(
            Database::all('SELECT id, code, libelle FROM roles ORDER BY id'), null, 'id'
        ), 3600, 'ref');
    }

    /**
     * Détermine la catégorie automatique d'un animal selon espèce, sexe, âge, castration.
     */
    public static function autoCategorie(int $especeId, string $sexe, string $dateNaissance, bool $castre = false, ?string $dateRef = null): ?int
    {
        $ageAdulte = (int) Settings::get('age_adulte_mois', 12);
        try {
            $naiss = new \DateTimeImmutable($dateNaissance);
            $ref = new \DateTimeImmutable($dateRef ?? 'today');
        } catch (\Exception) {
            return null;
        }
        $diff = $naiss->diff($ref);
        $mois = $diff->invert ? 0 : $diff->y * 12 + $diff->m;
        $adulte = $mois >= $ageAdulte ? 1 : 0;

        $fallback = null;
        foreach (self::categories($especeId) as $c) {
            if ($c['sexe'] !== $sexe || (int) $c['adulte'] !== $adulte) {
                continue;
            }
            if ((int) $c['castre'] === ($castre && $adulte ? 1 : 0)) {
                return (int) $c['id'];
            }
            $fallback ??= (int) $c['id'];
        }
        return $fallback;
    }
}
