# php-eligibility v1.0.0-rc.1 Delivery Plan

## Purpose

This document defines the integration rule for the first Release Candidate of `maatify/php-eligibility`.

The branch `phase/v1.0.0-rc.1` is the umbrella integration branch for the complete RC1 build. It is intentionally opened as a Draft Pull Request against `main` and MUST remain Draft until the package is complete and ready to be exported as `v1.0.0-rc.1`.

## Non-merge rule

The umbrella Pull Request MUST NOT be merged into `main` while any RC1 work remains incomplete.

It becomes eligible for final review and merge only when all of the following are true:

- the canonical package scope, boundaries, terminology, and rule semantics are documented and frozen for RC1;
- all planned RC1 implementation slices have been completed and merged into the umbrella branch;
- public contracts, DTOs, commands, queries, repositories, services, and persistence schema required by RC1 are complete;
- package behavior is covered by the required unit and integration tests;
- PHPStan passes at the repository's required level;
- the package documentation matches the implemented behavior;
- Composer metadata and installability are ready for external package consumption;
- migration/schema artifacts required by the package are complete;
- CHANGELOG/release documentation for `v1.0.0-rc.1` is complete;
- there are no known RC1 blockers or unfinished TODO items within the declared RC1 scope;
- the complete umbrella branch is judged ready to tag/export as `v1.0.0-rc.1` immediately after merge.

Passing individual child Pull Requests does not make the umbrella mergeable by itself.

## Stacked development workflow

All RC1 work is developed as stacked child branches and Draft Pull Requests targeting `phase/v1.0.0-rc.1`.

Each child Pull Request owns one coherent slice of the RC1 work. A child may be completed, reviewed, and squash-merged into the umbrella branch independently. The next slice is then based on the updated umbrella branch.

The umbrella branch is therefore the accumulated, reviewable RC1 candidate. `main` receives the package only once the entire RC1 scope is complete.

## Initial stack

The first child Pull Request establishes the canonical Eligibility concept and package boundaries before runtime implementation begins. Its purpose is to freeze what the package owns, what it does not own, the generic subject/context model, and deterministic eligibility evaluation semantics.

Implementation work must follow that canonical contract rather than inventing resource-specific restriction models inside Product, Category, Payment Method, Shipping, or other host domains.
