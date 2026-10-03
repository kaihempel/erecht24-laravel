# Feature Specification: Legal Text Synchronization Service

**Feature Branch**: `004-legal-text-sync`

**Created**: 2026-10-03

**Status**: Draft

**Input**: User description: "Add LegalTextSynchronizer sync service and SyncLegalTextJob queued job for eRecht24 legal text synchronization. Context: A push only tells the app that a text changed. A sync service fetches the affected text through the API client and writes it to the store for all configured languages. The push endpoint dispatches it as a queued job so the HTTP response to eRecht24 stays fast. The same service backs the manual erecht24:sync command."

## Clarifications

### Session 2026-10-03

- Q: Should the manual `erecht24:sync` command support targeting a single legal text type, or only sync all types? → A: Command supports an optional type argument: syncs one type if given, else all types.
- Q: What are the default retry count and backoff delays for the synchronization job? → A: 3 attempts, exponential backoff (e.g. 1m/5m/15m).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Keep local legal texts current after an eRecht24 change (Priority: P1)

As a host application operator, when eRecht24 notifies my app that a legal text (e.g. Imprint) has changed, I need the app to automatically fetch the latest version for every configured language and store it, so visitors always see up-to-date legal text without manual intervention.

**Why this priority**: This is the core value of the feature — without it, push notifications from eRecht24 are meaningless and legal texts go stale, which carries legal risk for host applications.

**Independent Test**: Can be fully tested by invoking the synchronizer for a single legal text type with a faked API response and verifying the correct files are written for each configured language.

**Acceptance Scenarios**:

1. **Given** a legal text type (e.g. Imprint) and a faked API response containing content for all configured languages, **When** the sync service processes that type, **Then** files exist for exactly the configured languages with the fetched content.
2. **Given** the API returns no content for one configured language, **When** the sync service processes that type, **Then** that language is skipped and reported as skipped, and any previously stored file for that language is left untouched.
3. **Given** a successful sync that writes at least one language, **When** the write completes, **Then** an update notification (event) is emitted naming the type and the languages that were written, so host applications can react (e.g. clear caches).

---

### User Story 2 - Fast, non-blocking response to eRecht24 pushes (Priority: P1)

As the system receiving a push notification from eRecht24, I need to acknowledge the push immediately and perform the actual content fetch-and-store work in the background, so the HTTP response stays fast and eRecht24 does not experience timeouts or retried pushes.

**Why this priority**: Equally critical to P1 User Story 1 — if the push handler blocks on the external API call, it risks timeouts, duplicate push retries, and a poor integration experience with eRecht24.

**Independent Test**: Can be tested by triggering a push for a given legal text type and confirming the work is queued (not executed synchronously) and that queuing the same work twice in quick succession results in only one execution.

**Acceptance Scenarios**:

1. **Given** a push notification for a legal text type arrives, **When** the system handles it, **Then** the synchronization work is handed off to a background job rather than executed inline.
2. **Given** two identical synchronization jobs for the same legal text type are queued at the same time, **When** the queue processes them, **Then** only one of them actually runs the synchronization work.
3. **Given** the background job fails when calling the external API, **When** the failure occurs, **Then** the job is retried automatically up to a bounded number of attempts with increasing delay between attempts, and previously stored content remains unchanged throughout.
4. **Given** the background job exhausts all retry attempts without success, **When** the final failure occurs, **Then** the system records a warning that does not expose sensitive credentials or secrets, and no partial or empty content is written.

---

### User Story 3 - On-demand full resynchronization (Priority: P2)

As an operator or administrator, I need a way to manually trigger synchronization of either one specific legal text type or all legal text types at once, so I can recover from a missed push, verify configuration, or refresh everything after changing configured languages.

**Why this priority**: Important for operational resilience and recovery, but not required for the day-to-day push-driven flow to function, so it can ship slightly after the core sync capability.

**Independent Test**: Can be fully tested by invoking the "sync all" operation and verifying every legal text type is processed and a result is reported per type.

**Acceptance Scenarios**:

1. **Given** multiple legal text types are configured, **When** a full resynchronization is triggered without specifying a type, **Then** every configured legal text type is synchronized and a per-type result (languages written, languages skipped) is returned.
2. **Given** one legal text type fails to synchronize during a full resynchronization, **When** the operation completes, **Then** the failure of that one type does not prevent the other types from being synchronized and reported.
3. **Given** an operator specifies a single legal text type when invoking the manual sync, **When** the operation runs, **Then** only that type is synchronized and its result is returned.

---

### Edge Cases

- What happens when the external API returns empty/no content for a language that has never been synced before (no existing file)? The language is skipped and reported as skipped; no empty file is created.
- What happens when the external API returns empty/no content for a language that already has a stored file from a previous sync? The existing file is left untouched; the language is reported as skipped.
- What happens when a push notification is received for a legal text type that is not configured/enabled in the host application? The notification is acknowledged (no error response) and no synchronization work is performed for it, but a warning is logged so operators have visibility into the mismatch.
- What happens when the external API is fully unavailable across all retry attempts? Existing content remains unchanged, the job ends in a failed state after exhausting retries, and a warning is logged without exposing secrets.
- What happens when two different legal text types are pushed at the same time? Each is synchronized independently; uniqueness locking only prevents duplicate jobs for the *same* type.
- What happens when the update notification (event) fires but a language was skipped? The event only reports the languages that were actually written, not the skipped ones.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a way to synchronize a single legal text type by fetching its current content from the external legal text provider and storing it for every configured language.
- **FR-002**: System MUST provide a way to synchronize all configured legal text types in one operation, producing an individual result per type.
- **FR-003**: For a given legal text type, System MUST fetch content once per configured language and write it to that language's storage location.
- **FR-004**: System MUST NOT overwrite previously stored content for a language when the external provider returns empty or missing content for that language during a sync.
- **FR-005**: System MUST report, for each synchronization of a legal text type, which languages were written and which were skipped due to missing content.
- **FR-006**: System MUST process an incoming change notification (push) for a legal text type by handing off the fetch-and-store work to an asynchronous background job rather than performing it during the request/response cycle.
- **FR-007**: System MUST ensure that multiple background jobs queued for the same legal text type at the same time result in only one actual execution of the synchronization work (duplicate suppression).
- **FR-008**: System MUST retry a failed background synchronization job automatically, up to a bounded, configured number of attempts (default: 3), with increasing delay between attempts (default: exponential backoff, e.g. 1 minute, 5 minutes, 15 minutes).
- **FR-009**: System MUST leave previously stored legal text content unchanged for the duration of retries and in the event of final failure after exhausting all retry attempts.
- **FR-010**: System MUST record a warning when a background synchronization job fails permanently, and that warning MUST NOT contain sensitive credentials or secrets.
- **FR-011**: System MUST emit an update notification (event) identifying the legal text type and the set of languages that were successfully written, and only after at least one language was successfully written.
- **FR-012**: System MUST NOT emit the update notification when a synchronization attempt results in zero languages written.
- **FR-013**: The manual resynchronization operation (triggered on demand by an operator) MUST reuse the same synchronization behavior (fetch, per-language write, skip-on-empty, reporting) as the push-triggered path, and MUST allow the operator to optionally target a single legal text type, defaulting to all types when none is specified.
- **FR-014**: System MUST allow the set of synchronized languages to be driven by host application configuration rather than being hardcoded.
- **FR-015**: When a push notification names a legal text type that is not configured/enabled, System MUST acknowledge the notification without an error response, perform no synchronization work for that type, and log a warning identifying the unrecognized type.

### Key Entities

- **Legal Text Type**: A category of legal document tracked by the integration (e.g. Imprint, Privacy Policy). Identifies which content to fetch and where it is stored.
- **Synchronization Result**: The outcome of synchronizing one legal text type — which languages had content successfully written, and which languages were skipped because the provider returned no content.
- **Legal Text Update Notification**: An event raised after a successful synchronization, carrying the legal text type and the list of languages that were updated, intended to let host applications react (e.g. invalidate caches).
- **Synchronization Job**: A unit of background work representing "synchronize this legal text type," uniquely identified per type so duplicate requests collapse into a single execution.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After a legal text change notification is received, the corresponding content is available in storage for all configured languages without any manual operator action, in the vast majority of cases (no persistent manual recovery needed under normal provider availability).
- **SC-002**: The system's response to an incoming change notification completes quickly regardless of external provider latency, because fetching and storing happens separately from the response.
- **SC-003**: Legal text content already in storage is never lost or blanked out due to a transient or permanent external provider failure.
- **SC-004**: When the external provider is temporarily unavailable, the system recovers automatically once availability returns, without requiring the change notification to be resent.
- **SC-005**: An operator can refresh all legal text types in one action and receive a clear per-type outcome (which languages updated, which were skipped) without inspecting logs.
- **SC-006**: Duplicate change notifications for the same legal text type arriving close together never result in redundant duplicate fetch-and-store work.

## Assumptions

- The external API client (legal text provider) and the local legal text storage mechanism already exist and are reusable building blocks for this feature (per repository history: API client and atomic storage were implemented in prior work).
- "Configured languages" refers to a list already defined in host application configuration; this feature consumes that list rather than introducing new language configuration.
- The queue/background job infrastructure (queue connection, driver) is already available in the host Laravel application; this feature only defines job behavior (retries, backoff, uniqueness), not queue infrastructure setup.
- A "push" (change notification) identifies a single legal text type per notification; batched multi-type notifications are out of scope.
- Retry attempt count and backoff timing are operator-configurable, defaulting to 3 attempts with exponential backoff (1m/5m/15m), consistent with existing configuration patterns in the package.
- An unconfigured or unknown legal text type in an incoming push is acknowledged without error and without performing synchronization work, but is logged as a warning so operators can detect configuration drift.
