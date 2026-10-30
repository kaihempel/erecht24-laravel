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

Das Paket kann das eRecht24-Vertrauenssiegel zentriert unter Impressum und Datenschutzerklärung ausgeben, innerhalb des umschließenden `<div>`. Das gilt für `<x-erecht24::imprint>`, `<x-erecht24::privacy-policy>` und die generische Komponente `legal-text` für diese beiden Typen. Die Social-Media-Datenschutzerklärung und der Platzhalter `missing` zeigen nie ein Siegel. Standardmäßig wird nichts angezeigt: Ohne Siegel-Konfiguration bleibt die Ausgabe unverändert.

Wählen Sie die Variante je Text in der `.env` (siehe [Konfiguration](/de/reference/configuration/)):

```dotenv
ERECHT24_IMPRINT_SEAL=light-horizontal
ERECHT24_PRIVACY_POLICY_SEAL=dark-vertical
```

Erlaubte Werte sind `light-horizontal`, `light-vertical`, `dark-horizontal` und `dark-vertical`. Lassen Sie eine Variable ungesetzt oder leer, um für diesen Text kein Siegel anzuzeigen.

Die Siegelbilder werden nicht aus dem Paketverzeichnis ausgeliefert. Veröffentlichen Sie sie nach der Installation und bei jedem Deployment nach `public/vendor/erecht24/`:

```bash
php artisan vendor:publish --tag=erecht24-assets
```

Nach einem Paket-Update ergänzen Sie `--force`, um die zuvor veröffentlichten Bilder zu überschreiben:

```bash
php artisan vendor:publish --tag=erecht24-assets --force
```

:::caution
Die Bild-URL (`vendor/erecht24/impressum-siegel-<variant>-large.webp` bzw. `vendor/erecht24/datenschutz-siegel-<variant>-large.webp`) wird auch ausgegeben, wenn die Datei nie veröffentlicht wurde. Wird der Veröffentlichungsschritt ausgelassen, erscheint auf Ihren Rechtstext-Seiten daher ein defektes Bild.
:::

- Die Siegelbilder enthalten ausschließlich deutschen Text, auch auf englischen Seiten. Der `alt`-Text folgt der ausgegebenen Sprache (Übersetzungsschlüssel `erecht24::seal.imprint` und `erecht24::seal.privacy_policy`).
- Das Siegel wird 300 px breit (horizontale Varianten) bzw. 180 px breit (vertikale Varianten) dargestellt, mit `width`-/`height`-Attributen und `loading="lazy"`. Es werden keine Inline-Styles verwendet, daher funktioniert auch eine strikte Content Security Policy.
- Bei einem ungültigen Wert wirft `Erecht24Settings` eine `InvalidConfigurationException`. Die Komponenten protokollieren sie als Warnung und geben den Text ohne Siegel aus.
- Um das Markup zu ändern, veröffentlichen Sie die Views mit dem Tag `erecht24-views` (siehe unten) und passen `components/legal-text` an. Das Siegel wird als `$seal` übergeben. Wenn Sie die Views veröffentlicht haben, bevor es Siegel gab, veröffentlichen Sie sie erneut oder reichen Sie `seal` in Ihren Kopien durch; sonst wird kein Siegel angezeigt.

## Views anpassen

```bash
php artisan vendor:publish --tag=erecht24-views
```

Die Views werden nach `resources/views/vendor/erecht24/` kopiert und haben dann Vorrang vor den Paket-Views: `components/legal-text`, `imprint`, `privacy-policy`, `privacy-policy-social-media` und `missing`. Die Inhalts-Views erhalten `$content`, `$type` (ein `LegalTextType`), `$lang` und optional `$seal` (ein `LegalTextSeal` oder `null`; Views, die es ignorieren, funktionieren weiter). Den englischen Hinweistext in `missing` können Sie hier übersetzen.

:::danger
Lassen Sie `{!! $content !!}` unescaped, sonst erscheint das HTML als Text. Die Texte sind vertrauenswürdiges HTML aus der eRecht24-API; leiten Sie niemals nutzergenerierte Inhalte durch diese Views.
:::
