# Contract: `LegalTextStore`

Public PHP class contract (this package's "API" for this feature — consumed by other classes in this package, e.g. a future sync/push-handling service, and potentially directly by consuming applications).

Namespace: `KaiHempel\ERecht24\Storage\LegalTextStore`
Container binding: singleton, resolved via `app(LegalTextStore::class)` or constructor injection.

```php
declare(strict_types=1);

namespace KaiHempel\ERecht24\Storage;

use Carbon\CarbonImmutable;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\LegalTextStoreException;

final class LegalTextStore
{
    /**
     * Persist HTML content for the given type/language, replacing any
     * previously stored content for the same type/language.
     *
     * Atomic: on failure, previously stored content (if any) remains
     * fully intact and readable — never partially overwritten.
     *
     * @throws \InvalidArgumentException when $lang is not a supported language code
     * @throws LegalTextStoreException when the underlying write fails
     */
    public function put(
        LegalTextType $type,
        string $lang,
        string $html,
        ?CarbonImmutable $sourceModifiedAt = null,
    ): void;

    /**
     * Retrieve previously stored HTML for the given type/language.
     *
     * @return string|null the stored HTML, or null if nothing has been stored yet
     *
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function get(LegalTextType $type, string $lang): ?string;

    /**
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function has(LegalTextType $type, string $lang): bool;

    /**
     * @return CarbonImmutable|null the time this type/language was last saved, or null if never saved
     *
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function lastModified(LegalTextType $type, string $lang): ?CarbonImmutable;

    /**
     * @return CarbonImmutable|null the source system's modification date passed to `put()`,
     *                               or null if never saved or no source modification date was supplied
     *
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function sourceModifiedAt(LegalTextType $type, string $lang): ?CarbonImmutable;

    /**
     * Remove stored content and its metadata for the given type/language.
     * No-op (no error) if nothing is currently stored.
     *
     * @throws \InvalidArgumentException when $lang is not a supported language code
     */
    public function forget(LegalTextType $type, string $lang): void;
}
```

## Behavioral contract (traceable to spec.md Functional Requirements)

| Method | Pre-condition | Post-condition | Spec refs |
|---|---|---|---|
| `put()` | `$lang` is a supported code | `get()` for the same type/lang returns exactly `$html`; `has()` returns `true`; `lastModified()` returns the save time | FR-001, FR-002, FR-010, SC-001 |
| `put()` (failure path) | underlying disk write fails | exception thrown; prior stored content (if any) for that type/lang unchanged; no truncated/empty file visible | FR-009, SC-003, Clarifications session 2026-10-02 (failure propagation) |
| `get()` | type/lang never saved, or previously `forget()`-ed | returns `null`, no exception | FR-003 |
| `has()` | type/lang never saved, or previously `forget()`-ed | returns `false` | FR-004 |
| `lastModified()` | type/lang never saved, or previously `forget()`-ed | returns `null` | FR-005 |
| `sourceModifiedAt()` | `$sourceModifiedAt` was supplied to `put()` for that type/lang | returns the supplied date, readable without parsing the HTML content | FR-011 |
| `sourceModifiedAt()` | type/lang never saved, previously `forget()`-ed, or `put()` was called without `$sourceModifiedAt` | returns `null` | FR-011 |
| `forget()` | type/lang has stored content | subsequent `get()` → `null`, `has()` → `false`, `lastModified()` → `null`, `sourceModifiedAt()` → `null` | FR-006, Clarifications session 2026-10-02 (metadata removed with content) |
| `forget()` | type/lang has no stored content | completes without error | FR-007 |
| any method | `$lang` not in supported set | throws `\InvalidArgumentException` before any I/O | FR-008, SC-005 |
| `put()` | any legal text type/language | content is never written as `.php`/`.blade.php`, never executed by the consuming app | FR-013, SC-004 |

## Exception contract

### `LegalTextStoreException` (new — `src/Exceptions/LegalTextStoreException.php`)

Thrown by `put()` when the underlying atomic write (temp-file write or rename) fails. Wraps the original `\Throwable` as `$previous` for diagnostics, without altering the previously stored content.
