# RC1 CI / Quality Gate Map

This document is the local/CI parity map and CI architecture record for
`maatify/php-eligibility` (B6). It maps every mandatory quality gate to its
maintained local invocation and its CI invocation, and records the fail-closed
CI architecture that produces the stable aggregate gates intended for future
branch-protection required checks.

Sources of truth:

- CI execution and enforcement: `std-ci-workflow` (CI_WORKFLOW_STANDARD.md).
- Testing and Harness semantics: `std-testing` (TESTING_STANDARD.md).
- Composer scripts/config/audit: `std-composer-package` (COMPOSER_PACKAGE_STANDARD.md).
- README/CHANGELOG/release presentation: `std-library-presentation` (LIBRARY_PRESENTATION_STANDARD.md).
- The canonical RC1 contract: `ELIGIBILITY_PACKAGE_REFERENCE.md`.

The canonical execution roadmap is `docs/RC1_DELIVERY_PLAN.md` (B6).

## 1. CI architecture

The repository uses the **always-run CI model** of the CI Standard: no
top-level path filtering is applied to required workflows, so every protected
pull request always starts the complete verification set and every workflow
always reports a conclusion. No relevance detection is needed because no heavy
check is hidden behind a top-level filter.

Three workflows provide clear ownership and stable aggregate gates:

| Workflow | File | Owned gates | Terminal stable gate job |
|---|---|---|---|
| `ci-quality` | `.github/workflows/ci-quality.yml` | Composer contract, platform, syntax, PHPStan max, whitespace, audit, workflow lint | `Quality aggregate gate` |
| `ci-tests` | `.github/workflows/ci-tests.yml` | Unit + Golden on the supported PHP matrix, lowest-dependency resolution | `Tests aggregate gate` |
| `ci-integration` | `.github/workflows/ci-integration.yml` | Real MySQL Integration, repeated Integration run, Consumer Verification Harness | `Integration aggregate gate` |

All three trigger on `pull_request` and `push` to `main` and
`phase/v1.0.0-rc.1`, plus `workflow_dispatch`. Each terminal gate uses
`if: always()`, inspects `needs.*.result` through the maintained
`tools/assert-gate.sh`, and fails on any `failure`, `cancelled`, or unexpected
`skipped` upstream result. Branch protection would target exactly those three
stable gate jobs, never matrix child jobs.

Reliability and security policy applied in every workflow:

- `permissions: contents: read` — least privilege; no write permission.
- Every externally sourced action pinned to an immutable full commit SHA
  (`actions/checkout@08c6903cd8c0fde910a37f88322edcfb5dd907a8` # v5.0.0,
  `shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240` # v2),
  with the human readable tag recorded as a comment.
- Explicit `timeout-minutes` on every job.
- Explicit `concurrency` group per workflow/ref with `cancel-in-progress`
  enabled for pull-request and non-default-branch runs only; default-branch
  (`main`) runs are not cancelled.
- No `continue-on-error`, no `allow-failure`, no `|| true`, no silent skips.
- Tool provisioning is deterministic: the workflow-lint gate always runs the
  pinned `actionlint` release verified against its published SHA-256 checksum
  and never relies on a binary found on the runner PATH.
- No repository secrets are used by baseline CI; all credentials are
  test-only and local to the runner.

## 2. Local / CI gate map

Prerequisites for local gates: PHP 8.4+, Composer, and once per checkout:

```bash
composer update --no-interaction --prefer-dist --no-progress
```

The maintained local aggregate command `tools/check-local.sh` runs every
non-service gate below in one pass and reflects the same verification
contracts as their CI jobs. `composer check:local` invokes it.
Integration/Harness gates require the MySQL fixture started first; use
`tools/check-local.sh --with-integration` after the fixture is healthy.

| Gate | Local invocation (maintained) | CI invocation | Verification contract |
|---|---|---|---|
| Composer strict validation | `composer validate --strict` | `composer validate --strict` (ci-quality) | Valid JSON, schema, license, identity, scripts, no committed `version`/lock. |
| Dependency resolution (latest) | `composer update --no-interaction --prefer-dist --no-progress` | Same command (ci-quality, ci-tests, ci-integration) | Current upper-bound resolution succeeds without a committed lock. |
| Dependency resolution (lowest) | `composer update --prefer-lowest --prefer-stable --no-interaction --prefer-dist --no-progress` | Same command, then static + test checks (ci-tests `lowest-deps`) | Declared lower bounds resolve and pass relevant checks on the minimum PHP version. |
| Platform requirements | `composer check-platform-reqs` | `composer check-platform-reqs` (ci-quality) | Declared PHP/extensions are satisfiable; no `--ignore-platform-*`. |
| Optimized strict autoload | `composer dump-autoload --optimize --strict-psr` | Same command (ci-quality) | PSR-4 production/development mappings are strict and optimized. |
| PHP syntax | `php tools/php-lint.php` (`composer lint:php`) | `php tools/php-lint.php` (ci-quality, PHP 8.4) | Every package-owned `.php` file under `src/`, `tests/`, `consumer-harness/`, and `tools/` passes `php -l`; failures abort. |
| PHPStan max | `composer analyse` | `composer analyse` (ci-quality, PHP 8.4; ci-tests lowest-deps) | Level `max`, zero errors, no baseline, no `ignoreErrors`, no suppressions. |
| Whitespace | `tools/check-whitespace.sh` (working tree + index vs HEAD) | `tools/check-whitespace.sh <base> <head>` on the exact PR/push range (ci-quality) | `git diff --check` flags trailing whitespace/malformed whitespace; fails the job. |
| Code style | No formatter adopted | Not applicable | No `.php-cs-fixer.php`/equivalent formatter configuration exists in this repository; code-style integrity is enforced by the whitespace gate and PHPStan max. Adding a formatter is an explicit future decision. |
| Unit suite | `composer test:unit` | `composer test:unit` (ci-tests matrix, ci-integration) | PHPUnit `unit` suite passes. |
| Golden suite | `composer test:golden` | `composer test:golden` (ci-tests matrix, ci-integration) | PHPUnit `golden` suite (52-scenario evidence) passes. |
| Full maintained suite | `composer test` (requires MySQL for the Integration suite) | Run as the explicit three suites in ci-integration | Complete maintained test suite runs with the real service at the integration boundary. |
| Real-service Integration | `composer test:integration` (requires MySQL fixture) | `composer test:integration` (ci-integration, PHP 8.4 + 8.5, service `mysql:8.4.11`) | Real MySQL-compatible boundary; schema apply/reapply, exact strings, lifecycle, replacement, transaction/cleanup; no SQLite/mock substitute. |
| Integration repeatability / residue | Fixture up → `composer test:integration` twice (also `tools/check-local.sh --with-integration`) | Integration suite run twice in each ci-integration matrix cell | Repeated Integration run proves cleanup and repeatability per the CI Standard. |
| Consumer Verification Harness | `composer test:harness` (requires MySQL fixture) | `composer test:harness` (ci-integration) | Two clean external-consumer runs; production PSR-4 autoload; real persistence; whole-table residue checks; no hidden Host dependencies. |
| Composer security audit | `composer audit --no-interaction --abandoned=fail` | Same command (ci-quality) | No unaddressed security advisories; abandoned packages fail the gate. |
| Workflow lint | `tools/lint-workflows.sh` | `tools/lint-workflows.sh` (ci-quality) | `actionlint` pinned `v1.7.12` over every `.github/workflows/*.yml`; the default always downloads the pinned release binary and verifies it against the published SHA-256 checksum file before execution, with no implicit reliance on a PATH binary. `ACTIONLINT_BIN`/`ACTIONLINT_VERSION` are explicit local-only overrides that fail closed in required CI. |

### Integration and Harness service prerequisites

Local real-service gates need the test-only MySQL fixture from
`docker-compose.integration.yml` (image `mysql:8.4.11`, loopback-only
`127.0.0.1:13306`, credentials `eligibility_test`/`eligibility_test`, database
`maatify_eligibility_test`):

```bash
docker compose -f docker-compose.integration.yml up -d --wait
tools/check-local.sh --with-integration
docker compose -f docker-compose.integration.yml down
```

A real PDO readiness check in `tools/mysql-ready.php` (the single maintained
readiness implementation) runs before the local suites, exactly mirroring the
`Wait for MySQL readiness` step of ci-integration. CI uses the same fixture
image and credentials as a pinned service container (no secrets), maps host
`13306`, and the readiness exit code decides the result of the step.

## 3. PHP compatibility matrix policy

The Composer constraint is `php: ^8.4`. Today's policy for every **currently
released** PHP minor under that constraint:

| PHP minor | Status | CI representation |
|---|---|---|
| 8.4 | Minimum supported | Static analysis, Unit, Golden, Integration, lowest dependencies |
| 8.5 | Latest supported | Unit, Golden, Integration |

Rule: the minimum and latest supported minors are always tested, and every
currently released minor inside `^8.4` is represented directly. PHP 8.6 is
currently unreleased (GA targeted 2026-11-19). When it is released, add `8.6`
to the ci-tests and ci-integration matrices before it is claimed as supported;
no architectural exception is currently used. Versions outside the declared
constraint are never added merely to widen the matrix.

## 4. Dependency ends policy

- **Latest-compatible**: `composer update` (upper bounds) is run and the Unit,
  Golden, and Integration suites are executed against the resolved set.
- **Lowest-supported**: `composer update --prefer-lowest --prefer-stable` runs
  on PHP 8.4 (minimum supported) and is followed by Unit, Golden, and
  PHPStan max. A failure to support the declared lower bounds must be fixed by
  correcting the constraint, never by hiding the job. PHPStan's declared
  minimum resolution is therefore `^2.2` (a dev-only analysis version; 2.1's
  inference of the concurrency worker/process fixtures is too imprecise to
  analyze this code correctly).

## 5. PHPStan configuration

`phpstan.neon` runs at `level: max` over `src/`, `tests/`, and
`consumer-harness/`. There is no baseline, no `ignoreErrors`, and no inline
suppression. Production code is never weakened to make tests mockable.

## 6. Action and tool pinning

| Tool | Pin | Integrity |
|---|---|---|
| `actions/checkout` | `08c6903cd8c0fde910a37f88322edcfb5dd907a8` (v5.0.0) | Full SHA |
| `shivammathur/setup-php` | `f3e473d116dcccaddc5834248c87452386958240` (v2) | Full SHA |
| `rhysd/actionlint` | `v1.7.12` release binary | SHA-256 verified against the published `actionlint_1.7.12_checksums.txt` before execution |
| `mysql` service | `mysql:8.4.11` | Explicit pinned image; reproducibility fixture only, not a compatibility claim |
| Composer | Runner-provided current Composer 2.x | Latest-compatible made explicit per run |

No mutable references (`main`, `latest`, `v1`, ...) are used for external
actions or the actionlint download, and a binary already installed on PATH is
never consulted by the workflow-lint default. `tools/lint-workflows.sh` uses
the immutable `v1.7.12` release by default; `ACTIONLINT_VERSION` and
`ACTIONLINT_BIN` are explicit local-only overrides that fail closed in required
CI, so a maintainer version bump must be deliberate and recorded in the
CHANGELOG.

## 7. Fail-closed behavior

- A required runner/configuration/dependency/setup failure fails CI.
- Missing or failed service readiness fails the Integration job.
- PHPStan errors, lint failures, audit failures, and abandoned packages fail
  their gates.
- Unexpected upstream `failure`, `cancelled`, or `skipped` results fail each
  stable aggregate gate.
- There is no `continue-on-error`, `|| true`, or conditional skip that can
  convert a required gate into a green run.
