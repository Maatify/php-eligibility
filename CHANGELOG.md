# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- B1 package foundation for `maatify/php-eligibility`, including the PHP 8.4 Composer contract, production autoload, development tooling, and proprietary Maatify license metadata.
- Immutable typed Subject, Context, Rule, and Decision model primitives with canonical UTF-8 validation, bytewise ordering, lifecycle/effect separation, and construction-time invariants.
- Focused B1 unit coverage for canonical input validation, Context shape, Rule identity, ordering, Decision states, and the Eligibility exception marker.
- B2 typed public evaluation and management seams, including ordered batch Subjects/Decisions, bounded Rule criteria, lifecycle and replacement commands, active-dimension results, replaceable repository contracts, and application service interfaces.
- Focused B2 contract coverage for duplicate-safe batches, canonical ordering, lifecycle-visible management results, replacement intent validation, scalar boundaries, and shared semantic exception hierarchies.
- B3 Eligibility-owned `maa_eligibility_rules` schema and direct-PDO Rule repository foundation, including exact byte-safe persistence, bounded inputs, natural-identity uniqueness, lifecycle/effect primitives, management reads, active-dimension reads, bounded bulk loading, cleanup, and MySQL Integration coverage.
- D2 database compatibility resolution recorded as capability-based MySQL-compatible PDO semantics; `mysql:8.4.11` is documented as a reproducibility fixture only, with no minimum MySQL or MariaDB product version claim.

### Fixed

- B1 structural and invariant failures now use a typed Eligibility exception backed by the shared Maatify validation hierarchy, `RuleReference` now exposes the canonical direct fields, and the runtime PCRE extension contract is declared.

No RC1 or published release is claimed by this entry.
