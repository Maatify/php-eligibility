# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [1.0.0-rc.1] - 2026-09-18

### Added

- Comprehensive consumer Usage Guide and capability decision map, runnable public API examples, and README documentation/presentation alignment.
- B1 package foundation for `maatify/php-eligibility`, including the PHP 8.4 Composer contract, production autoload, development tooling, and proprietary Maatify license metadata.
- Immutable typed Subject, Context, Rule, and Decision model primitives with canonical UTF-8 validation, bytewise ordering, lifecycle/effect separation, and construction-time invariants.
- Focused B1 unit coverage for canonical input validation, Context shape, Rule identity, ordering, Decision states, and the Eligibility exception marker.
- B2 typed public evaluation and management seams, including ordered batch Subjects/Decisions, bounded Rule criteria, lifecycle and replacement commands, active-dimension results, replaceable repository contracts, and application service interfaces.
- Focused B2 contract coverage for duplicate-safe batches, canonical ordering, lifecycle-visible management results, replacement intent validation, scalar boundaries, and shared semantic exception hierarchies.
- B3 Eligibility-owned `maa_eligibility_rules` schema and direct-PDO Rule repository foundation, including exact byte-safe persistence, bounded inputs, natural-identity uniqueness, lifecycle/effect primitives, management reads, active-dimension reads, bounded bulk loading, cleanup, and MySQL Integration coverage.
- D2 database compatibility resolution recorded as capability-based MySQL-compatible database-server semantics through direct PDO; PHP runtime requirements are `ext-pdo` and `ext-pdo_mysql`; `mysql:8.4.11` is documented as a reproducibility fixture only, with no minimum MySQL or MariaDB product version claim.
- B4 concrete evaluator and management services, shared single/batch evaluation semantics, typed lifecycle orchestration, atomic dimension replacement, package/Host transaction participation with operation-local savepoints, Subject coordination locking, and focused real-MySQL runtime/concurrency coverage.
- B5 Golden acceptance closure: the executable 52-scenario evidence map, Unit/Golden/Integration/concurrency suites, real-MySQL Integration and repeatability evidence, the Consumer Verification Harness with two clean external-consumer runs, and whole-table residue verification.
- B6 fail-closed CI and release readiness: the `ci-quality`, `ci-tests`, and `ci-integration` GitHub Actions workflows with stable aggregate gates, the PHP `8.4`/`8.5` matrix, newest and lowest dependency ends, real `mysql:8.4.11` integration with repeatability and Harness runs, immutable full-SHA action pins, least-privilege permissions, and workflows linted with the pinned `actionlint` v1.7.12 release verified against its published SHA-256 checksum (no implicit reliance on a PATH binary in required CI).
- B6 maintained local parity tooling: `tools/php-lint.php`, `tools/check-whitespace.sh`, `tools/lint-workflows.sh`, `tools/assert-gate.sh`, `tools/check-local.sh`, `tools/mysql-ready.php`, and the Composer scripts `check:local` and `lint:php`.
- B6 release-facing governance files per the Presentation Standard: `SECURITY.md` (support state, private reporting, scope), `CONTRIBUTING.md` (identity, boundaries, local verification, PR expectations, security route), and `CODE_OF_CONDUCT.md` (community rules).

### Changed

- B6 package presentation rewritten: the README now distinguishes Packagist registration from the `v1.0.0-rc.1` distribution state, and documents requirements, installation state, the public API surface, runtime behavior, persistence/schema, exceptions, security, and local/CI testing.
- Final capability-first source organization is documented, with separate Command, Management Query, Evaluation Read, and internal Mutation Support persistence responsibilities.
- `maatify/persistence ^1.4` is the explicit runtime transaction dependency, and Eligibility now uses its shared `PdoSavepointTransactionRunner` instead of package-local generic transaction/savepoint mechanics.
- Atomic replacement and Subject cleanup, Host-owned outer transaction participation, and Eligibility-owned Subject coordination locking remain preserved under the shared transaction composition.

### Fixed

- B1 structural and invariant failures now use a typed Eligibility exception backed by the shared Maatify validation hierarchy, `RuleReference` now exposes the canonical direct fields, and the runtime PCRE extension contract is declared.
