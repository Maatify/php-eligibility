# DEC-004 — RC2 Public Runtime Construction Contract

## Decision Metadata

| Field | Value |
|---|---|
| Decision ID | `DEC-004` |
| Title | RC2 Public Runtime Construction Contract |
| Status | `ACTIVE` |
| Scope / Concern | php-eligibility RC2 default PDO construction surface, service-interface returns, caller-owned PDO boundary, and explicit exclusion of locator/container behavior |
| Decision Authority / Deciders | Project Owner — approved RC2 public runtime construction direction for WU-RC2-04B — Public Runtime Construction & Wiring Closure |
| Canonical Contract / Current Owner | `ELIGIBILITY_PACKAGE_REFERENCE.md` for the full package runtime contract; this record for the default PDO construction and ownership boundary |

## Context

The package provides Evaluation and Management capabilities over caller-owned
PDO. The default consumer path must not require an ordinary consumer to know
the package-internal mutation-support contract or manually assemble the PDO
adapters and savepoint runner.

## Decision

1. The default package-owned PDO construction surface is
   `PdoEligibilityRuntimeFactory`.
2. The factory is placed at `Factory/Pdo/` because it is a package-wide
   construction responsibility whose technology is PDO.
3. The caller owns PDO creation, configuration, schema application, and any
   outer transaction.
4. The factory creates default Evaluation and Management service graphs over
   that caller-owned PDO.
5. The factory returns `EligibilityEvaluationServiceInterface` and
   `EligibilityManagementServiceInterface`.
6. Internal mutation support does not appear in ordinary consumer wiring.
7. No Builder is provided because the default composition has no progressive
   configuration.
8. No Facade or runtime aggregate is provided because the existing service
   interfaces already own capability access.
9. No factory interface is provided because the factory is not a runtime
   substitution boundary.
10. The factory has no Service Locator or container behavior.
11. Direct service and repository construction remains available as an
    advanced explicit composition path.
12. This construction path introduces no business, schema, or runtime
    semantic change.

## Rationale

One package-owned factory over the caller's existing PDO gives ordinary
consumers a complete default wiring path while preserving Host ownership of
connection configuration, schema application, and outer transactions. The
existing Evaluation and Management service interfaces already define capability
access, so adding a Builder, Facade, runtime aggregate, factory interface, or
Service Locator would introduce abstractions without a corresponding runtime
substitution or configuration requirement.

## Consequences

Ordinary consumers can construct both supported services from one existing
PDO without exposing `RuleMutationSupportInterface` or duplicating adapter
wiring. Advanced integrations retain the existing constructors, interfaces,
and concrete adapters for explicit composition.
