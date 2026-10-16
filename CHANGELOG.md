# Changelog

## 0.1.5

- Sicherheit: Unterzeichnen ohne Einladung nur, wenn der Block „AVV abschließen“ auf einer veröffentlichten Seite steht; eigene Begrenzung (3 je Anschluss, 20 je Tag) statt der gemeinsamen Formular-Grenze – die Website lässt sich nicht als Absender für PDFs an fremde Adressen missbrauchen.
- Download-Link in der E-Mail nur mit der festen Adresse der Website (`trusted_site_url()`), nie aus dem Host-Header der Anfrage.

## 0.1.4

- Titel: lange Titel zweizeilig, ausgewogen umbrochen; engerer Zeilenabstand (PDF und Website).

## 0.1.3

- Netzwerk: Website der AVV wählbar (Einstellungen → „AVV läuft auf der Website“, installationsweit in storage/avv-site.json; Standard Netzwerk-Website).
- „PDF ansehen“ im PDF-Viewer des Kerns (PdfController::file, ältere Kerne: Download), in Liste und Detail.
- Vereinbarung endgültig löschen (mit Warnung, unterzeichnete nur mit Bestätigung).
- Schrift im PDF: beliebige freie Schrift per Name.

## 0.1.2

- Einstellung „Schrift im PDF“: DejaVu Sans oder eine freie Schrift (z. B. Hausschrift Lato) – TTF wird beim Speichern einmal von Fontsource geladen und auf dem Server abgelegt; Fußzeile und getippte Unterschrift in derselben Schrift.

## 0.1.1

- Vertragstext: Liste aller Platzhalter rechts neben dem Editor (Bedeutung, Beispiel, Kopieren); Hinweis, was automatisch angehängt wird.

## 0.1.0

- Erste Fassung: Assistent in vier Schritten (Block „AVV abschließen“, Einladungslink), Unterschrift mit Namen oder gezeichnet, automatische Gegenzeichnung, PDF mit dompdf (Prüfsumme, Fußzeile mit Zeitpunkt/Fassung/Nummer), E-Mails mit PDF, Verwaltung (Verträge, Vertragstext mit Entwurf/Vorschau/Fassungen, Einstellungen), erneute Zeichnung bei neuer Fassung (einzeln oder alle), Netzwerk-Regel (nur Netzwerk-Website/Netzwerk-Konten), Selbsttest `avv:selftest`.
