<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Avv;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF mit dompdf: Vertrag (Avv::document) mit Kopf, Seitenzahlen und einem Prüfvermerk in der Fußzeile (Zeitpunkt der
 * Zeichnung, Fassung, Vertragsnummer). Keine Dateien aus dem Netz (isRemoteEnabled aus), Bilder nur als data-URL.
 * Abgelegt außerhalb des Webroots unter storage/…/avv/; die SHA-256-Prüfsumme des PDFs steht am Vertrag.
 */
final class Pdf
{
    public static function available(): bool
    {
        return class_exists(Dompdf::class);
    }

    public static function dir(): string
    {
        $d = site()->storage('avv');
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    /** PDF-Bytes für einen Vertrag (gezeichnet oder Vorschau des Entwurfs) */
    public static function render(array $version, array $c, string $stamp = ''): string
    {
        if (!self::available()) throw new \RuntimeException(__('dompdf fehlt – bitte „composer install“ im Ordner der Erweiterung ausführen.'));
        $o = new Options();
        $o->set('isRemoteEnabled', false);
        $o->set('isPhpEnabled', false);
        $o->set('defaultFont', 'DejaVu Sans');
        $o->set('tempDir', sys_get_temp_dir());
        $o->set('fontCache', self::dir());
        $o->setChroot([self::dir()]);
        $pdf = new Dompdf($o);
        $html = '<!doctype html><html lang="' . e(\Core\Lang::current()) . '"><head><meta charset="utf-8"><style>' . self::css() . '</style></head><body>'
            . '<header class="pdf-head">' . e(Avv::provider()['name']) . '</header>'
            . Avv::document($version, $c, false, true) . '</body></html>';
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        // Fußzeile: Seite x von y und Prüfvermerk
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $label = lt('Seite {n} von {total}', ['n' => '{PAGE_NUM}', 'total' => '{PAGE_COUNT}']);
        $canvas->page_text(510, 812, $label, $font, 7.5, [0.35, 0.35, 0.35]);
        if ($stamp !== '') $canvas->page_text(42, 812, $stamp, $font, 7.5, [0.35, 0.35, 0.35]);
        return (string) $pdf->output();
    }

    /** Gezeichneten Vertrag als PDF ablegen → [Dateiname, SHA-256] */
    public static function store(array $c): array
    {
        $v = Repo::version((int) $c['version_id']) ?? throw new \RuntimeException('Fassung fehlt');
        $stamp = lt('Elektronisch unterzeichnet am {date} · Fassung {v} · Nr. {id}', ['date' => date('d.m.Y H:i', (int) strtotime((string) $c['signed_at'])), 'v' => $v['number'], 'id' => $c['id']]);
        $bytes = self::render($v, $c, $stamp);
        $hash = hash('sha256', $bytes);
        $file = 'avv-' . (int) $c['id'] . '-' . substr($hash, 0, 16) . '.pdf';
        if (file_put_contents(self::dir() . '/' . $file, $bytes, LOCK_EX) === false) throw new \RuntimeException(__('PDF konnte nicht gespeichert werden.'));
        return [$file, $hash];
    }

    public static function path(array $c): ?string
    {
        $f = basename((string) $c['pdf_file']);
        $p = self::dir() . '/' . $f;
        return $f !== '' && is_file($p) ? $p : null;
    }

    /** Dateiname für den Download: AVV-{Firma}-{Datum}.pdf */
    public static function downloadName(array $c): string
    {
        $slug = trim((string) preg_replace('~[^A-Za-z0-9]+~', '-', iconv('UTF-8', 'ASCII//TRANSLIT', (string) $c['company']) ?: ''), '-');
        return 'AVV-' . ($slug !== '' ? mb_substr($slug, 0, 40) . '-' : '') . date('Y-m-d', (int) strtotime((string) ($c['signed_at'] ?: 'now'))) . '.pdf';
    }

    public static function response(array $c): \Core\Http\Response
    {
        $p = self::path($c) ?? throw new \Core\Http\HttpException(404);
        return (new \Core\Http\Response((string) file_get_contents($p)))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . self::downloadName($c) . '"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    private static function css(): string
    {
        return '@page{margin:22mm 18mm 20mm 18mm}
body{font-family:"DejaVu Sans",sans-serif;font-size:9.5pt;line-height:1.45;color:#1c1c1c}
.pdf-head{position:fixed;top:-14mm;left:0;right:0;font-size:7.5pt;color:#777;text-align:right}
.avv-doc__title{font-size:15pt;text-align:center;margin:0 0 6pt}
h2{font-size:11.5pt;margin:14pt 0 4pt;page-break-after:avoid}
h3{font-size:10pt;margin:10pt 0 3pt;page-break-after:avoid}
p{margin:0 0 6pt}
ol,ul{margin:0 0 6pt 0;padding-left:16pt}
li{margin:0 0 3pt}
.t-note{color:#8a5a00}
.avv-break{page-break-before:always}
.avv-sig{width:100%;margin-top:28pt;border-collapse:collapse;page-break-inside:avoid}
.avv-sig td{width:50%;vertical-align:bottom;padding:0 10pt 0 0}
.avv-sig__box{height:52pt;position:relative}
.avv-sig__img{max-height:50pt;max-width:200pt}
.avv-sig__typed{font-family:"DejaVu Serif",serif;font-style:italic;font-size:15pt}
.avv-sig__line{border-top:0.8pt solid #333;padding-top:3pt;font-size:8.5pt}
.avv-sig__when{margin-top:8pt;font-size:8.5pt;color:#444}';
    }
}
