# Maatify Eligibility — Canonical Package Reference

## Status

This document defines the canonical design intent for the first Release Candidate of `maatify/php-eligibility`.

The RC1 implementation MUST conform to the boundaries and decision semantics defined here. Changes to these semantics require an explicit documentation decision before implementation changes are accepted.

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

`subject_id` is an opaque external identity. The Eligibility package MUST NOT assume that all subject identities are numeric. A Host may use a database ID, UUID, stable code, or another validated scalar identity.

The package MUST NOT create foreign keys from Eligibility storage to Product, Category, Payment, Shipping, Customer, Geo, or other Host-owned tables.

The Host owns validation that the referenced external subject actually exists and remains meaningful.

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

Dimension keys and their semantic meanings are Host-defined. Eligibility owns only the generic rule/evaluation behavior.

The package MUST NOT contain a hardcoded enum of every possible business dimension.

This allows future projects to introduce a dimension without changing Product, Category, Shipping, Payment, or the Eligibility core itself.

A context may contain multiple values for the same dimension when the Host domain requires it. For example, a customer may belong to multiple segments. Public contracts MUST represent this using typed DTOs/collections rather than associative arrays.

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
product:150          country:EG                allow
product:150          country:IL                deny
category:25          customer_type:wholesale   deny
payment_method:tap   country:KW                allow
shipping_provider:aramex country:EG            deny
```

Rules are exact. RC1 has no implicit wildcard, fuzzy match, range expression, script, or executable expression language.

## Canonical evaluation semantics

Eligibility decisions MUST be deterministic.

### 1. No rules means unrestricted

If a Subject has no active rules, it is eligible from the Eligibility package's point of view.

This package does not replace the owning domain's own lifecycle rules. A Product may still be inactive, a Category may still be hidden, or a Payment Method may still be disabled even when Eligibility returns eligible.

### 2. Rules are evaluated per dimension

Rules for the same Subject are grouped by `dimension_key`.

Each dimension is evaluated independently against the values supplied for that dimension in the Context.

### 3. DENY always wins within a dimension

If any active `deny` rule for a dimension matches any supplied Context value for that dimension, the dimension fails immediately.

A matching DENY overrides matching ALLOW rules.

Example:

```text
Rules:
customer_segment:vip      allow
customer_segment:blocked  deny

Context values:
vip, blocked

Decision for this dimension: denied
```

### 4. ALLOW rules create an allow-list for that dimension

If at least one active `allow` rule exists for a dimension, and no matching DENY exists, at least one supplied Context value for that dimension MUST match an ALLOW rule.

Otherwise that dimension fails.

Example:

```text
Rules:
country:EG allow
country:KW allow

Context:
country:SA

Decision: denied because the subject has a country allow-list and SA is not in it.
```

### 5. DENY-only rules behave as a deny-list

If a dimension has active DENY rules but no ALLOW rules, the dimension passes unless a DENY value matches.

Example:

```text
Rules:
country:IL deny

Context:
country:EG

Decision: passes the country dimension.
```

### 6. Missing Context values are explicit

If the Subject has ALLOW rules for a dimension but the supplied Context contains no value for that dimension, the dimension fails because the allow-list cannot be satisfied.

If the Subject has DENY-only rules for a dimension and the supplied Context contains no value for that dimension, no DENY rule matches and the dimension passes.

The Host is responsible for deciding which Context dimensions it is required to resolve before asking Eligibility for a decision.

### 7. Different dimensions combine with AND

A Subject is eligible only when every dimension that has active rules passes.

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

The RC1 model intentionally stays deterministic and bounded: OR within allowed values of one dimension, AND across dimensions, with DENY precedence.

More complex policy composition requires an explicit future package design rather than hidden special cases.

## Decision result

The public decision contract SHOULD expose a typed immutable result rather than only a boolean.

At minimum, a decision must make it possible for the caller to know:

- whether the Subject is eligible;
- whether denial was caused by a matching DENY rule or by an unsatisfied ALLOW requirement;
- the relevant dimension when a rule caused the decision;
- the matched rule identity when one exists.

Human-facing translated messages are not owned by Eligibility.

The package returns machine-usable decision information; the Host decides how to present it to customers, administrators, logs, or APIs.

## Management responsibilities

RC1 must provide typed application contracts sufficient for a Host to manage Eligibility rules without direct SQL access from application/domain code.

The management surface must support the lifecycle required to:

- create an ALLOW or DENY rule for an external Subject and exact dimension value;
- inspect rules for a Subject;
- inspect/filter rules by dimension when required;
- remove or deactivate a rule without creating ambiguous duplicate active rules;
- restore/re-enable a rule if the final persistence lifecycle selected for RC1 supports reversible removal;
- evaluate a Subject through the canonical policy service.

Exact class names, repository split, lifecycle representation, and schema are implementation-stage decisions, but they MUST preserve the semantics in this reference.

Public/domain contracts must remain typed and MUST NOT use associative arrays as their API model.

## Package boundaries

### Eligibility owns

Eligibility owns:

- generic Subject identity representation;
- generic Context dimension/value representation;
- ALLOW/DENY rule representation;
- rule persistence and management behavior;
- deterministic evaluation semantics;
- typed decision results;
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
- automatic joins or foreign keys to Host domain tables.

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

Likewise, this package must not be used as a replacement for AdminKernel permissions, route guards, authentication, or security policy.

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

Eligibility does not infer that rules on one Subject automatically apply to another Subject.

For example, a restriction on:

```text
category:25
```

must not silently become a restriction on every Product assigned to Category 25 unless the Host explicitly defines that business behavior.

Likewise, the package does not know Product-to-Category membership or Category ancestry.

A Host that requires composite behavior may evaluate all relevant Subjects and combine the decisions according to its own documented policy.

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

This means future projects SHOULD NOT need to create a dedicated `ShippingProviderRestriction` persistence/domain subsystem merely to express this rule.

Shipping itself still owns shipping methods, zones, rates, weight/dimensional calculation, free-shipping thresholds, provider integration, and other shipping-specific behavior.

The recommended integration boundary is that Shipping or the Host may expose/consume a narrow availability-policy port, while the Host adapts that port to `maatify/php-eligibility` when Eligibility is installed.

`maatify/shipping` SHOULD NOT require Eligibility as a mandatory core dependency merely to remain independently usable in projects that need no eligibility rules.

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

The Payment Method domain still owns method configuration and lifecycle. Eligibility only determines whether the method passes the supplied business context.

## Country and geography

Country is a Context dimension, not an Eligibility-owned Country entity.

Eligibility may store an exact external country code as a dimension value, but it does not own ISO datasets or validate that a code exists in a Host geography registry.

The Host owns semantic geography validation and normalization before rules/context reach Eligibility.

The same principle applies to states, regions, postal zones, markets, or other geography concepts if a future Host uses them as Eligibility dimensions.

## Customer context

Eligibility does not require a Customer entity.

The Host resolves relevant customer information into Context values before evaluation.

For example:

```text
customer_type = wholesale
customer_segment = vip
country = EG
```

Eligibility does not query Customer tables to discover those values.

This keeps the package usable for guests, API clients, B2B accounts, organizations, devices, or other consumers that can be represented by business context without being modeled as a concrete Customer record.

## Persistence principles for RC1

The final RC1 schema must preserve these principles:

- Eligibility-owned tables only;
- no foreign keys to Host-owned Subject or Context domains;
- deterministic uniqueness preventing ambiguous duplicate active rules for the same Subject/dimension/value/effect identity;
- explicit typed ALLOW/DENY storage;
- bounded management reads;
- deterministic ordering for returned rule collections;
- concurrency-safe mutation behavior where rule uniqueness/lifecycle invariants require it;
- no dependency on a framework or HTTP runtime.

Exact table names and lifecycle columns are intentionally deferred to the implementation/schema slice.

## Host responsibilities

The Host owns:

- defining stable `subject_type` keys;
- providing validated external Subject identities;
- defining stable `dimension_key` semantics;
- resolving and normalizing Context values;
- validating external Subject and Context identities against Host-owned registries where required;
- deciding which domains call Eligibility and at what application boundary;
- combining Eligibility with intrinsic Product/Category/Payment/Shipping lifecycle rules;
- defining composite behavior across multiple Subjects when needed;
- mapping machine-readable Eligibility decisions to API/UI behavior and translated messages;
- transaction orchestration when an Eligibility mutation participates in a larger Host operation.

## RC1 exclusions

The following are explicitly outside the first RC scope unless a later documented decision adds them before implementation freeze:

- arbitrary executable expressions;
- nested boolean rule trees;
- scripting or callback rules persisted in the database;
- wildcard subject or dimension matching;
- numeric comparison/range operators;
- price/amount calculation;
- inventory checks;
- time-window scheduling;
- automatic Product/Category inheritance;
- automatic Category ancestor traversal;
- automatic geography hierarchy expansion;
- automatic Customer/segment lookup;
- authorization/RBAC/permissions;
- framework/HTTP/Admin UI integration;
- cross-domain foreign keys;
- implicit fallback between dimension values.

These exclusions keep RC1 a focused eligibility engine rather than an unbounded generic business-rules platform.

## Architectural target

The intended ecosystem relationship is:

```text
Category ─────────────┐
Product ──────────────┤
Payment Method ───────┤
Shipping ─────────────┤      Host resolves context
Promotion ────────────┘               │
                                      ▼
                              maatify/php-eligibility
                                      │
                                      ▼
                              EligibilityDecision
```

Owning domains remain independently reusable. Eligibility remains independently reusable. The Host composes them.

## RC1 success condition

The first Release Candidate is successful when a Host can install one framework-neutral package and use the same stable typed model to manage and evaluate customer/country-style business eligibility for multiple unrelated Subject types without adding domain-specific restriction tables or coupling those domains to one another.

The package must be small enough to reason about, strict enough to produce deterministic decisions, and generic enough to replace repeated restriction subsystems such as shipping-provider-by-country restrictions in future projects.
