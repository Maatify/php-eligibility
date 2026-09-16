# Contributing Guide

Thank you for contributing to `maatify/php-eligibility`. This package is part
of RC1 preparation for the `v1.0.0-rc.1` prerelease of the intended Stable
`1.0` line; the target version and the package itself are still unpublished.

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
  (including the current unpublished RC1 preparation state).
- Vulnerability reports: use the private route documented in
  [SECURITY.md](SECURITY.md), never a public issue.

## Local verification commands

Prerequisites: PHP `^8.4`, Composer, Docker for the real MySQL fixture.

```bash
composer update --no-interaction --prefer-dist --no-progress
composer check:local                 # non-service gates + Unit + Golden in one pass
docker compose -f docker-compose.integration.yml up -d --wait
composer check:local -- --with-integration   # adds real-MySQL Integration + Harness
docker compose -f docker-compose.integration.yml down
```

`composer check:local` mirrors the required CI gates: Composer
strict validation, optimized strict PSR-4 autoload, platform requirements,
PHP syntax lint, PHPStan level max (no baseline, no suppressions), the
whitespace gate, the Composer security audit, the workflow lint, the Unit
suite, and the Golden suite. With `--with-integration` it adds the MySQL
readiness check, the real-MySQL Integration suite run twice (repeatability),
and the two-run Consumer Verification Harness.

## Test and Integration requirements

- Unit and Golden suites must pass without a database.
- The real-MySQL Integration suite (no SQLite or mock substitutes) and the
  Consumer Verification Harness require the Docker fixture above and must pass
  before a PR can be green.
- New behavior that changes evaluation or lifecycle semantics should extend the
  52-scenario Golden evidence map rather than introduce isolated examples that
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
