<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Pagination efficace par "keyset" (seek method).
 *
 * Au lieu de LIMIT x OFFSET y (qui oblige MySQL à lire puis jeter y lignes),
 * on mémorise la dernière clé affichée (valeur de tri + id) et on demande
 * les lignes situées "après" cette clé :
 *
 *   WHERE (tri < :v OR (tri = :v AND id < :id)) ORDER BY tri DESC, id DESC LIMIT n+1
 *
 * Combiné à un index composite (filtres..., tri, id), le coût de chaque page
 * est constant quelle que soit sa position.
 */
final class KeysetPaginator
{
    /**
     * @param array{
     *   select: string, from: string, where?: string[], params?: array,
     *   sort: array{col: string, key: string, dir?: string},
     *   id?: array{col: string, key: string},
     *   limit?: int, cursor?: ?string
     * } $q
     */
    public static function paginate(array $q): array
    {
        $limit  = max(1, min(500, (int) ($q['limit'] ?? 25)));
        $dir    = strtoupper($q['sort']['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $idCol  = $q['id']['col'] ?? 'id';
        $idKey  = $q['id']['key'] ?? 'id';
        $sCol   = $q['sort']['col'];
        $sKey   = $q['sort']['key'];
        $sig    = substr(sha1($sCol . '|' . $dir), 0, 8);
        $cursor = self::decode($q['cursor'] ?? null, $sig);

        $where  = $q['where'] ?? [];
        $params = $q['params'] ?? [];
        $back   = $cursor !== null && $cursor['d'] === 'p';

        // Sens de lecture effectif
        $readAsc = ($dir === 'ASC') !== $back;
        $op      = $readAsc ? '>' : '<';
        $ord     = $readAsc ? 'ASC' : 'DESC';

        if ($cursor !== null) {
            if ($sCol === $idCol) {
                $where[] = "$idCol $op :__ki";
            } else {
                $where[] = "($sCol $op :__ks1 OR ($sCol = :__ks2 AND $idCol $op :__ki))";
                $params['__ks1'] = $cursor['s'];
                $params['__ks2'] = $cursor['s'];
            }
            $params['__ki'] = (int) $cursor['i'];
        }

        $sql = 'SELECT ' . $q['select'] . ' FROM ' . $q['from']
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ($sCol === $idCol ? " ORDER BY $idCol $ord" : " ORDER BY $sCol $ord, $idCol $ord")
            . ' LIMIT ' . ($limit + 1);

        $rows = Database::all($sql, $params);
        $more = count($rows) > $limit;
        if ($more) {
            array_pop($rows);
        }
        if ($back) {
            $rows = array_reverse($rows);
        }

        $hasNext = $back ? true : $more;
        $hasPrev = $back ? $more : $cursor !== null;
        $first = $rows[0] ?? null;
        $last  = $rows ? $rows[count($rows) - 1] : null;

        return [
            'rows'     => $rows,
            'limit'    => $limit,
            'has_next' => $hasNext && $last !== null,
            'has_prev' => $hasPrev && $first !== null,
            'next'     => $hasNext && $last ? self::encode($last[$sKey], $last[$idKey], 'n', $sig) : null,
            'prev'     => $hasPrev && $first ? self::encode($first[$sKey], $first[$idKey], 'p', $sig) : null,
            'sql'      => $sql,
        ];
    }

    /**
     * Parcourt TOUTES les lignes d'une requête par lots keyset (exports PDF),
     * sans jamais utiliser OFFSET. Mémoire constante par lot.
     */
    public static function iterate(array $q, int $max = 2000, int $batch = 500): \Generator
    {
        $count = 0;
        $q['cursor'] = null;
        do {
            $q['limit'] = min($batch, $max - $count);
            $page = self::paginate($q);
            foreach ($page['rows'] as $row) {
                yield $row;
                $count++;
            }
            $q['cursor'] = $page['next'];
        } while ($page['has_next'] && $count < $max);
    }

    /** Comptage optionnel, mis en cache APCu pour 60 s par combinaison de filtres. */
    public static function count(string $from, array $where, array $params, string $group = 'counts'): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $from . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $key = sha1($sql . serialize($params));
        return (int) Cache::remember($key, fn() => (int) Database::value($sql, $params), 60, $group);
    }

    private static function encode(mixed $sortValue, mixed $id, string $d, string $sig): string
    {
        $json = json_encode(['s' => $sortValue, 'i' => (int) $id, 'd' => $d, 'h' => $sig], JSON_UNESCAPED_UNICODE);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private static function decode(?string $cursor, string $sig): ?array
    {
        if (!$cursor) {
            return null;
        }
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = $json ? json_decode($json, true) : null;
        if (!is_array($data) || !isset($data['i'], $data['d'], $data['h']) || $data['h'] !== $sig
            || !array_key_exists('s', $data) || !in_array($data['d'], ['n', 'p'], true)
            || !(is_scalar($data['s']) || $data['s'] === null)) {
            return null;
        }
        return $data;
    }
}
