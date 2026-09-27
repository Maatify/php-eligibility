# Maatify Engineering Standards Manifest

## Adoption Status

- **Resolution Status:** `VALID`
- **Exception State:** `NONE`
- **Manifest Type:** completed selective pinned adoption record
- **Adoption Date:** `2026-09-26`
- **Local Standards Root:** `docs/php-engineering-standards/`

## Upstream Source

- **Upstream Repository:** `Maatify/php-engineering-standards`
- **Adoption Commit:** `7dd9d1d02b53013da0906c729dab4f667afeefb4`
- **Previous Adoption Commit:** `44c8827095ab4007c355aa21c56b853f3b49d795` (replaced by this completed upgrade)
- **Floating References:** none
- **Mixed-Commit Adoption:** no

Every copied upstream file listed below is byte-for-byte sourced from the exact Adoption Commit above.

## Pinned Adoption Control Set

The Control Set contains the Adoption Standard and the active Profile manifests required to resolve the activations. There are no inherited Profiles because both active Profiles declare `Extends: None`.

- `docs/php-engineering-standards/standards/STANDARDS_ADOPTION_STANDARD_AR.md` — `std-standards-adoption` `4.0.0`
- `docs/php-engineering-standards/standards/profiles/COMPOSER_PACKAGE_PROFILE.md`
- `docs/php-engineering-standards/standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md`

Unused Profiles are not copied.

## Profile Activations

| Profile ID | Profile Version | Scope | Activation Facts | Resolution Status | Exception State |
|---|---:|---|---|---|---|
| `composer-package` | `3.0.0` | `/` | Independent reusable PHP/Composer library (`maatify/php-eligibility`, `type: library`); owns Rule persistence behavior with direct PDO repositories, `schema/`, and a real MySQL integration fixture | `VALID` | `NONE` |
| `repository-governance` | `3.0.0` | `/` | Repository follows the Maatify phase/stacked-PR workflow and is subject to durable engineering decision governance | `VALID` | `NONE` |

### Structural / Transitive Resolution

- `composer-package` has `Extends: None` and eight valid direct Standard references.
- `repository-governance` has `Extends: None` and four valid direct Standard references.
- No inheritance cycle, missing Profile, missing Required Standard, broken reference to a selected local adoption file, or missing mandatory Profile metadata was found.
- No Explicit Additional Standards were declared.
- Every Candidate Standard Reference in the union of both activations resolved to an existing canonical file at the exact Adoption Commit. No Required Standard was dropped before applicability filtering.

### Frozen Profile Version Baseline

The previous completed `VALID` adoption pinned `composer-package 1.0.0` and `repository-governance 1.0.0` at Adoption Commit `44c8827095ab4007c355aa21c56b853f3b49d795`, and that Profile artifact snapshot is still exactly reproducible at that commit. This upgrade adopts `3.0.0` for both Profiles, so no frozen Profile version is presented with different Profile manifest/content. There is no known frozen-version/content mismatch, and the Frozen Version Baseline status is evidenced rather than assumed.

### Canonical Applicability Resolution

All eleven Candidate Standards were evaluated against the activation scope and the actual artifact facts, using only the canonical applicability and conditional applicability owned by each Standard. No candidate required a Profile or Manifest override, and no candidate was left undetermined.

Applicable conditional rules:

- The `Persistence Conditional Applicability` rules owned by `std-package-building` are **applicable**, because this package owns SQL persistence behavior: direct repositories under `src/Evaluation/Repository/` and `src/Management/Repository/`, technology-specific PDO implementations under `src/Evaluation/Repository/Pdo/` and `src/Management/Repository/Pdo/`, shared PDO support under `src/Repository/Pdo/`, `schema/eligibility_rules.sql`, `ext-pdo` / `ext-pdo_mysql` requirements, the `maatify/persistence` dependency, and a real MySQL integration fixture. All persistence, schema, transaction, and persistence-testing obligations of that Standard are therefore in force. This adds no extra Standard.
- The `AI consumer discovery applicability` rule owned by `std-library-presentation` is **applicable**, because this repository is a standalone reusable PHP Composer library.

Non-applicable conditional rules:

- The `project-host @ /` activation obligation in `std-standards-adoption` is **not applicable**. The repository root artifact is a package-only reusable library, not a deployable Host/Application Project, and no `project-aware-slim-module` activation exists.
- `STANDARD_VERSIONING_POLICY_AR.md` is **not applicable and not copied**. It is a central governance policy version-managed upstream, not a Required Engineering Standard, and `std-standards-adoption` excludes it from the Control Set and from the Resolved Applicable Standards Set. `std-php-source-documentation` and `std-php-coding-style` reference it by canonical filename only and explicitly declare that it creates no consumer pinned-file requirement.
- Module, project-host, project-application, project-documentation, and HTTP API endpoint Standards are **not candidates** for these activations and are not copied.

## Resolved Applicable Standards Set

Only the final applicable Standards are listed here. Candidate references that are not applicable would not be recorded in this set.

| Local Path | Standard ID | Standard Version | Applicable Activation / Scope |
|---|---|---:|---|
| `docs/php-engineering-standards/standards/packages/PACKAGE_BUILDING_STANDARD.md` | `std-package-building` | `3.0.1` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/packages/COMPOSER_PACKAGE_STANDARD.md` | `std-composer-package` | `4.0.0` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/packages/CI_WORKFLOW_STANDARD.md` | `std-ci-workflow` | `3.0.0` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/packages/LIBRARY_PRESENTATION_STANDARD.md` | `std-library-presentation` | `3.0.0` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/testing/TESTING_STANDARD.md` | `std-testing` | `1.1.1` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/governance/DOCUMENTATION_LIFECYCLE_STANDARD_AR.md` | `std-documentation-lifecycle` | `3.0.0` | `composer-package` `/`, `repository-governance` `/` |
| `docs/php-engineering-standards/standards/php/PHP_SOURCE_DOCUMENTATION_STANDARD.md` | `std-php-source-documentation` | `1.0.0` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/php/PHP_CODING_STYLE_STANDARD.md` | `std-php-coding-style` | `1.0.1` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/ai/AI_COLLABORATION_WORKFLOW_AR.md` | `std-ai-collaboration-workflow` | `8.0.0` | `repository-governance` `/` |
| `docs/php-engineering-standards/standards/GITHUB_PHASE_STACK_WORKFLOW_AR.md` | `std-github-phase-stack-workflow` | `4.0.0` | `repository-governance` `/` |
| `docs/php-engineering-standards/standards/governance/DECISION_GOVERNANCE_STANDARD_AR.md` | `std-decision-governance` | `1.0.0` | `repository-governance` `/` |

### Local Reference Closure

The final pinned local set is locally reference-closed. Every relative link required by an applicable Standard or by an active Profile manifest resolves to a target that exists either in the Pinned Adoption Control Set or in the Resolved Applicable Standards Set. `docs/decisions/` is repository-local and is not part of the upstream Standards Adoption Set, so the upstream central `docs/decisions/` records are not copied.

## Explicit Additional Standards

`None`.

## Explicit Exceptions / Overrides

`None`.

## Adoption Invariants

- Central canonical source: `Maatify/php-engineering-standards`
- Adoption Standard present locally: `YES`
- Active Profile manifests pinned locally: `YES`
- Inherited Profile manifests pinned locally: `NOT APPLICABLE` (`Extends: None` for both activations)
- Unused Profiles copied: `NO`
- Applicable Standards only: `YES`
- Ordinary task requires upstream network: `NO`
- Manifest auditable from local Control Set and exact Adoption Commit: `YES`
- Full upstream `standards/` snapshot copied: `NO`
- Historical audits, verification evidence, or decisions copied: `NO`
- Repository-owned decision governance: `docs/decisions/DECISIONS_INDEX.md` (required by `std-decision-governance`; canonical current Decision discovery lives there)
- Underlying Standards remain the source of truth: `YES`
