# Feature Specification: eRecht24 Artisan Commands

**Feature Branch**: `005-artisan-commands`

**Created**: 2026-10-03

**Status**: Draft

**Input**: User description: "Add Artisan commands: erecht24:register {--push-uri=} {--write-env}, erecht24:unregister {client-id?}, erecht24:status {--test-push}, erecht24:sync {type?}. Context: The push client must be registered with eRecht24 once per environment, which returns a secret. A project allows at most three clients (e.g. local, staging, production), so registration must be idempotent and must not blindly create duplicates. Since the secret lives in .env, the register command cannot reliably store it in production. register: build push URI from config('app.url') + push path unless --push-uri given; refuse localhost URIs with a hint to use a tunnel or --push-uri; list existing clients, update matching push URI instead of creating, abort with a list if three clients exist and none match; send push method POST, CMS Laravel, Laravel version, plugin name and author mail (ERECHT24_AUTHOR_MAIL, optional); print returned client ID and secret once; with --write-env write ERECHT24_PUSH_SECRET into .env only if writable, else print the line to copy. unregister: delete a client by ID or the one matching the current push URI when omitted, with confirmation. status: show configuration sanity (keys present without printing them, secret present, languages), registered clients, stored texts with timestamps; with --test-push fire ping for the matching client and report result. sync: run LegalTextSynchronizer synchronously for one type or all types, print SyncResult. All commands return non-zero exit codes on failure and never print API key, plugin key, or existing secrets."

## Clarifications

### Session 2026-10-03

- Q: When `erecht24:register` is run without `--write-env` (or `.env` is not writable), how should the returned secret be presented so an operator can copy it reliably? → A: Print it as a single `ERECHT24_PUSH_SECRET=...` line, ready to paste into `.env`.
- Q: Should `erecht24:unregister` prompt for confirmation even when a `client-id` argument is explicitly given? → A: Yes, always confirm before deleting, regardless of how the client was identified.
- Q: What should `erecht24:status --test-push` do when no registered client matches the current push URI? → A: Report that no matching client is registered and skip the ping, without treating it as a hard failure of the rest of the status output.
- Q: Should `erecht24:unregister` have a non-interactive escape hatch for scripted/CI use? → A: Yes, add a `--force` flag that skips the confirmation prompt.
- Q: How broadly should `register` detect "localhost" URIs to refuse? → A: Broad — also refuse common local dev TLDs/hosts (`.test`, `.local`, `.localhost`) in addition to loopback addresses.
- Q: How should `register` determine the "push path" portion of the push URI, given the push-receiving endpoint itself is not yet built? → A: Push path is a configuration value (with a sensible default); the actual push-receiving route is a separate, not-yet-built concern.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Register this environment as a push client (Priority: P1)

As an operator setting up the package in a new environment (local, staging, production), I need to register this environment with eRecht24 so it can receive push notifications, and receive the secret needed to validate those pushes, without accidentally creating a duplicate registration if I run the command again.

**Why this priority**: Without registration, the integration cannot receive push notifications at all — this is the entry point that makes the rest of the package's sync behavior possible.

**Independent Test**: Can be fully tested by running the register command against a faked API with no existing clients, verifying a client is created and the secret is printed exactly once, then running it again with the same push URI and verifying the existing client is updated rather than a new one created.

**Acceptance Scenarios**:

1. **Given** no existing push clients are registered, **When** an operator runs the register command, **Then** a new client is created using the computed or given push URI, and the returned client ID and secret are printed once.
2. **Given** a push client already exists with the same push URI, **When** an operator runs the register command again, **Then** the existing client is updated instead of a new one being created, and no duplicate client results.
3. **Given** three push clients already exist and none match the current push URI, **When** an operator runs the register command, **Then** the command aborts without creating any client, and lists the existing clients with instructions for manual cleanup.
4. **Given** no `--push-uri` option is given and the computed push URI resolves to a localhost address, **When** an operator runs the register command, **Then** the command refuses to proceed and explains that a tunnel or an explicit `--push-uri` is required.
5. **Given** the `--write-env` option is given and the `.env` file is writable, **When** registration succeeds, **Then** the `ERECHT24_PUSH_SECRET` line in `.env` is updated to the new secret and no other line in the file is altered.
6. **Given** the `--write-env` option is given but the `.env` file is not writable, **When** registration succeeds, **Then** the command prints the `ERECHT24_PUSH_SECRET` line for the operator to copy manually instead of failing silently.

---

### User Story 2 - Check integration health at a glance (Priority: P1)

As an operator, I need a single command that tells me whether the package is configured correctly, which push clients are registered, and what legal text content is currently stored, so I can verify the integration without reading code or querying the API manually.

**Why this priority**: Equally critical to registration — operators need a fast, safe way to confirm the integration is healthy, especially after deploys or configuration changes, without ever risking exposure of secrets.

**Independent Test**: Can be fully tested by running the status command against a faked API and fake storage, and verifying it reports configuration presence, registered clients, and stored text timestamps without printing any secret value.

**Acceptance Scenarios**:

1. **Given** valid configuration is present, **When** an operator runs the status command, **Then** the command reports that the API key, plugin key, and push secret are present (as booleans/labels only, never their values) and lists the configured languages.
2. **Given** push clients are registered with eRecht24, **When** an operator runs the status command, **Then** the command lists each registered client (e.g. ID and push URI) without printing any client secret.
3. **Given** legal texts have been previously synced and stored, **When** an operator runs the status command, **Then** the command lists each stored legal text with its last-fetched/stored timestamp.
4. **Given** the `--test-push` option is given and a registered client matches the current push URI, **When** the status command runs, **Then** it sends a test ping to that client and reports success or failure of the ping.
5. **Given** the `--test-push` option is given and no registered client matches the current push URI, **When** the status command runs, **Then** it reports that no matching client was found and skips the ping, while still completing the rest of the status output.

---

### User Story 3 - Remove a push client registration (Priority: P2)

As an operator decommissioning an environment or cleaning up a stale registration, I need to delete a specific push client from eRecht24, with a safety confirmation, so I don't accidentally remove the wrong client.

**Why this priority**: Important for environment lifecycle and avoiding hitting the three-client limit indefinitely, but less urgent day-to-day than registering and checking status.

**Independent Test**: Can be fully tested by running the unregister command with an explicit client ID against a faked API and confirming the deletion, then separately testing the no-argument form against a client matching the current push URI.

**Acceptance Scenarios**:

1. **Given** a client ID is provided, **When** an operator runs the unregister command and confirms, **Then** that specific client is deleted.
2. **Given** no client ID is provided, **When** an operator runs the unregister command and confirms, **Then** the client matching the current push URI is deleted.
3. **Given** an operator runs the unregister command, **When** prompted for confirmation, **Then** the deletion only proceeds if the operator explicitly confirms, regardless of whether a client ID was given.
4. **Given** no client ID is provided and no registered client matches the current push URI, **When** an operator runs the unregister command, **Then** the command fails clearly without attempting a deletion.
5. **Given** a `--force` flag is given, **When** an operator runs the unregister command in a non-interactive/scripted context, **Then** the confirmation prompt is skipped and the deletion proceeds directly.

---

### User Story 4 - Run a manual synchronization from the command line (Priority: P2)

As an operator, I need to trigger a legal text synchronization manually from the command line, for one type or all types, so I can recover from a missed push or refresh content on demand without waiting for a webhook.

**Why this priority**: Provides an operational escape hatch and a CLI entry point to the sync service, but the push-driven path remains the primary flow, so this can follow the P1 stories.

**Independent Test**: Can be fully tested by running the sync command with and without a type argument against a faked API and verifying the printed result matches the per-type synchronization outcome.

**Acceptance Scenarios**:

1. **Given** a specific legal text type is given as an argument, **When** an operator runs the sync command, **Then** only that type is synchronized and its result (languages written/skipped) is printed.
2. **Given** no type argument is given, **When** an operator runs the sync command, **Then** all configured legal text types are synchronized and a result is printed for each.
3. **Given** a synchronization fails for one or more types, **When** the sync command completes, **Then** the command exits with a non-zero status and the printed output distinguishes which types failed.

---

### Edge Cases

- What happens when the `register` command is run with `--push-uri` pointing to a non-HTTP(S) or malformed URI? The command rejects it before contacting the API and explains the expected format.
- What happens when the API call during `register`, `unregister`, `status`, or `sync` fails due to a network error or authentication failure? The command reports a clear, non-sensitive error message and exits with a non-zero status.
- What happens when `register` finds a matching client by push URI but the API update call itself fails? The command reports the failure and exits non-zero without claiming success; no secret is printed since none was returned.
- What happens when `status` is run but required configuration (e.g. API key) is missing entirely? The command reports which configuration is missing (by name only) and still attempts to report whatever else it can (e.g. stored texts), rather than crashing.
- What happens when `unregister` is run for a client ID that does not exist? The command reports that the client was not found and exits non-zero without treating it as a successful no-op.
- What happens when `sync` is given a type name that is not a recognized/configured legal text type? The command rejects the argument with a clear message listing valid types, before attempting any API call.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a `register` command that computes a push URI from the host application's base URL and a configurable push path (with a sensible default) unless an explicit push URI is supplied; the push-receiving route itself is a separate, not-yet-built concern that this feature does not depend on.
- **FR-002**: System MUST refuse to register using a push URI that resolves to a localhost/loopback address (including `localhost`, loopback IPs, and common local-dev hosts such as `.test`, `.local`, `.localhost`), and MUST instruct the operator to use a tunnel or supply an explicit push URI instead.
- **FR-003**: Before creating a new push client, System MUST check existing registered clients and, if one already matches the computed/given push URI, update that client instead of creating a new one.
- **FR-004**: System MUST refuse to create a new push client when three clients already exist and none match the current push URI, and MUST list the existing clients and next-step instructions instead of creating a fourth.
- **FR-005**: When registering or updating a client, System MUST submit the push method, CMS identifier, host application framework version, plugin name, and (optionally) an author contact email sourced from configuration.
- **FR-006**: System MUST print the client ID and secret returned by a successful registration exactly once, in a form the operator can copy directly into environment configuration.
- **FR-007**: When the `--write-env` option is given and the environment file is writable, System MUST update only the push-secret line in that file, leaving all other lines unchanged.
- **FR-008**: When the `--write-env` option is given and the environment file is not writable, System MUST fall back to printing the secret line for manual copying rather than failing without output.
- **FR-009**: System MUST provide an `unregister` command that deletes a push client identified either by an explicit client ID argument or, when omitted, by matching the current push URI.
- **FR-010**: System MUST require explicit operator confirmation before deleting a client via `unregister`, regardless of how the client was identified, unless a `--force` flag is given to skip the prompt for scripted/non-interactive use.
- **FR-011**: System MUST provide a `status` command that reports, without ever printing secret values, whether required configuration (API key, plugin key, push secret) is present, and which languages are configured.
- **FR-012**: The `status` command MUST list currently registered push clients (identifying information only, no secrets) and currently stored legal texts with their last-updated timestamps.
- **FR-013**: When the `status` command is run with a test-push option, System MUST send a test ping to the client matching the current push URI and report whether it succeeded; if no client matches, it MUST report that fact and skip the ping without failing the rest of the command.
- **FR-014**: System MUST provide a `sync` command that synchronizes a single legal text type when given, or all configured legal text types when no type is given, reusing the existing synchronization behavior.
- **FR-015**: Every command MUST exit with a non-zero status code when its operation fails (including partial failures during `sync` across multiple types), and with a zero status when it fully succeeds.
- **FR-016**: No command MUST ever print an API key, plugin key, or an existing/previously issued secret; a newly issued secret from `register` is the only secret value ever printed, and only once, at creation/update time.

### Key Entities

- **Push Client**: A registration record held by eRecht24 representing one environment's push endpoint, identified by a client ID and push URI, with an associated secret issued once.
- **Push URI**: The HTTPS endpoint eRecht24 sends change notifications to; computed from host application configuration or supplied explicitly.
- **Status Report**: A point-in-time summary of configuration presence, registered push clients, and stored legal texts with timestamps, excluding all secret values.
- **Sync Result**: The per-type outcome (languages written, languages skipped) produced by running the existing synchronization behavior from the command line.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An operator can register a new environment and obtain a usable push secret in a single command invocation, without manual API calls.
- **SC-002**: Running the register command repeatedly for the same environment never results in more than one push client for that environment.
- **SC-003**: An operator can determine the full health of the integration (configuration, registered clients, stored content) from one command's output, without inspecting source code or logs.
- **SC-004**: No command's output, under any flag combination, ever reveals an API key, plugin key, or a previously issued push secret.
- **SC-005**: An operator can clean up a specific environment's registration with a single confirmed command, with no risk of accidental deletion without confirmation.
- **SC-006**: An operator can manually recover from a missed push notification for any single legal text type, or all of them, using one command.

## Assumptions

- `LegalTextSynchronizer` (from the in-progress 004 sync feature) and `Erecht24Client`'s client CRUD/testPush operations already exist and are reusable building blocks for these commands.
- "Author mail" is an optional, package-level configuration value (`ERECHT24_AUTHOR_MAIL`) rather than something collected interactively by the command.
- The three-client limit and the matching-by-push-URI rule are enforced client-side by the command using the list of clients returned by the API, not by a dedicated API endpoint for this check.
- The `.env` file at the application root is the only environment file this feature writes to; multi-environment `.env` file setups (e.g. `.env.staging`) are out of scope.
- "Non-zero exit code on failure" follows standard Artisan command conventions (returning `Command::FAILURE` or a non-zero integer).
- The push-receiving endpoint (the route eRecht24 actually calls) is out of scope for this feature; `register` only needs a configurable push path to build the URI it sends to eRecht24, and that endpoint can be built as a separate later feature without requiring changes to `register`.
- Of the client metadata submitted during registration (FR-005), only the author contact email is operator-configurable (`ERECHT24_AUTHOR_MAIL`). The push method, CMS identifier, framework version, and plugin name identify this package and its Laravel integration itself, not a deployment-specific choice, so they are fixed values rather than `.env`-overridable settings.
