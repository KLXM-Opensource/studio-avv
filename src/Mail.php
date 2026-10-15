<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Avv;

use Core\Mailer;

/**
 * E-Mails der AVV (reiner Text, Zweck „AVV“ – Konto unter Grundeinstellungen → E-Mail-Versand → Verwendung wählbar):
 * Einladung zur Zeichnung, Kopie an den Kunden (PDF im Anhang + Download-Link), Hinweis an den Betreiber.
 * Einladungstext je Website änderbar (Einstellungen), Platzhalter {name} {firma} {site} {link} {tage} {nachricht}.
 */
final class Mail
{
    public const PURPOSE = 'ext:avv.' . Avv::MAIL;

    public static function inviteDefault(): array
    {
        return [lt('Bitte zeichnen: Vereinbarung zur Auftragsverarbeitung – {site}'),
            lt('Guten Tag {name},') . "\n\n"
            . lt('für unsere Zusammenarbeit benötigen wir eine Vereinbarung zur Auftragsverarbeitung (AVV) nach Art. 28 DS-GVO. Über diesen Link füllen Sie sie Schritt für Schritt aus und unterzeichnen sie elektronisch (gültig {tage} Tage):') . "\n\n"
            . '{link}' . "\n\n{nachricht}"
            . lt('Danach erhalten Sie die unterzeichnete Vereinbarung als PDF.') . "\n\n"
            . lt('Mit freundlichen Grüßen') . "\n{site}"];
    }

    public static function inviteTemplate(): array
    {
        [$ds, $dt] = self::inviteDefault();
        return [Avv::setting('invite_subject', $ds), Avv::setting('invite_text', $dt)];
    }

    /** Einladung senden (neuer Link, alter wird ungültig) → Fehlertext oder null */
    public static function invite(array $c): ?string
    {
        $token = Repo::token((int) $c['id'], 'sign', Avv::INVITE_DAYS);
        Repo::update((int) $c['id'], ['invited_at' => now()]);
        [$subject, $text] = self::inviteTemplate();
        $msg = trim((string) ($c['message'] ?? ''));
        if (!str_contains($text, '{link}')) $text .= "\n\n{link}";
        $vars = ['{name}' => $c['contact'] ?: lt('Damen und Herren'), '{firma}' => (string) $c['company'], '{site}' => Avv::provider()['name'],
            '{link}' => absolute_url(Avv::BASE . '/zeichnen/' . $token), '{tage}' => (string) Avv::INVITE_DAYS, '{nachricht}' => $msg !== '' ? $msg . "\n\n" : ''];
        return Mailer::send(strtr($subject, $vars), strtr($text, $vars), [(string) $c['email']], ['purpose' => self::PURPOSE]);
    }

    /** Nach der Zeichnung: Kopie an den Kunden (PDF im Anhang, dazu Link) und Hinweis an den Betreiber */
    public static function signed(array $c, string $dlToken): void
    {
        $p = Avv::provider();
        $file = Pdf::path($c);
        $attach = $file && filesize($file) < 4_000_000 ? [['name' => Pdf::downloadName($c), 'data' => (string) file_get_contents($file), 'type' => 'application/pdf']] : [];
        $link = absolute_url(Avv::BASE . '/pdf/' . $dlToken);
        if (filter_var((string) $c['email'], FILTER_VALIDATE_EMAIL)) {
            Mailer::send(lt('Ihre unterzeichnete Vereinbarung zur Auftragsverarbeitung – {site}', ['site' => $p['name']]),
                lt('Guten Tag {name},', ['name' => $c['signer'] ?: $c['contact']]) . "\n\n"
                . lt('vielen Dank – die Vereinbarung zur Auftragsverarbeitung ist unterzeichnet. Sie finden sie als PDF im Anhang.') . "\n\n"
                . lt('Herunterladen ({n} Tage gültig): {link}', ['n' => Avv::DOWNLOAD_DAYS, 'link' => $link]) . "\n\n"
                . lt('Prüfsumme (SHA-256): {hash}', ['hash' => $c['pdf_hash']]) . "\n\n" . $p['name'],
                [(string) $c['email']], ['purpose' => self::PURPOSE, 'attach' => $attach]);
        }
        $to = array_filter(array_map('trim', explode(',', $p['notify'])), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL));
        if ($to) {
            Mailer::send(__('AVV unterzeichnet: {firma}', ['firma' => $c['company']]),
                __('{signer} hat die AVV für {firma} unterzeichnet (Fassung {v}).', ['signer' => $c['signer'], 'firma' => $c['company'], 'v' => Repo::version((int) $c['version_id'])['number'] ?? '?']) . "\n\n"
                . absolute_url('/admin/avv/' . (int) $c['id']),
                $to, ['purpose' => self::PURPOSE, 'attach' => $attach]);
        }
    }
}
