# KLXM Studio – AVV

Vereinbarung zur Auftragsverarbeitung (Art. 28 DS-GVO) Schritt für Schritt ausfüllen, elektronisch unterzeichnen und als PDF ablegen – für Agenturen und Website-Betreiber, die mit ihren Kunden eine AVV schließen.

## Was die Erweiterung kann

- **Kunden füllen selbst aus**: Block „AVV abschließen“ auf einer Seite der Website – vier Schritte (Unternehmen · Details zum Auftrag · Vereinbarung lesen · Unterzeichnen). Der Vertragstext füllt sich live mit den Angaben.
- **Einladungen**: In der Verwaltung Kunden per E-Mail einladen – persönlicher Link, 30 Tage gültig.
- **Unterschrift**: mit Namen (elektronisch) oder von Hand gezeichnet (Maus, Finger, Stift). Die Gegenzeichnung des Auftragnehmers (Name, optional Unterschriftsbild) ist hinterlegt und steht sofort im PDF.
- **PDF** (dompdf): Kunde und Betreiber erhalten es sofort per E-Mail (Anhang + Link); jede Seite trägt Zeitpunkt der Zeichnung, Fassung und Vertragsnummer, die SHA-256-Prüfsumme steht am Vertrag.
- **Fassungen**: Text als Entwurf bearbeiten, PDF-Vorschau, als neue Fassung veröffentlichen. Kunden mit älterer Fassung lassen sich einzeln oder alle auf einmal zur erneuten Zeichnung einladen – ihre Angaben sind vorausgefüllt, die alte Zeichnung gilt danach als ersetzt.
- **Schrift im PDF**: DejaVu Sans oder eine freie Schrift von Fontsource (z. B. Lato) – einmal beim Speichern geladen.
- **Texte je Website**: Vertragstext mit Platzhaltern, Anlage A als Auswahllisten, Unterauftragnehmer, Anlage B (TOM), Einladungstext.

Mitgeliefert ist nur ein **Platzhaltertext** (Gliederung mit Platzhaltern) – kein fertiger Vertrag. Ersetzen Sie ihn durch Ihren eigenen, rechtlich geprüften Text.

## Wer verwaltet

- **Mehrere Websites in einer Installation (Netzwerk)**: auf einer Website – Standard die Netzwerk-Website, wählbar unter AVV → Einstellungen – und nur für Netzwerk-Konten.
- **Eine Website**: die Administration mit dem Recht „AVV verwalten“ (`avv.manage`).

## Installation

```bash
composer require klxm/studio-avv          # im Projekt (bringt dompdf mit)
# oder als Ordner extensions/avv: dort „composer install --no-dev“ (eigenes vendor/ für dompdf)
php bin/console migrate --all && php bin/console extensions:publish
```

Einschalten: Verwaltung → Administration → Funktionen & Erweiterungen → „AVV“ (bzw. `'extensions' => ['avv']`). Danach unter **AVV → Einstellungen** Anschrift und unterzeichnende Person des Auftragnehmers eintragen, unter **Vertragstext** den eigenen Text einfügen und veröffentlichen.

## Platzhalter im Vertragstext

`{{firma}}` `{{anschrift}}` `{{plz_ort}}` `{{unterzeichner}}` `{{funktion}}` `{{datum}}` `{{auftragnehmer}}` `{{auftragnehmer_anschrift}}` `{{ort}}`

## Technik

- Tabellen `avv_versions` (veröffentlichte Fassungen, unveränderlich) und `avv_contracts` (eine Zeile je Zeichnung: open · signed · replaced · revoked). Links zum Zeichnen und Herunterladen nur als SHA-256 gespeichert, IP nur als HMAC.
- PDFs außerhalb des Webroots: `storage/…/avv/`. dompdf ohne Netzzugriff (`isRemoteEnabled` aus), Bilder nur als data-URL.
- Spamschutz wie die Formulare des Kerns (`Core\SpamGuard`: Zeit-Token, Honigtopf, Rechenaufgabe, Mengenbegrenzung) – der Block steht auf gecachten Seiten.
- E-Mail-Zweck „AVV“ (Grundeinstellungen → E-Mail-Versand → Verwendung).
- Selbsttest: `php bin/console avv:selftest --site=…`

## Lizenz

MIT. dompdf steht unter LGPL-2.1 und wird als eigenständige Abhängigkeit über Composer geladen.
