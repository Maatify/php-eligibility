<div align="center">

# Maatify Eligibility

![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)

[![PHP](https://img.shields.io/badge/php-%5E8.4-8892BF)](composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-success)](phpstan.neon)
[![License](https://img.shields.io/badge/license-proprietary-lightgrey)](LICENSE)

[![Changelog](https://img.shields.io/badge/Changelog-View-blue)](CHANGELOG.md)
[![Package Reference](https://img.shields.io/badge/Reference-Read-blue)](ELIGIBILITY_PACKAGE_REFERENCE.md)
[![Schema](https://img.shields.io/badge/Schema-Read-blue)](schema/README.md)
[![Security Policy](https://img.shields.io/badge/Security-Policy-blue)](SECURITY.md)
[![Contributing Guide](https://img.shields.io/badge/Contributing-Guide-blue)](CONTRIBUTING.md)

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

The canonical behavioral contract is **RC1 preparation for `1.0.0-rc.1`** in
[ELIGIBILITY_PACKAGE_REFERENCE.md](ELIGIBILITY_PACKAGE_REFERENCE.md). This
package is not yet published; see [Installation](#installation).

## Status

- **Release state:** unpublished RC1 preparation. No `1.0.0-rc.1` tag exists
  yet, and there is no Packagist (or any other public Composer) distribution of
  this package.
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
- Direct-PDO MySQL-compatible persistence with package-owned transaction and
  concurrency guarantees, and no Host foreign keys or joins.
- Typed package exceptions on the shared `maatify/exceptions` hierarchy, with
  unknown external throwables propagated unchanged.

## Requirements

| Requirement | Constraint |
|---|---|
| PHP | `^8.4` |
| PHP extensions | `ext-pdo`, `ext-pdo_mysql`, `ext-pcre` |
| Runtime package | `maatify/exceptions` (`^1.0`) |
| Database | MySQL-compatible database-server semantics through direct PDO (capability-based; no minimum product version is declared — see [Persistence and Schema](#persistence-and-schema)) |

The database contract requires transactional InnoDB-style package-owned table
behavior, binary-safe exact-value storage/comparison, the bounded indexed-key
capacity of this package's schema, and the uniqueness/index semantics the
schema uses. Being labelled MySQL-compatible is not enough; the server must
provide those capabilities. MariaDB compatibility is not claimed without
executed MariaDB verification.

## Installation

The package is **not yet published** to Packagist or any other Composer
registry. Until the `1.0.0-rc.1` tag is published and externally resolvable, an
external consumer cannot `composer require maatify/php-eligibility` from
Packagist.

### Development installation (current unpublished state)

While the RC1 target is still unpublished, consume the package from a local
checkout through a Composer path repository. The `1.0.0-rc.1` version is mapped
explicitly in the repository configuration, because the corresponding tag does
not exist yet and cannot be resolved from a remote repository. From your
consumer project:

```bash
git clone --branch phase/v1.0.0-rc.1 https://github.com/Maatify/php-eligibility.git .tools/php-eligibility
composer config repositories.php-eligibility '{"type": "path", "url": ".tools/php-eligibility", "options": {"symlink": true, "versions": {"maatify/php-eligibility": "1.0.0-rc.1"}}}'
composer require maatify/php-eligibility:1.0.0-rc.1
```

This uses the `phase/v1.0.0-rc.1` development branch; it is executable in the
current state and does not create or imply that an `1.0.0-rc.1` tag already
exists or has been distributed. For developing the library itself (running the
full local parity suite), clone into a working directory and follow
[Development and Testing](#development-and-testing).

### After the RC is published

Once the `1.0.0-rc.1` tag is actually published through its approved
distribution source, installation becomes:

```bash
composer require maatify/php-eligibility:1.0.0-rc.1
```

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
use Maatify\Eligibility\Rule\Repository\PdoRuleRepository;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Common\Value\Context;
use Maatify\Eligibility\Common\Value\ContextDimension;
use Maatify\Eligibility\Common\Value\Subject;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=app;charset=utf8mb4', 'app', 'secret', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec(file_get_contents(__DIR__ . '/vendor/maatify/php-eligibility/schema/eligibility_rules.sql'));

$repository = new PdoRuleRepository($pdo);
$management = new EligibilityManagementService($repository);
$evaluation = new EligibilityEvaluationService($repository);

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
- **Transaction ownership:** with no outer transaction the package owns its
  transaction; inside a Host-owned outer transaction the package participates
  without committing or rolling it back.
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

| Document | Purpose |
|---|---|
| [Package Reference](ELIGIBILITY_PACKAGE_REFERENCE.md) | Canonical RC1 contract: identity, Context, Rule, Decision, lifecycle, ordering, persistence, transaction, concurrency, error, batch, and 52-scenario coverage. |
| [Schema](schema/README.md) | Persistence contract, tables, bounds, applying/reapplying, and the local MySQL fixture. |
| [CHANGELOG](CHANGELOG.md) | B1–B6 change history under `[Unreleased]`. |
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
[LICENSE](LICENSE). No public distribution exists yet; see [Installation](#installation).

## Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
