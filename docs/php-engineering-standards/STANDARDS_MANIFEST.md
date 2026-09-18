# Maatify Engineering Standards Manifest

## Adoption Status

- **Resolution Status:** `VALID`
- **Exception State:** `NONE`
- **Manifest Type:** completed selective pinned adoption record
- **Adoption Date:** `2026-09-16`
- **Local Standards Root:** `docs/php-engineering-standards/`

## Upstream Source

- **Upstream Repository:** `Maatify/php-engineering-standards`
- **Adoption Commit:** `44c8827095ab4007c355aa21c56b853f3b49d795`
- **Floating References:** none
- **Mixed-Commit Adoption:** no

Every copied upstream file listed below is byte-for-byte sourced from the exact Adoption Commit above.

## Pinned Adoption Control Set

The Control Set contains the Adoption Standard and the active Profile manifests required to resolve the activations. There are no inherited Profiles because both active Profiles declare `Extends: None`.

- `docs/php-engineering-standards/standards/STANDARDS_ADOPTION_STANDARD_AR.md`
- `docs/php-engineering-standards/standards/profiles/COMPOSER_PACKAGE_PROFILE.md`
- `docs/php-engineering-standards/standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md`

Unused Profiles are not copied.

## Profile Activations

| Profile ID | Profile Version | Scope | Activation Facts | Resolution Status | Exception State |
|---|---:|---|---|---|---|
| `composer-package` | `1.0.0` | `/` | Independent reusable PHP/Composer package; owns Rule persistence behavior and a planned framework-neutral/reference PDO adapter | `VALID` | `NONE` |
| `repository-governance` | `1.0.0` | `/` | Repository follows the Maatify phase/stacked-PR workflow | `VALID` | `NONE` |

### Structural / Transitive Resolution

- `composer-package` has `Extends: None` and five valid direct Standard references.
- `repository-governance` has `Extends: None` and two valid direct Standard references.
- No inheritance cycle, missing Profile, missing Required Standard, broken reference to a selected local adoption file, or missing mandatory Profile metadata was found.
- No Explicit Additional Standards were declared.

The Adoption Standard's reference to `governance/STANDARD_VERSIONING_POLICY_AR.md` is intentionally not copied because §5.1 excludes that policy from the Control Set and Resolved Applicable Standards Set. The conditional `MODULE_BUILDING_STANDARD.md` reference in the standalone Package Building Standard is likewise outside this package's activated artifact scope. References to future package files and canonical placeholders remain owned by the repository's later implementation/presentation work.

### Canonical Applicability Resolution

The final set below is the union of the Standards applicable to the two `/` activations. The persistence/database conditional rules inside `std-package-building` are applicable because this package owns Rule persistence behavior; this does not add an extra Standard or justify copying unrelated files.

## Resolved Applicable Standards Set

Only the final applicable Standards are listed here. Candidate references that are not applicable would not be recorded in this set.

| Local Path | Standard ID | Standard Version | Applicable Activation / Scope |
|---|---|---:|---|
| `docs/php-engineering-standards/standards/packages/PACKAGE_BUILDING_STANDARD.md` | `std-package-building` | `1.4.0` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/packages/COMPOSER_PACKAGE_STANDARD.md` | `std-composer-package` | `2.0.0` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/packages/CI_WORKFLOW_STANDARD.md` | `std-ci-workflow` | `1.1.0` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/packages/LIBRARY_PRESENTATION_STANDARD.md` | `std-library-presentation` | `1.0.1` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/testing/TESTING_STANDARD.md` | `std-testing` | `1.1.0` | `composer-package` `/` |
| `docs/php-engineering-standards/standards/ai/AI_COLLABORATION_WORKFLOW_AR.md` | `std-ai-collaboration-workflow` | `6.0.0` | `repository-governance` `/` |
| `docs/php-engineering-standards/standards/GITHUB_PHASE_STACK_WORKFLOW_AR.md` | `std-github-phase-stack-workflow` | `2.2.0` | `repository-governance` `/` |

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
- Historical audits or decisions copied: `NO`
- Underlying Standards remain the source of truth: `YES`
