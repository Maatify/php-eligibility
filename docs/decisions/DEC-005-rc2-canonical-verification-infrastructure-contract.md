# DEC-005 — RC2 Canonical Verification Infrastructure Contract

Status: `ACTIVE`

## Context

RC2 verification previously split service provisioning between local Compose,
workflow-native services, manual local startup, and consumer fixtures. That
split made endpoint ownership, cleanup, and current-source identity ambiguous.

## Decisions

1. `docker-compose.integration.yml` is the single repository-owned real-service
   definition, and Docker Compose is the canonical provisioning mechanism for
   Integration infrastructure.
2. PHPUnit, the Consumer Harness, and runnable examples remain on the Host/CI
   PHP runtime; they are not moved into a PHP container.
3. Every independent orchestration invocation creates a unique Compose project,
   fresh disposable state, a loopback-only dynamic MySQL host port, discovers
   its effective endpoint, performs deterministic service and real-PDO
   readiness checks, and removes volumes/state during teardown.
4. Local Integration, CI Integration, Consumer Harness, and database-backed
   example smoke use the same `tools/run-integration.sh` lifecycle contract.
5. The public Composer entries are `test:integration`, `test`, `test:harness`,
   and `test:examples`; raw PHPUnit and Docker lifecycle commands remain
   implementation details of the repository-owned tooling.
6. The current-source Harness uses the synthetic identity
   `dev-rc2-current-source`. It proves production Composer autoload, copied
   installation, public API behavior, and independent-process concurrency
   against real MySQL. It does not claim Published RC1 or RC2 verification.
7. CI-native `services.mysql` is not a canonical path and is not used as a
   convenience alternative after this closure. No production runtime,
   schema, or business semantic change follows from these verification rules.

## Consequences

The same named command owns provisioning and teardown in local and CI flows.
Failures retain Compose diagnostics before cleanup, and cleanup failures remain
observable when no primary verification failure exists. Published-RC proof is
deferred to a later post-publication verification scope.
