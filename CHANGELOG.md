# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- B1 package foundation for `maatify/php-eligibility`, including the PHP 8.4 Composer contract, production autoload, development tooling, and proprietary Maatify license metadata.
- Immutable typed Subject, Context, Rule, and Decision model primitives with canonical UTF-8 validation, bytewise ordering, lifecycle/effect separation, and construction-time invariants.
- Focused B1 unit coverage for canonical input validation, Context shape, Rule identity, ordering, Decision states, and the Eligibility exception marker.

### Fixed

- B1 structural and invariant failures now use a typed Eligibility exception backed by the shared Maatify validation hierarchy, `RuleReference` now exposes the canonical direct fields, and the runtime PCRE extension contract is declared.

No RC1 or published release is claimed by this entry.
