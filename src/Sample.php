<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Avv;

/**
 * Platzhaltertext für neue Installationen: Gliederung einer AVV nach Art. 28 DS-GVO mit Platzhaltern – KEIN fertiger
 * Vertrag. Betreiber ersetzen ihn unter Verwaltung → AVV → Vertragstext durch ihren eigenen, rechtlich geprüften Text.
 */
final class Sample
{
    public static function version(): array
    {
        $todo = fn(string $what) => '<p class="t-note">[' . e(lt('Platzhalter')) . ': ' . e($what) . ']</p>';
        $body = '<p>' . e(lt('gemäß Art. 28 DS-GVO')) . '</p>'
            . '<p><b>' . e(lt('zwischen dem Verantwortlichen:')) . '</b><br>{{firma}}<br>{{anschrift}}<br>{{plz_ort}}<br>' . e(lt('(nachstehend Auftraggeber genannt)')) . '</p>'
            . '<p><b>' . e(lt('und dem Auftragsverarbeiter:')) . '</b><br>{{auftragnehmer}}<br>{{auftragnehmer_anschrift}}<br>' . e(lt('(nachstehend Auftragnehmer genannt)')) . '</p>'
            . '<h2>' . e(lt('Präambel')) . '</h2>' . $todo(lt('Zweck der Vereinbarung und Bezug zum Hauptvertrag'))
            . '<h2>' . e(lt('§ 1 Gegenstand und Dauer')) . '</h2>' . $todo(lt('Gegenstand der Verarbeitung (siehe Anlage A), Laufzeit und Kündigung'))
            . '<h2>' . e(lt('§ 2 Art und Zweck der Verarbeitung, Daten und betroffene Personen')) . '</h2>' . $todo(lt('Verweis auf Anlage A'))
            . '<h2>' . e(lt('§ 3 Technische und organisatorische Maßnahmen')) . '</h2>' . $todo(lt('Pflichten nach Art. 32 DS-GVO, Verweis auf Anlage B'))
            . '<h2>' . e(lt('§ 4 Weisungen, Rechte und Pflichten')) . '</h2>' . $todo(lt('Weisungsgebundenheit, Pflichten von Auftragnehmer und Auftraggeber, Betroffenenrechte'))
            . '<h2>' . e(lt('§ 5 Unterauftragnehmer')) . '</h2>' . $todo(lt('Zustimmung, Liste der eingesetzten Unterauftragnehmer (siehe unten)'))
            . '<h2>' . e(lt('§ 6 Kontrollrechte, Löschung und Rückgabe')) . '</h2>' . $todo(lt('Kontrollen, Löschung bzw. Rückgabe der Daten nach Vertragsende'))
            . '<h2>' . e(lt('§ 7 Haftung und Schlussbestimmungen')) . '</h2>' . $todo(lt('Haftung, Schriftform, salvatorische Klausel'));
        $lists = [
            ['label' => lt('Leistungen'), 'options' => implode("\n", [lt('Hosting der Website'), lt('Pflege und Wartung der Website'), lt('E-Mail-Postfächer'), lt('Formulare und Anfragen')]), 'other' => true],
            ['label' => lt('Arten personenbezogener Daten'), 'options' => implode("\n", [lt('Kontaktdaten (Name, E-Mail, Telefon)'), lt('Nutzungsdaten (z. B. IP-Adressen in Protokollen)'), lt('Inhalte von Anfragen')]), 'other' => true],
            ['label' => lt('Betroffene Personen'), 'options' => implode("\n", [lt('Besucherinnen und Besucher der Website'), lt('Kundinnen und Kunden'), lt('Beschäftigte des Auftraggebers')]), 'other' => true],
        ];
        return [
            'title' => lt('Vereinbarung zur Auftragsverarbeitung'),
            'body' => $body,
            'lists' => json_encode($lists, JSON_UNESCAPED_UNICODE),
            'subprocessors' => $todo(lt('Name, Anschrift und Leistung je Unterauftragnehmer (z. B. Rechenzentrum)')),
            'toms' => $todo(lt('Technische und organisatorische Maßnahmen des Auftragnehmers')),
        ];
    }
}
