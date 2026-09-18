# Eligibility Usage Guide

This guide is the consumer-facing entry point for `maatify/php-eligibility`.
It explains what the package provides, which public API surface to choose, what
to send, what to receive, and which responsibilities remain with the Host.

The root [Package Reference](../../ELIGIBILITY_PACKAGE_REFERENCE.md) remains
the canonical normative RC1 contract. This guide does not replace it or repeat
its full acceptance matrix.

## Overview

Eligibility answers one reusable question:

```text
Subject + Context + active Rules -> EligibilityDecision
```

The package is framework-neutral and uses typed PHP values and services. A Host
defines the meaning of Subject types and Context dimensions, wires the package's
PDO adapters, manages the package-owned schema, and maps the typed result to its
own API or UI behavior.

This guide describes the **Pre-Stable `v1.0.0-rc.1` Release Candidate**:

- package: `maatify/php-eligibility`
- release: `v1.0.0-rc.1`
- stability: Pre-Stable Release Candidate
- distribution: [Packagist](https://packagist.org/packages/maatify/php-eligibility)

For local development, install dependencies with Composer before running the
examples:

```bash
composer update --no-interaction --prefer-dist --no-progress
php examples/exception-handling.php
```

The database-backed examples use these environment variables and never contain
credentials:

```text
ELIGIBILITY_DB_HOST
ELIGIBILITY_DB_PORT
ELIGIBILITY_DB_NAME
ELIGIBILITY_DB_USER
ELIGIBILITY_DB_PASSWORD
```

The runnable database examples are fail-closed and accept only a dedicated
local test database: `ELIGIBILITY_DB_HOST` must be exactly `127.0.0.1` or
`localhost`, and `ELIGIBILITY_DB_NAME` must match a clear `*_test` database
name. They reject any other Host or database name before creating PDO or
executing the schema. If a variable is absent or a guard fails, the example
prints `SKIP` and exits without attempting a connection. The examples apply
the package's `schema/eligibility_rules.sql` asset and use the same PDO
connection for every Eligibility adapter and the shared transaction runner.

## What Eligibility provides

- Typed `Subject`, `Context`, `Rule`, and decision values.
- Exact matching of validated canonical strings without trimming, case folding,
  coercion, transliteration, or Unicode normalization.
- Single and ordered batch evaluation through
  `EligibilityEvaluationServiceInterface`.
- Rule management through `EligibilityManagementServiceInterface`, including
  lifecycle, effect, active-dimension, replacement, and Subject cleanup
  operations.
- Direct-PDO MySQL-compatible persistence adapters with package-owned tables,
  typed semantic failures, and shared transaction/savepoint composition.
- Deterministic ordering for returned collections and input-order preservation
  for `SubjectDecisionCollection`.

## What Eligibility does NOT provide

- It does not validate that a Host-owned Product, Category, Payment Method,
  Shipping Method, Promotion, or other external Subject exists.
- It does not define the business meaning of dimension keys such as `country`
  or `customer_type`.
- It does not replace intrinsic Host-domain visibility, publication, stock,
  pricing, tenant, or authorization rules.
- It does not provide controllers, HTTP responses, UI messages, translations,
  framework bindings, ORM integration, or a query builder.
- It does not infer behavior between different Subjects or dimensions.
- It is a Pre-Stable Release Candidate and does not define a Stable release line.

## Host responsibilities

The Host must:

- Convert external identifiers to canonical strings before constructing
  `Subject` or Context values.
- Define stable Subject-type and dimension-key semantics and perform any
  domain-specific validation or normalization before the package boundary.
- Decide which Context dimensions are required for each application flow.
- Construct the package-owned PDO adapters and schema using a trusted database
  configuration.
- Use one PDO connection for `PdoRuleCommandRepository`,
  `PdoRuleManagementQuery`, `PdoActiveRuleReader`, and
  `PdoSavepointTransactionRunner`.
- Own the outer transaction when Eligibility mutation is composed with a larger
  Host operation.
- Combine the Eligibility decision with the Host domain's own lifecycle and
  visibility rules.
- Clean up Eligibility records when an external Subject is permanently deleted.

## Capability decision map

Use the smallest public surface that matches the task:

| If you need to… | Start here | Runnable example |
|---|---|---|
| Evaluate one Subject | [Evaluate one Subject](#evaluate-one-subject) | [`basic-evaluation.php`](../../examples/basic-evaluation.php) |
| Evaluate several Subjects in deterministic input order | [Evaluate many Subjects](#evaluate-many-subjects) | [`batch-evaluation.php`](../../examples/batch-evaluation.php) |
| Create a Rule | [Create a Rule](#create-a-rule) | [`management-lifecycle.php`](../../examples/management-lifecycle.php) |
| Read one Rule | [Read one Rule](#read-one-rule) | [`management-lifecycle.php`](../../examples/management-lifecycle.php) |
| Read Rules by criteria | [Read Rules by criteria](#read-rules-by-criteria) | [`management-lifecycle.php`](../../examples/management-lifecycle.php) |
| Find active dimension keys | [Find active dimension keys](#find-active-dimension-keys) | [`management-lifecycle.php`](../../examples/management-lifecycle.php) |
| Change a Rule effect | [Update a Rule effect](#update-a-rule-effect) | [`management-lifecycle.php`](../../examples/management-lifecycle.php) |
| Deactivate a Rule | [Deactivate a Rule](#deactivate-and-reactivate-a-rule) | [`management-lifecycle.php`](../../examples/management-lifecycle.php) |
| Reactivate a Rule | [Deactivate a Rule](#deactivate-and-reactivate-a-rule) | [`management-lifecycle.php`](../../examples/management-lifecycle.php) |
| Replace one complete dimension atomically | [Replace dimension Rules](#replace-dimension-rules) | [`replace-dimension-rules.php`](../../examples/replace-dimension-rules.php) |
| Remove all Rules for one Subject | [Clean up a Subject](#clean-up-a-subject) | [`management-lifecycle.php`](../../examples/management-lifecycle.php) |
| Wire production PDO adapters | [Production PDO wiring](#production-pdo-wiring) | [`persistence-wiring.php`](../../examples/persistence-wiring.php) |
| Participate in a Host-owned transaction | [Host-owned transactions](#host-owned-transactions) | [`replace-dimension-rules.php`](../../examples/replace-dimension-rules.php) |
| Handle typed package failures | [Typed exceptions](#typed-exceptions) | [`exception-handling.php`](../../examples/exception-handling.php) |

The intended relationship is:

```text
Guide explanation <-> Runnable example <-> Current public API
```

## Public API usage contract

### Evaluate one Subject

#### Purpose

Evaluate one external Subject against the active Rules loaded for it.

#### Input

- `Subject`: canonical `subjectType` and `subjectId` strings.
- `Context`: zero or more unique `ContextDimension` values.

#### Public call and return type

```php
use Maatify\Eligibility\Evaluation\Contract\EligibilityEvaluationServiceInterface;

$decision = $evaluation->decide($subject, $context);
// EligibilityDecision
```

The interface signature is:

```php
decide(Subject $subject, Context $context): EligibilityDecision
```

#### Observable behavior

Only active Rules are evaluated. Rules are grouped by dimension, DENY wins over
matching ALLOW within a dimension, ALLOW Rules form an allow-list, DENY-only
dimensions form a deny-list, and dimensions are combined with AND semantics.
With no active Rules, the result is `EligibilityDecision::unrestricted()`:
eligible with reason code `UNRESTRICTED` and no dimension outcomes.

#### Relevant failures and Host responsibility

Invalid Subject or Context input raises the typed
`InvalidEligibilityInputException`. Persistence failures from the active-rule
reader are not blindly wrapped; unknown external `PDOException`/`Throwable`
values propagate unchanged. The Host still decides whether the Subject is
intrinsically visible or otherwise usable.

#### Runnable example

Run [`examples/basic-evaluation.php`](../../examples/basic-evaluation.php).
It creates an `ALLOW` Rule for `country=EG`, evaluates the same Subject, and
prints the typed decision as JSON.

### Evaluate many Subjects

#### Purpose

Evaluate multiple distinct Subjects against one Context using the bounded bulk
read path.

#### Input, public call, and return type

```php
use Maatify\Eligibility\Common\Value\SubjectCollection;

$decisions = $evaluation->decideMany(
    new SubjectCollection($first, $second),
    $context,
);
// SubjectDecisionCollection
```

The interface signature is:

```php
decideMany(
    SubjectCollection $subjects,
    Context $context
): SubjectDecisionCollection
```

#### Observable behavior

The returned `SubjectDecisionCollection` preserves the accepted input Subject
order. An empty collection is valid and returns an empty result. Duplicate
Subject identities are rejected, and the canonical path uses one bounded bulk
load rather than a query per Subject.

#### Relevant failures and Host responsibility

Duplicate or invalid Subject values raise `InvalidEligibilityInputException`.
The Host chooses the input order and owns any cross-Subject business policy;
Eligibility does not infer a relationship between Subjects.

#### Runnable example

Run [`examples/batch-evaluation.php`](../../examples/batch-evaluation.php).
It supplies the Subjects in reverse creation order and prints the result in
that same accepted order.

## Management API

All management calls below are methods of
`EligibilityManagementServiceInterface`. The service accepts typed commands or
queries and returns typed values. Application code should not bypass it with
direct SQL.

### Create a Rule

- **Purpose:** Add one active Rule for a Subject, dimension key, dimension
  value, and `RuleEffectEnum::ALLOW` or `RuleEffectEnum::DENY` effect.
- **Input:** `CreateRuleCommand(Subject $subject, mixed $dimensionKey,
  mixed $dimensionValue, RuleEffectEnum $effect)`.
- **Public call:** `$management->createRule($command)`.
- **Return type:** `Rule`.
- **Observable behavior:** A newly created Rule is active. Its natural identity
  is Subject + dimension key + dimension value; effect is mutable state, not
  part of identity.
- **Relevant typed failures:** Invalid canonical input raises
  `InvalidEligibilityInputException`; an existing natural identity raises
  `RuleIdentityConflictException` with the original driver exception preserved
  as `previous` where applicable. Unknown storage failures propagate unchanged.
- **Transaction/concurrency:** This single command is not wrapped by the
  service's multi-step transaction runner. If it is composed with Host work,
  the Host owns the PDO transaction boundary.
- **Host responsibility:** Choose stable dimension semantics and ensure the
  external Subject is valid for the Host domain.
- **Runnable example:** [`management-lifecycle.php`](../../examples/management-lifecycle.php).

### Read one Rule

- **Purpose:** Inspect one Rule by its complete natural identity, whether active
  or inactive.
- **Input:** `RuleIdentity`.
- **Public call:** `$management->inspectRule($identity)`.
- **Return type:** `Rule`.
- **Observable behavior:** The returned Rule exposes `subject`,
  `dimensionKey`, `dimensionValue`, `effect`, and `lifecycle`.
- **Relevant typed failures:** Missing identity raises `RuleNotFoundException`.
  Invalid identity input raises `InvalidEligibilityInputException`; unknown
  storage failures propagate unchanged.
- **Transaction/concurrency:** Read-only; no package-owned transaction is
  opened.
- **Host responsibility:** Treat lifecycle as part of the result; inactive
  Rules remain management-visible but are ignored by evaluation.
- **Runnable example:** [`management-lifecycle.php`](../../examples/management-lifecycle.php).

### Read Rules by criteria

- **Purpose:** Read a bounded collection for one Subject, optionally filtered
  by dimension key and lifecycle.
- **Input:** `RuleCriteria(Subject $subject, ?dimensionKey,
  ?RuleLifecycleEnum $lifecycle, int $maxResults)`; `maxResults` is 1–500 and
  defaults to 100.
- **Public call:** `$management->inspectRules($criteria)`.
- **Return type:** `RuleCollection`.
- **Observable behavior:** Results are canonically ordered. Without a lifecycle
  filter, both active and inactive Rules can be returned. The bound is applied
  by the management query.
- **Relevant typed failures:** Invalid criteria raises
  `InvalidEligibilityInputException`; unknown storage failures propagate
  unchanged.
- **Transaction/concurrency:** Read-only; no package-owned transaction is
  opened.
- **Host responsibility:** Use the bounded result for management or inspection;
  do not treat it as a replacement for the active bulk evaluation reader.
- **Runnable example:** [`management-lifecycle.php`](../../examples/management-lifecycle.php).

### Find active dimension keys

- **Purpose:** Discover which dimensions currently have at least one active
  Rule for one Subject.
- **Input:** `ActiveDimensionKeysQuery(Subject $subject)`.
- **Public call:** `$management->inspectActiveDimensionKeys($query)`.
- **Return type:** `ActiveDimensionKeyCollection`.
- **Observable behavior:** Only active Rules contribute keys; returned keys are
  unique and in canonical bytewise order.
- **Relevant typed failures:** Invalid Subject input raises
  `InvalidEligibilityInputException`; unknown storage failures propagate
  unchanged.
- **Transaction/concurrency:** Read-only; no package-owned transaction is
  opened.
- **Host responsibility:** Interpret the keys according to Host-owned business
  definitions.
- **Runnable example:** [`management-lifecycle.php`](../../examples/management-lifecycle.php).

### Update a Rule effect

- **Purpose:** Change ALLOW to DENY or DENY to ALLOW without changing the Rule
  identity.
- **Input:** `UpdateRuleEffectCommand(RuleIdentity $identity,
  RuleEffectEnum $effect)`.
- **Public call:** `$management->updateRuleEffect($command)`.
- **Return type:** `void`.
- **Observable behavior:** The natural identity remains unchanged. Updating an
  inactive Rule does not reactivate it. Repeating the same update is safe.
- **Relevant typed failures:** Missing identity raises `RuleNotFoundException`;
  invalid values raise `InvalidEligibilityInputException`.
- **Transaction/concurrency:** A single direct mutation; the Host owns any
  surrounding transaction.
- **Host responsibility:** Re-evaluate or invalidate any Host cache according
  to its own policy after changing a Rule.
- **Runnable example:** [`management-lifecycle.php`](../../examples/management-lifecycle.php).

### Deactivate and reactivate a Rule

- **Purpose:** Change the lifecycle state while preserving the Rule's natural
  identity and management record.
- **Input:** `DeactivateRuleCommand(RuleIdentity)` or
  `ReactivateRuleCommand(RuleIdentity)`.
- **Public call:** `$management->deactivateRule($command)` or
  `$management->reactivateRule($command)`.
- **Return type:** `void`.
- **Observable behavior:** Deactivation makes the Rule invisible to evaluation
  but still visible to management reads. Reactivation makes it eligible for
  evaluation again. Both operations are safe to repeat.
- **Relevant typed failures:** Missing identity raises `RuleNotFoundException`;
  invalid identity raises `InvalidEligibilityInputException`.
- **Transaction/concurrency:** Each is a single direct mutation; the Host owns
  any surrounding transaction.
- **Host responsibility:** Decide when an inactive record should remain for
  audit or future reuse; Eligibility does not hard-delete on deactivation.
- **Runnable example:** [`management-lifecycle.php`](../../examples/management-lifecycle.php).

### Replace dimension Rules

- **Purpose:** Set the complete desired active Rule set for one Subject and one
  dimension.
- **Input:** `ReplaceDimensionRulesCommand(Subject $subject, mixed
  $dimensionKey, DesiredRuleCollection $desiredRules)`, where each
  `DesiredRule` contains one dimension value and effect.
- **Public call:** `$management->replaceDimensionRules($command)`.
- **Return type:** `void`.
- **Observable behavior:** Existing identities are reused; effects are updated;
  inactive desired identities are reactivated; missing identities are created;
  omitted active identities are deactivated; omitted inactive identities remain
  inactive. Other dimensions and Subjects are untouched. An empty desired
  collection deactivates all active Rules for that dimension without hard
  deletion. Repeating the same desired set is idempotent.
- **Relevant typed failures:** Invalid command or duplicate desired values raise
  `InvalidEligibilityInputException`. A duplicate natural identity is converted
  to `RuleIdentityConflictException` only when the concrete persistence boundary
  has the documented driver-specific duplicate evidence. The public exception
  inventory contains `RuleConcurrencyConflictException`, but the current RC1
  concrete PDO paths do not automatically throw or classify arbitrary
  concurrency/driver failures as that exception. Unknown storage failures
  propagate unchanged.
- **Transaction/concurrency:** `EligibilityManagementService` depends on
  `SavepointTransactionRunnerInterface`; production PDO wiring supplies
  `PdoSavepointTransactionRunner`. The service locks the Subject coordination
  row, reads the complete Subject + dimension state, and applies the replacement
  as one atomic operation. Concurrent replacements cannot expose a partial or
  mixed dimension state. The same-PDO requirement applies to the production PDO
  composition.
- **Host responsibility:** Pass adapters and the transaction runner built from
  the same PDO. If a Host transaction is already active, the Host owns the
  outer commit or rollback; Eligibility uses an operation-local savepoint and
  does not commit or fully roll back the Host transaction.
- **Runnable example:** [`replace-dimension-rules.php`](../../examples/replace-dimension-rules.php).

### Clean up a Subject

- **Purpose:** Remove all package-owned Rules and coordination metadata for one
  Subject.
- **Input:** `CleanupSubjectCommand(Subject $subject)`.
- **Public call:** `$management->cleanupSubject($command)`.
- **Return type:** `void`.
- **Observable behavior:** All Rules for the supplied Subject are physically
  removed, other Subjects are untouched, and cleanup for a Subject with no
  Rules is an idempotent success.
- **Relevant typed failures:** Invalid Subject input raises
  `InvalidEligibilityInputException`; unknown storage failures propagate
  unchanged.
- **Transaction/concurrency:** Uses the same shared transaction runner,
  Subject coordination lock, and Host-owned outer-transaction/savepoint rules
  as replacement.
- **Host responsibility:** Invoke cleanup when the external Subject is
  permanently deleted; the package does not discover Host deletions itself.
- **Runnable example:** The setup in each database-backed example calls
  `cleanupSubject` for its isolated example Subject; the lifecycle flow is
  shown in [`management-lifecycle.php`](../../examples/management-lifecycle.php).

## Important input and output types

These are the types a consumer needs to understand to call the public API.

### Inputs

- **`Subject`** — immutable canonical `subjectType` + `subjectId` identity.
  Host numeric IDs must become strings before construction.
- **`SubjectCollection`** — ordered distinct Subjects for `decideMany()`;
  duplicate identities are rejected and an empty collection is valid.
- **`Context`** — immutable collection of dimensions sorted canonically.
  Duplicate dimension keys are invalid; an entirely empty Context is valid.
- **`ContextDimension`** — one canonical dimension key and a
  `ContextValueCollection`; `ContextDimension::fromStrings()` is the concise
  construction helper.
- **`ContextValue`** — one canonical Context value. A present dimension's
  values are a semantic set; duplicate values are invalid.
- **`CreateRuleCommand`** — Subject, dimension key, dimension value, and
  `RuleEffectEnum` for one new active Rule.
- **`RuleIdentity`** — Subject type, Subject ID, dimension key, and dimension
  value; effect and lifecycle are intentionally not part of identity.
- **`RuleCriteria`** — bounded management read criteria for Subject, optional
  dimension key, optional lifecycle, and max result count.
- **`ActiveDimensionKeysQuery`** — Subject query for active dimension keys.
- **`DesiredRule` / `DesiredRuleCollection`** — desired values and effects for
  one replacement; desired values must be unique and are canonically ordered.
- **`ReplaceDimensionRulesCommand`** — Subject, dimension key, and complete
  desired collection for atomic replacement.
- **`CleanupSubjectCommand`** — Subject cleanup request.

### Outputs

- **`EligibilityDecision`** — `eligible`, `reasonCode`, and ordered dimension
  outcomes. `UNRESTRICTED` means eligible with no active Rule dimensions;
  `ELIGIBLE` and `DENIED` include dimension outcomes.
- **`SubjectDecisionResult`** — one Subject paired with its
  `EligibilityDecision`.
- **`SubjectDecisionCollection`** — ordered distinct batch results matching the
  supplied Subject order.
- **`Rule`** — Subject, dimension key, dimension value, effect, and lifecycle;
  `naturalIdentity()` returns its `RuleIdentity`.
- **`RuleCollection`** — unique Rules in canonical identity order.
- **`ActiveDimensionKeyCollection`** — unique active dimension keys in
  canonical order.

The decision trace also contains dimension outcomes and matched Rule references
through the public decision model. Consumers should use the machine-readable
reason codes and values for application behavior and provide translations or
presentation text in the Host.

## Production PDO wiring

The RC1 persistence implementation is direct PDO. The
`EligibilityManagementService` depends on
`SavepointTransactionRunnerInterface`; production PDO composition constructs
`PdoSavepointTransactionRunner` from the same PDO connection as the three
Eligibility adapters, then wires their separate capabilities into the two
services:

```php
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
```

Apply `schema/eligibility_rules.sql` as an installation asset. It is safe to
reapply with `CREATE TABLE IF NOT EXISTS`, but it is not an automatic migration
runner. Do not put Host credentials in examples or package configuration.

See [`persistence-wiring.php`](../../examples/persistence-wiring.php) for a
runnable construction-only example.

## Host-owned transactions

`replaceDimensionRules()` and `cleanupSubject()` use the shared
`SavepointTransactionRunnerInterface`. In production PDO composition, that
interface is supplied by `PdoSavepointTransactionRunner`. When no outer
transaction is active, the runner owns the complete operation transaction. When
a Host transaction is already active on the same PDO connection, the runner
creates an operation-local savepoint, releases it on success, and rolls back to
it on failure. It never commits or fully rolls back the Host transaction.

The Host must commit or roll back its outer transaction. A failed operation is
propagated; the package does not swallow the original Throwable. The
[`replace-dimension-rules.php`](../../examples/replace-dimension-rules.php)
example starts a Host-owned transaction around a complete replacement and then
commits it explicitly.

## Typed exceptions

Package-defined failures implement
`Maatify\Eligibility\Exception\EligibilityExceptionInterface`:

- `InvalidEligibilityInputException` — canonical input, collection, or bound
  violations.
- `RuleNotFoundException` — a requested Rule identity is absent for inspection
  or a lifecycle/effect mutation.
- `RuleIdentityConflictException` — a create would duplicate a natural Rule
  identity; the original driver exception is preserved where applicable.
- `RuleConcurrencyConflictException` — a public exception class in the package
  inventory. The current RC1 concrete PDO paths do not use it to classify
  arbitrary concurrency or driver failures; unknown external failures are not
  converted automatically.

Unknown external `PDOException` or other `Throwable` values propagate unchanged.
Consumers may catch the package marker for a package-level boundary, or catch a
specific exception when the application needs different recovery behavior.

See [`exception-handling.php`](../../examples/exception-handling.php) for a
database-free typed validation example.

## Runnable examples

| Example | Demonstrates |
|---|---|
| [`basic-evaluation.php`](../../examples/basic-evaluation.php) | PDO wiring, one Rule, and one Subject decision. |
| [`batch-evaluation.php`](../../examples/batch-evaluation.php) | Ordered `SubjectCollection` and `decideMany()`. |
| [`management-lifecycle.php`](../../examples/management-lifecycle.php) | Create, inspect, criteria reads, active dimensions, effect, deactivate, and reactivate. |
| [`replace-dimension-rules.php`](../../examples/replace-dimension-rules.php) | Atomic complete-dimension replacement and Host-owned transaction participation. |
| [`persistence-wiring.php`](../../examples/persistence-wiring.php) | Same-PDO production adapter and service construction. |
| [`exception-handling.php`](../../examples/exception-handling.php) | Typed package exception handling without a database. |

Every example declares strict types, requires the production Composer autoload,
uses only the current public package API, and avoids test fixtures, private
helpers, framework assumptions, and real credentials.
