<div align="center">

# Maatify Eligibility

![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)

**Package status:**<br>
[![Release state](https://img.shields.io/badge/Release-Pre--Stable%20v1.0.0--rc.1%20Release%20Candidate-orange)](#status)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-8892BF)](composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-success)](phpstan.neon)
[![License](https://img.shields.io/badge/License-proprietary-lightgrey)](LICENSE)

**Documentation:**<br>
[![Changelog](https://img.shields.io/badge/Changelog-View-blue)](CHANGELOG.md)
[![Package Reference](https://img.shields.io/badge/Package%20Reference-Read-blue)](ELIGIBILITY_PACKAGE_REFERENCE.md)
[![Usage Guide](https://img.shields.io/badge/Usage%20Guide-Read-blue)](docs/guides/USAGE_GUIDE.md)
[![Examples](https://img.shields.io/badge/Examples-Run-blue)](examples/)
[![Schema](https://img.shields.io/badge/Schema-Read-blue)](schema/README.md)
[![Security Policy](https://img.shields.io/badge/Security-Policy-blue)](SECURITY.md)
[![Contributing Guide](https://img.shields.io/badge/Contributing-Guide-blue)](CONTRIBUTING.md)

**Ecosystem and usage:**<br>
[![Latest Version](https://img.shields.io/packagist/v/maatify/php-eligibility.svg)](https://packagist.org/packages/maatify/php-eligibility)
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/php-eligibility?label=Monthly%20Downloads)](https://packagist.org/packages/maatify/php-eligibility)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/php-eligibility?label=Total%20Downloads)](https://packagist.org/packages/maatify/php-eligibility)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)
[![Install](https://img.shields.io/badge/Install-composer%20require%20maatify%2Fphp--eligibility-blue)](https://packagist.org/packages/maatify/php-eligibility)

Framework-neutral eligibility rules and typed decisions that answer one
reusable business question about an external Subject in a supplied Context.

</div>

---

## Package Summary

`maatify/php-eligibility` is a standalone, framework-neutral PHP library that
answers **"Is a given Subject eligible in the supplied Context?"** for domains
such as products, categories, payment methods, shipping, and promotions, so
those domains do not each re-invent a customer/country restriction subsystem.

```text
Host Input -> Public API -> Domain Service -> Integration Boundary -> Observable Result
```

The package knows external identities only. It does not know the database
model, lifecycle, or implementation of the domains that own those identities.

The canonical behavioral contract for the **Pre-Stable `v1.0.0-rc.1` Release
Candidate** is [ELIGIBILITY_PACKAGE_REFERENCE.md](ELIGIBILITY_PACKAGE_REFERENCE.md).
The package identity is `maatify/php-eligibility` and its distribution is
available through [Packagist](https://packagist.org/packages/maatify/php-eligibility).

## Status

- **Release state:** Pre-Stable `v1.0.0-rc.1` Release Candidate.
- **Package identity:** `maatify/php-eligibility`.
- **Distribution:** [Packagist](https://packagist.org/packages/maatify/php-eligibility).
- **Quality:** see [Quality Status](#quality-status).

## Key Features

- Immutable, typed `Subject`, `Context`, `Rule`, and `EligibilityDecision`
  values with canonical UTF-8 string validation and bytewise canonical order.
- Deterministic rule evaluation: ALLOW/DENY effects, active/inactive lifecycle,
  DENY precedence, ALLOW-list and DENY-only semantics, AND across dimensions,
  and no inferred cross-dimension or cross-Subject behavior.
- Fully typed decision output: `EligibilityDecision`, per-dimension
  `DimensionOutcome`, complete `RuleReference` traces, and stable machine
  reason codes.
- Single and ordered batch evaluation with duplicate-Subject rejection, empty
  batch support, and a bounded bulk load path (no canonical N+1 query pattern).
- Management lifecycle: create, inspect/list (including inactive Rules),
  active-dimension introspection, effect and lifecycle mutations, atomic
  `replaceDimensionRules()`, and idempotent Subject cleanup.
- Direct-PDO MySQL-compatible persistence with separate command, management
  query, and evaluation-read adapters, plus shared Persistence transaction
  ownership/savepoint mechanics and Eligibility-owned coordination locking;
  no Host foreign keys or joins.
- Typed package exceptions on the shared `maatify/exceptions` hierarchy, with
  unknown external throwables propagated unchanged.

## Requirements

| Requirement | Constraint |
|---|---|
| PHP | `^8.4` |
| PHP extensions | `ext-pdo`, `ext-pdo_mysql`, `ext-pcre` |
| Runtime packages | `maatify/exceptions` (`^1.0`), `maatify/persistence` (`^1.4`) |
| Database | MySQL-compatible database-server semantics through direct PDO (capability-based; no minimum product version is declared — see [Persistence and Schema](#persistence-and-schema)) |

`maatify/persistence ^1.4` is an explicit runtime dependency. `v1.4.0` is the
minimum stable line required for the released
`SavepointTransactionRunnerInterface` and `PdoSavepointTransactionRunner`.
Eligibility delegates transaction ownership, operation-local savepoints,
cleanup, and original-`Throwable` preservation to that shared API.

The database contract requires transactional InnoDB-style package-owned table
behavior, binary-safe exact-value storage/comparison, the bounded indexed-key
capacity of this package's schema, and the uniqueness/index semantics the
schema uses. Being labelled MySQL-compatible is not enough; the server must
provide those capabilities. MariaDB compatibility is not claimed without
executed MariaDB verification.

## Installation

Install the Pre-Stable `v1.0.0-rc.1` Release Candidate from
[Packagist](https://packagist.org/packages/maatify/php-eligibility):

```bash
composer require maatify/php-eligibility:1.0.0-rc.1
```

### Development access

For development access only, use a local checkout of the current `main` branch
through a Composer path repository. This development path is separate from the
`v1.0.0-rc.1` Release Candidate. From your consumer project:

```bash
git clone https://github.com/Maatify/php-eligibility.git .tools/php-eligibility
composer config repositories.php-eligibility '{"type":"path","url":".tools/php-eligibility","options":{"symlink":true}}'
composer require maatify/php-eligibility:dev-main
```

The `dev-main` constraint is development-only access to the current `main`
checkout. For developing the library itself (running the full local parity suite),
clone into a working directory and follow [Development and Testing](#development-and-testing).

Installation (both paths) requires `ext-pdo`, `ext-pdo_mysql`, and
`ext-pcre`. The package performs no automatic setup: the schema asset is an
install asset ([`schema/eligibility_rules.sql`](schema/eligibility_rules.sql)),
not a migration run at install time.

## Quick Usage

### Evaluation

```php
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Evaluation\Decision\DecisionReasonEnum;
use Maatify\Eligibility\Rule\Repository\PdoActiveRuleReader;
use Maatify\Eligibility\Rule\Repository\PdoRuleCommandRepository;
use Maatify\Eligibility\Rule\Repository\PdoRuleManagementQuery;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;
use Maatify\Eligibility\Evaluation\Value\Context;
use Maatify\Eligibility\Evaluation\Value\ContextDimension;
use Maatify\Eligibility\Common\Value\Subject;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=app;charset=utf8mb4', 'app', 'secret', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec(file_get_contents(__DIR__ . '/vendor/maatify/php-eligibility/schema/eligibility_rules.sql'));

$commandRepository = new PdoRuleCommandRepository($pdo);
$managementQuery = new PdoRuleManagementQuery($pdo);
$activeRuleReader = new PdoActiveRuleReader($pdo);
$transactionRunner = new PdoSavepointTransactionRunner($pdo);
$management = new EligibilityManagementService(
    $commandRepository,
    $managementQuery,
    $commandRepository,
    $transactionRunner,
);
$evaluation = new EligibilityEvaluationService($activeRuleReader);

$subject = new Subject('product', '150');
$management->createRule(new \Maatify\Eligibility\Management\Command\CreateRuleCommand(
    $subject,
    'country',
    'EG',
    RuleEffectEnum::ALLOW,
));
$management->createRule(new \Maatify\Eligibility\Management\Command\CreateRuleCommand(
    $subject,
    'customer_type',
    'blocked',
    RuleEffectEnum::DENY,
));

$decision = $evaluation->decide($subject, new Context(
    ContextDimension::fromStrings('country', 'EG'),
    ContextDimension::fromStrings('customer_type', 'retail'),
));

if ($decision->eligible) {
    // $decision->reasonCode is DecisionReasonEnum::ELIGIBLE
}
```

### Ordered batch evaluation

```php
use Maatify\Eligibility\Common\Value\SubjectCollection;

$decisions = $evaluation->decideMany(
    new SubjectCollection(
        new Subject('product', '150'),
        new Subject('product', '151'),
    ),
    new Context(ContextDimension::fromStrings('country', 'EG')),
);
// Results preserve the supplied Subject order; duplicate Subjects are rejected.
```

### Management lifecycle

```php
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Command\DesiredRule;
use Maatify\Eligibility\Management\Command\DesiredRuleCollection;

// Atomic replacement of the complete active set for one Subject dimension.
$management->replaceDimensionRules(new ReplaceDimensionRulesCommand(
    $subject,
    'country',
    new DesiredRuleCollection(
        new DesiredRule('EG', RuleEffectEnum::ALLOW),
        new DesiredRule('KW', RuleEffectEnum::ALLOW),
    ),
));

// Bounded management reads include inactive Rules.
$rules = $management->inspectRules(new RuleCriteria($subject, 'country'));
```

## Public Runtime API

Public surface (see the
[Package Reference](ELIGIBILITY_PACKAGE_REFERENCE.md) for the full contract):

- **Values:** `Subject`, `SubjectCollection`, `Context`, `ContextDimension`,
  `ContextValue`, `ContextValueCollection`, `Rule`, `RuleIdentity`,
  `RuleCollection`, `RuleEffectEnum`, `RuleLifecycleEnum`,
  `CanonicalString`.
- **Decisions:** `EligibilityDecision`, `DimensionOutcome`,
  `DimensionOutcomeCollection`, `RuleReference`, `RuleReferenceCollection`,
  `DecisionReasonEnum` (`UNRESTRICTED`, `ELIGIBLE`, `DENIED`),
  `DimensionReasonEnum`.
- **Evaluation service:** `EligibilityEvaluationService` /
  `EligibilityEvaluationServiceInterface` — `decide()` and `decideMany()`.
- **Management service:** `EligibilityManagementService` /
  `EligibilityManagementServiceInterface` — `createRule()`, `inspectRule()`,
  `inspectRules()`, `inspectActiveDimensionKeys()`, `updateRuleEffect()`,
  `deactivateRule()`, `reactivateRule()`, `replaceDimensionRules()`,
  `cleanupSubject()`.
- **Persistence contracts:** `RuleCommandRepositoryInterface` owns command
  mutations, `RuleManagementQueryInterface` owns bounded management reads, and
  `ActiveRuleReaderInterface` owns active bulk reads for evaluation. The
  internal `RuleMutationSupportInterface` owns only the coordination lock,
  complete Subject + dimension mutation read, and coordination cleanup needed
  by atomic replacement/cleanup. Generic transaction/savepoint mechanics are
  owned by `maatify/persistence`; `EligibilityManagementService` receives
  its `SavepointTransactionRunnerInterface` separately.
- **PDO adapters:** `PdoRuleCommandRepository`, `PdoRuleManagementQuery`,
  `PdoActiveRuleReader`, and `PdoSavepointTransactionRunner` are constructed
  from the same PDO connection. The command adapter implements only the
  Eligibility command and mutation-support contracts; callers wire each
  responsibility explicitly to the corresponding service dependency.
- **Commands / queries / results:** `CreateRuleCommand`,
  `UpdateRuleEffectCommand`, `DeactivateRuleCommand`, `ReactivateRuleCommand`,
  `DesiredRule`, `DesiredRuleCollection`, `ReplaceDimensionRulesCommand`,
  `CleanupSubjectCommand`, `RuleCriteria`, `ActiveDimensionKeysQuery`,
  `ActiveDimensionKeyCollection`, `SubjectDecisionResult`,
  `SubjectDecisionCollection`.
- **Exceptions:** `EligibilityExceptionInterface`, `InvalidEligibilityInputException`,
  `RuleNotFoundException`, `RuleIdentityConflictException`,
  `RuleConcurrencyConflictException` (backed by the shared
  `maatify/exceptions` hierarchy).

## Critical Runtime Behavior

- **No active Rules for any dimension** -> `UNRESTRICTED`, eligible, with no
  dimension outcomes. This does not mean the owning domain has enabled the
  Subject.
- **Per-dimension evaluation**, grouped by dimension key, combined with **AND**
  across dimensions. No inference across dimensions or Subjects.
- **DENY always wins within a dimension** over every matching ALLOW.
- **ALLOW Rules create an allow-list**: at least one supplied Context value must
  match an ALLOW, otherwise the dimension fails.
- **DENY-only Rules behave as a deny-list**: the dimension passes unless a DENY
  value matches.
- **Missing Context values are explicit**: an ALLOW dimension absent from the
  Context fails; a DENY-only dimension absent from the Context passes
  (fail-open). The trace distinguishes both from supplied non-matching values.
- **Lifecycle**: create always stores active Rules; inactive Rules stay
  unique and management-visible but are invisible to evaluation. Deactivate /
  reactivate / update-effect are idempotent and typed not-found on a missing
  identity; create against an existing natural identity is a typed conflict.
- **Replacement**: `replaceDimensionRules()` defines the complete desired
  active set for one Subject + dimension, reuses/reactivates existing
  identities, creates missing identities, deactivates omitted active Rules
  (never hard-deletes), preserves omitted inactive Rules, and leaves other
  dimensions/Subjects untouched.
- **Batch**: duplicate Subjects rejected, empty batch valid, results in
  accepted input order, single and batch evaluation equivalent, bounded bulk
  loading with no canonical N+1 path.

## Architecture Guarantees

- **Canonical identity:** `subject_type + subject_id + dimension_key +
  dimension_value` is the one natural identity, independent of effect and
  lifecycle. Canonical strings reject null/non-string/coerced input, malformed
  UTF-8, empty/whitespace-only values, and leading/trailing whitespace without
  trimming, case conversion, transliteration, or normalization.
- **Canonical ordering:** string comparison and ordering use ascending
  bytewise ordering over the validated UTF-8 byte sequence, independent of
  database row order or collation.
- **Framework neutrality:** direct PDO persistence only; no ORM, no external
  query builder, no framework runtime requirement, and no Host foreign keys or
  joins. The Host validates external identities; the package trusts them.
- **Transaction ownership:** `PdoSavepointTransactionRunner` from
  `maatify/persistence` owns the transaction lifecycle when no outer
  transaction is active. Inside a Host-owned outer transaction it creates an
  operation-local savepoint, releases it on success, and rolls back to it on
  failure without committing or fully rolling back the Host transaction.
  Eligibility still owns mutation coordination, and the runner plus all
  Eligibility PDO adapters MUST use the same PDO connection.
- **Concurrency:** parallel creates cannot duplicate a natural identity;
  parallel replacements cannot produce partial or mixed dimension state; reads
  observe coherent committed states.

## Exception and Error Propagation

- Package typified failures use the single marker
  `Maatify\Eligibility\Exception\EligibilityExceptionInterface` and the shared
  `maatify/exceptions` hierarchy.
- Duplicate natural identities classify to `RuleIdentityConflictException`;
  missing identities to `RuleNotFoundException`; unresolved uniqueness or
  concurrency conditions to `RuleConcurrencyConflictException`.
- Unknown `PDOException`/`Throwable` instances propagate unchanged; a known
  semantic condition is converted to a package exception only on documented
  driver-specific evidence, preserving the original as `previous`.

## Security and Trust Boundaries

- No repository secrets in baseline CI; integration credentials are temporary
  and local to the runner/service container only.
- CI actions are pinned to immutable full commit SHAs. The workflow-lint tool
  (`actionlint` pinned to `v1.7.12`) is downloaded from its release and
  verified against the published SHA-256 checksum file before execution; the
  Composer security audit runs as part of the Composer gate.
- The package never reads Host application databases, schemas, or credentials,
  and never requires Host-side mutations or setup scripts at install time.

## Documentation

The [Usage Guide](docs/guides/USAGE_GUIDE.md) contains the capability decision
map and helps a consumer choose the smallest suitable public API surface before
installing or trying the package.

| Document | Purpose |
|---|---|
| [Package Reference](ELIGIBILITY_PACKAGE_REFERENCE.md) | Canonical RC1 contract: identity, Context, Rule, Decision, lifecycle, ordering, persistence, transaction, concurrency, error, batch, and 52-scenario coverage. |
| [Usage Guide](docs/guides/USAGE_GUIDE.md) | Consumer-facing API guide, capability decision map, input/output types, transaction notes, and links to runnable examples. |
| [Runnable Examples](examples/) | Standalone public-API examples for evaluation, batch evaluation, management, replacement, PDO wiring, and typed exception handling. |
| [Schema](schema/README.md) | Persistence contract, tables, bounds, applying/reapplying, and the local MySQL fixture. |
| [CHANGELOG](CHANGELOG.md) | RC1 change history under `[1.0.0-rc.1]`. |
| [Security Policy](SECURITY.md) | Support state, vulnerability reporting, and scope. |
| [Contributing Guide](CONTRIBUTING.md) | Contribution expectations, local verification, and PR requirements. |
| [Code of Conduct](CODE_OF_CONDUCT.md) | Community rules and reporting. |

## Persistence and Schema

The package owns two tables with the `maa_eligibility_` prefix:

- `maa_eligibility_rules` — Rules with the exact-string-safe `VARBINARY`
  columns and a 574-byte natural-identity unique key.
- `maa_eligibility_subject_locks` — package-owned coordination rows used by
  replacement and cleanup to serialize per-Subject mutations.

See [schema/README.md](schema/README.md) for bounds, storage guarantees,
transaction/savepoint behavior, and the local `mysql:8.4.11` reproducibility
fixture. The fixture version is **not** a minimum supported product version.

The runtime composition keeps command, management-query, evaluation-read, and
internal mutation-support responsibilities explicit. A Host constructs the
three PDO adapters and `PdoSavepointTransactionRunner` from the same PDO
connection, passes the command adapter, management query, mutation-support
capability, and shared transaction runner separately to
`EligibilityManagementService`, and passes the active-rule reader to
`EligibilityEvaluationService`. Generic transaction/savepoint mechanics belong
to `maatify/persistence`; per-Subject coordination locking remains
Eligibility-owned.

## Quality Status

- PHPStan **level max**, zero errors, no baseline and no suppressions.
- Unit, Golden (52 canonical acceptance scenarios with an executable evidence
  map), real-MySQL Integration, and concurrency/invariant suites.
- Real-service Integration is run on both supported PHP minors with a repeated
  run for cleanup/repeatability evidence; there is no SQLite or mock substitute.
- Consumer Verification Harness: two clean external-consumer runs using
  production autoload, public contracts, and the real persistence boundary.
- Fail-closed CI with stable aggregate gates: `ci-quality`, `ci-tests`,
  `ci-integration`.

## Development and Testing

Prerequisites: PHP `^8.4`, Composer, Docker (for the real MySQL fixture).

```bash
composer update --no-interaction --prefer-dist --no-progress
tools/check-local.sh                # Composer, platform, syntax, PHPStan, whitespace, audit, workflow lint, Unit, Golden
docker compose -f docker-compose.integration.yml up -d --wait
tools/check-local.sh --with-integration   # adds real-MySQL Integration + Harness
docker compose -f docker-compose.integration.yml down
```

Generated files such as `vendor/`, `composer.lock` (this library does not
track it), and PHPUnit/PHPStan caches are not committed.

## License

This package is released under a **proprietary Maatify license**. See
[LICENSE](LICENSE). The Pre-Stable `v1.0.0-rc.1` Release Candidate is
distributed through [Packagist](https://packagist.org/packages/maatify/php-eligibility).

## Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
