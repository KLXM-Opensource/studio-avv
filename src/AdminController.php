<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Avv;

use Core\Fields;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Theme;

/**
 * Verwaltung → AVV: Verträge (Liste, einladen, ansehen, PDF, neu zur Zeichnung senden), Vertragstext (Entwurf, PDF-Vorschau,
 * neue Fassung veröffentlichen), Einstellungen (Auftragnehmer, Unterschrift, E-Mails). Bei mehreren Websites nur auf der
 * Netzwerk-Website für Netzwerk-Konten (Avv::canManage).
 */
final class AdminController extends \Core\Http\Controllers\Admin\AdminController
{
    private function guard(Request $r): array
    {
        if (!Avv::here()) throw new HttpException(404);
        $u = $this->auth($r);
        if (!Avv::canManage($u)) throw new HttpException(403, Avv::multi() ? __('Die AVV verwaltet die Netzwerk-Administration.') : __('Keine Berechtigung.'));
        return $u;
    }

    private function page(string $view, array $vars = [], int $status = 200): Response
    {
        $vars += ['errors' => [], 'tab' => $view, 'title' => __('AVV')];
        $vars['flash'] = app()->session->takeFlash();
        $vars['css'] = [];
        $vars['user'] = app()->auth->user();
        $vars['counts'] = Repo::counts();
        $dir = dirname(__DIR__) . '/views/admin/';
        $GLOBALS['avv_shell'] = false;
        $content = '<link rel="stylesheet" href="' . e(Avv::asset('css/avv-admin.css')) . '">' . Theme::capture($dir . '_nav.php', $vars) . Theme::capture($dir . $view . '.php', $vars)
            . (!empty($GLOBALS['avv_shell']) ? \Core\AdminUi::close() : '');   // Bereichsliste (Core\AdminUi) schließen
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => 'avv']);
        return self::secure(new Response($html, $status));
    }

    private function to(string $path, string $type, string $msg): Response
    {
        app()->session->flash($type, $msg);
        return Response::redirect(url($path), 303);
    }

    private static function by(array $u): string
    {
        return (string) ($u['name'] ?: $u['email']);
    }

    // ------------------------------------------------------------------ Verträge

    public function index(Request $r): Response
    {
        $this->guard($r);
        Repo::ensureVersion();
        $filter = in_array($r->str('status'), ['open', 'signed', 'outdated', 'replaced', 'revoked'], true) ? $r->str('status') : '';
        return $this->page('index', ['rows' => Repo::list($filter, $r->str('q')), 'filter' => $filter, 'q' => $r->str('q'), 'current' => Repo::current(),
            'missing' => Avv::providerMissing(), 'pdfOk' => Pdf::available(), 'invite' => app()->session->get('_avv_old', []) ?: []]);
    }

    public static function inviteFields(): array
    {
        return [
            ['name' => 'email', 'label' => __('E-Mail-Adresse'), 'type' => 'email', 'required' => true, 'width' => 'half'],
            ['name' => 'contact', 'label' => __('Ansprechperson (Anrede in der E-Mail)'), 'type' => 'text', 'max' => 191, 'width' => 'half'],
            ['name' => 'company', 'label' => __('Firma (vorausgefüllt, änderbar)'), 'type' => 'text', 'max' => 191, 'width' => 'half'],
            ['name' => 'customer_no', 'label' => __('Kundennummer (optional)'), 'type' => 'text', 'max' => 60, 'width' => 'half'],
            ['name' => 'message', 'label' => __('Persönliche Nachricht (optional)'), 'type' => 'textarea', 'max' => 1000],
        ];
    }

    public function invite(Request $r): Response
    {
        $u = $this->guard($r);
        [$v, $errors] = Fields::sanitize(self::inviteFields(), (array) ($r->post['f'] ?? []));
        if ($errors) {
            app()->session->set('_avv_old', $v);
            return $this->to('/admin/avv#einladen', 'error', implode(' ', $errors));
        }
        app()->session->forget('_avv_old');
        if ($m = Avv::providerMissing()) return $this->to('/admin/avv/einstellungen', 'error', __('Bitte zuerst die Angaben des Auftragnehmers ergänzen: {list}', ['list' => implode(', ', $m)]));
        $v0 = Repo::ensureVersion();
        $id = Repo::create(['version_id' => (int) $v0['id'], 'status' => 'open', 'source' => 'invite', 'email' => mb_strtolower((string) $v['email']), 'contact' => (string) $v['contact'],
            'company' => (string) $v['company'], 'customer_no' => (string) $v['customer_no'], 'message' => (string) $v['message'], 'invited_by' => self::by($u), 'lang' => \Core\Lang::current()]);
        $err = Mail::invite(Repo::find($id));
        return $err ? $this->to('/admin/avv/' . $id, 'error', __('Einladung angelegt, aber die E-Mail ging nicht hinaus: {error}', ['error' => $err]))
            : $this->to('/admin/avv', 'success', __('Einladung an {email} gesendet.', ['email' => $v['email']]));
    }

    public function show(Request $r, string $id): Response
    {
        $this->guard($r);
        $c = Repo::find((int) $id) ?? throw new HttpException(404);
        // Frühere Zeichnungen desselben Kunden (Kette über replaces_id)
        $chain = []; $x = $c;
        while (!empty($x['replaces_id']) && count($chain) < 20 && ($x = Repo::find((int) $x['replaces_id']))) $chain[] = $x;
        $newer = app()->db->fetchAll('SELECT * FROM avv_contracts WHERE replaces_id = ? ORDER BY id DESC', [(int) $c['id']]);
        return $this->page('show', ['c' => $c, 'version' => Repo::version((int) $c['version_id']), 'current' => Repo::current(), 'older' => $chain, 'newer' => $newer]);
    }

    public function pdf(Request $r, string $id): Response
    {
        $this->guard($r);
        return Pdf::response(Repo::find((int) $id) ?? throw new HttpException(404));
    }

    /** PDF im Viewer des Kerns (PdfController::file) – ältere Kerne: Download */
    public function viewPdf(Request $r, string $id): Response
    {
        $this->guard($r);
        $c = Repo::find((int) $id) ?? throw new HttpException(404);
        $path = Pdf::path($c) ?? throw new HttpException(404);
        if (!method_exists(\Core\Http\Controllers\PdfController::class, 'file')) return Pdf::response($c);
        return \Core\Http\Controllers\PdfController::file(url('/admin/avv/' . $c['id'] . '/pdf'), Pdf::downloadName($c), (int) filesize($path), null, url('/admin/avv/' . $c['id']));
    }

    /** Offen: Einladung erneut senden · Gezeichnet: neue Zeichnung (aktuelle Fassung) anfordern – Angaben vorausgefüllt */
    public function resend(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $c = Repo::find((int) $id) ?? throw new HttpException(404);
        if ($c['status'] === 'open') {
            $err = Mail::invite($c);
            return $this->to('/admin/avv/' . $c['id'], $err ? 'error' : 'success', $err ? __('E-Mail ging nicht hinaus: {error}', ['error' => $err]) : __('Einladung erneut gesendet – der bisherige Link gilt nicht mehr.'));
        }
        $new = $this->renew($c, $u, (string) ($r->post['message'] ?? ''));
        $err = Mail::invite(Repo::find($new));
        return $this->to('/admin/avv/' . $new, $err ? 'error' : 'success', $err ? __('Einladung angelegt, aber die E-Mail ging nicht hinaus: {error}', ['error' => $err]) : __('Neue Zeichnung angefordert – Einladung an {email} gesendet.', ['email' => $c['email']]));
    }

    /** Alle Kunden mit gezeichneter älterer Fassung zur Zeichnung der aktuellen einladen */
    public function renewAll(Request $r): Response
    {
        $u = $this->guard($r);
        $n = 0; $fail = 0;
        foreach (Repo::list('outdated') as $c) {
            if (!filter_var((string) $c['email'], FILTER_VALIDATE_EMAIL)) continue;
            $open = app()->db->fetchValue("SELECT COUNT(*) FROM avv_contracts WHERE replaces_id = ? AND status = 'open'", [(int) $c['id']]);
            if ($open) continue;   // schon eingeladen
            $new = $this->renew($c, $u, (string) ($r->post['message'] ?? ''));
            Mail::invite(Repo::find($new)) ? $fail++ : $n++;
        }
        return $this->to('/admin/avv?status=open', $fail ? 'error' : 'success', __('{n} Einladungen zur neuen Fassung gesendet.', ['n' => $n]) . ($fail ? ' ' . __('{n} E-Mails gingen nicht hinaus.', ['n' => $fail]) : ''));
    }

    private function renew(array $c, array $u, string $message): int
    {
        $v = Repo::ensureVersion();
        return Repo::create(['version_id' => (int) $v['id'], 'status' => 'open', 'source' => 'invite', 'email' => $c['email'], 'contact' => $c['contact'] ?: $c['signer'],
            'company' => $c['company'], 'street' => $c['street'], 'zip_city' => $c['zip_city'], 'customer_no' => $c['customer_no'], 'signer' => $c['signer'],
            'signer_role' => $c['signer_role'], 'details' => $c['details'], 'message' => mb_substr(trim($message), 0, 1000), 'replaces_id' => (int) $c['id'],
            'invited_by' => self::by($u), 'lang' => $c['lang']]);
    }

    public function revoke(Request $r, string $id): Response
    {
        $this->guard($r);
        $c = Repo::find((int) $id) ?? throw new HttpException(404);
        if ($c['status'] === 'open') { Repo::delete((int) $c['id']); return $this->to('/admin/avv', 'success', __('Einladung zurückgezogen.')); }
        Repo::update((int) $c['id'], ['status' => 'revoked', 'dl_token' => '']);
        return $this->to('/admin/avv/' . $c['id'], 'success', __('Als beendet markiert. Das PDF bleibt zur Nachweisführung erhalten.'));
    }

    /**
     * Endgültig löschen (Vertrag, Unterschrift, PDF) – nur Administration, mit Warnung; bei unterzeichneten Verträgen muss
     * zusätzlich „Nachweis geht verloren“ bestätigt werden. Verweise neuerer Zeichnungen (replaces_id) bleiben als Zahl stehen.
     */
    public function delete(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $c = Repo::find((int) $id) ?? throw new HttpException(404);
        if (in_array($c['status'], ['signed', 'replaced', 'revoked'], true) && empty($r->post['confirm'])) {
            return $this->to('/admin/avv/' . $c['id'], 'error', __('Bitte bestätigen Sie, dass der Nachweis der Unterzeichnung verloren geht.'));
        }
        Repo::delete((int) $c['id']);
        try {
            if (Avv::multi()) \Core\Network\Network::log('avv.delete', site()->key, (string) $u['email'], ($c['company'] ?: $c['email']) . ' · ' . $c['status']);
        } catch (\Throwable) {
        }
        return $this->to('/admin/avv', 'success', __('Vereinbarung mit {name} gelöscht – einschließlich PDF.', ['name' => $c['company'] ?: $c['email']]));
    }

    // ------------------------------------------------------------------ Vertragstext

    public static function templateFields(): array
    {
        return [
            ['name' => 'title', 'label' => __('Titel'), 'type' => 'text', 'required' => true, 'max' => 191],
            ['name' => 'body', 'label' => __('Vertragstext'), 'type' => 'richtext', 'help' => __('Platzhalter wie {{firma}} stehen in der Liste rechts. Unterschriften folgen automatisch unter dem Text.')],
            ['name' => 'lists', 'label' => __('Anlage A – Auswahllisten für den Kunden'), 'type' => 'repeater', 'help' => __('Je Liste eine Überschrift und die Möglichkeiten (eine je Zeile). Der Kunde kreuzt an, was zutrifft.'),
                'fields' => [
                    ['name' => 'label', 'label' => __('Überschrift'), 'type' => 'text', 'max' => 120],
                    ['name' => 'options', 'label' => __('Möglichkeiten (eine je Zeile)'), 'type' => 'textarea', 'max' => 3000],
                    ['name' => 'other', 'label' => __('Feld „Weitere“ anbieten'), 'type' => 'bool'],
                ]],
            ['name' => 'subprocessors', 'label' => __('Unterauftragnehmer'), 'type' => 'richtext', 'help' => __('Name, Anschrift und Leistung je Unterauftragnehmer (z. B. Rechenzentrum, Registrar).')],
            ['name' => 'toms', 'label' => __('Anlage B – Technische und organisatorische Maßnahmen'), 'type' => 'richtext'],
        ];
    }

    private static function draftValues(): array
    {
        $d = Repo::draft();
        $d['lists'] = json_decode((string) ($d['lists'] ?? ''), true) ?: [];
        return $d;
    }

    public function template(Request $r): Response
    {
        $this->guard($r);
        Repo::ensureVersion();
        $draft = app()->settings->get('avv.draft', null);
        return $this->page('template', ['values' => self::draftValues(), 'isDraft' => is_array($draft), 'versions' => Repo::versions()]);
    }

    public function saveTemplate(Request $r): Response
    {
        $u = $this->guard($r);
        [$v, $errors] = Fields::sanitize(self::templateFields(), (array) ($r->post['f'] ?? []));
        if ($errors) return $this->page('template', ['values' => $v, 'isDraft' => true, 'versions' => Repo::versions(), 'errors' => $errors], 422);
        $v['lists'] = json_encode(array_values(array_map(fn($l) => ['label' => (string) ($l['label'] ?? ''), 'options' => (string) ($l['options'] ?? ''), 'other' => !empty($l['other'])], (array) $v['lists'])), JSON_UNESCAPED_UNICODE);
        Repo::saveDraft($v);
        if (($r->post['do'] ?? '') === 'publish') {
            $n = Repo::publish((string) ($r->post['note'] ?? ''), self::by($u));
            $outdated = Repo::counts()['outdated'];
            return $this->to('/admin/avv/vorlage', 'success', __('Fassung {n} veröffentlicht – neue Zeichnungen nutzen sie ab sofort.', ['n' => $n])
                . ($outdated ? ' ' . __('{n} Kunden haben eine ältere Fassung gezeichnet – unter „Verträge“ → „Ältere Fassung“ zur neuen Zeichnung einladen.', ['n' => $outdated]) : ''));
        }
        return $this->to('/admin/avv/vorlage', 'success', __('Entwurf gespeichert. Erst „Als neue Fassung veröffentlichen“ macht ihn für Kunden wirksam.'));
    }

    public function discardDraft(Request $r): Response
    {
        $this->guard($r);
        app()->settings->delete('avv.draft');
        return $this->to('/admin/avv/vorlage', 'success', __('Entwurf verworfen – es gilt die veröffentlichte Fassung.'));
    }

    /** PDF-Vorschau des Entwurfs mit Beispielangaben */
    public function preview(Request $r): Response
    {
        $this->guard($r);
        $d = Repo::draft() + ['number' => 0];
        $example = ['company' => __('Muster GmbH'), 'street' => __('Musterstraße 1'), 'zip_city' => __('12345 Musterstadt'), 'signer' => __('Erika Musterfrau'),
            'signer_role' => __('Geschäftsführung'), 'signed_at' => now(), 'sig_kind' => 'typed', 'details' => json_encode(array_map(fn($l) => $l['options'], Avv::lists($d)))];
        $bytes = Pdf::render($d, $example, __('Vorschau – nicht unterzeichnet'));
        return (new Response($bytes))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'inline; filename="AVV-Vorschau.pdf"')->header('Cache-Control', 'no-store');
    }

    // ------------------------------------------------------------------ Einstellungen

    public static function settingsFields(): array
    {
        [$ds, $dt] = Mail::inviteDefault();
        $site = [];
        if (Avv::multi()) {
            $opts = [];
            foreach (\Core\Sites::all() as $k => $cfg) $opts[$k] = (new \Core\Site($k, $cfg))->label() . ' (' . $k . ')';
            $site = [['type' => 'heading', 'label' => __('Website')],
                ['name' => '_site', 'label' => __('AVV läuft auf der Website'), 'type' => 'select', 'options' => $opts,
                    'help' => __('Dort zeichnen Kunden (Block „AVV abschließen“, Einladungslinks) und dort liegen Text, Angaben und Verträge. Verwalten kann sie nur die Netzwerk-Administration. Nach einem Wechsel: auf der neuen Website die Erweiterung einschalten und die Angaben dort einrichten – Verträge ziehen nicht mit um.')]];
        }
        return [...$site,
            ['type' => 'heading', 'label' => __('Auftragnehmer (Ihr Unternehmen)')],
            ['name' => 'provider_name', 'label' => __('Name'), 'type' => 'text', 'max' => 191, 'help' => __('Leer: Name der Website.')],
            ['name' => 'provider_street', 'label' => __('Straße und Hausnummer'), 'type' => 'text', 'max' => 191, 'width' => 'half', 'required' => true],
            ['name' => 'provider_zip_city', 'label' => __('PLZ und Ort'), 'type' => 'text', 'max' => 191, 'width' => 'half', 'required' => true],
            ['name' => 'provider_place', 'label' => __('Ort für „Ort, Datum“'), 'type' => 'text', 'max' => 80, 'width' => 'half', 'help' => __('Leer: PLZ und Ort.')],
            ['type' => 'heading', 'label' => __('Gegenzeichnung')],
            ['name' => 'provider_signer', 'label' => __('Name der unterzeichnenden Person'), 'type' => 'text', 'max' => 191, 'width' => 'half', 'required' => true],
            ['name' => 'provider_role', 'label' => __('Funktion'), 'type' => 'text', 'max' => 120, 'width' => 'half'],
            ['name' => 'provider_signature', 'label' => __('Unterschrift als Bild (optional)'), 'type' => 'media', 'help' => __('PNG mit transparentem Hintergrund. Ohne Bild steht der Name. Erscheint automatisch in jeder unterzeichneten Vereinbarung.')],
            ['type' => 'heading', 'label' => __('PDF')],
            ['name' => 'pdf_font', 'label' => __('Schrift im PDF (Hausschrift)'), 'type' => 'select', 'options' => ['' => __('DejaVu Sans (Standard)')] + array_slice(PdfFont::OPTIONS, 1, null, true) + [PdfFont::CUSTOM => __('Andere Schrift …')],
                'help' => __('Freie Schriften (Google Fonts / Fontsource) werden beim Speichern einmal geladen und auf dem Server abgelegt.')],
            ['name' => 'pdf_font_custom', 'label' => __('Name der Schrift'), 'type' => 'text', 'max' => 80, 'placeholder' => 'Montserrat', 'show_if' => ['pdf_font' => [PdfFont::CUSTOM]],
                'help' => __('So wie bei Google Fonts, z. B. „Source Sans 3“ oder „Montserrat“. Nur frei lizenzierte Schriften.')],
            ['type' => 'heading', 'label' => __('E-Mails')],
            ['name' => 'notify', 'label' => __('Hinweis bei neuer Zeichnung an'), 'type' => 'text', 'max' => 500, 'help' => __('Eine oder mehrere Adressen, mit Komma getrennt – erhält das PDF.')],
            ['name' => 'invite_subject', 'label' => __('Einladung: Betreff'), 'type' => 'text', 'max' => 191, 'placeholder' => $ds],
            ['name' => 'invite_text', 'label' => __('Einladung: Text'), 'type' => 'textarea', 'max' => 4000, 'placeholder' => $dt,
                'help' => __('Leer: Standardtext. Platzhalter: {name} {firma} {site} {link} {tage} {nachricht}')],
        ];
    }

    public function settings(Request $r): Response
    {
        $this->guard($r);
        $vals = [];
        foreach (self::settingsFields() as $f) if (isset($f['name'])) $vals[$f['name']] = app()->settings->get('avv.' . $f['name'], '');
        $vals['_site'] = Avv::siteKey();
        return $this->page('settings', ['values' => $vals]);
    }

    public function saveSettings(Request $r): Response
    {
        $this->guard($r);
        [$v, $errors] = Fields::sanitize(self::settingsFields(), (array) ($r->post['f'] ?? []));
        if ($errors) return $this->page('settings', ['values' => $v, 'errors' => $errors], 422);
        // Website der AVV (Netzwerk): installationsweit, nicht in den Einstellungen dieser Website
        $newSite = (string) ($v['_site'] ?? '');
        unset($v['_site']);
        foreach ($v as $k => $val) {
            $val = is_scalar($val) || $val === null ? trim((string) $val) : json_encode($val);
            $val === '' ? app()->settings->delete('avv.' . $k) : app()->settings->set('avv.' . $k, $val);
        }
        if ($err = PdfFont::ensure(PdfFont::current())) return $this->to('/admin/avv/einstellungen', 'error', $err);
        if (Avv::multi() && $newSite !== '' && $newSite !== Avv::siteKey()) {
            Avv::setSiteKey($newSite);
            try { \Core\Network\Network::log('avv.site', $newSite, (string) (app()->auth->user()['email'] ?? ''), 'AVV-Website gewechselt'); } catch (\Throwable) {}
            $label = (new \Core\Site($newSite, \Core\Sites::all()[$newSite]))->label();
            app()->session->flash('success', __('Die AVV läuft jetzt auf „{site}“. Dort die Erweiterung einschalten (Funktionen & Erweiterungen) und unter AVV → Einstellungen die Angaben einrichten.', ['site' => $label]));
            return Response::redirect(url('/admin'), 303);
        }
        return $this->to('/admin/avv/einstellungen', 'success', __('Gespeichert.'));
    }
}
