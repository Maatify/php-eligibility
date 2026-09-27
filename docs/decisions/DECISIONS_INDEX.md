# Decisions Index

This file is the canonical repository-root Decision Index for `Maatify/php-eligibility`.

It is a discovery surface, not an ADR, not a copy of decision rationale, not a
substitute for a Decision Record, and not a substitute for
`docs/php-engineering-standards/STANDARDS_MANIFEST.md`.

## Ownership

- Discovery, Status Model, and Applicability of this index are owned by
  `std-decision-governance` (`DECISION_GOVERNANCE_STANDARD_AR.md`), which is in this
  repository's Resolved Applicable Standards Set.
- ADR document role, current-versus-historical semantics, freshness, retention, and
  durable documentation language are owned by `std-documentation-lifecycle`
  (`DOCUMENTATION_LIFECYCLE_STANDARD_AR.md`).
- The Standards Adoption composition, applicable set, and pinned Adoption Commit are
  recorded separately in `docs/php-engineering-standards/STANDARDS_MANIFEST.md`.

## Current Discovery State

```text
Decision Records: 4
Active Decisions: 4
Pending Owner Decisions: 0
```

This repository currently holds four durable engineering decision records.

The registry is intentionally maintained as the discovery surface required by
`DECISION_GOVERNANCE_STANDARD_AR.md`.

`docs/decisions/` contains this index and the active records below. Runtime code paths that contain the word `Decision`
(for example `src/Evaluation/ValueObject/`) are package runtime artifacts governed by
`ELIGIBILITY_PACKAGE_REFERENCE.md`; they are not governance decision records and are
deliberately not indexed here.

## Historical Backfill State

No historical decision records have been reconstructed from chat history, pull request
narratives, memory, or inferred implementation. Historical backfill is prohibited by
`DECISION_GOVERNANCE_STANDARD_AR.md` and is not performed implicitly by this bootstrap.

Where a current canonical Standard or `ELIGIBILITY_PACKAGE_REFERENCE.md` already
represents current behavior, that artifact remains the current source of truth and no
fabricated historical ADR was created to complete this index.

## Decision Entry Format

Each future decision record must be reachable from a row below with, as applicable:

| Field | Value |
|---|---|
| Decision ID | Stable unique identifier; the filename alone is not the identity |
| Title | Decision title |
| Status | `PROPOSED`, `ACTIVE`, `SUPERSEDED`, `REJECTED`, or `DEFERRED` |
| Scope / Concern | Applicability scope of the decision inside this repository |
| Decision Record | Path to the real record under `docs/decisions/` |
| Canonical Contract / Current Owner | Standard, architecture document, or artifact that currently owns the contract |
| Supersedes | Decision ID replaced by this one |
| Superseded By | Decision ID that replaced this one |

## Decision Entries

| Decision ID | Title | Status | Scope / Concern | Decision Record | Canonical Contract / Current Owner | Supersedes | Superseded By |
|---|---|---|---|---|---|---|---|
| `DEC-001` | RC2 Canonical Source Topology and Pre-Stable FQCN Migration | `ACTIVE` | php-eligibility RC2 source topology, namespace/FQCN placement, and pre-Stable structural compatibility boundary | `docs/decisions/DEC-001-rc2-canonical-source-topology-and-pre-stable-fqcn-migration.md` | `ELIGIBILITY_PACKAGE_REFERENCE.md` for package behavior; `DEC-001` for RC2 structural placement and compatibility boundary | None | None |
| `DEC-002` | RC2 Management Operational-Read Contract | `ACTIVE` | php-eligibility RC2 Management/Admin operational-read boundary: Host/Admin vs. Eligibility ownership, paginated Rule management reads, paginated active-dimension discovery, and the Rule lifecycle summary | `docs/decisions/DEC-002-rc2-management-operational-read-contract.md` | `ELIGIBILITY_PACKAGE_REFERENCE.md` for package behavior; `DEC-002` for the Host/Admin boundary and pagination/summary contract | None | None |
| `DEC-003` | RC2 Persisted-State Exception Contract | `ACTIVE` | php-eligibility RC2 package-defined exception hierarchy/marker ownership, and classification of malformed persisted Rule state | `docs/decisions/DEC-003-rc2-persisted-state-exception-contract.md` | `ELIGIBILITY_PACKAGE_REFERENCE.md` for package behavior; `DEC-003` for the persisted-state classification and propagation boundary | None | None |
| `DEC-004` | RC2 Public Runtime Construction Contract | `ACTIVE` | php-eligibility RC2 default PDO construction surface, service-interface returns, caller-owned PDO boundary, and explicit exclusion of locator/container behavior | `docs/decisions/DEC-004-rc2-public-runtime-construction-contract.md` | `ELIGIBILITY_PACKAGE_REFERENCE.md` for package behavior; `DEC-004` for the default construction and ownership boundary | None | None |

## Discovery Integrity

- Decision IDs are unique.
- Every durable decision record under `docs/decisions/` is discoverable from this index.
- Every index reference points to a real record.
- Status recorded here matches the current lifecycle status in the decision record.
- No supersession chain contains a cycle.
- Any status or supersession change is made in the decision record and in this index
  within the same bounded change.
- An un-indexed decision file is a decision governance gap.
- Conflicting `ACTIVE` decisions in overlapping scopes are an unresolved governance
  conflict and are not resolved unilaterally by an agent.
