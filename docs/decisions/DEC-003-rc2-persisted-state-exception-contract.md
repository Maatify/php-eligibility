# DEC-003 — RC2 Persisted-State Exception Contract

## Decision Metadata

| Field | Value |
|---|---|
| Decision ID | `DEC-003` |
| Title | RC2 Persisted-State Exception Contract |
| Status | `ACTIVE` |
| Scope / Concern | php-eligibility RC2 package-defined exception hierarchy/marker ownership, and classification of package-owned malformed persisted Rule state during Rule hydration or lifecycle-summary reads |
| Owner approval context | Owner-approved RC2 architecture and scope for WU-RC2-04A — Management / Admin Integration & Operational Read Closure |
| Canonical Contract / Current Owner | `ELIGIBILITY_PACKAGE_REFERENCE.md` for the full package exception contract; this record for the continuing-effect persisted-state classification and propagation-boundary decisions below |

## Decision

### Exception hierarchy and marker ownership

The existing package exception marker remains the single Eligibility marker:

```text
Maatify\Eligibility\Exception\EligibilityExceptionInterface
```

Every Eligibility-defined exception MUST use the appropriate `maatify/exceptions`
hierarchy and MUST implement `EligibilityExceptionInterface` directly or
indirectly. Unknown/external infrastructure throwables (for example an
unclassified `PDOException`) are not forced into that marker.

### Malformed persisted Rule state classification

A new package exception is added:

```text
Maatify\Eligibility\Exception\InvalidPersistedRuleStateException
    extends Maatify\Exceptions\Exception\System\SystemMaatifyException
    implements EligibilityExceptionInterface
```

It uses `ErrorCodeEnum::MAATIFY_ERROR`, the System category, HTTP `500`, and
`safe = false` (inherited from `SystemMaatifyException`'s defaults), and its
constructor preserves an optional `?Throwable $previous` without inventing a
competing exception hierarchy.

The package classifies the following conditions as
`InvalidPersistedRuleStateException`:

- **Rule hydration corruption:** a non-array row shape; non-string column
  keys; a missing or non-string required persisted column; a persisted
  canonical Subject/dimension component invalid under Eligibility's own
  invariants; or a persisted `effect`/`lifecycle` value not represented by
  `RuleEffectEnum`/`RuleLifecycleEnum`. `PdoRuleHydrationTrait` converts the
  applicable native or package validation failure to the typed exception and
  preserves it as `previous` where applicable.
- **Lifecycle-summary persisted inconsistency:** an independent persisted
  total differs from active plus inactive counts because a persisted
  lifecycle value is unrecognized. `PdoRuleManagementQuery` raises the typed
  exception instead of silently returning an undercounted summary; this path
  has no wrapped Throwable by itself.

When the classification is caused by an existing
`InvalidEligibilityInputException` or a `\ValueError` raised while validating
persisted bytes, that original exception is preserved as `previous`.

### External throwable propagation boundary

The hydration trait does not blanket-catch `\Throwable`. An unknown
`PDOException` or other unclassified storage/infrastructure failure continues
to propagate unchanged; it is not translated merely to make every throwable
implement the Eligibility marker. Shared `maatify/persistence` exceptions
(for example pagination failures) remain external dependency failures unless
Eligibility owns a distinct semantic classification for the specific
condition, and are not forced into the Eligibility marker either.

## Compatibility Boundary

This is an additive pre-Stable classification: no previously thrown
Eligibility-defined exception type is removed or renamed, and no legacy alias
or compatibility shim is introduced. Callers that previously observed a native
`UnexpectedValueException`/`ValueError` from malformed persisted hydration
input will now observe `InvalidPersistedRuleStateException` instead, with the
original condition preserved as `previous` where applicable. Callers that
previously observed a silent lifecycle-summary undercount for an unrecognized
persisted lifecycle will now observe the typed
`InvalidPersistedRuleStateException` instead.

## Semantic Boundary

This decision changes only the classification of package-owned malformed
persisted Rule state during Rule hydration and lifecycle-summary reads, and
does not change Rule natural-identity, lifecycle, matching, ordering,
replacement, cleanup, transaction, or concurrency semantics, and does not
change the database schema. It does not alter the existing duplicate-key
classification (`RuleIdentityConflictException`, proven MySQL/MariaDB driver
code `1062` only) or the existing propagation behavior for unknown storage
failures (Canonical Acceptance Scenario 48).

## Non-Goals

- Inventing a second Eligibility exception marker or a competing exception
  hierarchy alongside `maatify/exceptions`.
- Blanket-wrapping every `\Throwable` observed by the persistence adapters.
- Forcing shared `maatify/persistence` exceptions to implement the
  Eligibility marker.
- Changing the schema, business semantics, or evaluation/lifecycle/
  replacement/concurrency behavior.

## Consequences

- Hydration corruption no longer leaks its applicable native or package
  validation failure: it is classified as the typed
  `InvalidPersistedRuleStateException`, preserving `previous` where
  applicable.
- A lifecycle-summary aggregate inconsistency no longer produces a silent
  undercount: it is classified as the same typed
  `InvalidPersistedRuleStateException`.
- Consumers catching `EligibilityExceptionInterface` now also observe this
  classification; consumers relying on the previous native
  `UnexpectedValueException`/`ValueError` type from hydration must update
  their catch clauses.
- Unknown/external storage failures remain observably unchanged, preserving
  existing diagnostic behavior for infrastructure failures the package does
  not own a semantic classification for.
