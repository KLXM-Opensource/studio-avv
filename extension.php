<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – AVV. Copyright (C) 2026 KLXM and contributors (see LICENSE)
/**
 * Erweiterung „avv“: Vereinbarung zur Auftragsverarbeitung (Art. 28 DS-GVO) Schritt für Schritt ausfüllen, elektronisch
 * unterzeichnen und als PDF (dompdf) ablegen. Kunden füllen selbst aus (Block „AVV abschließen“) oder kommen über eine
 * Einladung; neue Fassungen lassen sich zur erneuten Zeichnung schicken.
 *
 * Wer verwaltet: mehrere Websites in der Installation → nur auf der Netzwerk-Website, nur Netzwerk-Konten;
 * eine Website → Administration mit Recht „AVV verwalten“ (avv.manage). Doku: README.md.
 * Abhängigkeit: dompdf/dompdf (composer install im Ordner der Erweiterung, wenn sie nicht über Composer des Kerns kommt).
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Klxm\\Avv\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('Klxm\\Avv\\')) . '.php';
        if (is_file($file)) require $file;
    }
});
// dompdf: über den Kern (Composer) oder eigenes vendor/ der Erweiterung
if (!class_exists(\Dompdf\Dompdf::class) && is_file(__DIR__ . '/vendor/autoload.php')) require_once __DIR__ . '/vendor/autoload.php';

use Klxm\Avv\AdminController;
use Klxm\Avv\Avv;
use Klxm\Avv\Repo;
use Klxm\Avv\SiteController;

return [
    'name' => 'avv',
    'label' => 'AVV – Auftragsverarbeitung zeichnen lassen',
    'version' => '0.1.2',
    'requires' => '>=1.0.0',
    'description' => 'Vereinbarung zur Auftragsverarbeitung (Art. 28 DS-GVO) Schritt für Schritt ausfüllen, elektronisch unterzeichnen und als PDF ablegen; Kunden einladen, neue Fassungen erneut zeichnen lassen.',
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'provides' => ['Menüpunkt „AVV“ (Verträge, Vertragstext mit Fassungen, Einstellungen)', 'Block „AVV abschließen“', 'Seiten /avv/zeichnen/{link}, /avv/pdf/{link}',
        'E-Mail-Zweck „AVV“', 'Recht „AVV verwalten“'],
    'commands' => ['avv:selftest'],
    'usage' => function (): ?string {
        try {
            if (!Avv::here()) return null;
            $c = Repo::counts();
        } catch (\Throwable) {
            return null;
        }
        return $c['signed'] || $c['open'] ? __('{n} unterzeichnet', ['n' => $c['signed']]) . ($c['open'] ? ' · ' . __('{n} offen', ['n' => $c['open']]) : '') : null;
    },
    'boot' => function (Core\Extension $x): void {
        $x->feature(Avv::FEATURE, 'AVV: Auftragsverarbeitung zeichnen lassen', [Avv::PERM]);
        $x->permissions('AVV', [Avv::PERM => 'Vereinbarungen zur Auftragsverarbeitung verwalten (Kunden einladen, Text, PDFs)']);
        foreach (Repo::tables() as $name => $define) $x->table($name, $define);
        $x->mailPurpose(Avv::MAIL, 'AVV', 'Einladungen zur Zeichnung und unterzeichnete Vereinbarungen');
        $x->blocks(['avv' => ['label' => __('AVV abschließen'), 'icon' => 'signature', 'group' => 'Formulare',
            'help' => __('Kunden füllen die Vereinbarung zur Auftragsverarbeitung in vier Schritten aus und unterzeichnen – sie erhalten sofort das PDF. Text und Anlagen: Verwaltung → AVV.'),
            'fields' => [
                ['name' => 'title', 'label' => __('Überschrift'), 'type' => 'text', 'max' => 120, 'default' => 'Vereinbarung zur Auftragsverarbeitung abschließen'],
                ['name' => 'text', 'label' => __('Einleitung (optional)'), 'type' => 'richtext'],
            ]]]);

        $x->health(function (): array {
            if (!Avv::here()) return [];
            $out = [];
            if (!Klxm\Avv\Pdf::available()) $out[__('AVV: dompdf fehlt – „composer install“ im Ordner der Erweiterung')] = false;
            if ($m = Avv::providerMissing()) $out[__('AVV: Angaben des Auftragnehmers fehlen ({list})', ['list' => implode(', ', $m)])] = null;
            return $out;
        });

        $x->adminPage(['href' => '/admin/avv', 'label' => 'AVV', 'kind' => 'content', 'icon' => 'signature', 'perm' => Avv::PERM, 'feature' => Avv::FEATURE,
            'description' => 'Vereinbarungen zur Auftragsverarbeitung: Kunden einladen, PDFs, Vertragstext',
            'visible' => fn(): bool => Avv::canManage()]);

        $x->routes(function (Core\Http\Router $r): void {
            $s = SiteController::class;
            $r->get(Avv::BASE . '/token', [$s, 'token']);
            $r->get(Avv::BASE . '/zeichnen/{token}', [$s, 'invite']);
            $r->post(Avv::BASE . '/zeichnen', [$s, 'sign']);
            $r->get(Avv::BASE . '/fertig/{token}', [$s, 'done']);
            $r->get(Avv::BASE . '/pdf/{token}', [$s, 'pdf']);

            // Verwaltung: Recht + CSRF prüft der Router (avv.manage – Netzwerk-Konten haben es immer), die Regel Netzwerk/Einzel AdminController::guard
            $c = AdminController::class;
            $p = Avv::PERM;
            $r->get('/admin/avv', [$c, 'index'], $p);
            $r->post('/admin/avv/einladen', [$c, 'invite'], $p);
            $r->post('/admin/avv/alle-erneuern', [$c, 'renewAll'], $p);
            $r->get('/admin/avv/vorlage', [$c, 'template'], $p);
            $r->post('/admin/avv/vorlage', [$c, 'saveTemplate'], $p);
            $r->post('/admin/avv/vorlage/verwerfen', [$c, 'discardDraft'], $p);
            $r->get('/admin/avv/vorlage/vorschau.pdf', [$c, 'preview'], $p);
            $r->get('/admin/avv/einstellungen', [$c, 'settings'], $p);
            $r->post('/admin/avv/einstellungen', [$c, 'saveSettings'], $p);
            $r->get('/admin/avv/{id}', [$c, 'show'], $p);
            $r->get('/admin/avv/{id}/pdf', [$c, 'pdf'], $p);
            $r->post('/admin/avv/{id}/erneut', [$c, 'resend'], $p);
            $r->post('/admin/avv/{id}/beenden', [$c, 'revoke'], $p);
        });

        $x->command('avv:selftest', 'AVV: Regeln, Platzhalter, Unterschrift, Links, PDF', fn(array $args): int => Klxm\Avv\SelfTest::run());
    },
];
