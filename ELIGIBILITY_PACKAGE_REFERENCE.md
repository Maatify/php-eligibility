# Maatify Eligibility — Canonical Package Reference

## Status

This document is the canonical RC1 design and behavioral contract for `maatify/php-eligibility`.

The RC1 implementation MUST conform to the boundaries, invariants, decision semantics, lifecycle rules, and operational contracts defined here. Changes to these semantics require an explicit documentation decision before implementation changes are accepted.

This document intentionally freezes behavior without prematurely freezing internal class names, table names, or framework-specific wiring.

## Purpose

`maatify/php-eligibility` is a framework-neutral package for answering one reusable business question:

> Is a given external subject eligible in the supplied business context?

The package exists to prevent Product, Category, Payment Method, Shipping, Promotion, and other domains from each inventing their own customer/country restriction subsystem.

Typical examples include:

- whether a Product is available to a customer type;
- whether a Category is available in a country;
- whether a Payment Method is available to a customer type or country;
- whether a Shipping Method or Shipping Provider is available in a country;
- whether a Promotion is available to a customer segment;
- equivalent future eligibility decisions that fit the same subject/context rule model.

Eligibility is a reusable business-policy domain. It is not owned by Category, Product, Shipping, Payment, Customer, Geo, HTTP, Admin, or any presentation layer.

## Core model

The canonical model is:

```text
Subject + Context + Rules -> Eligibility Decision
```

The package knows external identities only. It does not need to know the database model, lifecycle, or implementation of the domains that own those identities.

### Subject

A Subject is the thing whose eligibility is being evaluated.

Conceptually:

```text
subject_type + subject_id
```

Examples:

```text
product + 150
category + 25
payment_method + tap
shipping_method + dhl_express
shipping_provider + aramex
promotion + summer-2026
```

`subject_type` is a stable Host-defined domain key.

`subject_id` is an opaque external scalar identity. Public contracts MUST treat it as an opaque value rather than assuming numeric identity. A Host may use a database ID, UUID, stable code, or another validated scalar identity.

The package MUST NOT create foreign keys from Eligibility storage to Product, Category, Payment, Shipping, Customer, Geo, or other Host-owned tables.

The Host owns validation that the referenced external Subject actually exists and remains meaningful.

### Context dimension

A Context Dimension describes one business dimension against which a Subject may be restricted.

Conceptually:

```text
dimension_key + one-or-more context values
```

Examples:

```text
country = EG
customer_type = retail
customer_type = wholesale
customer_segment = vip
sales_channel = mobile_app
```

Dimension keys and their semantic meanings are Host-defined. Eligibility owns only generic rule and evaluation behavior.

The package MUST NOT contain a hardcoded enum of every possible business dimension.

This allows future projects to introduce a dimension without changing Product, Category, Shipping, Payment, or Eligibility core behavior.

### Multi-value Context semantics

A Context may contain multiple values for the same dimension when the Host domain requires it. For example, a customer may belong to multiple segments.

For RC1:

- Context values for one dimension form a semantic set;
- duplicate values for the same dimension are invalid input rather than silently deduplicated;
- order of values has no effect on eligibility;
- public contracts MUST use typed DTOs/value objects/collections rather than associative arrays;
- extra Context dimensions for which the Subject has no active rules are ignored;
- a matching DENY for any supplied value denies that dimension;
- when ALLOW rules exist, at least one supplied value matching an ALLOW is sufficient unless a DENY also matches.

Example:

```text
Rules:
country:EG allow

Context values:
EG, SA

Decision for country: passes because at least one Context value satisfies the allow-list.
```

### Rule

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

Rules are exact. RC1 has no implicit wildcard, fuzzy match, range expression, script, callback, or executable expression language.

## Canonical identity, matching, and normalization

### Rule natural identity

The canonical natural identity of one Rule is:

```text
subject_type
+ subject_id
+ dimension_key
+ dimension_value
```

`effect` is NOT part of the natural identity.

Therefore the following state is invalid and MUST NOT be representable as two independent rules:

```text
product:150 country:EG allow
product:150 country:EG deny
```

Changing `allow` to `deny`, or `deny` to `allow`, is a mutation of the same Rule identity.

Persistence and management contracts MUST enforce this invariant and MUST surface conflicts through typed package/domain errors rather than leaking raw PDO/database uniqueness errors.

### Exact matching

Eligibility performs exact matching on the canonical values supplied to it.

The package MUST NOT silently trim, lowercase, uppercase, transliterate, or otherwise semantically normalize Subject or Context values.

Consequently:

```text
EG != eg
" EG " != EG
```

unless the Host normalized those values before they reached Eligibility.

Empty and whitespace-only structural keys/values MUST be rejected by package validation.

The Host owns semantic normalization such as deciding that country codes are uppercase ISO codes.

Database collation MUST NOT change package matching semantics. The persistence implementation MUST preserve the same exact comparison semantics defined by the package regardless of database defaults.

Exact maximum lengths and storage column sizes are schema-slice decisions, but the schema MUST define explicit bounded limits before RC1 implementation is accepted. Those limits MUST not introduce silent truncation.

## Canonical Rule lifecycle

RC1 uses an explicit active/inactive Rule lifecycle.

A Rule's natural identity remains unique regardless of active state. Deactivation does not create a second identity and reactivation does not create a new conflicting Rule.

Canonical lifecycle behavior:

- active Rules participate in evaluation;
- inactive Rules are ignored completely by evaluation;
- a Rule may be deactivated;
- an inactive Rule may be reactivated if its identity is still valid;
- the effect of an existing Rule may be changed as a mutation of that Rule;
- creating a second Rule with the same natural identity is a typed conflict;
- lifecycle operations MUST remain concurrency-safe around this uniqueness invariant.

RC1 MUST NOT rely on duplicate active rows, effect-specific duplicates, or ambiguous restore behavior.

Physical cleanup of all Eligibility data for a deleted external Subject is a separate management operation and does not change the evaluation semantics above.

## Canonical evaluation semantics

Eligibility decisions MUST be deterministic.

### 1. No active rules means unrestricted

If a Subject has no active Rules, it is eligible from the Eligibility package's point of view.

This means only:

> Eligibility imposes no restriction on this Subject.

It does NOT mean that the owning domain has enabled, published, activated, or otherwise made the Subject available.

A Product may still be inactive, a Category may still be hidden, a Payment Method may still be disabled, and a Shipping Method may still be unavailable for intrinsic domain reasons.

Eligibility MUST NOT be used as the owning domain's master enable/disable switch.

Removing or deactivating the last Rule changes Eligibility state to unrestricted; Hosts and Admin integrations MUST account for this explicitly.

### 2. Rules are evaluated per dimension

Active Rules for the same Subject are grouped by `dimension_key`.

Each dimension is evaluated independently against the values supplied for that dimension in the Context.

Inactive Rules do not participate.

### 3. DENY always wins within a dimension

If any active `deny` Rule for a dimension matches any supplied Context value for that dimension, the dimension fails.

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

The evaluator may short-circuit further matching work inside that already-denied dimension, but overall RC1 decision construction MUST still evaluate the remaining ruled dimensions so the final Decision contains a complete deterministic trace.

### 4. ALLOW rules create an allow-list for that dimension

If at least one active `allow` Rule exists for a dimension, and no matching DENY exists, at least one supplied Context value for that dimension MUST match an ALLOW Rule.

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

### 5. DENY-only rules behave as a deny-list

If a dimension has active DENY Rules but no ALLOW Rules, the dimension passes unless a DENY value matches.

Example:

```text
Rules:
country:IL deny

Context:
country:EG

Decision: passes the country dimension.
```

### 6. Missing Context values are explicit

If the Subject has ALLOW Rules for a dimension but the supplied Context contains no value for that dimension, the dimension fails because the allow-list cannot be satisfied.

If the Subject has DENY-only Rules for a dimension and the supplied Context contains no value for that dimension, no DENY Rule matches and the dimension passes.

This second case is intentionally fail-open from Eligibility's perspective and MUST NOT be silently changed by an implementation.

The Host is responsible for deciding which Context dimensions it is required to resolve before asking Eligibility for a decision.

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

- OR across allowed Context values within one dimension;
- AND across dimensions;
- DENY precedence within a dimension.

More complex policy composition requires an explicit future package design rather than hidden special cases.

## Canonical Decision contract

The public decision contract MUST be typed and immutable. A boolean-only public result is insufficient for RC1.

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
- dimensionKey
- passed: bool
- reasonCode: DimensionReason
- matchedRuleId: optional Rule identity
```

The Decision MUST represent all ruled dimensions rather than stopping after the first failed dimension.

Dimension outcomes MUST have package-defined deterministic ordering independent of database default ordering/collation.

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
DENIED_BY_RULE
ALLOW_LIST_UNSATISFIED
ALLOW_LIST_CONTEXT_MISSING
```

Meaning:

- `UNRESTRICTED`: the Subject has no active Rules;
- `ELIGIBLE`: the Subject has active Rules and all ruled dimensions passed;
- `DENIED`: one or more ruled dimensions failed;
- `PASSED_ALLOW_LIST`: an ALLOW requirement was satisfied and no DENY matched;
- `PASSED_DENY_LIST`: the dimension has DENY-only Rules and none matched;
- `DENIED_BY_RULE`: a matching DENY Rule caused failure;
- `ALLOW_LIST_UNSATISFIED`: Context values were supplied but none matched an existing ALLOW requirement;
- `ALLOW_LIST_CONTEXT_MISSING`: ALLOW Rules exist but the Context supplied no value for that dimension.

Exact PHP enum/class names are implementation-stage naming decisions, but these semantics are canonical and MUST remain machine-stable for RC1.

Human-facing translated messages are not owned by Eligibility. The Host maps machine-readable decisions to customer/admin/API presentation.

## Single and batch evaluation

RC1 MUST support both single-Subject and batch evaluation.

Conceptually:

```text
decide(Subject, Context) -> EligibilityDecision

decideMany(list<Subject>, Context)
    -> deterministic collection/map of Subject -> EligibilityDecision
```

The single and batch paths MUST use identical evaluation semantics and produce equivalent decisions for the same Subject/Context pair.

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

If a future project requires eligibility-aware SQL/search pagination across a very large Host dataset, that requires an explicit integration design such as a projection, query adapter, or search index. It is not silently folded into the RC1 core.

## Management responsibilities

RC1 MUST provide typed application contracts sufficient for a Host to manage Eligibility Rules without direct SQL access from application/domain code.

The management surface MUST support the capability to:

- create a Rule for an external Subject and exact dimension value;
- inspect Rules for a Subject;
- inspect/filter Rules by dimension using bounded reads;
- inspect the active dimension keys governing a Subject;
- update an existing Rule's effect;
- deactivate a Rule;
- reactivate an inactive Rule;
- replace all Rules for one Subject + dimension atomically;
- clean up all Eligibility Rules for one external Subject;
- evaluate one Subject through the canonical policy service;
- evaluate many supplied Subjects against one Context.

### Atomic dimension replacement

Administrative use cases commonly express a desired final set rather than a sequence of individual adds/removes, for example:

```text
product:150
country allow-list = {EG, KW, SA}
```

RC1 therefore MUST support a typed operation conceptually equivalent to:

```text
replaceDimensionRules(Subject, dimensionKey, desiredRules)
```

The operation MUST be atomic from the caller's perspective and preserve Rule natural-identity uniqueness throughout the mutation.

This prevents every Host from implementing its own unsafe "delete all then insert" synchronization routine.

The package MUST coordinate correctly with an existing Host transaction when the Host composes Eligibility mutation with a larger domain operation. Concrete transaction abstractions are implementation-stage decisions.

### Subject cleanup

Because Eligibility intentionally has no foreign keys to Host-owned Subjects, the Host requires an official cleanup path when an external Subject is permanently removed.

RC1 MUST therefore expose a typed operation conceptually equivalent to removing all Eligibility Rules owned by a supplied Subject identity.

This cleanup capability MUST NOT require the Eligibility package to query or understand the Host-owned Subject table.

### Typed errors

Management conflicts and invalid input MUST surface through typed package/domain errors rather than raw storage-driver exceptions.

RC1 error semantics MUST distinguish at least:

- invalid structural input;
- natural-identity conflict;
- requested Rule not found;
- concurrency/uniqueness conflict that could not be resolved safely.

Exact exception class names are implementation-stage decisions.

Public/domain contracts MUST remain typed and MUST NOT use associative arrays as their API model.

## Package boundaries

### Eligibility owns

Eligibility owns:

- generic Subject identity representation;
- generic Context dimension/value representation;
- ALLOW/DENY Rule representation;
- Rule natural-identity and lifecycle invariants;
- Rule persistence and management behavior;
- deterministic evaluation semantics;
- single and batch evaluation contracts;
- typed Decision and dimension-outcome results;
- stable machine-readable decision reasons;
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

is eligibility and belongs here.

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

## No automatic cross-subject inheritance

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

If a future use case genuinely requires tenant identity to participate in the business meaning of an Eligibility decision rather than storage isolation, that requires an explicit future design decision.

## Persistence principles for RC1

The final RC1 schema and adapter MUST preserve these principles:

- Eligibility-owned tables only;
- no foreign keys to Host-owned Subject or Context domains;
- one persistent natural Rule identity for `subject_type + subject_id + dimension_key + dimension_value`;
- `effect` is mutable state, not part of uniqueness;
- explicit active/inactive lifecycle;
- explicit typed ALLOW/DENY storage;
- exact matching independent of database default collation;
- bounded management reads;
- bulk Rule loading for supplied Subject sets;
- deterministic package-defined ordering for returned Rule and Decision collections;
- concurrency-safe mutation behavior where uniqueness/lifecycle invariants require it;
- typed conflict translation rather than raw driver errors;
- no dependency on a framework or HTTP runtime.

Exact table/column names, indexes, maximum lengths, timestamp fields, and adapter internals are implementation/schema-slice decisions, but they MUST be explicitly documented and verified before RC1 release readiness.

A reference PDO adapter is appropriate for RC1; Eligibility MUST NOT require Laravel, Doctrine, Slim, or another framework runtime.

The package itself owns no cache semantics. Hosts may cache derived Decisions or loaded Rules only if their invalidation strategy preserves canonical Rule mutations and Decision correctness.

Audit actor identity (`created_by`, admin/user identity, etc.) is not an Eligibility domain requirement. A Host may compose external auditing without making Eligibility depend on a User/Admin entity.

## Host responsibilities

The Host owns:

- defining stable `subject_type` keys;
- providing validated external Subject identities;
- defining stable `dimension_key` semantics;
- resolving and semantically normalizing Context values;
- validating external Subject and Context identities against Host-owned registries where required;
- deciding which Context dimensions are mandatory for a particular application flow;
- deciding which domains call Eligibility and at what application boundary;
- combining Eligibility with intrinsic Product/Category/Payment/Shipping lifecycle rules;
- defining composite behavior across multiple Subjects when needed;
- owning domain search, filtering, sorting, and pagination;
- mapping machine-readable Eligibility Decisions to API/UI behavior and translated messages;
- transaction orchestration when an Eligibility mutation participates in a larger Host operation;
- tenant/storage isolation where the Host is multi-tenant;
- cleaning Eligibility Rules when an external Subject is permanently deleted.

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
- built-in tenant identity or tenant routing;
- persisted executable expressions.

These exclusions keep RC1 a focused Eligibility engine rather than an unbounded generic business-rules platform.

## Canonical acceptance scenarios

Before a persistence adapter or release candidate can be considered correct, executable tests MUST cover at least the following behavioral classes using the canonical semantics above:

1. no active Rules -> unrestricted;
2. one matching ALLOW -> eligible;
3. ALLOW exists but supplied value does not match -> denied;
4. ALLOW exists but Context dimension is missing -> denied;
5. DENY-only with non-matching Context -> passes;
6. DENY-only with matching Context -> denied;
7. DENY-only with missing Context -> passes;
8. multiple Context values where one ALLOW matches -> passes;
9. multiple Context values where ALLOW and DENY values both match -> denied;
10. multiple dimensions where all pass -> eligible;
11. multiple dimensions where more than one fails -> Decision contains every failed dimension in deterministic order;
12. inactive Rules do not participate;
13. changing Rule effect changes the same natural Rule identity rather than creating a duplicate;
14. duplicate natural identity is rejected through a typed conflict;
15. single and batch evaluation return equivalent Decisions for the same Subject/Context pair;
16. extra Context dimensions with no active Rules are ignored;
17. exact matching does not collapse differently cased or padded values;
18. batch evaluation does not require a persistence query per Subject as its canonical path.

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

- canonical Rule identity and lifecycle invariants implemented and enforced;
- exact matching and structural validation semantics implemented consistently;
- typed immutable Decisions with stable machine-readable reasons;
- complete deterministic dimension traces;
- single and batch evaluation with equivalent semantics;
- an atomic dimension-replacement management operation;
- official Subject cleanup capability;
- no required direct SQL from consuming application/domain code;
- executable golden tests covering the canonical edge cases;
- a persistence adapter that preserves the package semantics without N+1 as the canonical batch path;
- real Product/Category/Payment/Shipping-style Host integrations remaining decoupled from Eligibility internals.

The package must remain small enough to reason about, strict enough to produce deterministic Decisions, and generic enough to replace repeated restriction subsystems such as shipping-provider-by-country restrictions in future projects without becoming a general-purpose rule engine.