<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Avv;

/**
 * Regeln und Bausteine der AVV (Vereinbarung zur Auftragsverarbeitung, Art. 28 DS-GVO).
 *
 * Wer verwaltet: Die AVV gehört dem Betreiber der Installation. Mit mehreren Websites (Netzwerk) nur auf der Netzwerk-Website
 * und nur für Netzwerk-Konten; bei einer einzelnen Website die Administration (Recht avv.manage).
 * Texte: Vertragstext (Rich-Text mit Platzhaltern {{…}}), Anlage A (Auswahllisten), Anlage B (TOM), Unterauftragnehmer –
 * als Entwurf in den Einstellungen, veröffentlicht als Fassung (Repo::versions). Auftragnehmer-Angaben in den Einstellungen.
 */
final class Avv
{
    public const NAME = 'avv';
    public const FEATURE = 'avv';
    public const PERM = 'avv.manage';
    public const BASE = '/avv';
    public const MAIL = 'avv';
    /** Gültigkeit einer Einladung (Tage) und eines Download-Links für den Kunden */
    public const INVITE_DAYS = 30;
    public const DOWNLOAD_DAYS = 30;
    /** Höchstgröße der gezeichneten Unterschrift (PNG als data-URL) */
    public const SIG_MAX = 300_000;

    /** Platzhalter im Vertragstext → Beschreibung (Vorlage-Editor) */
    public static function placeholders(): array
    {
        return [
            'firma' => lt('Firma des Auftraggebers'), 'anschrift' => lt('Straße und Hausnummer'), 'plz_ort' => lt('PLZ und Ort'),
            'unterzeichner' => lt('Name der unterzeichnenden Person'), 'funktion' => lt('Funktion der unterzeichnenden Person'),
            'datum' => lt('Datum der Unterzeichnung'), 'auftragnehmer' => lt('Name des Auftragnehmers'),
            'auftragnehmer_anschrift' => lt('Anschrift des Auftragnehmers'), 'ort' => lt('Ort des Auftragnehmers'),
        ];
    }

    /** Platzhalter mit Beispiel (Liste neben dem Editor): key => [Bedeutung, Beispiel] – Beispiele aus den eigenen Angaben, wo vorhanden */
    public static function placeholderInfo(): array
    {
        $p = self::provider();
        $ex = ['firma' => 'Muster GmbH', 'anschrift' => 'Musterstraße 1', 'plz_ort' => '12345 Musterstadt', 'unterzeichner' => 'Erika Musterfrau',
            'funktion' => 'Geschäftsführung', 'datum' => date('d.m.Y'), 'auftragnehmer' => $p['name'],
            'auftragnehmer_anschrift' => trim($p['street'] . ', ' . $p['zip_city'], ', ') ?: 'Agenturweg 2, 12345 Musterstadt', 'ort' => $p['place'] ?: ($p['zip_city'] ?: 'Musterstadt')];
        $out = [];
        foreach (self::placeholders() as $k => $label) $out[$k] = [$label, $ex[$k] ?? ''];
        return $out;
    }

    public static function asset(string $path): string
    {
        $x = \Core\Extensions::active()[self::NAME] ?? null;
        return $x ? $x->asset($path) : '';
    }

    public static function url(string $sub = ''): string
    {
        return url(self::BASE . ($sub !== '' ? '/' . ltrim($sub, '/') : ''));
    }

    /** Mehrere Websites in der Installation → AVV nur auf der Netzwerk-Website */
    public static function multi(): bool
    {
        return count(\Core\Sites::all()) > 1;
    }

    /** Läuft die AVV auf dieser Website? (Erweiterung aktiv, Funktion an, bei Netzwerk nur die Netzwerk-Website) */
    public static function here(): bool
    {
        if (!\Core\Extensions::isActive(self::NAME) || !\Core\Features::on(self::FEATURE)) return false;
        return !self::multi() || \Core\Network\Network::isNetworkSite();
    }

    /** Darf die angemeldete Person die AVV verwalten? */
    public static function canManage(?array $u = null): bool
    {
        $u ??= app()->auth->user();
        if (!$u || !self::here()) return false;
        if (self::multi()) return \Core\Network\Network::isNetworkUser($u);
        return can(self::PERM);
    }

    // ------------------------------------------------------------------ Einstellungen (Auftragnehmer, E-Mails)

    public static function setting(string $k, string $default = ''): string
    {
        $v = app()->settings->get('avv.' . $k, null);
        return is_string($v) && $v !== '' ? $v : $default;
    }

    /** Auftragnehmer (Betreiber): Name, Anschrift, Ort, Unterzeichner, Unterschrift (Medien-ID) */
    public static function provider(): array
    {
        return [
            'name' => self::setting('provider_name', site_name()), 'street' => self::setting('provider_street'), 'zip_city' => self::setting('provider_zip_city'),
            'place' => self::setting('provider_place'), 'signer' => self::setting('provider_signer'), 'role' => self::setting('provider_role'),
            'signature' => (int) self::setting('provider_signature', '0'), 'notify' => self::setting('notify'),
        ];
    }

    /** Fehlende Pflichtangaben des Auftragnehmers (sonst keine Zeichnung möglich) */
    public static function providerMissing(): array
    {
        $p = self::provider();
        $out = [];
        foreach (['street' => __('Anschrift'), 'zip_city' => __('PLZ und Ort'), 'signer' => __('Name der unterzeichnenden Person')] as $k => $l) if (trim($p[$k]) === '') $out[] = $l;
        return $out;
    }

    // ------------------------------------------------------------------ Text zusammensetzen

    /**
     * Vertrag als HTML (Website-Vorschau und PDF): Text der Fassung mit ersetzten Platzhaltern, Anlage A (Angaben des Kunden),
     * Anlage B (TOM), Unterauftragnehmer, Unterschriften. $preview: Platzhalter als <span data-avv-ph>, die das Skript live füllt.
     */
    public static function document(array $version, array $c, bool $preview = false, bool $pdf = false): string
    {
        $p = self::provider();
        $vals = [
            'firma' => (string) ($c['company'] ?? ''), 'anschrift' => (string) ($c['street'] ?? ''), 'plz_ort' => (string) ($c['zip_city'] ?? ''),
            'unterzeichner' => (string) ($c['signer'] ?? ''), 'funktion' => (string) ($c['signer_role'] ?? ''),
            'datum' => !empty($c['signed_at']) ? date('d.m.Y', (int) strtotime((string) $c['signed_at'])) : date('d.m.Y'),
            'auftragnehmer' => $p['name'], 'auftragnehmer_anschrift' => trim($p['street'] . ', ' . $p['zip_city'], ', '), 'ort' => $p['place'] ?: $p['zip_city'],
        ];
        $labels = self::placeholders();
        $fill = function (string $html) use ($vals, $labels, $preview): string {
            return preg_replace_callback('~\{\{\s*([a-z_]+)\s*\}\}~', function ($m) use ($vals, $labels, $preview) {
                $k = $m[1];
                if (!array_key_exists($k, $vals)) return $m[0];
                $v = $vals[$k];
                if ($preview && in_array($k, ['firma', 'anschrift', 'plz_ort', 'unterzeichner', 'funktion'], true)) {
                    return '<span class="avv-ph" data-avv-ph="' . e($k) . '">' . e($v !== '' ? $v : '[' . ($labels[$k] ?? $k) . ']') . '</span>';
                }
                return e($v);
            }, $html) ?? $html;
        };
        $h = '<div class="avv-doc">';
        $h .= '<h1 class="avv-doc__title">' . e((string) $version['title']) . '</h1>';
        $h .= $fill(self::clean((string) $version['body']));
        $h .= self::signatures($c, $vals, $pdf);   // Unterschriften unter dem Vertragstext, Anlagen danach
        // Anlage A: Angaben des Kunden je Liste
        $details = is_array($c['details'] ?? null) ? $c['details'] : (json_decode((string) ($c['details'] ?? ''), true) ?: []);
        $lists = self::lists($version);
        if ($lists) {
            $h .= '<div class="avv-annex' . ($pdf ? ' avv-break' : '') . '"><h2>' . e(lt('Anlage A – Details zum Auftrag')) . '</h2>';
            foreach ($lists as $key => $l) {
                $chosen = array_values(array_intersect($l['options'], (array) ($details[$key] ?? [])));
                $other = trim((string) ($details[$key . '_other'] ?? ''));
                if ($other !== '') $chosen[] = $other;
                $h .= '<h3>' . e($l['label']) . '</h3>';
                if ($preview) $h .= '<ul data-avv-list="' . e($key) . '">' . ($chosen ? implode('', array_map(fn($o) => '<li>' . e($o) . '</li>', $chosen)) : '<li class="avv-ph">[' . e(lt('Ihre Auswahl')) . ']</li>') . '</ul>';
                else $h .= $chosen ? '<ul>' . implode('', array_map(fn($o) => '<li>' . e($o) . '</li>', $chosen)) . '</ul>' : '<p>–</p>';
            }
            $h .= '</div>';
        }
        if (trim(strip_tags((string) ($version['subprocessors'] ?? ''))) !== '') {
            $h .= '<div class="avv-annex"><h2>' . e(lt('Unterauftragnehmer')) . '</h2>' . $fill(self::clean((string) $version['subprocessors'])) . '</div>';
        }
        if (trim(strip_tags((string) ($version['toms'] ?? ''))) !== '') {
            $h .= '<div class="avv-annex' . ($pdf ? ' avv-break' : '') . '"><h2>' . e(lt('Anlage B – Technische und organisatorische Maßnahmen')) . '</h2>' . $fill(self::clean((string) $version['toms'])) . '</div>';
        }
        return $h . '</div>';
    }

    /** Unterschriften: Auftraggeber (getippt oder gezeichnet) und Auftragnehmer (hinterlegt) */
    private static function signatures(array $c, array $vals, bool $pdf): string
    {
        $p = self::provider();
        $cust = '';
        if (($c['sig_kind'] ?? '') === 'drawn' && self::validSignature((string) ($c['sig_png'] ?? ''))) {
            $cust = '<img class="avv-sig__img" src="' . e((string) $c['sig_png']) . '" alt="' . e(lt('Unterschrift')) . '">';
        } elseif (!empty($c['signed_at'])) {
            $cust = '<span class="avv-sig__typed">' . e((string) $c['signer']) . '</span>';
        }
        $prov = '';
        if ($p['signature'] && ($m = \Core\Media::find($p['signature'])) && str_starts_with((string) $m['mime'], 'image/')) {
            $file = \Core\Media::localFile($m);
            $prov = $pdf && is_file($file) ? '<img class="avv-sig__img" src="data:' . e((string) $m['mime']) . ';base64,' . base64_encode((string) file_get_contents($file)) . '" alt="">'
                : '<img class="avv-sig__img" src="' . e(\Core\Media::url($m)) . '" alt="">';
        } elseif ($p['signer'] !== '') {
            $prov = '<span class="avv-sig__typed">' . e($p['signer']) . '</span>';
        }
        $when = e($vals['ort'] !== '' ? $vals['ort'] . ', ' . $vals['datum'] : $vals['datum']);
        return '<table class="avv-sig"><tr>'
            . '<td><div class="avv-sig__box">' . $cust . '</div><div class="avv-sig__line">' . ($c['signer'] ?? '' ? e((string) $c['signer']) . (($c['signer_role'] ?? '') !== '' ? ', ' . e((string) $c['signer_role']) : '') . '<br>' : '') . e(lt('Auftraggeber')) . ': ' . e($vals['firma']) . '</div></td>'
            . '<td><div class="avv-sig__box">' . $prov . '</div><div class="avv-sig__line">' . ($p['signer'] !== '' ? e($p['signer']) . ($p['role'] !== '' ? ', ' . e($p['role']) : '') . '<br>' : '') . e(lt('Auftragnehmer')) . ': ' . e($p['name']) . '</div></td>'
            . '</tr></table><p class="avv-sig__when">' . $when . '</p>';
    }

    /** Anlage-A-Listen der Fassung: key => {label, options[]} */
    public static function lists(array $version): array
    {
        $raw = json_decode((string) ($version['lists'] ?? ''), true);
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $i => $l) {
            $opts = array_values(array_filter(array_map('trim', preg_split('~\R~', (string) ($l['options'] ?? '')) ?: [])));
            if (trim((string) ($l['label'] ?? '')) === '' || !$opts) continue;
            $out['l' . $i] = ['label' => trim((string) $l['label']), 'options' => $opts, 'other' => !empty($l['other'])];
        }
        return $out;
    }

    /** Rich-Text der Vorlage über die Whitelist des Kerns (Absätze, Überschriften, Listen, Links, Hervorhebungen) */
    public static function clean(string $html): string
    {
        return \Core\Sanitizer::block($html);
    }

    public static function validSignature(string $png): bool
    {
        if (strlen($png) > self::SIG_MAX || !str_starts_with($png, 'data:image/png;base64,')) return false;
        $bin = base64_decode(substr($png, 22), true);
        return $bin !== false && str_starts_with($bin, "\x89PNG\r\n\x1a\n") && @getimagesizefromstring($bin) !== false;
    }

    /** Neutraler Mustertext (Erstinstallation) – Betreiber passen ihn unter Verwaltung → AVV → Vertragstext an */
    public static function sampleVersion(): array
    {
        return Sample::version();
    }
}
