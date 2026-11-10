<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Journal des traces : qui a fait quoi, quand, depuis où, avec les
 * valeurs avant / après modification.
 */
final class Audit
{
    public static function log(
        string $action,
        string $entite,
        ?int $entiteId = null,
        ?string $description = null,
        ?array $anciennes = null,
        ?array $nouvelles = null,
        ?int $userId = null,
        ?string $login = null
    ): void {
        try {
            $u = Auth::user();
            Database::insert('journal_audit', [
                'utilisateur_id'    => $userId ?? ($u['id'] ?? null),
                'login'             => $login ?? ($u['login'] ?? null),
                'action'            => mb_substr($action, 0, 40),
                'entite'            => mb_substr($entite, 0, 50),
                'entite_id'         => $entiteId,
                'description'       => $description !== null ? mb_substr($description, 0, 255) : null,
                'anciennes_valeurs' => $anciennes !== null ? json_encode($anciennes, JSON_UNESCAPED_UNICODE) : null,
                'nouvelles_valeurs' => $nouvelles !== null ? json_encode($nouvelles, JSON_UNESCAPED_UNICODE) : null,
                'ip'                => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[audit] ' . $e->getMessage());
        }
    }

    /**
     * Calcule la différence entre deux enregistrements.
     * @return array{0: array, 1: array} [anciennes valeurs, nouvelles valeurs] limitées aux champs modifiés
     */
    public static function diff(array $old, array $new): array
    {
        $a = $b = [];
        foreach ($new as $k => $v) {
            $o = $old[$k] ?? null;
            $same = (is_numeric($o) && is_numeric($v)) ? abs((float) $o - (float) $v) < 1e-9 : (string) $o === (string) $v;
            if (!$same) {
                $a[$k] = $o;
                $b[$k] = $v;
            }
        }
        return [$a, $b];
    }
}
