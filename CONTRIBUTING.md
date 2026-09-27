# Contributing Guide

Thank you for contributing to `maatify/php-eligibility`. This repository
currently targets the Pre-Stable `v1.0.0-rc.2` source/release line in the
intended Stable `1.0` line. Its package identity is `maatify/php-eligibility`;
Packagist is the intended distribution channel.

Repository source alone does not establish Published state; exact external
availability is determined by the exact `v1.0.0-rc.2` version tag through the
approved Composer distribution source, not by this guide. No Published Stable
release exists.

## Package identity and boundaries

- Package: `maatify/php-eligibility`, repository
  `https://github.com/Maatify/php-eligibility`.
- This is a standalone, framework-neutral eligibility library for PHP `^8.4`.
  Runtime contracts are the canonical source of truth:
  [`ELIGIBILITY_PACKAGE_REFERENCE.md`](ELIGIBILITY_PACKAGE_REFERENCE.md).
- The package owns `src/` (production API) and the schema asset in `schema/`.
  It never queries, mutates, or introspects the Host application's data model.
- Changes that alter public API shape, runtime behavior, persistence, error
  semantics, or the schema are runtime-contract changes and must be reconciled
  with the Package Reference before they can be accepted.

## Ways to contribute

- Bug reports and design/architecture proposals: open a GitHub issue first.
- Code changes: open a pull request; see expectations below.
- Documentation/presentation improvements: PRs are welcome, but they must not
  change runtime contracts and must stay accurate to the actual state
  (including the current Pre-Stable `v1.0.0-rc.2` source/release-line state).
- Vulnerability reports: use the private route documented in
  [SECURITY.md](SECURITY.md), never a public issue.

## Local verification commands

Prerequisites: PHP `^8.4`, Composer, Docker for the real MySQL fixture.

The repository development and verification workflow requires Composer `2.10.x`.
This is a development-tooling policy only; it is not a package runtime or
consumer install requirement.

```bash
composer --version | grep -Eq '^Composer version 2\.10\.'
```

Resolve the latest-compatible dependencies before the local aggregate gate:

```bash
composer update --no-interaction --prefer-dist --no-progress
composer check:local                 # local gates + Unit + Golden; no dependency matrix
composer check:local -- --with-integration   # adds fresh Integration + Harness + examples lifecycles
composer test:integration                      # focused Integration lifecycle
composer test:harness                          # focused Consumer Harness lifecycle
composer test:examples                          # focused example smoke lifecycle
```

The latest-compatible local sequence matching the authoritative `ci-quality`
Composer contract is:

```bash
composer --version | grep -Eq '^Composer version 2\.10\.'
composer validate --strict
composer check:composer-policy
composer update --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
composer dump-autoload --optimize --strict-psr
composer audit --no-interaction --abandoned=fail
```

The lowest-supported local sequence matching `ci-tests / lowest-deps` is:

```bash
composer update --prefer-lowest --prefer-stable --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
composer test:unit
composer test:golden
composer analyse
```

Run the lowest-supported sequence separately because it rewrites `vendor/`.
`composer check:local` requires Composer `2.10.x` and mirrors the local static,
policy, Unit, and Golden gates: strict Composer validation, optimized strict
PSR-4 autoload, platform requirements, PHP syntax lint, PHPStan level max (no
baseline or suppressions), PER-CS 3.1, whitespace, fail-closed Composer audit,
workflow lint, Unit, and Golden. It does not run either dependency-resolution
matrix. With `--with-integration` it additionally runs the real-MySQL
Integration suite twice, the two-run Consumer Verification Harness, and the
standalone-example smoke gate. Each invocation provisions and tears down its
own disposable Compose state.

## Test and Integration requirements

- Unit and Golden suites must pass without a database.
- The real-MySQL Integration suite (no SQLite or mock substitutes) and the
  Consumer Verification Harness require the Docker fixture above and must pass
  before a PR can be green.
- New behavior that changes evaluation or lifecycle semantics should extend the
  canonical Golden acceptance evidence map rather than introduce isolated examples that
  contradict it.
- Concurrency changes need the real-MySQL concurrency coverage to stay intact
  and deterministic.

## Pull request expectations

- Work on a descriptive branch and open pull requests with a clear title,
  scope, and verification summary.
- Keep PRs focused.
- Merge, tagging, release, and publication require explicit owner approval and
  are governed by the applicable release controls.
- No `composer.lock` is tracked for this library (it is a library, not an
  application); do not add one. Generated, cache, and fixture artifacts such as
  `vendor/` and PHPUnit/PHPStan caches must not be committed.
- Before review, run the local parity commands above and make sure the three
  required CI workflows (`ci-quality`, `ci-tests`, `ci-integration`) are green
  on your head, including their aggregate gates.

## Architecture discussion requirements

- Public API and runtime behavior discussions must reference the Package
  Reference and explain the impact on identity, ordering, lifecycle,
  transaction, concurrency, or error contracts.
- Design changes that reshape the package boundaries (what the package does and
  does not do for the Host) require a documented proposal and the tests that
  prove the new invariants.
- Do not weaken production code to make it mockable, and do not suppress
  PHPStan findings.

## Security reporting route

Report suspected vulnerabilities privately via the
[GitHub Security Advisories](https://github.com/Maatify/php-eligibility/security/advisories/new)
channel described in [SECURITY.md](SECURITY.md). Do not file them as public
issues.
