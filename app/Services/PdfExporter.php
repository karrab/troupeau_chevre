<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Settings;
use App\Core\View;

/**
 * Génération PDF avec Dompdf (composer require dompdf/dompdf).
 * Si Dompdf n'est pas installé, une page HTML optimisée pour l'impression
 * est servie et la boîte d'impression du navigateur s'ouvre (« Enregistrer en PDF »).
 */
final class PdfExporter
{
    public static function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    /**
     * @param string $template gabarit dans views/pdf/
     */
    public static function output(string $template, array $data, string $filename, string $orientation = 'portrait', bool $download = true): void
    {
        $data += [
            'exploitation' => Settings::get('nom_exploitation', ''),
            'adresse'      => Settings::get('adresse_exploitation', ''),
            'telephone'    => Settings::get('telephone_exploitation', ''),
            'genere_le'    => date('d/m/Y H:i'),
            'orientation'  => $orientation,
            'print_mode'   => !self::available(),
        ];
        $data['body'] = View::fetch('pdf/' . $template, $data);
        $html = View::fetch('pdf/layout', $data);

        Audit::log('export_pdf', 'export', null, 'Export PDF : ' . $filename);

        if (!self::available()) {
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            return;
        }
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isPhpEnabled', false);
        $options->set('tempDir', sys_get_temp_dir());
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();
        // Numérotation des pages
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 24, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.4, 0.4, 0.4]);
        $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $filename);
        $dompdf->stream($safe . '.pdf', ['Attachment' => $download]);
    }

    /** Export PDF générique d'une liste (colonnes + lignes). */
    public static function liste(string $titre, array $colonnes, iterable $lignes, array $filtres = [], string $orientation = 'landscape', bool $download = true): void
    {
        $rows = [];
        foreach ($lignes as $l) {
            $rows[] = $l;
        }
        self::output('liste', [
            'titre'    => $titre,
            'colonnes' => $colonnes,
            'lignes'   => $rows,
            'filtres'  => array_filter($filtres, fn($v) => $v !== '' && $v !== null),
        ], $titre . '_' . date('Ymd_His'), $orientation, $download);
    }
}
