# DEC-002 — RC2 Management Operational-Read Contract

## Decision Metadata

| Field | Value |
|---|---|
| Decision ID | `DEC-002` |
| Title | RC2 Management Operational-Read Contract |
| Status | `ACTIVE` |
| Scope / Concern | php-eligibility RC2 Management/Admin operational-read boundary: Host/Admin vs. Eligibility ownership, paginated Rule management reads, paginated active-dimension discovery, and the Rule lifecycle summary |
| Decision Authority / Deciders | Project Owner — approved RC2 architecture and scope for WU-RC2-04A — Management / Admin Integration & Operational Read Closure |
| Canonical Contract / Current Owner | `ELIGIBILITY_PACKAGE_REFERENCE.md` for the full package behavior; this record for the continuing-effect Host/Admin boundary and pagination/summary contract decisions below |

## Context

Eligibility owns persisted Rule and lifecycle state, so Host/Admin integrations
need a stable package API for operational reads instead of direct SQL against
package tables. The previous bounded Rule read contract capped results at 500,
while active-dimension discovery had no proven small hard bound. RC2 therefore
needed an operational-read contract that remains complete for larger persisted
states without creating a Host-specific Admin capability or a package-local
pagination subsystem.

## Decision

`Management` is the framework-neutral PHP API through which a Host/Admin
integration manages and reads Eligibility-owned Rule/lifecycle state without
direct package-table SQL. There is no `Admin` Capability inside the package;
Admin is a Host use case composed on top of the existing two-Capability
topology fixed by `DEC-001`:

```text
Eligibility
├── Evaluation   ← runtime/site decisions
└── Management   ← Host/Admin management + operational reads
```

### Host/Admin ownership boundary

The package owns: Rule identity, effect, active/inactive lifecycle,
Eligibility dimension/value semantics, Management reads, Management
mutations, and package-owned counts/summaries.

The Host owns: authentication, authorization, roles/permissions,
HTTP/routes/controllers, UI, exports, Product/Category/Customer names, Host
search, Host dataset pagination, Host joins, and Host actor identity.

No Host table, repository, model, foreign key, name resolution, or global
Subject inventory may enter Eligibility.

### Paginated Rule management

The previous bounded `RuleCriteria` contract (`maxResults` capped at 500) is
replaced by real pagination reused from the stable published
`maatify/persistence ^1.4` API:

```text
RuleCriteria(Subject $subject, ?dimensionKey, ?RuleLifecycleEnum $lifecycle, ?RuleEffectEnum $effect)

EligibilityManagementServiceInterface::inspectRules(RuleCriteria $criteria, PageRequest $pageRequest): PageResult<Rule>
RuleManagementQueryInterface::findByCriteria(RuleCriteria $criteria, PageRequest $pageRequest): PageResult<Rule>
```

`maxResults`, `RuleCriteria::DEFAULT_MAX_RESULTS`, and
`RuleCriteria::MAX_MAX_RESULTS` are removed from the public contract with no
deprecated alias or compatibility shim, consistent with the pre-Stable direct
migration already established by `DEC-001`.

Eligibility owns only its domain query/filter/count semantics and row
mapping; page/per-page normalization, sort resolution, count execution, and
pagination metadata are owned by `maatify/persistence`'s `PdoPaginator`, using
the shared `PageRequest`/`PageResult`/`PaginationConfig`/
`PdoPaginationQueryDescriptor`/`SortWhitelist` types. No package-local
pagination engine is introduced.

For one supplied Subject: `total` counts all persisted Rules belonging to
that Subject before optional `RuleCriteria` filters; `filtered` counts
persisted Rules after the optional `dimensionKey`/`lifecycle`/`effect`
filters.

The public Rule page ordering contract is one fixed canonical sort, not a
generic Admin sorter: primary `dimension_key` ascending, tie-breaker
`dimension_value` ascending, because within one Subject `(dimension_key,
dimension_value)` is the natural Rule identity remainder and therefore gives
deterministic complete ordering. `PageRequest.sortBy`/`sortDirection` accept
only `null` (this canonical order) or the explicit equivalent (`sortBy =
dimension_key`, `sortDirection = ASC`); any other explicit sort request is
invalid Eligibility Management input and produces
`InvalidEligibilityInputException`. Shared per-page bounds remain those of
the approved Pagination configuration: default `20`, minimum `1`, maximum
`200`.

### Paginated active-dimension discovery

There is no proven small hard domain bound for the number of distinct active
dimensions for a Subject, so the previous unbounded
`ActiveDimensionKeyCollectionDTO` collection is replaced by pagination:

```text
ActiveDimensionKeyDTO { dimensionKey: string }

EligibilityManagementServiceInterface::inspectActiveDimensionKeys(ActiveDimensionKeysCriteria $criteria, PageRequest $pageRequest): PageResult<ActiveDimensionKeyDTO>
RuleManagementQueryInterface::findActiveDimensionKeys(ActiveDimensionKeysCriteria $criteria, PageRequest $pageRequest): PageResult<ActiveDimensionKeyDTO>
```

Semantics: scope is one Subject; visibility is active Rules only; the result
is distinct dimension keys; inactive-only dimensions are excluded; ordering is
`dimension_key` ascending. For this query `total === filtered`, because no
optional domain filter exists beyond the query's intrinsic active-visibility
contract. Only canonical ascending `dimension_key` ordering is supported;
other explicit sort requests are rejected with
`InvalidEligibilityInputException`. `ActiveDimensionKeyCollectionDTO` is
removed with no remaining runtime/public owner and no alias/wrapper
compatibility code.

### Rule lifecycle summary

```text
RuleLifecycleSummaryCriteria(Subject $subject, ?dimensionKey)
RuleLifecycleSummaryDTO { totalRules: int, activeRules: int, inactiveRules: int }

EligibilityManagementServiceInterface::inspectRuleLifecycleSummary(RuleLifecycleSummaryCriteria $criteria): RuleLifecycleSummaryDTO
RuleManagementQueryInterface::summarizeLifecycle(RuleLifecycleSummaryCriteria $criteria): RuleLifecycleSummaryDTO
```

`dimensionKey` is an optional exact filter; `null` summarizes all Rules for
the Subject. There is no pagination, lifecycle filter, effect filter, time
window, or Host dimension on this criteria. The invariant
`totalRules === activeRules + inactiveRules` holds, all counts `>= 0`. The PDO
implementation computes the aggregate in the database; it MUST NOT load all
Rules into PHP to calculate it. It reads an independent persisted total for
the same scope and requires that total to equal `activeRules + inactiveRules`.
When the mismatch is caused by an unrecognized persisted lifecycle state, the
summary raises `InvalidPersistedRuleStateException` rather than returning an
undercounted summary. An empty scope returns exactly `0`/`0`/`0`.

## Rationale

Keeping operational reads inside the existing Management capability preserves
the Evaluation/Management boundary established by `DEC-001` and prevents Host
authentication, UI, search, naming, or dataset concerns from entering the
package. Reusing the stable `maatify/persistence ^1.4` paginator gives complete,
bounded, deterministic reads without duplicating shared pagination mechanics.
The lifecycle summary is limited to stable package-owned counts that are useful
to a Host and can be computed directly from persisted Eligibility state.

## Compatibility Boundary

This is a pre-Stable direct migration consistent with `DEC-001`: the bounded
`RuleCriteria`/`maxResults` contract and the unbounded
`ActiveDimensionKeyCollectionDTO` contract are replaced directly, with no
legacy alias, `class_alias()` bridge, deprecated wrapper, proxy class, or
duplicate old/new API.

## Semantic Boundary

This decision changes the Management operational-read Public Contract shape
(pagination, filtering, and the lifecycle summary) and reuses the released
`maatify/persistence` pagination capability. It does not change Evaluation
business semantics, Rule natural-identity or lifecycle semantics, replacement
or cleanup semantics, transaction or concurrency semantics, or the database
schema.

## Non-Goals

- A root Admin capability, generic Dashboard/Statistics/Report subsystem, or
  any other reporting taxonomy beyond the Rule lifecycle summary defined here.
- A Host Subject inventory or Host-domain search inside the package.
- HTTP/UI/permissions/export surfaces inside the package.
- Schema migration, timestamps, or audit history.
- Time-window analytics.
- Public Factory/Facade/wiring redesign, Docker/CI lifecycle redesign, or
  Harness publication/version-identity redesign.
- Preparing an RC2 or Stable release.

## Consequences

- Host/Admin integrations read and manage Rules through a stable paginated
  PHP API instead of direct package-table SQL, including result sets above
  the previous 500-row bound.
- Consumers using the removed `maxResults`/`ActiveDimensionKeyCollectionDTO`
  contract must migrate to `PageRequest`/`PageResult` and
  `ActiveDimensionKeyDTO` with no compatibility shim, consistent with
  `DEC-001`.
- Eligibility remains free of a package-local pagination engine; pagination
  correctness is proven against the real MySQL persistence boundary.
