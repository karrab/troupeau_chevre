<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    $cfg = $GLOBALS['__config'] ?? [];
    foreach (explode('.', $key) as $k) {
        if (!is_array($cfg) || !array_key_exists($k, $cfg)) return $default;
        $cfg = $cfg[$k];
    }
    return $cfg;
}

/** URL d'une route : url('animaux/voir', ['id' => 5]) */
function url(string $route = '', array $params = []): string
{
    $base = rtrim((string) config('app.base_url', ''), '/') . '/index.php';
    $q = $route !== '' ? ['r' => $route] + $params : $params;
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return $base . ($q ? '?' . str_replace('%2F', '/', http_build_query($q)) : '');
}

/** URL de la page courante avec paramètres modifiés (null supprime le paramètre). */
function url_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    $route = $q['r'] ?? '';
    unset($q['r']);
    return url((string) $route, $q);
}

function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 1;
    return rtrim((string) config('app.base_url', ''), '/') . '/assets/' . $path . '?v=' . $v;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_field(): string
{
    return Csrf::field();
}

function can(string $perm): bool
{
    return Auth::can($perm);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Valeur précédente d'un formulaire (après erreur de validation). */
function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function keep_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function fdate(?string $d, bool $time = false): string
{
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date($time ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '';
}

function fnum(mixed $n, int $dec = 2): string
{
    if ($n === null || $n === '') return '—';
    return number_format((float) $n, $dec, ',', ' ');
}

function age_label(?string $naissance, ?string $ref = null): string
{
    if (!$naissance) return '';
    try {
        $d = (new DateTimeImmutable($naissance))->diff(new DateTimeImmutable($ref ?: 'today'));
    } catch (Exception) {
        return '';
    }
    if ($d->invert) return '';
    if ($d->y > 0) return $d->y . ' an' . ($d->y > 1 ? 's' : '') . ($d->m ? ' ' . $d->m . ' mois' : '');
    if ($d->m > 0) return $d->m . ' mois';
    return $d->d . ' j';
}

function statut_badge(string $statut): string
{
    $map = [
        'actif'   => ['success', 'Actif'],
        'vendu'   => ['primary', 'Vendu'],
        'mort'    => ['dark', 'Mort'],
        'reforme' => ['warning', 'Réformé'],
        'perdu'   => ['secondary', 'Perdu'],
    ];
    [$c, $l] = $map[$statut] ?? ['secondary', $statut];
    return '<span class="badge text-bg-' . $c . '">' . e($l) . '</span>';
}

function niveau_badge(string $niveau): string
{
    $map = ['bon' => ['success', 'Bon'], 'moyen' => ['warning', 'Moyen'], 'faible' => ['danger', 'Faible'], 'na' => ['secondary', 'N/C']];
    [$c, $l] = $map[$niveau] ?? ['secondary', $niveau];
    return '<span class="badge text-bg-' . $c . '">' . $l . '</span>';
}

function sexe_label(string $s): string
{
    return $s === 'F' ? '<i class="bi bi-gender-female text-danger"></i> Femelle' : '<i class="bi bi-gender-male text-primary"></i> Mâle';
}

function input_str(string $key, int $max = 255): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function input_int(string $key, ?int $default = null): ?int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return (is_scalar($v) && $v !== '' && preg_match('/^-?\d+$/', (string) $v)) ? (int) $v : $default;
}

function valid_date(?string $d): bool
{
    if (!$d) return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
