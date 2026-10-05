# Contract: Public PHP API additions

SemVer impact: **MINOR** (additive only).

## `KaiHempel\ERecht24\Erecht24Manager::resolve()`

```php
/**
 * @throws \InvalidArgumentException when $type is not a known legal text type
 */
public function resolve(LegalTextType|string $type, ?string $lang = null): ?ResolvedLegalText
```

- Same language selection as `html()` (explicit → app locale primary subtag → first configured → any other stored configured language).
- Returns `null` when no text of `$type` is stored in any configured language, or the language configuration is invalid (logged, not thrown).
- Reads the store only; never performs HTTP requests.
- Unknown `$type` string → `\InvalidArgumentException('Unknown legal text type [<value>].')`.
- Invariant: `resolve($t, $l)?->content === html($t, $l)` and `(resolve($t, $l) !== null) === has($t, $l)`.

## Facade `KaiHempel\ERecht24\Facades\ERecht24`

```php
/**
 * @method static ResolvedLegalText|null resolve(LegalTextType|string $type, ?string $lang = null)
 */
```

## `KaiHempel\ERecht24\View\ResolvedLegalText`

```php
final readonly class ResolvedLegalText
{
    public function __construct(
        public string $content,
        public string $lang,
        public ?string $requestedLang = null,
    ) {}

    public function isFallback(): bool;
}
```

- Existing two-argument construction remains valid.
- `isFallback()` is true iff `requestedLang !== null && requestedLang !== lang`.

## Unchanged

`html()`, `has()`, `lastModified()`, `languages()` signatures and behavior; `LegalTextResolver` stays `@internal`; Blade components unchanged.
