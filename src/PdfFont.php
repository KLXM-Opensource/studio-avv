<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Avv;

/**
 * Schrift im PDF (Einstellungen → „Schrift im PDF“): DejaVu Sans (in dompdf enthalten) oder eine freie Schrift von Fontsource
 * (OFL/Apache), z. B. die Hausschrift Lato. dompdf braucht TTF-Dateien: beim Speichern der Einstellung werden Normal, Fett,
 * Kursiv und Fett kursiv (Zeichensatz latin) einmal von cdn.jsdelivr.net geladen und unter storage/…/avv/fonts/ abgelegt –
 * danach kein Netzzugriff mehr (das PDF selbst lädt nie etwas nach).
 */
final class PdfFont
{
    /** Auswahl: Fontsource-Kennung => Name (leer = DejaVu Sans) */
    public const OPTIONS = ['' => 'DejaVu Sans', 'lato' => 'Lato', 'inter' => 'Inter', 'open-sans' => 'Open Sans', 'roboto' => 'Roboto',
        'source-sans-3' => 'Source Sans 3', 'noto-sans' => 'Noto Sans', 'ibm-plex-sans' => 'IBM Plex Sans', 'merriweather' => 'Merriweather', 'source-serif-4' => 'Source Serif 4'];
    private const FACES = [['400', 'normal'], ['700', 'normal'], ['400', 'italic'], ['700', 'italic']];
    private const MAX = 2_000_000;

    public static function current(): string
    {
        $id = Avv::setting('pdf_font');
        return isset(self::OPTIONS[$id]) ? $id : '';
    }

    private static function dir(): string
    {
        $d = Pdf::dir() . '/fonts';
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    private static function file(string $id, string $w, string $st): string
    {
        return self::dir() . "/$id-$w-$st.ttf";
    }

    /** Dateien vorhanden (mindestens Normal)? */
    public static function ready(string $id): bool
    {
        return $id === '' || is_file(self::file($id, '400', 'normal'));
    }

    /** Fehlende Schnitte laden → Fehlertext oder null */
    public static function ensure(string $id): ?string
    {
        if ($id === '' || !isset(self::OPTIONS[$id])) return null;
        foreach (self::FACES as [$w, $st]) {
            $f = self::file($id, $w, $st);
            if (is_file($f)) continue;
            $bin = self::download("https://cdn.jsdelivr.net/fontsource/fonts/$id@latest/latin-$w-$st.ttf");
            if ($bin === null) {
                if ($w === '400' && $st === 'normal') return __('Die Schrift „{name}“ konnte nicht geladen werden (cdn.jsdelivr.net nicht erreichbar?). Das PDF nutzt bis dahin DejaVu Sans.', ['name' => self::OPTIONS[$id]]);
                continue;   // fehlender Schnitt (z. B. ohne Kursiv): dompdf nimmt den nächsten vorhandenen
            }
            file_put_contents($f, $bin, LOCK_EX);
        }
        return null;
    }

    private static function download(string $url): ?string
    {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'KLXM-Studio-AVV']);
        $bin = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (!is_string($bin) || $status !== 200 || strlen($bin) > self::MAX || strlen($bin) < 1000) return null;
        // TrueType/OpenType-Kopf
        return in_array(substr($bin, 0, 4), ["\x00\x01\x00\x00", 'true', 'OTTO'], true) ? $bin : null;
    }

    /** CSS für dompdf: @font-face je vorhandenem Schnitt + Schriftfamilie; leer = DejaVu Sans */
    public static function css(): string
    {
        $id = self::current();
        if ($id === '' || !self::ready($id)) return '';
        $out = '';
        foreach (self::FACES as [$w, $st]) {
            $f = self::file($id, $w, $st);
            if (is_file($f)) $out .= '@font-face{font-family:"AvvPdf";font-weight:' . $w . ';font-style:' . $st . ';src:url("' . $f . '") format("truetype")}';
        }
        return $out . 'body{font-family:"AvvPdf","DejaVu Sans",sans-serif}.avv-sig__typed{font-family:"AvvPdf",serif}';
    }
}
