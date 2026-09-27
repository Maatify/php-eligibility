# DEC-001 — RC2 Canonical Source Topology and Pre-Stable FQCN Migration

## Decision Metadata

| Field | Value |
|---|---|
| Decision ID | `DEC-001` |
| Title | RC2 Canonical Source Topology and Pre-Stable FQCN Migration |
| Status | `ACTIVE` |
| Scope / Concern | php-eligibility RC2 source topology, namespace/FQCN placement, and pre-Stable structural compatibility boundary |
| Decision Authority / Deciders | Project Owner — approved RC2 architecture and compatibility direction for WU-RC2-02 |
| Canonical Contract / Current Owner | `ELIGIBILITY_PACKAGE_REFERENCE.md` for package behavior; this record for RC2 structural placement and compatibility boundary |

## Context

RC2 needed one explicit source-placement and compatibility contract before
further capability work could proceed. The package identity already represents
the Eligibility Domain, while the runtime responsibilities are split between
Evaluation and Management. Because the package is still Pre-Stable, correcting
public FQCN placement directly is preferable to preserving obsolete structural
names through a parallel compatibility layer.

## Decision

For RC2, `maatify/php-eligibility` uses the following canonical source topology:

```text
Source Topology: Multi Capability

Capabilities:
- Evaluation
- Management
```

The package identity itself represents the `Eligibility` Domain boundary. A
redundant `src/Eligibility/` directory MUST NOT be introduced.

The canonical placement law is:

```text
Domain → Capability → Responsibility → Technology
```

Because the package identity is the Domain boundary, package-wide
responsibilities (`ValueObject`, `Enum`, `Exception`, `Repository`, and
`Common`) remain directly under `src/`, while capability-owned responsibilities
are placed under `src/Evaluation/` or `src/Management/`. A responsibility
belongs to the capability that owns its contract. Interfaces remain with the
capability/responsibility whose contract they define. Technology-specific
implementations remain under the owning `Repository/Pdo/` responsibility.

The exact structural target is the post-migration `src/` tree defined by the
WU-RC2-02 contract: Evaluation owns `Service`, `ValueObject`, `DTO`, `Enum`,
and `Repository`; Management owns `Command`, `Criteria`, `ValueObject`, `DTO`,
`Service`, and `Repository`; package-wide value objects and enums are placed
directly under `src/ValueObject/` and `src/Enum/`; shared PDO hydration support
is placed at `src/Repository/Pdo/`; and common primitives are placed at
`src/Common/`.

## Rationale

A Multi Capability topology keeps responsibility ownership visible without
introducing a redundant `src/Eligibility/` layer that repeats the package
identity. Applying the corrected topology directly in RC2 keeps one canonical
public structure and avoids aliases, wrappers, or duplicate old/new APIs that
would make the Pre-Stable contract harder to reason about and maintain.

## Compatibility Boundary

`v1.0.0-rc.1` is a published Pre-Stable historical contract. RC2 applies the
canonical source topology directly and performs the required public FQCN
migration. RC2 does not provide legacy namespace aliases, `class_alias()`
bridges, deprecated wrappers, proxy classes, duplicate old/new APIs, or any
other compatibility shim.

Consumers migrating from RC1 to RC2 may need to update imports and FQCNs. The
RC1 names remain historical release context only and are not current RC2 API
claims.

## Semantic Boundary

This decision changes source placement, namespaces, and the explicitly named
public type names required by the RC2 topology. It does not change Eligibility
business rules, matching semantics, ordering semantics, lifecycle semantics,
transaction semantics, concurrency semantics, persistence behavior, database
schema, exception meaning, or result meaning.

## Non-Goals

- Redesigning presentation or consumer documentation beyond directly affected FQCNs.
- Adding a redundant Domain directory.
- Adding legacy aliases or compatibility shims.
- Changing Composer or CI architecture.
- Changing the schema, persistence semantics, or business behavior.
- Preparing an RC2 release or Stable release.

## Consequences

- RC2 consumers using RC1 imports may need to update imports and type references.
- The canonical source layout is explicit and discoverable for future work.
- Direct FQCN migration makes the Pre-Stable structural correction visible rather than preserving obsolete names indefinitely.
