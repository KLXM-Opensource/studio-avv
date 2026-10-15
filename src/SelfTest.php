<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Avv;

/**
 * php bin/console avv:selftest --site=… – Platzhalter und Escaping, Anlage A (nur angebotene Optionen), Prüfung der Eingaben,
 * gezeichnete Unterschrift (nur PNG, Größe), Links (nur Hash gespeichert, Ablauf, Zustand), Fassungen, PDF (dompdf, Prüfsumme).
 * Alles in einer Transaktion, die zurückgerollt wird; das Test-PDF wird gelöscht.
 */
final class SelfTest
{
    public static function run(): int
    {
        $ok = 0; $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; echo "  ✓ $what\n"; return; }
            $fails[] = $what; echo "  ✗ $what – erwartet " . var_export($want, true) . ', erhalten ' . var_export($got, true) . "\n";
        };
        $db = app()->db;
        foreach (Repo::tables() as $name => $def) { try { $db->ensureTable($name, $def); } catch (\Throwable) {} }
        $db->pdo->beginTransaction();
        $draft = app()->settings->get('avv.draft', null);
        $file = null;
        try {
            // Klassen laden (Signaturen gegen den Kern – z. B. gleichnamige Methoden der Elternklasse)
            $eq('Verwaltung und Website-Controller laden', [class_exists(AdminController::class), class_exists(SiteController::class)], [true, true]);
            $eq('AVV-Website im Netzwerk gültig', isset(\Core\Sites::all()[Avv::siteKey()]), true);
            $v = ['id' => 0, 'number' => 99, 'title' => 'Test <AVV>', 'body' => '<p>Zwischen {{firma}} und {{auftragnehmer}} – {{unbekannt}}</p><script>alert(1)</script>',
                'lists' => json_encode([['label' => 'Daten', 'options' => "Kontakt\nNutzung", 'other' => true]]), 'subprocessors' => '', 'toms' => '<p>TOM</p>'];
            $html = Avv::document($v, ['company' => 'Muster <b>GmbH</b>', 'details' => json_encode(['l0' => ['Kontakt', 'Böse'], 'l0_other' => 'Extra'])]);
            $eq('Platzhalter ersetzt und escaped', str_contains($html, 'Muster &lt;b&gt;GmbH&lt;/b&gt;'), true);
            $eq('unbekannter Platzhalter bleibt stehen', str_contains($html, '{{unbekannt}}'), true);
            $eq('Skript aus dem Vertragstext entfernt', str_contains($html, '<script'), false);
            $eq('Anlage A: nur angebotene Optionen + „Weitere“', [str_contains($html, '<li>Kontakt</li>'), str_contains($html, 'Böse'), str_contains($html, '<li>Extra</li>')], [true, false, true]);
            $prev = Avv::document($v, [], true);
            $eq('Vorschau: Platzhalter als Feld fürs Skript', str_contains($prev, 'data-avv-ph="firma"'), true);

            [$d, $e] = SiteController::validate(['company' => 'A', 'street' => 'B 1', 'zip_city' => '1 C', 'email' => 'x@example.org', 'signer' => 'Erika', 'agree' => '1', 'd' => ['l0' => ['Kontakt', 'X']]], $v);
            $eq('gültige Eingaben ohne Fehler', $e, []);
            $eq('Auswahl gefiltert', json_decode($d['details'], true)['l0'], ['Kontakt']);
            [, $e] = SiteController::validate(['email' => 'kaputt'], $v);
            $eq('Pflichtfelder und Zustimmung geprüft', array_keys($e), ['company', 'street', 'zip_city', 'signer', 'email', 'agree']);
            [, $e] = SiteController::validate(['company' => 'A', 'street' => 'B', 'zip_city' => 'C', 'email' => 'x@example.org', 'signer' => 'E', 'agree' => '1', 'sig_kind' => 'drawn', 'sig_png' => 'data:image/svg+xml;base64,PHN2Zz4='], $v);
            $eq('gezeichnete Unterschrift nur als PNG', isset($e['sig_png']), true);
            $img = imagecreatetruecolor(4, 4); ob_start(); imagepng($img); $png = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
            $eq('echtes PNG akzeptiert', Avv::validSignature($png), true);
            $eq('zu große Unterschrift abgelehnt', Avv::validSignature('data:image/png;base64,' . str_repeat('A', Avv::SIG_MAX)), false);

            // Fassungen und Links
            app()->settings->set('avv.draft', ['title' => 'Selbsttest', 'body' => '<p>{{firma}}</p>', 'lists' => '[]', 'subprocessors' => '', 'toms' => '']);
            $n = Repo::publish('Test', 'selftest');
            $cur = Repo::current();
            $eq('Entwurf als neue Fassung veröffentlicht', [(int) $cur['number'], $cur['title']], [$n, 'Selbsttest']);
            $id = Repo::create(['version_id' => (int) $cur['id'], 'status' => 'open', 'email' => 'kunde@example.org', 'company' => 'Kunde']);
            $t = Repo::token($id, 'sign', 1);
            $row = Repo::find($id);
            $eq('Link nur als Hash gespeichert', [$row['sign_token'] === hash('sha256', $t), str_contains(json_encode($row), $t)], [true, false]);
            $eq('Link öffnet offene Vereinbarung', (int) (Repo::byToken($t, 'sign')['id'] ?? 0), $id);
            $eq('Download-Link gilt nicht für offene', Repo::byToken($t, 'dl'), null);
            Repo::update($id, ['sign_expires' => date('Y-m-d H:i:s', time() - 5)]);
            $eq('abgelaufener Link ungültig', Repo::byToken($t, 'sign'), null);
            $eq('ungültiges Format abgelehnt', Repo::byToken('../../etc', 'sign'), null);

            // PDF
            if (Pdf::available()) {
                $prov = app()->settings->get('avv.provider_signer', null);
                app()->settings->set('avv.provider_signer', 'Test Person');
                Repo::update($id, ['status' => 'signed', 'signed_at' => now(), 'signer' => 'Erika Musterfrau', 'company' => 'Ä Ö Ü GmbH', 'sig_kind' => 'drawn', 'sig_png' => $png]);
                [$file, $hash] = Pdf::store(Repo::find($id));
                $bytes = (string) file_get_contents(Pdf::dir() . '/' . $file);
                $eq('PDF erzeugt (Kopf %PDF, Prüfsumme stimmt)', [str_starts_with($bytes, '%PDF'), hash('sha256', $bytes) === $hash], [true, true]);
                $prov === null ? app()->settings->delete('avv.provider_signer') : app()->settings->set('avv.provider_signer', $prov);
            } else {
                echo "  – PDF übersprungen (dompdf fehlt)\n";
            }
        } finally {
            if ($file) @unlink(Pdf::dir() . '/' . $file);
            $db->pdo->rollBack();
            $draft === null ? app()->settings->delete('avv.draft') : app()->settings->set('avv.draft', $draft);
        }
        echo "\n  " . $ok . ' Prüfungen bestanden, ' . count($fails) . " fehlgeschlagen\n";
        return $fails ? 1 : 0;
    }
}
