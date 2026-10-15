<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Avv;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\SpamGuard;

/**
 * Website: /avv/… – Assistent aus der Einladung (/avv/zeichnen/{token}), Zeichnen (POST /avv/zeichnen – auch aus dem Block
 * „AVV abschließen“ auf einer beliebigen Seite), Ergebnis (/avv/fertig/{token}) und PDF (/avv/pdf/{token}).
 * Schutz wie bei den Formularen des Kerns (Core\SpamGuard: signiertes Zeit-Token, Honigtopf, Mengenbegrenzung je IP) – der
 * Block steht auf gecachten Seiten, deshalb kein Sitzungs-Token; das Skript holt beim Laden ein frisches (/avv/token).
 */
final class SiteController
{
    public const FORM = 'avv';

    private function guard(): void
    {
        if (!Avv::here()) throw new HttpException(404);
    }

    /** Seite im Layout des Kits (nie im Seiten-Cache) */
    public function page(string $view, array $vars, string $title, int $status = 200): Response
    {
        $theme = app()->theme;
        $page = ['id' => 0, 'title' => $title, 'slug' => 'avv', 'is_home' => 0, 'meta_description' => '', 'noindex' => 1, 'status' => 'published'];
        app()->currentPage = $page;
        $content = '<link rel="stylesheet" href="' . e(Avv::asset('css/avv.css')) . '">'
            . '<div class="' . e(app()->theme->def['container_class'] ?? 'wrap') . ' avv-wrap">' . \Core\Theme::capture(dirname(__DIR__) . '/views/site/' . $view . '.php', $vars + ['title' => $title]) . '</div>';
        $seo = \Core\Seo::forError(200);
        $seo['title'] = $title;
        $html = $theme->render('layout', ['page' => $page, 'content' => $content, 'seo' => $seo, 'editor' => null, 'toolbar' => null,
            'extraCss' => $theme->conditionalCss(['form'])]);
        return (new \Core\Http\Controllers\SiteController())->respond($html, false, $status, true)
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex');
    }

    private function message(string $title, string $text, int $status = 200): Response
    {
        return $this->page('message', ['text' => $text], $title, $status);
    }

    /** Frisches Prüf-Token für den Assistenten (Block auf gecachter Seite) */
    public function token(Request $r): Response
    {
        $this->guard();
        return Response::json(SpamGuard::challenge(self::FORM))->header('Cache-Control', 'no-store');
    }

    /** Assistent aus der Einladung */
    public function invite(Request $r, string $token): Response
    {
        $this->guard();
        $c = Repo::byToken($token, 'sign');
        if (!$c) return $this->message(lt('Link nicht mehr gültig'), lt('Dieser Link ist abgelaufen oder die Vereinbarung wurde bereits unterzeichnet. Bitte wenden Sie sich an {name}.', ['name' => Avv::provider()['name']]), 410);
        $v = Repo::ensureVersion();
        return $this->page('wizard', ['version' => $v, 'contract' => $c, 'token' => $token, 'errors' => []], (string) $v['title']);
    }

    /** Zeichnen: prüfen, Vertrag anlegen bzw. Einladung abschließen, PDF ablegen, E-Mails, weiter zum Ergebnis */
    public function sign(Request $r): Response
    {
        $this->guard();
        $in = $r->post;
        $token = (string) ($in['invite'] ?? '');
        $c = $token !== '' ? Repo::byToken($token, 'sign') : null;
        if ($token !== '' && !$c) return $this->message(lt('Link nicht mehr gültig'), lt('Dieser Link ist abgelaufen oder die Vereinbarung wurde bereits unterzeichnet.'), 410);
        $spam = SpamGuard::check(self::FORM, $in, $r->ip());
        if (!$spam['ok']) {
            if (!empty($spam['silent'])) return $this->message(lt('Vielen Dank'), lt('Ihre Angaben sind eingegangen.'));
            return $this->message(lt('Bitte erneut versuchen'), (string) ($spam['error'] ?? lt('Das hat nicht geklappt.')), 422);
        }
        if (!Pdf::available() || Avv::providerMissing()) return $this->message(lt('Gerade nicht möglich'), lt('Die Vereinbarung kann gerade nicht unterzeichnet werden. Bitte wenden Sie sich an {name}.', ['name' => Avv::provider()['name']]), 503);
        $v = Repo::ensureVersion();
        [$data, $errors] = self::validate($in, $v);
        if ($errors) return $this->page('wizard', ['version' => $v, 'contract' => ($c ?? []) + $data, 'token' => $token, 'errors' => $errors, 'old' => $in], (string) $v['title'], 422);

        $now = now();
        $row = $data + ['version_id' => (int) $v['id'], 'status' => 'signed', 'signed_at' => $now, 'lang' => \Core\Lang::current(),
            'sign_ip' => hash_hmac('sha256', $r->ip(), app()->key()), 'sign_ua' => mb_substr((string) ($r->server['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'sign_token' => '', 'sign_expires' => null];
        if ($c) { Repo::update((int) $c['id'], $row); $id = (int) $c['id']; }
        else $id = Repo::create($row + ['source' => 'self']);
        $c = Repo::find($id);
        try {
            [$file, $hash] = Pdf::store($c);
        } catch (\Throwable $e) {
            error_log('[avv] PDF: ' . $e->getMessage());
            Repo::update($id, ['status' => 'open']);
            return $this->message(lt('Gerade nicht möglich'), lt('Das PDF konnte nicht erstellt werden. Bitte versuchen Sie es später erneut.'), 500);
        }
        Repo::update($id, ['pdf_file' => $file, 'pdf_hash' => $hash]);
        // Ältere Zeichnung desselben Kunden (neue Fassung) gilt als ersetzt
        if (!empty($c['replaces_id']) && ($old = Repo::find((int) $c['replaces_id'])) && $old['status'] === 'signed') Repo::update((int) $old['id'], ['status' => 'replaced']);
        $dl = Repo::token($id, 'dl', Avv::DOWNLOAD_DAYS);
        $c = Repo::find($id);
        try { Mail::signed($c, $dl); } catch (\Throwable $e) { error_log('[avv] Mail: ' . $e->getMessage()); }
        return Response::redirect(Avv::url('fertig/' . $dl), 303);
    }

    public function done(Request $r, string $token): Response
    {
        $this->guard();
        $c = Repo::byToken($token, 'dl') ?? throw new HttpException(404);
        return $this->page('done', ['contract' => $c, 'token' => $token], lt('Vielen Dank'));
    }

    public function pdf(Request $r, string $token): Response
    {
        $this->guard();
        $c = Repo::byToken($token, 'dl') ?? throw new HttpException(404);
        return Pdf::response($c);
    }

    /** Eingaben prüfen → [Daten für avv_contracts, Fehler je Feld] */
    public static function validate(array $in, array $version): array
    {
        $s = fn(string $k, int $max = 191) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);
        $d = ['company' => $s('company'), 'street' => $s('street'), 'zip_city' => $s('zip_city'), 'email' => mb_strtolower($s('email')),
            'customer_no' => $s('customer_no', 60), 'signer' => $s('signer'), 'signer_role' => $s('signer_role', 120)];
        $e = [];
        foreach (['company' => lt('Bitte die Firma angeben.'), 'street' => lt('Bitte Straße und Hausnummer angeben.'), 'zip_city' => lt('Bitte PLZ und Ort angeben.'),
            'signer' => lt('Bitte den Namen der unterzeichnenden Person angeben.')] as $k => $msg) if ($d[$k] === '') $e[$k] = $msg;
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = lt('Bitte eine gültige E-Mail-Adresse angeben – dorthin geht Ihr PDF.');
        // Anlage A: nur angebotene Optionen, dazu „Weitere“
        $details = [];
        foreach (Avv::lists($version) as $key => $l) {
            $details[$key] = array_values(array_intersect($l['options'], array_map('strval', (array) ($in['d'][$key] ?? []))));
            if ($l['other']) $details[$key . '_other'] = mb_substr(trim((string) ($in['d'][$key . '_other'] ?? '')), 0, 500);
        }
        $d['details'] = json_encode($details, JSON_UNESCAPED_UNICODE);
        if (empty($in['agree'])) $e['agree'] = lt('Bitte bestätigen Sie, dass Sie die Vereinbarung gelesen haben und ihr zustimmen.');
        $kind = ($in['sig_kind'] ?? 'typed') === 'drawn' ? 'drawn' : 'typed';
        $d['sig_kind'] = $kind;
        $d['sig_png'] = '';
        if ($kind === 'drawn') {
            $png = (string) ($in['sig_png'] ?? '');
            if (!Avv::validSignature($png)) $e['sig_png'] = lt('Bitte unterschreiben Sie im Feld – oder wählen Sie „Mit Namen unterzeichnen“.');
            else $d['sig_png'] = $png;
        }
        return [$d, $e];
    }
}
