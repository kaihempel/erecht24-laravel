# Feature Specification: eRecht24 API Client

**Feature Branch**: `002-erecht24-api-client`

**Created**: 2026-10-02

**Status**: Draft

**Input**: User description: "eRecht24 API client: Implement a single HTTP client class (Erecht24Client) built on Laravel's HTTP client that registers and manages push clients (create, update, delete, list), fetches the three legal texts on demand (imprint, privacy policy, privacy policy social media) via a LegalTextType enum, and can trigger a test push. Includes readonly DTOs (LegalText, PushClient), proper auth headers (API key, plugin key), retry on connection errors/5xx (not on 4xx), and error mapping to Erecht24ApiException / Erecht24AuthenticationException (401/403) without ever leaking secrets in exceptions or logs. Endpoints must be verified against https://api-docs.e-recht24.de/ (Swagger UI) and documented in the class docblock. Registered as a singleton in the container. This is a fresh MIT-licensed implementation, not copied from the official SDK. Depends on issue #3 (package config). Based on GitHub issue #4."

## Clarifications

### Session 2026-10-02

- Q: How should the client handle a 429 Too Many Requests response from eRecht24? → A: Treat it like 5xx/connection errors — retry with bounded backoff, not as a non-retryable 4xx.
- Q: What happens when deleting a push client that no longer exists (already deleted, or never existed)? → A: Idempotent — the operation succeeds silently, with no error surfaced.
- Q: Does listing push clients need to handle pagination from eRecht24? → A: No — assume a single, unpaginated response covering all registered push clients for the account.
- Q: Does updating a push client's details return a new/changed secret? → A: No — the secret is immutable once issued at creation; update never returns or changes it.
- **Correction (API verification, 2026-10-02)**: Verifying endpoints against eRecht24's actual OpenAPI spec showed this answer was wrong — `PUT /clients/{id}` always returns a fresh secret that MUST replace the previously stored one. FR-005 and the Push Client entity below are corrected accordingly. (FR-016 is unaffected — it governs DTO read-only-ness, not secret mutability.)
- **Correction (API verification, 2026-10-02)**: The earlier idempotent-delete decision was also found incorrect — the verified API returns a 404-style error when deleting a client_id that does not exist or is not owned by the caller. FR-006 and the related edge case are corrected accordingly.
- **Confirmed (API verification, 2026-10-02)**: eRecht24 limits each account to a maximum of 3 registered push clients, reinforcing the earlier decision that `listClients()` needs no pagination.
- **Correction (API verification, 2026-10-02)**: The verified API only ever returns 401 for actual authentication failures (invalid/missing API key or plugin key). Its one documented 403 (on push-client registration) means "too many clients already registered for this project" — a quota/business-rule error, not an authentication failure. FR-012/FR-013 are corrected so only 401 maps to the authentication-failure error; 403 and all other 4xx/5xx map to the general API-failure error.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Fetch a legal text for display (Priority: P1)

As a developer using this package in a Laravel application, I want to fetch the current imprint, privacy policy, or privacy policy for social media from eRecht24 on demand, so I can render it on my site without maintaining the text myself.

**Why this priority**: This is the core value of the package — replacing a manual/pull-based legal text workflow with an on-demand fetch. Without this, the package has no reason to exist.

**Independent Test**: Can be fully tested by resolving the client from the container and calling the legal-text lookup for each of the three text types against a faked HTTP response, verifying the returned text object exposes the right content for a given language.

**Acceptance Scenarios**:

1. **Given** valid API credentials are configured, **When** the application requests the imprint, **Then** the client returns the legal text content for the requested language.
2. **Given** valid API credentials are configured, **When** the application requests the privacy policy or the social-media privacy policy, **Then** the client returns the corresponding legal text content.
3. **Given** the eRecht24 service returns a server error, **When** the application requests a legal text, **Then** the request is retried a limited number of times before failing with a client-facing error.

---

### User Story 2 - Register and manage a push client (Priority: P2)

As a developer integrating this package, I want to register my application as a push client with eRecht24 (and update or remove that registration later), so eRecht24 can push legal text updates to my application instead of me polling for changes.

**Why this priority**: Required for the push mechanism to work at all, but only needs to happen once per installation/environment rather than on every request — making it slightly less critical path than reading texts.

**Independent Test**: Can be fully tested by creating a push client registration against a faked HTTP response and asserting the returned registration data, then separately testing update, list, and delete operations the same way.

**Acceptance Scenarios**:

1. **Given** valid API credentials, **When** a new push client is registered with its required details (push method, push URI, CMS name/version, plugin name, author email), **Then** eRecht24 confirms the registration and returns an identifier for it.
2. **Given** an existing push client registration, **When** its details are updated, **Then** eRecht24 reflects the updated information.
3. **Given** an existing push client registration, **When** it is deleted, **Then** it no longer appears when listing registered push clients.
4. **Given** one or more registered push clients, **When** the application lists them, **Then** it receives the full set of currently registered push clients.

---

### User Story 3 - Verify push delivery works (Priority: P3)

As a developer who just registered a push client, I want to trigger a test push to confirm eRecht24 can successfully reach my application, so I can verify the integration before relying on it in production.

**Why this priority**: Useful for confidence and debugging during setup, but not required for the core read/manage flows to function.

**Independent Test**: Can be fully tested by firing a test push for a registered client identifier against a faked HTTP response and asserting the request was sent with the expected parameters.

**Acceptance Scenarios**:

1. **Given** a registered push client, **When** a test push is triggered, **Then** the request is sent to eRecht24 with the client's identifier and completes without error on success.

---

### Edge Cases

- What happens when the configured API key or plugin key is invalid or expired? → The client must surface a distinct authentication error (401 only) rather than a generic failure, and must never include the key value in that error.
- What happens when registering a push client while the account already has the maximum number of registered clients? → The client surfaces a general API-failure error (eRecht24 signals this with a 403, but it is a quota/business-rule rejection, not an authentication failure).
- What happens when eRecht24 returns a 4xx client error (e.g. malformed request, not-found client id)? → The client must surface the error (with any API-provided message) without retrying, since retrying a client error will not succeed.
- What happens when eRecht24 is temporarily unreachable, returns repeated 5xx errors, or signals rate limiting (429)? → The client retries a bounded number of times with backoff in all three cases, then surfaces a failure if retries are exhausted.
- What happens when a legal text is requested for a language eRecht24 does not have content for? → The returned legal text object reflects whatever languages the API actually provided; absence of a language is not treated as an error by the client itself.
- What happens when credentials or secrets would otherwise appear in a thrown error or log line? → They must be omitted/redacted in all cases, including nested/wrapped errors.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a single point of integration (resolved from the application's service container) for all communication with the eRecht24 service.
- **FR-002**: System MUST allow retrieving the current legal text for each of the three supported text types: imprint, privacy policy, and privacy policy for social media.
- **FR-003**: System MUST represent the three legal text types as a fixed, enumerated set of values rather than free-form strings, and each type must expose a corresponding stable file-name-safe identifier for local storage/caching use.
- **FR-004**: System MUST allow registering a new push client with eRecht24, supplying the push method, push target address, CMS name and version, plugin name, and author contact email, and MUST return the registration's identifying details (including any secret issued by eRecht24 for that registration).
- **FR-005**: System MUST allow updating an existing push client registration's details, and MUST return the new secret issued by eRecht24 for that registration so the caller can replace its previously stored value.
- **FR-006**: System MUST allow deleting an existing push client registration by its identifier; deleting an identifier that does not currently correspond to a registration owned by the caller MUST surface an API-failure error (not silently succeed).
- **FR-007**: System MUST allow listing all push client registrations currently known to eRecht24 for the configured account, returned as a single, unpaginated result (the account is limited to a small, fixed maximum number of registered push clients).
- **FR-008**: System MUST allow triggering a test push for a given push client identifier, to verify delivery works end-to-end.
- **FR-009**: System MUST authenticate every request to eRecht24 using the configured API key and plugin key.
- **FR-010**: System MUST automatically retry a request a limited number of times, with backoff, when it fails due to a connection failure, a server error (5xx) response, or a rate-limit (429) response.
- **FR-011**: System MUST NOT retry a request that fails due to any other client error (4xx, excluding 429) response, since such errors indicate the request itself is invalid.
- **FR-012**: System MUST translate a 401 response from eRecht24 into a distinct authentication-failure error, separate from other failures.
- **FR-013**: System MUST translate any other failing response from eRecht24 (including 403, which signals a quota/business-rule rejection rather than an authentication failure, and all other 4xx/5xx) into a general API-failure error that includes the response's status and, when provided, the API's error message.
- **FR-014**: System MUST NOT include the API key, plugin key, or any push client secret in any error message or log output it produces, under any failure path.
- **FR-015**: System MUST document, alongside the integration point's source, the exact set of eRecht24 endpoints (paths, methods, and auth header names) it relies on, as verified against eRecht24's published API documentation.
- **FR-016**: System MUST represent legal texts and push client registrations as immutable data objects whose fields cannot be altered after creation.

### Key Entities

- **Legal Text**: Represents one eRecht24-managed legal document (imprint, privacy policy, or privacy policy for social media) as currently published, including its content per available language and any timestamps supplied by eRecht24. Identified by its legal text type.
- **Legal Text Type**: The enumerated kind of legal document that can be fetched — imprint, privacy policy, or privacy policy for social media — each with a stable identifier suitable for use as a file name.
- **Push Client**: Represents one registration of this application with eRecht24 for receiving pushed legal text updates — including its identifier, push delivery method, push target address, CMS name/version, plugin name, author contact email, and a secret used to authenticate incoming pushes. A new secret is issued on both creation and every update, and the caller MUST persist the latest one each time.
- **Authentication Error**: Represents a failure specifically caused by invalid or rejected credentials (401 only) when talking to eRecht24. (403 is a quota/business-rule error — see FR-013 — not a credential failure.)
- **API Error**: Represents any other failure response from eRecht24, carrying the response status and any error message the API provided.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer can fetch any of the three legal texts and successfully render its content for a given language without needing to know eRecht24's underlying HTTP contract.
- **SC-002**: A developer can complete the full push-client lifecycle (register, update, list, delete) and confirm delivery with a test push, without directly issuing any HTTP requests themselves.
- **SC-003**: 100% of integration points' request paths, methods, and header names are verified against eRecht24's published API documentation and recorded alongside the code, with zero undocumented endpoints in use.
- **SC-004**: 100% of failure scenarios (authentication failure, other API failure, retried transient failure) are distinguishable by developers catching errors, without needing to inspect raw HTTP responses.
- **SC-005**: Zero instances of API keys, plugin keys, or push client secrets appear in any error message or log line produced by the integration, across all failure scenarios.
- **SC-006**: Transient failures (connection errors, 5xx responses) are automatically recovered from without developer intervention in the majority of cases, while invalid requests (4xx) fail fast without wasted retry attempts.

## Assumptions

- This package's configuration layer (API key, plugin key, base URL, timeouts) already exists and is available to this feature, per the dependency on issue #3.
- "On demand" fetching means legal texts are retrieved synchronously when requested by the consuming application; no background scheduling or automatic polling is part of this package.
- The eRecht24 account used for integration testing has (or can be given) at least one registered push client for exercising update/delete/list/test-push flows, and sandbox/test credentials are available for verifying real endpoint behavior during implementation.
- Retry behavior defaults to industry-standard bounded attempts with exponential backoff; the exact count/timing is an implementation detail, not a user-facing requirement, as long as it is bounded and does not retry 4xx responses.
- Legal text content is returned by eRecht24 as per-language HTML; this package's responsibility ends at exposing that content, not sanitizing or further processing it.
