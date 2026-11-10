<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Settings;
use App\Core\View;

abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        View::render($view, $data);
    }

    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    protected function pageSize(): int
    {
        $n = input_int('n') ?? (int) Settings::get('taille_page', 25);
        return in_array($n, [10, 25, 50, 100], true) ? $n : (int) Settings::get('taille_page', 25);
    }

    protected function maxExport(): int
    {
        return max(100, (int) Settings::get('max_export_pdf', 2000));
    }

    protected function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function back(string $fallback): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($ref && $host && parse_url($ref, PHP_URL_HOST) === parse_url('//' . $host, PHP_URL_HOST)) {
            redirect($ref);
        }
        redirect($fallback);
    }
}
