# Research: eRecht24 Artisan Commands

## Decision: Localhost/dev-host detection is string/host matching, not DNS resolution

**Decision**: `register` rejects a push URI when `parse_url()`'s host is `localhost`, a loopback IP (`127.0.0.1`, `::1`), or ends with `.test`, `.local`, or `.localhost` (case-insensitive).

**Rationale**: Per the clarified spec (FR-002), the goal is to catch common local-dev hosts (Laravel Herd/Valet/Sail all default to `.test`; some setups use `.local`) before eRecht24 is asked to push to an address it can never reach. A static suffix/host check is deterministic, requires no network call, and is trivially unit-testable — unlike attempting a real DNS lookup or `gethostbyname()`, which would be slow, flaky in CI, and could resolve differently per machine.

**Alternatives considered**:
- DNS resolution + checking if the resolved IP is loopback: rejected — adds network I/O and non-determinism to a command whose job is to *avoid* false confidence about reachability; a sandboxed/offline CI run would behave differently than a developer machine.
- Only checking literal `localhost`/`127.0.0.1`: rejected — the clarified spec explicitly asked for the broader `.test`/`.local`/`.localhost` coverage to catch Herd/Valet-style local domains.

## Decision: Confirmation uses `Command::confirm()` with a `--force` bypass

**Decision**: `erecht24:unregister` always calls `$this->confirm(...)` before deleting, unless `--force` is passed, in which case the prompt is skipped entirely.

**Rationale**: This mirrors the established Laravel convention for destructive Artisan commands (e.g. `migrate:fresh --force`, `db:wipe --force`). It satisfies both clarifications: confirmation is required by default regardless of how the client was identified (FR-010), and a scripted/CI caller has an explicit, auditable way to opt out rather than being blocked or having the command silently behave differently when `--no-interaction` is globally set.

**Alternatives considered**:
- Relying solely on Artisan's global `--no-interaction` flag (which auto-answers confirmations with their default): rejected — it's an implicit, easy-to-miss global flag not specific to this command, and `confirm()`'s default answer is `false`, which would make `--no-interaction` always abort rather than always proceed; an explicit `--force` is clearer intent.

## Decision: CMS/plugin identity is hardcoded, not configurable

**Decision**: `register` always submits `cms = 'Laravel'`, `cms_version = app()->version()`, and `plugin_name = 'erecht24-laravel'` (this package's name). Only `author_mail` is configuration-driven (`ERECHT24_AUTHOR_MAIL`, optional).

**Rationale**: These three values identify *this package* and the framework it integrates with, not something a consuming application should override — allowing them to be configured would let a misconfigured `.env` silently misreport what's actually registered with eRecht24. `author_mail` is the one genuinely deployment-specific, optional piece (whose team to contact), so it's the only one sourced from config, consistent with the existing `erecht24.*` config keys.

**Alternatives considered**: Making all four fields config-driven for maximum flexibility — rejected as unnecessary flexibility that contradicts Constitution Principle V's "no hard-coded... environment assumptions" only where an assumption is actually environment-specific; CMS/plugin identity is not.

## Decision: `.env` writing replaces an existing line or appends if absent, via temp-file-then-rename

**Decision**: Writing `ERECHT24_PUSH_SECRET` to `.env` finds a line matching `^ERECHT24_PUSH_SECRET=` (anchored, single occurrence) and replaces it in place; if no such line exists, the key=value pair is appended on a new line at the end of the file. The whole file is rewritten to a temp file in the same directory, then renamed into place, mirroring `LegalTextStore`'s atomic-write convention.

**Rationale**: First-time registration in an environment that has never had the key before must still succeed (FR-007/FR-008 only specify "update... when writable", not "only if the key already exists"); append-if-absent avoids forcing operators to pre-seed an empty `ERECHT24_PUSH_SECRET=` line. Temp-file-then-rename avoids ever leaving `.env` truncated if the process is interrupted mid-write, consistent with the existing storage-layer convention in this codebase.

**Alternatives considered**: Using a third-party `.env` manipulation library (e.g. `vlucas/phpdotenv` writer) — rejected; the package has no such dependency today, and the single-line replace/append operation needed here doesn't justify adding one.

## Decision: Three-client limit and push-URI matching are enforced client-side from `listClients()`

**Decision**: Before creating, `register` always calls `Erecht24Client::listClients()`, searches for a client whose `pushUri` equals the computed/given URI, and only creates a new client if none matches. If none match and the list already has 3 entries, it aborts without calling `createClient()`.

**Rationale**: Already assumed in the spec; confirmed by `Erecht24Client::listClients()`'s existing docblock noting "an eRecht24 account holds at most 3 registrations" and that there is no dedicated API-side idempotency/limit-check endpoint — the API will presumably reject a 4th client itself (403 quota, per `createClient()`'s existing `@throws` docs), but checking client-side lets the command give an actionable, pre-emptive message instead of surfacing a raw `Erecht24ApiException`.

**Alternatives considered**: Skipping the client-side check and letting the API's 403 quota error surface — rejected; it would still work correctness-wise, but the spec's acceptance criteria require listing existing clients and instructions on abort, which only the client-side list provides to compose that message.

## Decision: `status`'s configuration-presence checks never call the existing throwing accessors

**Decision**: `status` checks presence using small boolean helpers added to `Erecht24Settings` (`hasApiKey()`, `hasPluginKey()`, `hasPushSecret()`) that read the raw config value without throwing, rather than calling `apiKey()`/`pluginKey()`/`pushSecret()` inside a try/catch.

**Rationale**: `apiKey()` etc. throw `MissingConfigurationException` by design (existing behavior, not to be changed per Constitution Principle IV). Using try/catch around them for a routine presence check would work but conflates "exception as control flow" with "exception as truly exceptional", and would require catching three separate times. Dedicated boolean accessors are a small, additive, backward-compatible extension to `Erecht24Settings` and keep `status` straightforward to test.

**Alternatives considered**: try/catch around existing accessors — rejected as noted above; functionally equivalent but noisier and slightly against the existing exception-usage convention in this class (exceptions reserved for "value was required for an operation and is missing/invalid", not for a status check).
