# Feature Specification: Shipped plugin key

**Feature Branch**: `feature/shipped-plugin-key`
**Created**: 2026-11-18
**Status**: Implemented (#67)
**Supersedes**: `specs/001-erecht24-config` FR-004, FR-016/FR-017 (plugin key part), SC-005 (plugin key part), User Story 1 scenario 4, User Story 3 scenario 3, `contracts/erecht24-settings.md` (`plugin_key` "no default, ever"), `quickstart.md` (required `pluginKey()`), tasks T007, T018 (plugin key part) and T020.

**Input**: Ship the eRecht24 plugin key with the package so installations no longer have to obtain and configure `ERECHT24_PLUGIN_KEY`.

## Background

Spec 001 required that no plugin key ships with the package because the key identifies the plugin author's registration with eRecht24. The package author is that registrant and decided to ship the key: eRecht24 issues one plugin key per plugin (not per installation), it identifies the integration rather than authorizing access, and requiring every user to request their own key blocked adoption. The API key (per project) and the push secret (per push client) remain the credentials that protect access.

## User Scenarios & Testing

### User Story 1 - Install without a plugin key (Priority: P1)

A developer installs the package, sets only `ERECHT24_API_KEY`, and can call the API.

**Acceptance Scenarios**:

1. **Given** `ERECHT24_PLUGIN_KEY` is unset, null or blank, **When** `Erecht24Settings::pluginKey()` is read, **Then** it returns `Erecht24Settings::DEFAULT_PLUGIN_KEY` and never throws.
2. **Given** a non-blank `ERECHT24_PLUGIN_KEY` (or `erecht24.plugin_key` in a published config), **When** `pluginKey()` is read, **Then** the configured value is returned unchanged.
3. **Given** `erecht24.plugin_key` is changed at runtime (after boot) to a blank value, **When** `pluginKey()` is read, **Then** the shipped key is returned.
4. **Given** any of the above, **When** `Erecht24Client` sends a request, **Then** the `eRecht24-plugin-key` header carries the resolved key.

## Requirements

- **FR-001**: `Erecht24Settings` MUST expose the shipped key as the public constant `DEFAULT_PLUGIN_KEY`, which MUST be non-blank.
- **FR-002**: `Erecht24Settings::pluginKey()` MUST fall back to `DEFAULT_PLUGIN_KEY` on every read when `erecht24.plugin_key` is null or blank (after `trim`), and MUST otherwise return the configured value as a string.
- **FR-003**: The fallback MUST NOT be written into the config repository; `config('erecht24.plugin_key')` stays empty by default, so `config:cache` never pins a shipped key as if it were a user override.
- **FR-004**: `Erecht24Settings` remains the only place that reads `erecht24.*` config; the service provider does not read or write `erecht24.plugin_key`.
- **FR-005**: `api_key` and `push_secret` keep the spec 001 behaviour (lazy `MissingConfigurationException`).
- **FR-006**: The plugin key MUST still never appear in exception messages or logs (existing redaction tests stay green).
- **FR-007**: Docs (EN/DE), READMEs and `config/erecht24.php` MUST describe `ERECHT24_PLUGIN_KEY` as an optional override and explain recovery when eRecht24 rejects the shipped key (401: update the package, or set an own key).

## Constitution: Principle V exception (reviewed)

Principle V forbids hard-coded credentials. This feature records an explicit, reviewed exception for the plugin key only:

- **Scope**: `Erecht24Settings::DEFAULT_PLUGIN_KEY`. No other credential may be hard-coded.
- **Justification**: the plugin key is a per-plugin identifier issued to the package author, not an access credential; it is useless without a project API key, which stays per installation and is never shipped.
- **Risk**: if eRecht24 revokes the key, every installation relying on the default receives `401` at the same time. Mitigation: a non-blank `ERECHT24_PLUGIN_KEY` overrides the default immediately; a new release ships a replacement key; the exceptions reference documents both fixes.
- **Approval**: approved by the package maintainer in PR #67.

## SemVer impact

MINOR. The change relaxes a precondition: every configuration that worked before behaves identically; only the former `MissingConfigurationException` path for an unset plugin key now succeeds. `hasPluginKey()` and `erecht24:status` report the key as set by default.

## Success Criteria

- **SC-001**: A fresh install with only `ERECHT24_API_KEY` can fetch legal texts.
- **SC-002**: Tests fail if the override is ignored, the fallback is removed, or `DEFAULT_PLUGIN_KEY` is blank.
