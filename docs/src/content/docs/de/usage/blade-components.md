---
title: "Blade-Komponenten"
description: "Geben Sie Impressum, Datenschutzerklärung und Social-Media-Datenschutzerklärung mit den Blade-Komponenten des Pakets aus und passen Sie deren Views an."
sidebar:
  order: 1
---

```blade
<x-erecht24::imprint />
<x-erecht24::privacy-policy lang="en" class="prose" />
<x-erecht24::privacy-policy-social-media />

{{-- generische Form --}}
<x-erecht24::legal-text type="imprint" lang="de" />
```

- `type` (nur generische Komponente): `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` oder die Slugs `privacy-policy` und `privacy-policy-social-media`. Jeder andere Wert löst eine `\InvalidArgumentException` aus, die die erlaubten Werte nennt.
- `lang` (optional): `de` oder `en`. Regionale Formen wie `de_DE` oder `en-US` werden auf die Hauptsprache reduziert.
- Weitere Attribute wie `class` landen am umschließenden `<div>`, das zusätzlich ein `lang`-Attribut mit der tatsächlich ausgegebenen Sprache trägt.
- Beim Rendern wird nur die gespeicherte Datei gelesen, nie die API aufgerufen.

**Sprachauswahl:** das `lang`-Attribut, dann die Locale der Anwendung (`app()->getLocale()`), dann die erste Sprache in `ERECHT24_TEXT_LANGUAGES`. Berücksichtigt werden nur konfigurierte Sprachen. Hat keine davon gespeicherten Inhalt, wird die nächste konfigurierte Sprache mit Inhalt verwendet.

**Fehlender Text:** Ist für den Typ kein Text gespeichert, rendert die View `erecht24::missing` einen kurzen neutralen Hinweis („This legal text is currently not available.“), statt eine Exception auszulösen. Mit `APP_DEBUG=true` zeigt sie zusätzlich den Hinweis, `php artisan erecht24:sync` auszuführen. Lesefehler werden geloggt und wie ein fehlender Text behandelt.

## Vertrauenssiegel

Wenn Sie `ERECHT24_IMPRINT_SEAL` und/oder `ERECHT24_PRIVACY_POLICY_SEAL` setzen (siehe [Konfiguration](/de/reference/configuration/)), geben die Komponenten für Impressum und Datenschutzerklärung das eRecht24-Siegel zentriert direkt unter dem Text aus, innerhalb des umschließenden `<div>`. Die generische Komponente `legal-text` verhält sich für diese beiden Typen genauso.

- Das Bild wird aus `vendor/erecht24/` geladen (`impressum-siegel-<variant>-large.webp` bzw. `datenschutz-siegel-<variant>-large.webp`); veröffentlichen Sie die Bilddateien mit `php artisan vendor:publish --tag=erecht24-assets` (Ziel: `public/vendor/erecht24/`). Die URL wird auch ausgegeben, wenn die Datei fehlt.
- Es wird 300 px breit (horizontale Varianten) bzw. 180 px breit (vertikale Varianten) dargestellt, mit `width`-/`height`-Attributen und `loading="lazy"`. Es werden keine Inline-Styles verwendet, daher funktioniert auch eine strikte Content Security Policy.
- Der `alt`-Text ist deutsch oder englisch, passend zur ausgegebenen Sprache (Übersetzungsschlüssel `erecht24::seal.imprint` und `erecht24::seal.privacy_policy`).
- Eine ungültige Siegel-Einstellung wird als Warnung protokolliert, der Text erscheint dann ohne Siegel.
- Wenn Sie die Views früher veröffentlicht haben, veröffentlichen Sie sie erneut oder reichen Sie `seal` in Ihren Kopien durch; sonst wird kein Siegel angezeigt.
- Die Social-Media-Datenschutzerklärung und der Platzhalter `missing` zeigen nie ein Siegel. Ohne Siegel-Konfiguration bleibt die Ausgabe unverändert.

## Views anpassen

```bash
php artisan vendor:publish --tag=erecht24-views
```

Die Views werden nach `resources/views/vendor/erecht24/` kopiert und haben dann Vorrang vor den Paket-Views: `components/legal-text`, `imprint`, `privacy-policy`, `privacy-policy-social-media` und `missing`. Die Inhalts-Views erhalten `$content`, `$type` (ein `LegalTextType`), `$lang` und optional `$seal` (ein `LegalTextSeal` oder `null`; Views, die es ignorieren, funktionieren weiter). Den englischen Hinweistext in `missing` können Sie hier übersetzen.

:::danger
Lassen Sie `{!! $content !!}` unescaped, sonst erscheint das HTML als Text. Die Texte sind vertrauenswürdiges HTML aus der eRecht24-API; leiten Sie niemals nutzergenerierte Inhalte durch diese Views.
:::
