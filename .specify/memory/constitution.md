<!--
Sync Impact Report
Version change: none → 1.0.0 (initial ratification)
Modified principles: n/a (first adoption)
Added sections:
  - Core Principles: I. Test-First with Pest, II. Static Analysis Gate,
    III. Consistent Code Style, IV. Backward-Compatible Public API,
    V. Laravel Package Conventions
  - Quality Gates
  - Development Workflow
  - Governance
Removed sections: none
Templates requiring updates:
  - .specify/templates/plan-template.md ✅ (generic Constitution Check gate compatible, no edit required)
  - .specify/templates/spec-template.md ✅ (no principle-specific references)
  - .specify/templates/tasks-template.md ✅ (task categories already cover tests/polish; no edit required)
  - .specify/templates/checklist-template.md ✅ (no principle-specific references)
Follow-up TODOs:
  - TODO(RATIFICATION_DATE): original project start date unknown; using date of this
    constitution's adoption (2026-09-30) as the ratification date since no earlier
    governing document existed.
-->

# erecht24-laravel Constitution

## Core Principles

### I. Test-First with Pest (NON-NEGOTIABLE)
Every behavioral change MUST be covered by a Pest test (`tests/`) written before or
alongside the implementation, using `orchestra/testbench` to exercise the package
inside a real Laravel application context. A change MUST NOT be merged if
`composer test` fails or if new logic ships with no corresponding test. Bug fixes
MUST include a regression test that fails before the fix and passes after.

**Rationale**: This package is consumed by third-party Laravel applications that
generate legal texts (imprint, privacy policy). Silent regressions here have legal
and reputational consequences for consumers, so behavior must be pinned down by
tests rather than left to manual verification.

### II. Static Analysis Gate
All PHP source MUST pass `composer analyse` (Larastan/PHPStan) at the configured
level in `phpstan.neon` with zero unresolved errors before merge. New code MUST NOT
lower the configured analysis level or add blanket `@phpstan-ignore` suppressions
to silence real errors; a suppression is only acceptable with an inline comment
explaining the specific false positive.

**Rationale**: As a library consumed via Composer, type and contract mistakes
surface in downstream applications, not in this repo's own runtime. Static
analysis is the primary defense against shipping broken public contracts.

### III. Consistent Code Style
All PHP code MUST conform to `vendor/bin/pint` formatting (`pint.json`) before
commit; CI MUST run `composer lint` (`pint --test`) as a required check. Formatting
fixes MUST NOT be bundled into commits that also contain behavioral changes.

**Rationale**: A single consistent style keeps diffs focused on substance for a
small open-source package maintained with community contributions, and avoids
style-only review churn.

### IV. Backward-Compatible Public API
Public API surface (service provider bindings, facade methods, published config
keys, artisan commands, and any class/interface under `src/` not marked internal)
follows Semantic Versioning. Breaking a public method signature, removing a config
key, or changing default behavior observable by consumers REQUIRES a MAJOR version
bump and a `CHANGELOG.md` entry describing the migration. Additive, backward
-compatible changes are MINOR; fixes are PATCH.

**Rationale**: Consumers install this package via Composer version constraints
(`^1.0` style). Unannounced breaking changes break builds downstream with no
warning, which is unacceptable for infrastructure that touches legally required
site content.

### V. Laravel Package Conventions
New functionality MUST integrate through the existing package extension points —
`ERecht24ServiceProvider`, the `ERecht24` facade, and published config — rather
than introducing parallel bootstrapping mechanisms. Configuration MUST be
publishable and overridable by the consuming application; no hard-coded
credentials, environment assumptions, or global state outside Laravel's
container/config lifecycle.

**Rationale**: Consumers expect this package to behave like any standard Laravel
package (auto-discovery, publishable config, facade access). Deviating from these
conventions increases integration friction and support burden.

## Quality Gates

Before a change is considered mergeable:
- `composer test` (Pest, via Testbench) passes.
- `composer analyse` (Larastan/PHPStan) passes with no new errors.
- `composer lint` (Pint) reports no violations.
- `CHANGELOG.md` is updated for any user-visible change, with the correct SemVer
  impact noted per Principle IV.

These gates are enforced in CI (`.github/`) and MUST also be run locally before
opening a pull request.

## Development Workflow

- Work is scoped through GitHub issues; non-trivial features SHOULD go through
  spec-kit's `/speckit.specify` → `/speckit.plan` → `/speckit.tasks` →
  `/speckit.implement` flow before implementation begins.
- Pull requests MUST describe the user-visible effect of the change and which
  Quality Gates were run locally.
- Reviewers MUST verify Core Principles I–V are respected, not just that CI is
  green, since CI cannot detect scope creep in the public API or missing
  regression tests for edge cases already covered by manual testing.

## Governance

This constitution supersedes ad hoc conventions for this repository. Amendments
are made by editing this file directly via pull request and MUST include:
1. The specific principle or section being changed and why.
2. A version bump per the policy below.
3. Updates to any `.specify/templates/*` files whose guidance depends on the
   changed principle.

**Versioning policy** (applies to this constitution document itself):
- MAJOR: Backward-incompatible governance changes, or removal/redefinition of an
  existing principle.
- MINOR: A new principle or section is added, or existing guidance is materially
  expanded.
- PATCH: Wording clarifications, typo fixes, and non-semantic refinements.

All pull requests MUST be checked against this constitution during review;
unjustified deviation from a Core Principle blocks merge unless the PR
description documents an explicit, reviewed exception.

**Version**: 1.0.0 | **Ratified**: 2026-09-30 | **Last Amended**: 2026-09-30
