<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Avv;

use Core\Db\Table;

/**
 * Datenbank (Datenbank der Website, Präfix avv_):
 *
 *  avv_versions   veröffentlichte Fassungen (Nummer, Titel, Text, Anlage-A-Listen, Unterauftragnehmer, TOM) – unveränderlich,
 *                 gezeichnete Verträge verweisen darauf. Der Entwurf liegt in den Einstellungen (avv.draft).
 *  avv_contracts  ein Vertrag je Zeichnung: offen (Einladung) | signed | replaced (durch neue Zeichnung ersetzt) | revoked;
 *                 Angaben des Kunden, Unterschrift, Prüfsumme des PDFs. Links (Zeichnen, Herunterladen) nur als SHA-256.
 * PDFs liegen außerhalb des Webroots: storage/…/avv/{id}-{prüfsumme}.pdf
 */
final class Repo
{
    public const STATUSES = ['open', 'signed', 'replaced', 'revoked'];

    /** @return array<string, callable(Table): mixed> */
    public static function tables(): array
    {
        return [
            'avv_versions' => fn(Table $t) => $t->id()
                ->column('number', 'int', ['null' => false])
                ->column('title', 'string', ['length' => 191, 'null' => false])
                ->column('body', 'text')->column('lists', 'text')->column('subprocessors', 'text')->column('toms', 'text')
                ->column('note', 'string', ['length' => 191, 'default' => ''])
                ->column('created_by', 'string', ['length' => 191, 'default' => ''])
                ->column('created_at', 'datetime')
                ->unique('number'),
            'avv_contracts' => fn(Table $t) => $t->id()
                ->column('version_id', 'int')
                ->column('status', 'string', ['length' => 12, 'default' => 'open'])
                ->column('source', 'string', ['length' => 12, 'default' => 'self'])   // self (Website) | invite
                ->column('email', 'string', ['length' => 191, 'default' => ''])
                ->column('contact', 'string', ['length' => 191, 'default' => ''])     // Name aus der Einladung
                ->column('company', 'string', ['length' => 191, 'default' => ''])
                ->column('street', 'string', ['length' => 191, 'default' => ''])
                ->column('zip_city', 'string', ['length' => 191, 'default' => ''])
                ->column('customer_no', 'string', ['length' => 60, 'default' => ''])
                ->column('signer', 'string', ['length' => 191, 'default' => ''])
                ->column('signer_role', 'string', ['length' => 120, 'default' => ''])
                ->column('details', 'text')
                ->column('sig_kind', 'string', ['length' => 8, 'default' => ''])     // typed | drawn
                ->column('sig_png', 'text')
                ->column('signed_at', 'datetime')
                ->column('sign_ip', 'string', ['length' => 64, 'default' => ''])     // HMAC der IP, nie die IP
                ->column('sign_ua', 'string', ['length' => 255, 'default' => ''])
                ->column('pdf_file', 'string', ['length' => 191, 'default' => ''])
                ->column('pdf_hash', 'string', ['length' => 64, 'default' => ''])
                ->column('sign_token', 'string', ['length' => 64, 'default' => ''])  // SHA-256 des Einladungslinks
                ->column('sign_expires', 'datetime')
                ->column('dl_token', 'string', ['length' => 64, 'default' => ''])    // SHA-256 des Download-Links für den Kunden
                ->column('dl_expires', 'datetime')
                ->column('message', 'text')
                ->column('replaces_id', 'int')
                ->column('lang', 'string', ['length' => 10, 'default' => ''])
                ->column('invited_by', 'string', ['length' => 191, 'default' => ''])
                ->column('invited_at', 'datetime')->column('created_at', 'datetime')->column('updated_at', 'datetime')
                ->index('status')->index('email')->index('sign_token')->index('dl_token'),
        ];
    }

    private static function db(): \Core\Database
    {
        return app()->db;
    }

    // ------------------------------------------------------------------ Fassungen

    public static function current(): ?array
    {
        return self::db()->fetch('SELECT * FROM avv_versions ORDER BY number DESC LIMIT 1') ?: null;
    }

    public static function version(int $id): ?array
    {
        return self::db()->fetch('SELECT * FROM avv_versions WHERE id = ?', [$id]) ?: null;
    }

    public static function versions(): array
    {
        return self::db()->fetchAll('SELECT v.*, (SELECT COUNT(*) FROM avv_contracts c WHERE c.version_id = v.id AND c.status = ?) AS signed FROM avv_versions v ORDER BY number DESC', ['signed']);
    }

    /** Entwurf (Einstellungen) – ohne Entwurf: aktuelle Fassung bzw. Platzhaltertext */
    public static function draft(): array
    {
        $d = app()->settings->get('avv.draft', null);
        if (is_array($d) && isset($d['title'])) return $d;
        $c = self::current();
        return $c ? array_intersect_key($c, array_flip(['title', 'body', 'lists', 'subprocessors', 'toms'])) : Sample::version();
    }

    public static function saveDraft(array $d): void
    {
        app()->settings->set('avv.draft', array_intersect_key($d, array_flip(['title', 'body', 'lists', 'subprocessors', 'toms'])));
    }

    /** Entwurf als neue Fassung veröffentlichen → Nummer */
    public static function publish(string $note, string $by): int
    {
        $d = self::draft();
        $n = (int) self::db()->fetchValue('SELECT COALESCE(MAX(number), 0) FROM avv_versions') + 1;
        self::db()->insert('avv_versions', ['number' => $n, 'title' => (string) $d['title'], 'body' => Avv::clean((string) $d['body']), 'lists' => (string) $d['lists'],
            'subprocessors' => Avv::clean((string) $d['subprocessors']), 'toms' => Avv::clean((string) $d['toms']), 'note' => mb_substr($note, 0, 191),
            'created_by' => $by, 'created_at' => now()]);
        app()->settings->delete('avv.draft');
        return $n;
    }

    /** Erste Fassung (Platzhaltertext), damit Block und Einladungen sofort funktionieren */
    public static function ensureVersion(): array
    {
        if ($c = self::current()) return $c;
        self::publish(lt('Platzhaltertext'), 'system');
        return self::current();
    }

    // ------------------------------------------------------------------ Verträge

    public static function find(int $id): ?array
    {
        return self::db()->fetch('SELECT * FROM avv_contracts WHERE id = ?', [$id]) ?: null;
    }

    /** Liste für die Verwaltung; $filter: '' | open | signed | outdated (gezeichnet, ältere Fassung) | replaced | revoked */
    public static function list(string $filter = '', string $q = ''): array
    {
        $cur = self::current();
        $where = []; $args = [];
        if ($filter === 'outdated') { $where[] = "c.status = 'signed' AND c.version_id <> ?"; $args[] = (int) ($cur['id'] ?? 0); }
        elseif (in_array($filter, self::STATUSES, true)) { $where[] = 'c.status = ?'; $args[] = $filter; }
        if (($q = trim($q)) !== '') { $where[] = '(c.company LIKE ? OR c.email LIKE ? OR c.contact LIKE ? OR c.signer LIKE ? OR c.customer_no LIKE ?)'; array_push($args, ...array_fill(0, 5, '%' . $q . '%')); }
        return self::db()->fetchAll('SELECT c.*, v.number AS version_number FROM avv_contracts c LEFT JOIN avv_versions v ON v.id = c.version_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY COALESCE(c.signed_at, c.invited_at, c.created_at) DESC, c.id DESC LIMIT 500', $args);
    }

    public static function counts(): array
    {
        $cur = (int) (self::current()['id'] ?? 0);
        $r = self::db()->fetch("SELECT SUM(status = 'open') AS open, SUM(status = 'signed') AS signed, SUM(status = 'signed' AND version_id <> ?) AS outdated FROM avv_contracts", [$cur]) ?: [];
        return ['open' => (int) ($r['open'] ?? 0), 'signed' => (int) ($r['signed'] ?? 0), 'outdated' => (int) ($r['outdated'] ?? 0)];
    }

    public static function create(array $row): int
    {
        return (int) self::db()->insert('avv_contracts', $row + ['created_at' => now(), 'updated_at' => now()]);
    }

    public static function update(int $id, array $row): void
    {
        self::db()->update('avv_contracts', $row + ['updated_at' => now()], 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        if (($c = self::find($id)) && $c['pdf_file'] !== '') @unlink(Pdf::dir() . '/' . basename((string) $c['pdf_file']));
        self::db()->query('DELETE FROM avv_contracts WHERE id = ?', [$id]);
    }

    // ------------------------------------------------------------------ Links (nur SHA-256 gespeichert)

    /** Neuer Link → Klartext-Token (einmal sichtbar), gespeichert als Hash */
    public static function token(int $id, string $kind, int $days): string
    {
        $t = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $col = $kind === 'sign' ? 'sign' : 'dl';
        self::update($id, [$col . '_token' => hash('sha256', $t), $col . '_expires' => date('Y-m-d H:i:s', time() + $days * 86400)]);
        return $t;
    }

    /** Vertrag zu einem gültigen Link (Zeichnen: nur offene Verträge; Herunterladen: nur gezeichnete) */
    public static function byToken(string $token, string $kind): ?array
    {
        if (!preg_match('~^[A-Za-z0-9_-]{20,64}$~', $token)) return null;
        $col = $kind === 'sign' ? 'sign' : 'dl';
        $c = self::db()->fetch("SELECT * FROM avv_contracts WHERE {$col}_token = ?", [hash('sha256', $token)]);
        if (!$c || strtotime((string) $c[$col . '_expires']) < time()) return null;
        if ($kind === 'sign' && $c['status'] !== 'open') return null;
        if ($kind === 'dl' && !in_array($c['status'], ['signed', 'replaced'], true)) return null;
        return $c;
    }
}
