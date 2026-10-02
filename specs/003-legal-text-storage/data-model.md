# Data Model: Legal Text Storage Layer

## Entities

### Stored Legal Text (on-disk representation, not a PHP class)

The persisted form of one legal text type in one language. Represented as two files on the configured disk, not a single in-memory entity — `LegalTextStore` reads/writes these directly rather than materializing a combined object (keeping `get()`'s return type a plain `?string` of raw HTML, per FR-002 and the spec's "byte-identical" requirement).

| File | Path pattern | Contents |
|---|---|---|
| Content | `{directory}/{type-slug}.{lang}.html` | Raw HTML exactly as passed to `put()`. Never compiled/executed. |
| Metadata | `{directory}/{type-slug}.{lang}.meta.json` | JSON object, see `LegalTextMetadata` below. |

Example for imprint/de: `erecht24/imprint.de.html` + `erecht24/imprint.de.meta.json`.

### `LegalTextMetadata` (new DTO — `src/DTOs/LegalTextMetadata.php`)

Immutable (readonly) value object representing the parsed contents of a `meta.json` file.

| Field | Type | Required | Notes |
|---|---|---|---|
| `fetchedAt` | `CarbonImmutable` | Yes | Set by the store at `put()` time; source of `lastModified()`. |
| `sourceModifiedAt` | `?CarbonImmutable` | No | Passed through from the caller if the eRecht24 API supplied a modification date; `null` when unavailable (per spec edge case: absence doesn't block save/retrieve). |

Validation rules:
- `fetchedAt` is always set by the store itself (not caller-supplied) — guarantees FR-011's "when it was saved locally" is always present.
- `sourceModifiedAt` is optional and passed through as given; no validation beyond being a valid date when present.

State transitions: a metadata file is created/replaced atomically on every `put()`, and deleted (along with its content file) on `forget()`. There is no partial-update path — `put()` always writes a complete new `LegalTextMetadata`.

### `LegalTextType` (existing — `src/Enums/LegalTextType.php`)

No changes needed. Already provides `fileSlug()` used to build the file-name-safe path segment (FR-010, Key Entities).

### Language

Not a new type. Represented as a plain `string` parameter validated against `Erecht24Settings::languages()` (existing config-driven list, currently `de`/`en`). No new enum/class introduced — matches existing `Erecht24Settings` design (research.md "Decision: Language validation").

## Relationships

```
LegalTextType (enum) ──┐
                        ├──> identifies ──> Stored Legal Text (2 files: .html + .meta.json)
Language (string)  ─────┘

LegalTextStore
  ├── depends on Erecht24Settings (disk(), directory(), languages())
  ├── depends on Illuminate\Contracts\Filesystem\Filesystem (via Storage::disk())
  └── produces/consumes LegalTextMetadata (serialized to/from meta.json)
```

## `LegalTextStore` public surface (summary — see contracts/ for full signatures)

| Method | Returns | Throws |
|---|---|---|
| `put(LegalTextType $type, string $lang, string $html, ?CarbonImmutable $sourceModifiedAt = null): void` | `void` | `InvalidArgumentException` (bad lang), `LegalTextStoreException` (write failure) |
| `get(LegalTextType $type, string $lang): ?string` | raw HTML or `null` | `InvalidArgumentException` (bad lang) |
| `has(LegalTextType $type, string $lang): bool` | existence | `InvalidArgumentException` (bad lang) |
| `lastModified(LegalTextType $type, string $lang): ?CarbonImmutable` | fetched-at time or `null` | `InvalidArgumentException` (bad lang) |
| `forget(LegalTextType $type, string $lang): void` | `void` (no-op if absent) | `InvalidArgumentException` (bad lang) |
