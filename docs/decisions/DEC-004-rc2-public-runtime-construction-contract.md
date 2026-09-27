# DEC-004 — RC2 Public Runtime Construction Contract

## Status

`ACTIVE`

## Context

The package provides Evaluation and Management capabilities over caller-owned
PDO. The default consumer path must not require an ordinary consumer to know
the package-internal mutation-support contract or manually assemble the PDO
adapters and savepoint runner.

## Decisions

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

## Consequences

Ordinary consumers can construct both supported services from one existing
PDO without exposing `RuleMutationSupportInterface` or duplicating adapter
wiring. Advanced integrations retain the existing constructors, interfaces,
and concrete adapters for explicit composition.
