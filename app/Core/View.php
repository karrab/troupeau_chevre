<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static string $base;

    public static function setBase(string $dir): void
    {
        self::$base = rtrim($dir, '/');
    }

    /** Rendu d'un gabarit seul (sans layout). */
    /** Variables de page définies par un gabarit et transmises au layout. */
    private const PAGE_VARS = ['title', 'charts'];
    private static array $pageVars = [];

    public static function fetch(string $template, array $data = []): string
    {
        $__file = self::$base . '/' . $template . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException("Vue introuvable : $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        foreach (self::PAGE_VARS as $__v) {
            if (isset($$__v)) {
                self::$pageVars[$__v] = $$__v;
            }
        }
        return (string) ob_get_clean();
    }

    /** Rendu d'une page complète dans le layout principal. */
    public static function render(string $template, array $data = [], string $layout = 'layout/main'): void
    {
        self::$pageVars = [];
        $data['content'] = self::fetch($template, $data);
        echo self::fetch($layout, self::$pageVars + $data);
    }
}
