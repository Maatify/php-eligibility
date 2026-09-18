# Maatify Eligibility — Canonical Package Reference

## Status

This document is the canonical RC1 design and behavioral contract for `maatify/php-eligibility`.

The RC1 implementation MUST conform to the boundaries, invariants, decision semantics, lifecycle rules, ordering rules, and operational contracts defined here. Changes to these semantics require an explicit documentation decision before implementation changes are accepted.

This document freezes externally observable behavior without prematurely freezing internal class names, table names, column names, indexes, or framework-specific wiring.

## Purpose

`maatify/php-eligibility` is a framework-neutral package for answering one reusable business question:

> Is a given external Subject eligible in the supplied business Context?

The package exists to prevent Product, Category, Payment Method, Shipping, Promotion, and other domains from each inventing their own customer/country restriction subsystem.

Typical examples include:

- whether a Product is available to a customer type;
- whether a Category is available in a country;
- whether a Payment Method is available to a customer type or country;
- whether a Shipping Method or Shipping Provider is available in a country;
- whether a Promotion is available to a customer segment;
- equivalent future eligibility decisions that fit the same Subject/Context Rule model.

Eligibility is a reusable business-policy domain. It is not owned by Category, Product, Shipping, Payment, Customer, Geo, HTTP, Admin, or any presentation layer.

## Core model

The canonical model is:

```text
Subject + Context + Rules -> EligibilityDecision
```

The package knows external identities only. It does not need to know the database model, lifecycle, or implementation of the domains that own those identities.

## Canonical scalar representation

All externally supplied identities, keys, and values that participate in Eligibility matching are represented canonically as strings at the package boundary.

This applies to:

- `subject_type`;
- `subject_id`;
- `dimension_key`;
- `dimension_value`.

For RC1, every canonical string MUST:

- be valid UTF-8;
- be non-empty;
- contain at least one non-whitespace character;
- contain no leading or trailing whitespace;
- be accepted without silent trimming;
- be accepted without silent case conversion;
- be accepted without transliteration;
- be accepted without Unicode normalization;
- be accepted without scalar type coercion.

Internal whitespace is not inherently invalid. Semantic validation beyond the package's structural contract remains the Host's responsibility.

The package MUST NOT expose equality semantics where PHP scalar coercion changes identity.

A Host using numeric database identifiers MUST convert them to a canonical string before constructing Eligibility value objects/DTOs. For example, integer `150` from a Host database becomes canonical Subject ID string `"150"` before entering Eligibility.

The same rule applies to numeric-looking Context values.

Malformed UTF-8, null values, non-string values at typed package boundaries, empty strings, whitespace-only strings, and strings with leading/trailing whitespace MUST be rejected through typed validation errors rather than silently transformed.

RC1 does not restrict business values to ASCII. Persistence MUST preserve the exact validated UTF-8 sequence and MUST NOT silently normalize or truncate it.

RC1 canonical package bounds are resolved and apply at every semantic package
boundary, not only at SQL columns. `Maatify\Eligibility\Common\Validation\CanonicalString`
is the production source of truth and measures bytes with `strlen()` after valid
UTF-8 validation:

| Canonical component | Maximum bytes |
|---|---:|
| `subject_type` | 64 |
| `subject_id` | 191 |
| `dimension_key` | 64 |
| `dimension_value` | 255 |

Every public/raw boundary for these components MUST reject an over-limit value
with `InvalidEligibilityInputException` before constructing contract state or
performing persistence work. These are canonical RC1 package bounds, not merely
SQL column-size choices.

## Subject

A Subject is the thing whose eligibility is being evaluated.

Conceptually:

```text
subject_type + subject_id
```

Examples:

```text
product + "150"
category + "25"
payment_method + "tap"
shipping_method + "dhl_express"
shipping_provider + "aramex"
promotion + "summer-2026"
```

`subject_type` is a stable Host-defined domain key.

`subject_id` is an opaque external canonical string identity. Its semantic source may be a database ID, UUID, stable code, or another Host-owned identifier, but Eligibility does not interpret it.

The package MUST NOT create foreign keys from Eligibility storage to Product, Category, Payment, Shipping, Customer, Geo, or other Host-owned tables.

The Host owns validation that the referenced external Subject actually exists and remains meaningful.

## Context

A Context is an immutable collection of zero or more Context Dimensions.

A Context Dimension describes one business dimension against which a Subject may be restricted.

Conceptually:

```text
dimension_key + one-or-more dimension_value strings
```

Examples:

```text
country = EG
customer_type = retail
customer_segment = vip, loyalty_gold
sales_channel = mobile_app
```

Dimension keys and their semantic meanings are Host-defined. Eligibility owns only generic Rule and evaluation behavior.

The package MUST NOT contain a hardcoded enum of every possible business dimension.

This allows future projects to introduce a dimension without changing Product, Category, Shipping, Payment, or Eligibility core behavior.

### Canonical Context shape

For RC1, Context shape is strict:

- a `dimension_key` may appear at most once in one Context;
- duplicate dimension entries for the same `dimension_key` are invalid input;
- a present dimension MUST contain one or more values;
- an empty value collection for a present dimension is invalid input;
- a missing dimension is represented only by the complete absence of that `dimension_key` from the Context;
- an entirely empty Context is valid;
- values within one dimension form a semantic set;
- duplicate values within one dimension are invalid input rather than silently deduplicated;
- the order of values within one dimension has no effect on eligibility;
- public contracts MUST use typed DTOs/value objects/collections rather than associative arrays as their API model.

This distinction is canonical:

```text
country absent
```

means the Context does not supply a country dimension.

There is no separate RC1 representation for:

```text
country = []
```

because an explicitly present empty dimension is invalid.

### Multi-value Context semantics

A Context may contain multiple values for one dimension when the Host domain requires it. For example, a customer may belong to multiple segments.

For evaluation:

- extra Context dimensions for which the Subject has no active Rules are ignored;
- a matching DENY for any supplied value denies that dimension;
- when ALLOW Rules exist, at least one supplied value matching an ALLOW is sufficient unless any DENY also matches.

Example:

```text
Rules:
country:EG allow

Context values:
EG, SA

Decision for country: passes because at least one supplied value satisfies the allow-list and no DENY matches.
```

## Rule

A Rule binds one Subject to one dimension value with one explicit effect:

```text
subject_type
subject_id
dimension_key
dimension_value
effect = allow | deny
```

Examples:

```text
product:150               country:EG                allow
product:150               country:IL                deny
category:25               customer_type:wholesale   deny
payment_method:tap        country:KW                allow
shipping_provider:aramex  country:EG                deny
```

Rules are exact. RC1 has no implicit wildcard, fuzzy match, range expression, script, callback, priority, score, or executable expression language.

## Canonical Rule identity

The canonical natural identity of one Rule is:

```text
subject_type
+ subject_id
+ dimension_key
+ dimension_value
```

`effect` is NOT part of the natural identity.

Therefore the following state is invalid and MUST NOT be representable as two independent Rules:

```text
product:150 country:EG allow
product:150 country:EG deny
```

Changing `allow` to `deny`, or `deny` to `allow`, is a mutation of the same Rule identity.

The active/inactive state is also not part of natural identity.

Persistence and management contracts MUST enforce one persistent Rule per natural identity and MUST surface conflicts through typed package/domain errors rather than leaking raw PDO/database uniqueness errors.

## Exact matching

Eligibility performs exact matching on validated canonical strings.

The package MUST NOT silently trim, lowercase, uppercase, transliterate, Unicode-normalize, coerce numeric values, or otherwise semantically normalize Subject or Context data.

Consequently:

```text
EG != eg
```

and a padded value such as:

```text
" EG "
```

is invalid package input; it MUST be rejected rather than treated as `EG`.

The Host owns semantic normalization, such as deciding that country codes are uppercase ISO codes before constructing Eligibility inputs.

Database collation MUST NOT change package matching semantics. Persistence MUST preserve exact equality according to the validated UTF-8 sequence defined by the package, regardless of database defaults.

## Canonical ordering

Deterministic ordering is part of the RC1 observable contract and MUST NOT depend on database default collation or row-return order.

Canonical string ordering is ascending binary/bytewise ordering over the validated UTF-8 byte sequence.

Canonical tuple ordering compares each component in sequence and moves to the next component only when the previous component compares equal.

The following orderings are fixed for RC1:

- active-dimension key collections: `dimension_key` ascending;
- `DimensionOutcome` collections: `dimension_key` ascending;
- Rule collections spanning multiple Subjects: `subject_type`, then `subject_id`, then `dimension_key`, then `dimension_value`;
- Rule collections already scoped to one Subject: `dimension_key`, then `dimension_value`;
- Rule collections already scoped to one Subject + dimension: `dimension_value`;
- matched Rule references within one `DimensionOutcome`: `dimension_value` ascending;
- batch Decisions: the same order as the accepted input Subject collection.

Adapters MAY use a different internal ordering when useful, but public/domain results MUST be normalized to canonical ordering before return.

## Canonical Rule lifecycle

RC1 uses an explicit active/inactive Rule lifecycle.

A Rule's natural identity remains unique regardless of active state. Deactivation does not create a second identity and reactivation does not create a new Rule.

Canonical lifecycle behavior:

- `create` always creates the new Rule in the active state;
- individual RC1 `create` does not accept an initial inactive state;
- active Rules participate in evaluation;
- inactive Rules are ignored completely by evaluation;
- a Rule may be deactivated;
- an inactive Rule may be reactivated;
- the effect of an existing Rule may be changed as a mutation of that Rule;
- creating another Rule with the same natural identity is a typed conflict regardless of whether the existing Rule is active or inactive;
- lifecycle operations MUST remain concurrency-safe around the uniqueness invariant.

### Lifecycle operation orthogonality

Lifecycle and effect mutations are separate state transitions in RC1:

- `updateEffect` changes only the Rule effect and MUST preserve the current active/inactive state;
- `deactivate` changes only lifecycle state to inactive and MUST preserve the current effect;
- `reactivate` changes only lifecycle state to active and MUST preserve the current effect.

Therefore updating the effect of an inactive Rule leaves that Rule inactive until an explicit reactivate operation or a `replaceDimensionRules()` operation reactivates it under the replacement semantics defined below.

### Lifecycle command idempotency

RC1 state-setting commands are idempotent when the target Rule exists:

- deactivating an already inactive Rule succeeds with no state change;
- reactivating an already active Rule succeeds with no state change;
- updating a Rule to its current effect succeeds with no state change.

The distinction between idempotency and absence is canonical:

- deactivate/reactivate/update-effect against a natural identity that does not exist MUST produce a typed Rule-not-found outcome;
- create against a natural identity that already exists MUST produce a typed natural-identity conflict and MUST NOT silently reactivate or update the existing Rule.

`replaceDimensionRules()` has its own set-replacement semantics defined below and may reuse/reactivate existing persistent Rule identities.

RC1 MUST NOT rely on duplicate active rows, effect-specific duplicates, ambiguous restore behavior, or create-as-update behavior.

Physical cleanup of all Eligibility data for a deleted external Subject is a separate management operation.

## Canonical evaluation semantics

Eligibility Decisions MUST be deterministic.

### 1. No active Rules means unrestricted

If a Subject has no active Rules, it is eligible from the Eligibility package's point of view.

This means only:

> Eligibility imposes no restriction on this Subject.

It does NOT mean that the owning domain has enabled, published, activated, or otherwise made the Subject available.

A Product may still be inactive, a Category may still be hidden, a Payment Method may still be disabled, and a Shipping Method may still be unavailable for intrinsic domain reasons.

Eligibility MUST NOT be used as the owning domain's master enable/disable switch.

Removing or deactivating the last active Rule changes Eligibility state to unrestricted; Hosts and Admin integrations MUST account for this explicitly.

### 2. Rules are evaluated per dimension

Active Rules for the same Subject are grouped by `dimension_key`.

Each ruled dimension is evaluated independently against the Context values supplied for that dimension.

Inactive Rules do not participate.

### 3. DENY always wins within a dimension

If any active DENY Rule for a dimension matches any supplied Context value for that dimension, the dimension fails.

A matching DENY overrides every matching ALLOW in that dimension.

Example:

```text
Rules:
customer_segment:vip      allow
customer_segment:blocked  deny

Context values:
vip, blocked

Decision for this dimension: denied
```

The evaluator MAY short-circuit internal work only if doing so does not lose matched-Rule trace information required by the canonical Decision contract.

Overall RC1 Decision construction MUST still evaluate all ruled dimensions so the final Decision contains a complete deterministic trace.

### 4. ALLOW Rules create an allow-list for that dimension

If at least one active ALLOW Rule exists for a dimension, and no matching DENY exists, at least one supplied Context value for that dimension MUST match an ALLOW Rule.

Otherwise that dimension fails.

Example:

```text
Rules:
country:EG allow
country:KW allow

Context:
country:SA

Decision: denied because the Subject has a country allow-list and SA is not in it.
```

### 5. DENY-only Rules behave as a deny-list

If a dimension has active DENY Rules but no active ALLOW Rules, the dimension passes unless a DENY value matches.

Example:

```text
Rules:
country:IL deny

Context:
country:EG

Decision: passes the country dimension.
```

### 6. Missing Context values are explicit

Because present Context dimensions cannot be empty in RC1, a missing Context value means the dimension key is absent from the Context entirely.

If the Subject has ALLOW Rules for a dimension but the Context omits that dimension, the dimension fails because the allow-list cannot be satisfied.

If the Subject has DENY-only Rules for a dimension and the Context omits that dimension, no DENY Rule matches and the dimension passes.

This DENY-only missing-context case is intentionally fail-open from Eligibility's perspective and MUST NOT be silently changed by an implementation.

The Decision trace MUST distinguish this case from a DENY-only dimension that received Context values and passed because none matched.

The Host is responsible for deciding which Context dimensions it is required to resolve before asking Eligibility for a Decision.

To support safe Host integration, RC1 management/query contracts MUST allow the Host to discover the active dimension keys that currently govern a supplied Subject.

This introspection is informational only; Eligibility does not infer that every active dimension is mandatory Host input because DENY-only dimensions legally pass when absent.

### 7. Different dimensions combine with AND

A Subject is eligible only when every dimension that has active Rules passes.

Example:

```text
Rules:
customer_type:vip allow
country:EG allow
country:KW allow

Context:
customer_type:vip
country:EG

Decision: eligible
```

But:

```text
customer_type:vip
country:SA
```

is denied because the country dimension fails.

### 8. Eligibility does not infer relationships between dimensions

RC1 does not implement arbitrary boolean expression trees such as:

```text
(country = EG AND customer_type = retail)
OR
(country = KW AND customer_type = wholesale)
```

The RC1 model intentionally stays bounded:

- OR across supplied/matched values within one dimension;
- AND across dimensions;
- DENY precedence within a dimension.

More complex policy composition requires an explicit future package design rather than hidden special cases.

## Canonical Decision contract

The public Decision contract MUST be typed and immutable. A boolean-only public result is insufficient for RC1.

A Decision MUST expose at least:

```text
EligibilityDecision
- eligible: bool
- reasonCode: DecisionReason
- dimensionOutcomes: ordered collection<DimensionOutcome>
```

Each ruled dimension MUST produce a deterministic outcome containing at least:

```text
DimensionOutcome
- dimensionKey: string
- passed: bool
- reasonCode: DimensionReason
- matchedRules: ordered collection<RuleReference>
```

A `RuleReference` represents the canonical natural identity and effect of a matched Rule sufficiently for machine diagnostics without requiring a persistence-specific surrogate primary key:

```text
RuleReference
- subjectType
- subjectId
- dimensionKey
- dimensionValue
- effect
```

An implementation MAY additionally expose a stable package-owned Rule identifier if the contracts/schema slice chooses one, but RC1 Decision correctness MUST NOT depend on a database-specific surrogate ID.

### Decision invariants

The three overall Decision states are mutually exclusive and MUST obey these invariants:

#### `UNRESTRICTED`

```text
eligible = true
reasonCode = UNRESTRICTED
dimensionOutcomes = []
```

`UNRESTRICTED` is valid only when the Subject has no active Rules.

#### `ELIGIBLE`

```text
eligible = true
reasonCode = ELIGIBLE
dimensionOutcomes = non-empty
```

`ELIGIBLE` is valid only when the Subject has at least one active ruled dimension and every `DimensionOutcome.passed` is `true`.

#### `DENIED`

```text
eligible = false
reasonCode = DENIED
dimensionOutcomes = non-empty
```

`DENIED` is valid only when at least one `DimensionOutcome.passed` is `false`.

A Decision representation that violates these combinations is invalid package state.

The Decision MUST represent every ruled dimension rather than stopping after the first failed dimension.

Dimension outcomes and matched Rule collections MUST follow canonical ordering.

### Stable machine reason semantics

RC1 owns stable machine-readable reason semantics.

Overall Decision reasons:

```text
UNRESTRICTED
ELIGIBLE
DENIED
```

Dimension outcome reasons:

```text
PASSED_ALLOW_LIST
PASSED_DENY_LIST
PASSED_DENY_LIST_CONTEXT_MISSING
DENIED_BY_RULE
ALLOW_LIST_UNSATISFIED
ALLOW_LIST_CONTEXT_MISSING
```

Meaning:

- `PASSED_ALLOW_LIST`: one or more ALLOW Rules matched and no DENY matched;
- `PASSED_DENY_LIST`: the dimension has DENY-only Rules, Context values were supplied, and none matched;
- `PASSED_DENY_LIST_CONTEXT_MISSING`: the dimension has DENY-only Rules and the Context omitted that dimension, so it intentionally passed fail-open;
- `DENIED_BY_RULE`: one or more matching DENY Rules caused failure;
- `ALLOW_LIST_UNSATISFIED`: Context values were supplied but none matched an existing ALLOW requirement and no DENY matched;
- `ALLOW_LIST_CONTEXT_MISSING`: ALLOW Rules exist but the Context omitted that dimension.

### Complete matched-Rule trace

`matchedRules` MUST represent every active Rule in that dimension whose `dimension_value` exactly matches any supplied Context value, regardless of Rule effect.

Therefore:

- for `PASSED_ALLOW_LIST`, `matchedRules` contains all matching active ALLOW Rules; no DENY may be present because any matching DENY would change the reason to `DENIED_BY_RULE`;
- for `DENIED_BY_RULE`, `matchedRules` contains all matching active Rules, including matching DENY Rules and any simultaneously matching ALLOW Rules;
- for `PASSED_DENY_LIST`, `PASSED_DENY_LIST_CONTEXT_MISSING`, `ALLOW_LIST_UNSATISFIED`, and `ALLOW_LIST_CONTEXT_MISSING`, `matchedRules` is empty because no active Rule matched supplied Context values.

The presence of matching ALLOW Rules inside a `DENIED_BY_RULE` trace does not weaken DENY precedence; it exists only for complete machine diagnostics.

Exact PHP enum/class names are implementation-stage naming decisions, but these semantics are canonical and MUST remain machine-stable for RC1.

Human-facing translated messages are not owned by Eligibility. The Host maps machine-readable Decisions to customer/admin/API presentation.

## Single and batch evaluation

RC1 MUST support both single-Subject and batch evaluation.

Conceptually:

```text
decide(Subject, Context) -> EligibilityDecision

decideMany(ordered list<Subject>, Context)
    -> ordered list<SubjectDecision>
```

where each `SubjectDecision` associates exactly one supplied Subject with its `EligibilityDecision`.

Batch semantics are canonical:

- duplicate Subject natural identities in one batch request are invalid input and MUST be rejected rather than silently deduplicated or overwritten;
- an empty batch is valid and returns an empty result collection;
- results MUST preserve the accepted input Subject order;
- single and batch paths MUST use identical evaluation semantics and produce equivalent Decisions for the same Subject/Context pair.

Batch evaluation is a first-class RC1 requirement so consumers such as Catalog listings, checkout method lists, and admin diagnostics do not require one persistence read per Subject.

The persistence/evaluation design MUST support loading Rules for a supplied candidate set in bounded bulk operations and MUST NOT require an N+1 query pattern as the canonical batch path.

The exact SQL query shape and internal grouping strategy remain implementation details.

## Query and pagination boundary

Eligibility is an evaluator, not a Host-domain query planner.

It does not own the complete universe of Product, Category, Payment Method, Shipping Method, or other external Subject identities and therefore MUST NOT pretend that it can globally discover every eligible or ineligible Host entity by itself.

For RC1:

- the Host owns Catalog/domain search, sorting, and pagination;
- the Host may supply a candidate Subject set for batch Eligibility evaluation;
- Eligibility does not join Host-owned Product/Category/etc. tables;
- Eligibility does not expose a global `getIneligibleSubjectIds(subjectType, context)` contract that assumes knowledge of the Host's complete Subject universe.

A Host MUST NOT assume that applying `LIMIT/OFFSET` first and then filtering that page through `decideMany()` produces eligibility-correct pagination. That pattern can return short pages and can move eligible entities onto later pages because ineligible rows consumed positions before Eligibility evaluation.

Therefore `decideMany()` is suitable for evaluating a supplied candidate set, but it is not by itself a solution for eligibility-aware global filtering or pagination.

If a project requires eligibility-aware SQL/search pagination across a large Host dataset, that requires an explicit integration design such as a projection, query adapter, materialized eligibility view, or search index. It is not silently folded into the RC1 core.

## Management responsibilities

RC1 MUST provide typed application contracts sufficient for a Host to manage Eligibility Rules without direct SQL access from application/domain code.

The management surface MUST support the capability to:

- create a Rule for an external Subject and exact dimension value;
- inspect Rules for a Subject across both active and inactive lifecycle states;
- inspect/filter Rules by dimension using bounded reads;
- filter management Rule reads by lifecycle state when required;
- inspect the active dimension keys governing a Subject;
- update an existing Rule's effect;
- deactivate a Rule;
- reactivate an inactive Rule;
- replace all Rules for one Subject + dimension atomically;
- clean up all Eligibility Rules for one external Subject;
- evaluate one Subject through the canonical policy service;
- evaluate many supplied Subjects against one Context.

Management Rule representations MUST expose lifecycle state explicitly so callers can distinguish active from inactive Rules.

Management reads MUST be capable of returning inactive Rules. An adapter or service MUST NOT silently hide inactive Rules from the management surface merely because the evaluator ignores them.

Lifecycle filtering MAY be represented through typed criteria/query contracts; exact class names are an implementation-stage decision.

Active-dimension queries describe evaluation state and therefore consider active Rules only. Management Rule collections may include active and inactive Rules according to the requested criteria. All returned collections MUST follow canonical ordering.

### Atomic dimension replacement

Administrative use cases commonly express a desired final active set rather than a sequence of individual adds/removes, for example:

```text
product:150
country allow-list = {EG, KW, SA}
```

RC1 therefore MUST support a typed operation conceptually equivalent to:

```text
replaceDimensionRules(Subject, dimensionKey, desiredRules)
```

`desiredRules` defines the complete desired active Rule set for that Subject + dimension after the operation commits.

Canonical replacement semantics are:

- duplicate `dimension_value` entries in `desiredRules` are invalid input because effect is not part of natural identity;
- an existing active Rule present in `desiredRules` remains active and its effect is updated when necessary;
- an existing inactive Rule present in `desiredRules` is reactivated and its effect is updated when necessary;
- a desired Rule whose natural identity does not yet exist is created active;
- an existing active Rule for the same Subject + dimension that is absent from `desiredRules` is deactivated, not hard-deleted;
- an existing inactive Rule absent from `desiredRules` remains inactive;
- an empty `desiredRules` collection deactivates every currently active Rule for that Subject + dimension;
- Rules belonging to other dimensions or Subjects are untouched;
- repeating the same replacement request against the same resulting state succeeds without creating duplicate Rules or changing semantics.

The operation MUST be atomic from the caller's perspective and preserve Rule natural-identity uniqueness throughout the mutation.

This prevents every Host from implementing its own unsafe `delete all then insert` synchronization routine while preserving the canonical reversible lifecycle.

The package MUST coordinate correctly with an existing Host transaction when the Host composes Eligibility mutation with a larger domain operation. Eligibility delegates generic transaction/savepoint mechanics to the released `maatify/persistence` API through `SavepointTransactionRunnerInterface`; the command/mutation adapters and runner MUST use the same PDO connection. The transaction participation contract is:

- when no transaction is active on the shared runner's PDO boundary, `PdoSavepointTransactionRunner` owns the transaction for this multi-step operation: it begins the transaction, commits only after the complete replacement succeeds, and rolls back only while that transaction remains active;
- when an outer Host transaction is already active on the same PDO boundary, the shared runner uses an operation-local savepoint and MUST NOT commit or fully roll back the Host transaction; the Host owns the outer commit/rollback;
- a failure in either mode MUST be propagated. For a shared-runner-owned transaction, rollback is attempted only while that transaction remains active, and the original `Throwable` is rethrown unless an explicitly documented semantic conversion applies. When semantic wrapping occurs, the original throwable MUST be preserved as `previous` where supported;
- the operation MUST NOT expose a partially applied replacement. Under an outer transaction, the replacement is atomic as part of the Host's larger transaction and becomes durable only when that outer transaction commits.

This contract applies to `replaceDimensionRules()` and `cleanupSubject()`. The shared Persistence package owns transaction ownership, savepoint naming, cleanup, and original-`Throwable` preservation; Eligibility owns only its mutation orchestration and Subject coordination locking.

### Transaction and concurrency boundaries

The following caller-visible concurrency guarantees are part of RC1. Their implementation remains inside the package persistence boundary and MUST NOT be reproduced by Hosts:

- persistence MUST enforce one Rule per natural identity (`subject_type + subject_id + dimension_key + dimension_value`), independent of `effect` and lifecycle state;
- concurrent creates for the same natural identity MUST NOT create duplicate Rules. One successful create may win; a competing create MUST produce the typed natural-identity conflict when the duplicate is proven, or the typed concurrency/uniqueness conflict when safe classification or resolution is not possible;
- effect and lifecycle mutations MUST be atomic for one Rule identity and MUST preserve their orthogonal-state contracts. Concurrent operations are observed in a serialized committed order: an effect update does not implicitly reactivate a Rule, and lifecycle changes do not implicitly change its effect;
- `replaceDimensionRules()` MUST serialize or otherwise safely coordinate the complete Subject + dimension state. Concurrent replacements MUST NOT produce duplicate identities, a partially replaced dimension, or a mixed state assembled from two desired sets. The committed result follows the persistence boundary's serialization order; no stronger last-writer policy is promised. If safe resolution cannot be completed, the operation MUST fail with a typed concurrency/uniqueness outcome and leave no partial replacement;
- evaluation and management reads MUST observe a coherent committed state. They may observe the state before or after a concurrent committed mutation according to the persistence boundary's normal visibility rules, but MUST NOT observe an intermediate replacement state;
- Subject cleanup MUST remove only Rules for the supplied Subject as one coherent cleanup operation. The Host remains responsible for coordinating permanent Subject deletion so that new Rule mutations are not admitted after cleanup; a later, separately committed create is outside the cleanup operation's guarantee.

Exact SQL locking, lock mode, retry policy, isolation level, and database-specific concurrency mechanism are implementation/schema-slice decisions unless a later approved contract changes this boundary.

### Subject cleanup

Because Eligibility intentionally has no foreign keys to Host-owned Subjects, the Host requires an official cleanup path when an external Subject is permanently removed.

RC1 MUST therefore expose a typed operation conceptually equivalent to physically removing all Eligibility Rules owned by a supplied Subject identity.

Subject cleanup is the canonical hard-delete boundary for orphan removal. It is distinct from ordinary Rule deactivation and dimension replacement.

Subject cleanup is idempotent:

- if Rules exist for the Subject, all of them are physically removed;
- if no Rules exist for the Subject, the operation still succeeds with no state change.

This cleanup capability MUST NOT require the Eligibility package to query or understand the Host-owned Subject table.

### Typed errors

Management conflicts and invalid input MUST surface through typed package/domain errors rather than raw storage-driver exceptions when Eligibility owns a stable semantic classification. Unknown or external infrastructure failures remain external failures and MAY propagate unchanged; they MUST NOT be silently swallowed or converted by a blind catch-all.

RC1 error semantics MUST distinguish at least:

- invalid structural input;
- natural-identity conflict on create;
- requested Rule not found for commands that require an existing Rule;
- concurrency/uniqueness conflict that could not be resolved safely.

The exception ownership contract required by the adopted package standards is:

- `maatify/exceptions` remains the owner of the shared exception hierarchy, including `MaatifyException` as its abstract root and `ApiAwareExceptionInterface` as its general public contract. Eligibility owns only its package marker and Eligibility-specific semantic classifications;
- package-defined semantic exceptions MUST use the appropriate stable hierarchy from `maatify/exceptions` and MUST declare `maatify/exceptions` as a direct runtime dependency when those public types are implemented;
- Eligibility MUST expose exactly one package marker at `Maatify\Eligibility\Exception\EligibilityExceptionInterface`. The marker MUST be named `EligibilityExceptionInterface` and extend `\Throwable`;
- every Eligibility-defined exception MUST implement the marker directly or indirectly and MUST follow the required `Exception` suffix. The marker is package-owned and is not a replacement for the shared `maatify/exceptions` hierarchy;
- propagated `PDOException` or other external `Throwable` instances MUST NOT be forced to implement the Eligibility marker;
- a known domain or storage condition MAY be converted to a named Eligibility exception only when the package owns that semantic classification. A duplicate-key conversion requires documented driver-specific evidence; SQLSTATE class `23` alone is insufficient, and nullable driver error metadata MUST be handled safely;
- any `PDOException` or `Throwable` not explicitly classified by such evidence MUST propagate unchanged. Blind catch-all wrapping is forbidden. When a semantic wrapper is used, it MUST preserve the original throwable as `previous` where supported;
- repository and read operations MUST never swallow storage failures, and service orchestration MUST allow these exceptions to propagate rather than hiding them.

Exact named exception class inventory and constructor names remain implementation-stage decisions; the marker name, shared hierarchy ownership, semantic-conversion boundary, and propagation behavior above are frozen for RC1.

Public/domain contracts MUST remain typed and MUST NOT use associative arrays as their API model.

## Package boundaries

### Eligibility owns

Eligibility owns:

- generic Subject identity representation;
- generic Context dimension/value representation;
- canonical UTF-8 string validation and exact matching semantics;
- canonical Context shape invariants;
- ALLOW/DENY Rule representation;
- Rule natural-identity and lifecycle invariants;
- Rule persistence and management behavior;
- deterministic evaluation semantics;
- canonical result ordering;
- single and batch evaluation contracts;
- typed Decision and dimension-outcome results;
- stable machine-readable Decision reasons;
- package-level validation of its own keys, values, effects, identities, and storage invariants.

### Eligibility does not own

Eligibility does not own:

- Product, Category, Payment Method, Shipping Method, Shipping Provider, Promotion, or Customer entities;
- Customer authentication or authorization;
- roles/permissions for administrators;
- geographic country/state/postal datasets;
- customer-type or segment registries;
- Product/Category hierarchy;
- Product availability, stock, inventory, pricing, or publication lifecycle;
- payment processing;
- shipping rate calculation, zones, weight logic, or carrier APIs;
- HTTP routes, Slim middleware, controllers, Twig, JavaScript, or presentation;
- translation of denial reasons into customer-facing text;
- Host-domain search/pagination planning;
- automatic joins or foreign keys to Host domain tables;
- cross-tenant discovery or tenant identity modeling.

## Eligibility is not authorization

Eligibility MUST remain separate from security authorization.

Examples:

```text
"Can this administrator edit payment methods?"
```

is authorization and does not belong here.

```text
"Is payment method X available to this customer context?"
```

is Eligibility and belongs here.

This package MUST NOT be used as a replacement for AdminKernel permissions, route guards, authentication, or security policy.

## Eligibility is not domain visibility

A domain may have its own intrinsic visibility rules.

For example, Category may define that an inactive or soft-deleted Category, or a Category below an inactive ancestor, is not visible. Eligibility does not replace that behavior.

The consuming Host combines intrinsic domain visibility with Eligibility when needed:

```text
category is intrinsically visible
AND
category is eligible for current context
```

Only then should the Host expose it to that consumer.

## No automatic cross-Subject inheritance

Eligibility does not infer that Rules on one Subject automatically apply to another Subject.

For example, a restriction on:

```text
category:25
```

MUST NOT silently become a restriction on every Product assigned to Category 25 unless the Host explicitly defines that composite business behavior.

Likewise, the package does not know Product-to-Category membership or Category ancestry.

A Host that requires composite behavior may evaluate all relevant Subjects and combine the Decisions according to its own documented policy.

This prevents Eligibility from depending on Catalog/Category schemas and keeps the package reusable outside ecommerce projects.

## Shipping migration/use case

The existing shipping provider-country restriction pattern is a direct Eligibility use case.

A shipping-specific rule such as:

```text
provider = ARAMEX
country = EG
restricted = true
```

maps to the generic model as:

```text
subject_type = shipping_provider
subject_id = ARAMEX
dimension_key = country
dimension_value = EG
effect = deny
```

Future projects SHOULD NOT need to create a dedicated `ShippingProviderRestriction` persistence/domain subsystem merely to express this rule.

Shipping itself still owns shipping methods, zones, rates, weight/dimensional calculation, free-shipping thresholds, provider integration, and other shipping-specific behavior.

The recommended integration boundary is that Shipping or the Host may expose/consume a narrow availability-policy port, while the Host adapts that port to `maatify/php-eligibility` when Eligibility is installed.

`maatify/shipping` SHOULD NOT require Eligibility as a mandatory core dependency merely to remain independently usable in projects that need no Eligibility Rules.

## Product use case

Example:

```text
subject: product:150

rules:
customer_type:wholesale allow
country:EG allow
country:KW allow
```

A wholesale customer in Egypt passes.

A retail customer in Egypt fails the customer-type dimension.

A wholesale customer in Saudi Arabia fails the country dimension.

Product still owns Product lifecycle, pricing, stock, and other Product rules.

## Category use case

Example:

```text
subject: category:25

rules:
customer_type:retail deny
```

Retail customers are denied; other customer types are not restricted by that dimension.

Category still owns hierarchy, intrinsic active/deleted visibility, content, ordering, and its own domain invariants.

Eligibility does not create a Category dependency and Category does not need customer/country columns or restriction tables.

## Payment Method use case

Example:

```text
subject: payment_method:tap

rules:
country:KW allow
country:SA allow
customer_type:blocked deny
```

The Payment Method domain still owns method configuration and lifecycle. Eligibility only determines whether the method passes the supplied business Context.

## Country and geography

Country is a Context dimension, not an Eligibility-owned Country entity.

Eligibility may store an exact external country code as a dimension value, but it does not own ISO datasets or validate that a code exists in a Host geography registry.

The Host owns semantic geography validation and normalization before Rules/Context reach Eligibility.

The same principle applies to states, regions, postal zones, markets, or other geography concepts if a future Host uses them as Eligibility dimensions.

## Customer Context

Eligibility does not require a Customer entity.

The Host resolves relevant customer information into Context values before evaluation.

For example:

```text
customer_type = wholesale
customer_segment = vip
country = EG
```

Eligibility does not query Customer tables to discover those values.

This keeps the package usable for guests, API clients, B2B accounts, organizations, devices, or other consumers that can be represented by business Context without being modeled as a concrete Customer record.

## Multi-tenancy and scope

RC1 does NOT add `tenant_id`, `scope_key`, organization identity, or another tenancy concept to the Eligibility domain model.

Tenant isolation is a Host/storage-boundary responsibility. A multi-tenant Host MUST provide isolated persistence/connection/schema/database scope such that one tenant cannot observe or mutate another tenant's Eligibility Rules.

The package MUST NOT silently add a global cross-tenant Rule namespace merely because its Subject identities are opaque.

If a future use case genuinely requires tenant identity to participate in the business meaning of an Eligibility Decision rather than storage isolation, that requires an explicit future design decision.

## Persistence principles for RC1

### D2 — Resolved database compatibility contract

For RC1, Eligibility persistence targets **MySQL-compatible database-server
semantics through direct PDO**. The database compatibility contract is
capability-based, not product-version-based.

Accordingly, this package declares no minimum MySQL version and no minimum MariaDB
version. A compatible database server must provide the capabilities actually used
by the schema and SQL: transactional InnoDB-style package-owned table behavior,
binary-safe exact-value storage/comparison, the bounded indexed-key capacity
documented below, and the required uniqueness/index semantics. A server is not
supported merely because it describes itself as MySQL-compatible; it must provide
those capabilities. MariaDB compatibility is not claimed without executed MariaDB
verification.

Separately, the PHP runtime executing this package MUST provide `ext-pdo` and
`ext-pdo_mysql`. These are PHP runtime requirements, not capabilities supplied by
the database server.

The RC1 reproducibility fixture is `mysql:8.4.11`. That fixture version is test
infrastructure evidence only and is not a minimum supported product version.

The B3 schema bounds canonical UTF-8 inputs by bytes, before persistence:

| Canonical component | Maximum bytes | Storage |
|---|---:|---|
| `subject_type` | 64 | `VARBINARY(64)` |
| `subject_id` | 191 | `VARBINARY(191)` |
| `dimension_key` | 64 | `VARBINARY(64)` |
| `dimension_value` | 255 | `VARBINARY(255)` |

The aggregate natural-identity index is 574 bytes, staying within the
conservative indexed-key capability used by this schema. `VARBINARY` preserves
the validated UTF-8 byte sequence and avoids collation-based case folding or
Unicode normalization. PHP validates the byte bounds before SQL; the database
must not trim, normalize, coerce, or truncate input. Repository results are
hydrated and then normalized by the existing package collections to canonical
bytewise ordering.

The concrete B3 adapters are
`Maatify\Eligibility\Rule\Repository\PdoRuleCommandRepository`,
`Maatify\Eligibility\Rule\Repository\PdoRuleManagementQuery`, and
`Maatify\Eligibility\Rule\Repository\PdoActiveRuleReader`. They are
constructed from the same PDO connection while keeping mutation, management
query, evaluation-read, and internal mutation-support responsibilities
separate. `PdoSavepointTransactionRunner` is also constructed from that same
PDO connection and is wired as the service's transaction dependency. The
command adapter implements only `RuleCommandRepositoryInterface` and
`RuleMutationSupportInterface`.
The command adapter's internal auto-increment `BIGINT UNSIGNED` primary key is infrastructure-only
and is not part of `Rule`, `RuleIdentity`, `RuleReference`, Decisions, or B2
public contracts. B3 converts only MySQL/MariaDB driver error code `1062` to
`RuleIdentityConflictException` and preserves the original `PDOException` as
`previous`; unknown storage failures propagate unchanged. No Eligibility table
has a Host foreign key or Host join.

The final RC1 schema and adapter MUST preserve these principles:

- Eligibility-owned tables only;
- no foreign keys to Host-owned Subject or Context domains;
- all canonical identity/matching components persist as exact validated UTF-8 strings consistent with package semantics;
- no storage collation or transformation may collapse distinct canonical strings;
- one persistent natural Rule identity for `subject_type + subject_id + dimension_key + dimension_value`;
- `effect` is mutable state, not part of uniqueness;
- explicit active/inactive lifecycle;
- individual create persists new Rules active;
- explicit typed ALLOW/DENY storage;
- management storage reads preserve and expose lifecycle state;
- bounded management reads;
- bulk Rule loading for supplied Subject sets;
- canonical package-defined ordering for returned Rule and Decision collections;
- concurrency-safe mutation behavior where uniqueness/lifecycle invariants require it;
- typed conflict translation rather than raw driver errors;
- direct PDO for the RC1 persistence implementation, with no ORM and no external query builder;
- no dependency on a framework or HTTP runtime.

Exact table/column names, indexes, maximum lengths, timestamp fields, optional surrogate Rule IDs, and adapter internals are implementation/schema-slice decisions, but they MUST be explicitly documented and verified before RC1 release readiness.

The RC1 persistence implementation MUST use direct PDO. It MUST NOT use an ORM or an external query builder. Repository and interface substitution boundaries MAY remain part of the public architecture, but every RC1 persistence implementation MUST preserve this direct-PDO requirement; a non-PDO implementation MUST NOT be presented as an RC1 alternative. Eligibility MUST NOT require Laravel, Doctrine, Slim, or another framework runtime.

The package itself owns no cache semantics. Hosts may cache derived Decisions or loaded Rules only if their invalidation strategy preserves canonical Rule mutations and Decision correctness.

Audit actor identity (`created_by`, admin/user identity, etc.) is not an Eligibility domain requirement. A Host may compose external auditing without making Eligibility depend on a User/Admin entity.

## Host responsibilities

The Host owns:

- defining stable `subject_type` keys;
- converting external Subject identities to canonical strings before entering Eligibility;
- providing validated external Subject identities;
- defining stable `dimension_key` semantics;
- resolving and semantically normalizing Context values before entering Eligibility;
- validating external Subject and Context identities against Host-owned registries where required;
- deciding which Context dimensions are mandatory for a particular application flow;
- deciding which domains call Eligibility and at what application boundary;
- combining Eligibility with intrinsic Product/Category/Payment/Shipping lifecycle rules;
- defining composite behavior across multiple Subjects when needed;
- owning domain search, filtering, sorting, and pagination;
- mapping machine-readable Eligibility Decisions to API/UI behavior and translated messages;
- owning the outer transaction and its commit/rollback when an Eligibility mutation participates in a larger Host operation; the package owns a transaction only when no outer transaction is active, according to the transaction contract above;
- tenant/storage isolation where the Host is multi-tenant;
- cleaning Eligibility Rules when an external Subject is permanently deleted.

## Persistence dependency and transaction ownership

Eligibility declares `maatify/persistence ^1.4` as an explicit runtime
dependency. `v1.4.0` is the minimum stable line required for the released
`SavepointTransactionRunnerInterface` and `PdoSavepointTransactionRunner`.
Eligibility uses that shared runner for replacement and cleanup; it does not
implement a local generic transaction or savepoint engine.

## Consumer workflow and integration boundary

The canonical consumer path is:

```text
Host Input
  → Public Eligibility API
  → Domain Service
  → Integration Boundary
  → Observable Result
```

This workflow is normative at the responsibility and observable-behavior level. Concrete PHP class names, method signatures, factories, and dependency-wiring details remain implementation/contracts-slice decisions and MUST NOT be invented here.

1. The Host validates the external Subject and resolves its business Context. It constructs the canonical typed Subject and immutable Context using the exact string rules and Context shape defined in this reference. Host-owned semantic normalization, such as choosing an uppercase country code, occurs before the package boundary.
2. The Host calls the public Eligibility API for one Subject or an ordered batch of Subjects. The public operation is conceptually `decide(Subject, Context)` or `decideMany(Subjects, Context)`; these labels describe the frozen capability and do not freeze concrete PHP names.
3. The package-owned Domain Service orchestrates evaluation. It applies the canonical rule semantics, requests active Rules only through `ActiveRuleReaderInterface`, and uses bounded bulk loading for the batch path. It does not query or join Host-owned Subject, Product, Category, Payment, Shipping, Customer, or geography tables.
4. The Integration Boundary consists of the package-owned command, management-query, evaluation-read, and internal mutation-support contracts backed by the direct-PDO RC1 adapters, plus the released Persistence transaction runner. The Host constructs `PdoRuleCommandRepository`, `PdoRuleManagementQuery`, `PdoActiveRuleReader`, and `PdoSavepointTransactionRunner` from the same PDO connection and wires each required capability explicitly to `EligibilityManagementService` or `EligibilityEvaluationService`. Together they preserve exact validated strings, active/inactive lifecycle state, natural-identity uniqueness, canonical ordering, transaction participation, and the concurrency guarantees above. Repository/interface substitution MUST NOT be used to introduce a non-PDO RC1 persistence implementation.
5. The Host receives a typed immutable `EligibilityDecision` (or an ordered collection of typed Subject Decisions for batch evaluation), including its machine-readable reason and complete dimension/matched-Rule traces. The Host then combines that Decision with its own domain lifecycle and visibility rules where applicable, for example `intrinsically visible AND eligible`; `eligible=true` MUST NOT be interpreted as Product, Category, Payment Method, Shipping Method, or other Host-domain publication/availability.

Rule management follows the same boundary: the Host submits typed management commands/criteria through the public package contracts, the Domain Service coordinates the mutation or read, and the package-owned persistence boundary produces the typed management result or documented typed failure. Application/domain code MUST NOT require direct SQL access.

The Consumer Verification Harness required by the adopted Testing and CI Standards is an RC1 readiness gate for a later implementation/readiness slice. It MUST exercise this external-consumer workflow through Composer production autoload and the public contracts in clean, repeatable consumer states, including the real persistence boundary when applicable. This Standards Decision Alignment pass freezes the workflow contract only; it does not implement or design the Harness scripts, fixtures, database setup, or CI job.

## Public Runtime API inventory

This inventory records the public Runtime API currently implemented across B1–B4.
It is an inventory of the code at this branch. B1 owns the model and validation
types; B2 owns commands, interfaces, results, and semantic exceptions; B3 owns
the concrete direct-PDO persistence implementation and the canonical-bound
extensions; B4 owns the concrete evaluator and application-service runtime.

### B1 model and validation types

- `Maatify\Eligibility\Common\Validation\CanonicalString::validate(mixed $value, string $field): string` validates a generic canonical package string without transforming it. Its semantic bounded methods and constants are the single source of truth for the four RC1 canonical component limits; canonical ordering remains intentionally generic and unbounded.
- `Maatify\Eligibility\Common\Value\Subject` represents `subjectType` and `subjectId`.
- `Maatify\Eligibility\Evaluation\Value\ContextValue`, `ContextValueCollection`, `ContextDimension`, and `Context` represent the immutable Context shape. `Context` exposes `getDimension(mixed $dimensionKey): ?ContextDimension` and `hasDimension(mixed $dimensionKey): bool`.
- `Maatify\Eligibility\Rule\Rule`, `RuleIdentity`, `RuleCollection`, `RuleEffectEnum`, and `RuleLifecycleEnum` represent typed Rules, natural identity, effects, lifecycle, and canonical Rule collections. `RuleCollection` exposes active and inactive lifecycle state through each returned `Rule`.
- `Maatify\Eligibility\Evaluation\Decision\EligibilityDecision`, `DimensionOutcome`, `DimensionOutcomeCollection`, `RuleReference`, `RuleReferenceCollection`, `DecisionReasonEnum`, and `DimensionReasonEnum` represent immutable typed Decisions and complete matched-Rule traces.
- `Maatify\Eligibility\Exception\EligibilityExceptionInterface` is the single package marker. `InvalidEligibilityInputException` is the B1 typed validation error and uses the shared `maatify/exceptions` hierarchy.

### B2 commands and query contracts

- `Maatify\Eligibility\Common\Value\SubjectCollection` is the ordered, duplicate-free B2 batch value. It accepts an empty batch and exposes `count()`, iteration, `items(): list<Subject>`, and JSON serialization.
- `CreateRuleCommand(Subject $subject, mixed $dimensionKey, mixed $dimensionValue, RuleEffectEnum $effect)` represents creation of a new active Rule. It has no initial lifecycle input.
- `UpdateRuleEffectCommand(RuleIdentity $identity, RuleEffectEnum $effect)` represents an effect mutation.
- `DeactivateRuleCommand(RuleIdentity $identity)` and `ReactivateRuleCommand(RuleIdentity $identity)` represent explicit lifecycle mutations.
- `DesiredRule(mixed $dimensionValue, RuleEffectEnum $effect)` represents one desired active value/effect pair without lifecycle state. `DesiredRuleCollection` rejects duplicate dimension values, orders values canonically, and accepts an empty set.
- `ReplaceDimensionRulesCommand(Subject $subject, mixed $dimensionKey, DesiredRuleCollection $desiredRules)` represents complete desired active-set replacement intent.
- `CleanupSubjectCommand(Subject $subject)` represents idempotent Subject cleanup intent.
- `RuleCriteria(Subject $subject, mixed $dimensionKey = null, ?RuleLifecycleEnum $lifecycle = null, mixed $maxResults = 100)` represents a bounded management read. A dimension filter is optional, lifecycle filtering is optional, and `maxResults` must be an integer from `1` through `500`. This is a bounded read limit, not Host-global pagination or search.
- `ActiveDimensionKeysQuery(Subject $subject)` represents active-dimension introspection for one Subject.

### B2 typed results and service boundaries

- `ActiveDimensionKeyCollection` contains only canonical dimension-key strings, rejects duplicates, and returns them in ascending bytewise order.
- `SubjectDecisionResult` associates one `Subject` with one `EligibilityDecision`. `SubjectDecisionCollection` rejects duplicate Subject identities, accepts an empty result, and preserves the supplied result order.
- `EligibilityEvaluationServiceInterface` exposes `decide(Subject $subject, Context $context): EligibilityDecision` and `decideMany(SubjectCollection $subjects, Context $context): SubjectDecisionCollection`. The interface defines the public evaluation seam; B2 does not implement the evaluator.
- `EligibilityManagementServiceInterface` exposes typed Rule creation, identity inspection, bounded Rule inspection, active-dimension inspection, effect/lifecycle mutations, replacement intent, and Subject cleanup. Its state-setting methods return `void` except `createRule(...): Rule`; `inspectRule(...): Rule` has a typed Rule-not-found contract.
- `RuleCommandRepositoryInterface` is the replaceable command/mutation persistence contract. It exposes only canonical Rule creation, effect/lifecycle mutations, and Subject cleanup.
- `RuleManagementQueryInterface` is the replaceable management-query persistence contract. It exposes natural-identity lookup, bounded management reads, and active-dimension lookup, including inactive Rules where criteria allow them.
- `ActiveRuleReaderInterface` is the replaceable evaluation-read persistence contract. It exposes only bounded bulk loading of active Rules for a supplied `SubjectCollection`.
- `RuleMutationSupportInterface` is a package-internal Eligibility-specific persistence contract. It owns only the coordination lock, complete Subject + dimension mutation read, and coordination cleanup required by atomic replacement and cleanup; it is not a Management Query or Evaluation Read contract.

### B4 concrete runtime services

- `Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationService` implements `EligibilityEvaluationServiceInterface` and depends only on `ActiveRuleReaderInterface`. It loads active Rules through one reader bulk call for a batch and delegates both single and batch calls to the shared pure `Maatify\Eligibility\Evaluation\Engine\EligibilityRuleEvaluator`; `PdoActiveRuleReader` internally chunks large Subject collections at its configured bound, and the service preserves input order.
- `Maatify\Eligibility\Management\Service\EligibilityManagementService` implements `EligibilityManagementServiceInterface`. It maps missing identity mutation results to `RuleNotFoundException` and coordinates create, inspect, lifecycle/effect, replacement, and cleanup behavior without SQL. Its dependencies are explicit: `RuleCommandRepositoryInterface` for command mutations, `RuleManagementQueryInterface` for management reads, `RuleMutationSupportInterface` for atomic replacement/cleanup support, and `Maatify\Persistence\Pdo\Transaction\SavepointTransactionRunnerInterface` for shared transaction/savepoint execution.
- `Maatify\Eligibility\Evaluation\Engine\EligibilityRuleEvaluator` is package-internal shared evaluation logic. It groups active Rules by dimension, evaluates every ruled dimension, applies DENY precedence, preserves the complete matching trace, and constructs the existing immutable Decision types in canonical order.

### B2 semantic exceptions

- `RuleNotFoundException` extends the shared `ResourceNotFoundMaatifyException` hierarchy and identifies the requested `RuleIdentity`.
- `RuleIdentityConflictException` extends the shared `GenericConflictMaatifyException` hierarchy and identifies a conflicting natural identity.
- `RuleConcurrencyConflictException` extends the shared `GenericConflictMaatifyException` hierarchy for an unresolved Rule uniqueness/concurrency condition.

All three B2 semantic exceptions implement `EligibilityExceptionInterface`. B3
classifies only proven MySQL/MariaDB duplicate-key driver code `1062` at the
repository boundary; other PDO/storage failures propagate unchanged. No concrete
B2 evaluator or application service is claimed by this inventory; the concrete
PDO persistence adapter is listed separately below.

### B3 persistence implementation and bounds extensions

- `Maatify\Eligibility\Rule\Repository\PdoRuleCommandRepository` is the concrete direct-PDO implementation of `RuleCommandRepositoryInterface` and `RuleMutationSupportInterface`. It provides both Eligibility-specific capabilities over the same PDO connection without owning generic transaction/savepoint mechanics.
- `Maatify\Eligibility\Rule\Repository\PdoRuleManagementQuery` is the concrete direct-PDO implementation of `RuleManagementQueryInterface`. It provides exact identity reads, bounded management reads, lifecycle visibility, and active-dimension reads.
- `Maatify\Eligibility\Rule\Repository\PdoActiveRuleReader` is the concrete direct-PDO implementation of `ActiveRuleReaderInterface`. It provides the active-only bounded bulk read used by evaluation.
- `PdoRuleHydrationTrait` is an internal implementation helper for shared PDO row binding and Rule hydration; it is not a public contract or business-service abstraction.
- `Maatify\Eligibility\Rule\Repository\RuleMutationSupportInterface` is a package-internal mutation-support contract used by B4 for the complete Subject + dimension read, Subject coordination lock, and coordination cleanup; it is not an additional Host-facing service method.
- B4 uses the package-owned `maa_eligibility_subject_locks` table as an explicit coordination row per Subject. Replacement and management cleanup create-or-lock this row inside their transaction before reading or mutating Rules, so an initially empty dimension is serialized without relying on database gap-lock behavior. Cleanup removes the coordination row for the cleaned Subject. The table has no Host foreign key or join.
- When the Host already owns a transaction, the shared `PdoSavepointTransactionRunner` creates an operation-local savepoint, releases it on success, and rolls back to it on failure while leaving the Host transaction active. Savepoint cleanup is best-effort and never replaces the original operation Throwable. This uses transactional MySQL-compatible savepoint capability without declaring a minimum database product version.
- B3 extends `Maatify\Eligibility\Common\Validation\CanonicalString` with the single source of truth for the four canonical byte bounds and routes every semantic B1/B2 boundary through those bounded validators. The B1 validation type remains B1-owned; these bound constants and validators are the B3 contract extension.
- B4 supplies the concrete evaluator and application-service implementations listed above; the B2 interfaces remain the public replaceable seams for consumers and persistence adapters.

## RC1 exclusions

The following are explicitly outside the first RC scope unless a later documented decision adds them before implementation freeze:

- arbitrary executable expressions;
- nested boolean Rule trees;
- scripting or persisted callback Rules;
- wildcard Subject or dimension matching;
- numeric comparison/range operators;
- price/amount calculation;
- inventory checks;
- time-window scheduling;
- Rule priority/scoring/weights;
- automatic Product/Category inheritance;
- automatic Category ancestor traversal;
- automatic geography hierarchy expansion;
- automatic Customer/segment lookup;
- authorization/RBAC/permissions;
- framework/HTTP/Admin UI integration;
- cross-domain foreign keys;
- implicit fallback between dimension values;
- a global Subject registry;
- global eligible/ineligible Host-entity discovery;
- built-in tenant identity or tenant routing.

These exclusions keep RC1 a focused Eligibility engine rather than an unbounded generic business-rules platform.

## Canonical acceptance scenarios

Before a persistence adapter or Release Candidate can be considered correct, executable tests MUST cover at least the following behavioral classes using the canonical semantics above:

1. no active Rules -> `UNRESTRICTED`, `eligible=true`, empty `dimensionOutcomes`;
2. a Decision cannot represent `UNRESTRICTED` with outcomes, `ELIGIBLE` with a failed outcome, or `DENIED` with all outcomes passing;
3. one matching ALLOW -> eligible;
4. ALLOW exists but supplied value does not match -> denied;
5. ALLOW exists but Context dimension is absent -> `ALLOW_LIST_CONTEXT_MISSING`;
6. DENY-only with non-matching Context -> `PASSED_DENY_LIST`;
7. DENY-only with matching Context -> `DENIED_BY_RULE`;
8. DENY-only with missing Context -> `PASSED_DENY_LIST_CONTEXT_MISSING`;
9. a duplicate `dimension_key` in one Context is rejected;
10. a present Context dimension with zero values is rejected;
11. duplicate values inside one Context dimension are rejected;
12. an entirely empty Context is valid;
13. multiple Context values where one ALLOW matches -> passes;
14. multiple Context values where multiple ALLOW Rules match -> every matching ALLOW appears in `matchedRules` in canonical order;
15. ALLOW and DENY values both match in one dimension -> denied and `matchedRules` contains every matching active Rule of both effects;
16. multiple dimensions where all pass -> eligible;
17. multiple dimensions where more than one fails -> Decision contains every ruled dimension in canonical order and every failed dimension is visible;
18. inactive Rules do not participate;
19. changing Rule effect changes the same natural Rule identity rather than creating a duplicate;
20. individual create creates a new Rule active;
21. individual create does not accept an initial inactive state;
22. create against any existing natural identity, active or inactive, is rejected through a typed conflict;
23. updating effect on an inactive Rule preserves its inactive state;
24. deactivate/reactivate preserve the current Rule effect;
25. deactivate of an existing inactive Rule is idempotent success;
26. reactivate of an existing active Rule is idempotent success;
27. update-effect to the current effect is idempotent success;
28. deactivate/reactivate/update-effect of a missing Rule returns typed not-found;
29. management reads can return inactive Rules and expose lifecycle state explicitly;
30. lifecycle-filtered management reads distinguish active and inactive Rules without changing evaluator behavior;
31. canonical string identity prevents scalar-type coercion from creating alternate equality semantics;
32. malformed UTF-8 is rejected;
33. leading/trailing whitespace is rejected rather than trimmed;
34. exact matching does not collapse differently cased or differently Unicode-encoded strings;
35. extra Context dimensions with no active Rules are ignored;
36. single and batch evaluation return equivalent Decisions for the same Subject/Context pair;
37. duplicate Subjects in a batch are rejected;
38. batch result order matches accepted input Subject order;
39. empty batch returns an empty result;
40. batch evaluation does not require a persistence query per Subject as its canonical path;
41. `replaceDimensionRules()` reactivates/reuses existing identities, updates effects, creates missing Rules, and deactivates omitted active Rules atomically;
42. repeating the same `replaceDimensionRules()` desired set is idempotent;
43. empty dimension replacement deactivates all active Rules in that dimension without hard-deleting them;
44. Subject cleanup physically removes all Rules for the supplied Subject without touching other Subjects;
45. Subject cleanup for a Subject with no Rules is idempotent success;
46. active-dimension, Rule, matched-Rule, and Decision collections obey canonical package ordering independent of database row order/collation;
47. post-pagination `decideMany()` filtering is not treated as eligibility-correct global pagination behavior.
48. an unknown or external `PDOException`/`Throwable` propagates unchanged, while a documented known semantic storage condition is converted to a package exception that preserves the original as `previous` where supported;
49. `replaceDimensionRules()` called inside a Host-owned outer transaction participates without committing or rolling back that transaction, and the Host's commit or rollback determines durability;
50. a package-owned multi-step mutation commits only a complete successful state, attempts rollback only while its transaction is active after failure, and rethrows the original `Throwable` unless an explicitly documented semantic conversion applies;
51. concurrent creates for one natural Rule identity result in exactly one persistent Rule, with the competing operation returning the typed natural-identity or concurrency/uniqueness conflict rather than creating a duplicate;
52. concurrent `replaceDimensionRules()` operations preserve natural-identity uniqueness and complete-dimension atomicity, while evaluation and management reads observe either a coherent committed state before or after the replacement and never a mixed intermediate state.

These scenarios are the minimum golden behavioral suite, not an exhaustive test list.

## Architectural target

The intended ecosystem relationship is:

```text
Category ─────────────┐
Product ──────────────┤
Payment Method ───────┤
Shipping ─────────────┤      Host resolves Context
Promotion ────────────┘               │
                                      ▼
                              maatify/php-eligibility
                                      │
                                      ▼
                              EligibilityDecision
```

Owning domains remain independently reusable. Eligibility remains independently reusable. The Host composes them.

## RC1 success condition

The first Release Candidate is successful only when a Host can install one framework-neutral package and use the same stable typed model to manage and evaluate customer/country-style business Eligibility for multiple unrelated Subject types without adding domain-specific restriction tables or coupling those domains to one another.

RC1 success additionally requires:

- canonical valid-UTF-8 string identity and exact matching semantics implemented consistently;
- canonical Context shape invariants enforced;
- canonical Rule identity and lifecycle invariants implemented and enforced;
- individual Rule creation starts active and remains distinct from reactivation;
- lifecycle/effect mutations preserve orthogonal state as frozen above;
- idempotent state-setting lifecycle behavior implemented as frozen above;
- management reads expose lifecycle state and can retrieve inactive Rules;
- typed immutable Decisions with mutually consistent state invariants;
- stable machine-readable reasons;
- complete deterministic dimension and matched-Rule traces;
- canonical public ordering independent of database ordering/collation;
- single and batch evaluation with equivalent semantics;
- duplicate-safe deterministic batch contracts;
- an atomic, idempotent dimension-replacement management operation with the frozen active-set semantics;
- official idempotent Subject hard-cleanup capability;
- no required direct SQL from consuming application/domain code;
- executable golden tests covering the canonical edge cases;
- a persistence adapter that preserves package semantics without N+1 as the canonical batch path;
- real Product/Category/Payment/Shipping-style Host integrations remaining decoupled from Eligibility internals.

The package must remain small enough to reason about, strict enough to produce deterministic Decisions, and generic enough to replace repeated restriction subsystems such as shipping-provider-by-country restrictions in future projects without becoming a general-purpose rule engine.
