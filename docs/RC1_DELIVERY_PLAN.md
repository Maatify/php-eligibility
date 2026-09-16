# php-eligibility v1.0.0-rc.1 Delivery Plan

## Purpose and status

This document is the authoritative execution roadmap for completing the first Release Candidate of `maatify/php-eligibility`.

It is a planning and delivery document. It does not itself implement PHP, Composer, schema, tests, CI, or runtime behavior. The canonical externally observable contract remains [ELIGIBILITY_PACKAGE_REFERENCE.md](../ELIGIBILITY_PACKAGE_REFERENCE.md), and the adopted local standards remain the governing engineering baseline.

The RC1 umbrella is the existing integration branch `phase/v1.0.0-rc.1`. Implementation work integrates into that umbrella; it does not bypass the umbrella by targeting `main` directly.

## 1. Current baseline

### Baseline used for this roadmap

The remote state was fetched and verified on 2026-09-16.

| Item | Verified state |
|---|---|
| Repository | `Maatify/php-eligibility` |
| Umbrella remote ref | `origin/phase/v1.0.0-rc.1` |
| Umbrella baseline SHA | `ac4a8dd6a977f7d81d368704c729fec8cfad2fbd` |
| Umbrella baseline commit | `docs: align eligibility contract with adopted standards (#4)` |
| Parent of baseline | `0e8ef2bce6dc44f6f33362a895ef249f6f4d1714` |
| Roadmap branch | `docs/rc1-implementation-plan`, created from the verified umbrella SHA |
| Existing umbrella PR | Draft PR `#1`, `v1.0.0-rc.1 umbrella: complete eligibility package`, base `main` |
| Umbrella PR treatment | Remains the owner-controlled Draft integration path; this roadmap does not redesign it |

The checkout also contained an existing untracked `.idea/` directory. It is outside this task's scope and is not part of the roadmap change.

### Actual repository shape at the baseline

The tracked repository is documentation and governance heavy. It contains the canonical reference, the delivery plan, `AGENTS.md`, `README.md`, and the adopted standards snapshot. It does not contain:

- `composer.json` or a Composer dependency graph;
- `src/` or package runtime code;
- `tests/`, a test runner, or executable acceptance scenarios;
- `schema/` or an Eligibility-owned persistence schema;
- `phpstan.neon` or maintained static-analysis configuration;
- `.github/workflows/` or CI gates;
- a separate Consumer Verification Harness root.

This absence is the implementation baseline, not evidence that the documented contract is already implemented.

## 2. Completed foundation

The following are completed pre-implementation work and must not be scheduled as future implementation slices:

1. The generic Eligibility concept and package boundaries were established.
2. The canonical Subject, Context, Rule, Decision, lifecycle, ordering, persistence, transaction, concurrency, error, batch, and consumer-workflow contract was consolidated in the root Package Reference.
3. Selective pinned standards adoption was completed. `STANDARDS_MANIFEST.md` records `VALID` adoption at the exact pinned commit `2fc57f9320f8a7f7147fb20abbcfa311fdf40c28`.
4. The final Resolved Applicable Standards Set was adopted locally, including package building, Composer, CI, presentation, testing, AI collaboration, and Phase Stack standards.
5. Standards Decision Alignment was completed in the current umbrella baseline; the contract now records the required `maatify/exceptions` ownership, direct-PDO RC1 boundary, transaction participation, concurrency guarantees, Consumer Verification Harness requirement, and related standards alignment.

These items freeze the implementation constraints. They do not create runtime classes, schema objects, Composer metadata, tests, or CI.

## 3. Phase, batch, Work Unit, PR, and umbrella mapping

The adopted governance model is dependency-aware and uses the following meanings:

| Term | Meaning for this repository |
|---|---|
| Roadmap Phase | A logical acceptance boundary describing a coherent capability and its proof. It is not automatically a branch or PR. |
| Execution Batch | A delivery unit containing one or more dependency-related Roadmap Phases that share context, files, architecture, or verification setup. |
| Work Unit | A bounded implementation unit with its own ownership, acceptance, tests/evidence, and out-of-scope boundary. Runtime Work Units include their directly related tests and documentation. |
| Work Branch | A Git isolation boundary selected only when it improves reviewability, dependency isolation, rollback clarity, or safe integration. |
| Child PR | A focused Draft PR from a Work Branch to `phase/v1.0.0-rc.1`, covering a coherent Execution Batch when a PR boundary adds value. |
| Umbrella integration gate | The full verification and review gate for the accumulated RC1 candidate on the umbrella before the owner considers `phase/v1.0.0-rc.1` eligible for `main`. |

Therefore:

```text
Roadmap Phase != Work Branch != Child PR
```

No child PR is created merely for a DTO, enum, SQL file, test file, or verification activity. Tightly coupled Work Units are grouped in one Batch and may share one Work Branch and child PR. Independent or high-risk boundaries may use separate child PRs, but every child PR targets the umbrella, never `main`.

Each child PR must preserve phase-level traceability through its title/body, commits, changed-file scope, acceptance evidence, and the umbrella integration result. Verification and Final Review are gates, not PRs, unless they produce an independent repository change.

## 4. Baseline Reconciliation

The following classification is based on the actual baseline above, not on the presence of prose in the Package Reference.

| RC1 responsibility | Classification | Evidence and treatment |
|---|---|---|
| Canonical Eligibility concept and package boundaries | `ALREADY IMPLEMENTED + PROVEN` | Root Package Reference exists and is the canonical contract; completed before runtime work. |
| Selective pinned standards adoption and final applicability record | `ALREADY IMPLEMENTED + PROVEN` | `STANDARDS_MANIFEST.md` is `VALID`; the recorded Control Set and Resolved Applicable Standards Set are present locally. |
| Standards Decision Alignment | `ALREADY IMPLEMENTED + PROVEN` | Current umbrella HEAD is the alignment commit and the Package Reference records the resulting decisions. |
| Composer package foundation, PHP 8.4, autoload, namespaces, mandatory runtime dependencies, PHPStan, and test tooling | `NOT IMPLEMENTED` | No `composer.json`, `src/`, `phpstan.neon`, test runner, or package scaffold exists. |
| Core typed model and domain contracts | `NOT IMPLEMENTED` | No runtime source exists. Contract prose is not runtime implementation. |
| Public evaluation and management API | `NOT IMPLEMENTED` | No public PHP contracts, commands, criteria, services, or result types exist. |
| Eligibility-owned schema and direct-PDO adapter | `NOT IMPLEMENTED` | No `schema/`, tables, repository, PDO adapter, fixtures, or integration tests exist. |
| Evaluation, management lifecycle, replacement, cleanup, batch, transaction, and concurrency behavior | `NOT IMPLEMENTED` | No executable behavior or persistence boundary exists. |
| Unit, integration, concurrency, and 52-scenario golden evidence | `NOT IMPLEMENTED` | No `tests/` or maintained test configuration exists. |
| Consumer Verification Harness | `NOT IMPLEMENTED` | No external Composer consumer root or repeatability evidence exists. |
| CI and quality gates | `NOT IMPLEMENTED` | No workflow, aggregate gate, local parity command map, or CI evidence exists. |
| Package presentation and release readiness | `PARTIAL / GAP` | A minimal README exists, but the required release-facing package files, accurate runtime claims, install documentation, quality status, and release evidence are not present. |
| RC1 requirements that are already implemented but lack only proof | No qualifying runtime item found | The baseline contains no runtime implementation to prove. Future claims must be backed by executed checks and real boundary evidence. |
| Supported database engine and RC1 compatibility contract | `BLOCKED BY DECISION` | The canonical reference freezes direct PDO but does not freeze the supported DBMS or compatibility range. The adopted CI Standard requires the package/project to define that contract and verify against the actual engine. An owner/package-contract decision is required before B3 real schema/persistence work; the engine must not be inferred from examples or other Maatify projects. |
| RC1 package license | `BLOCKED BY DECISION` | D1 is strictly the owner-approved RC1 package-license decision. No repository `LICENSE` file exists; B1 owns creating it and adding matching `composer.json` metadata, then verifying consistency before B1 package foundation can be complete. The adopted Composer Standard requires the metadata to match `LICENSE` but does not choose a license. |
| Unresolved decisions that currently block the planned implementation | `BLOCKED BY DECISION` | The two blocking owner/package-contract decisions are the supported database engine/compatibility contract and the package license. Exact type names, folders, schema identifiers, bounded lengths, PDO query shape, lock/isolation strategy, fixture layout, and CI filenames remain implementation choices unless they would change the frozen observable contract. |

The reconciliation prevents both duplicate work and false completion. The three completed foundation rows remain closed. The two decision gates below are explicit preconditions, not artificial execution batches or PRs; every remaining runtime responsibility enters the dependency graph only after its relevant gate is satisfied.

### Current execution status — D2 resolution recorded by B3

The historical baseline above correctly records D2 as unresolved when the roadmap
was created. The owner decision is now resolved and locked for the B3 execution
slice:

| Decision | Current status | Resolution |
|---|---|---|
| D2 — supported database engine and compatibility contract | `RESOLVED` | RC1 uses MySQL-compatible database-server semantics through direct PDO. Database compatibility is capability-based, not product-version-based; no minimum MySQL or MariaDB version is declared. The database server must provide the documented transactional InnoDB-style table behavior, binary-safe `VARBINARY` storage/comparison, indexed-key capacity, and uniqueness/index semantics. Separately, the PHP runtime must provide `ext-pdo` and `ext-pdo_mysql`. `mysql:8.4.11` is a reproducibility fixture only, not a minimum supported product version, and no MariaDB verification is claimed without execution. |

This decision is recorded within the B3 child PR. D2 is not a separate PR or
execution batch and is not reopened by the implementation choices below.

## 5. Frozen contract versus implementation choices

### Frozen and not reopenable inside implementation slices

The implementation must preserve, without silently changing:

- canonical string identity and exact validated UTF-8 behavior for Subject, dimension, and value components;
- immutable typed Context shape, absent-versus-present semantics, and multi-value rules;
- Rule natural identity independent of effect and lifecycle;
- ALLOW/DENY effects, active/inactive lifecycle, orthogonal mutations, idempotency, and typed not-found/conflict behavior;
- deterministic per-dimension evaluation, DENY precedence, ALLOW-list and DENY-only semantics, AND across dimensions, and no inferred cross-dimension or cross-Subject behavior;
- typed immutable `EligibilityDecision`, `DimensionOutcome`, `RuleReference`, stable reason codes, and complete matched-rule traces;
- single and ordered batch evaluation, duplicate Subject rejection, empty-batch behavior, single/batch equivalence, bounded bulk loading, and no canonical N+1 path;
- management reads including inactive Rules, lifecycle filtering, active-dimension introspection, canonical ordering, and bounded reads;
- atomic/idempotent `replaceDimensionRules()` semantics, Subject cleanup, and the specified package-owned versus Host-owned transaction behavior;
- natural-identity uniqueness, duplicate-key evidence/classification, typed package errors, `EligibilityExceptionInterface`, `maatify/exceptions` ownership, and propagation of unknown external throwables;
- direct PDO RC1 persistence, no ORM or external query builder, no Host foreign keys or joins, framework neutrality, and Host-owned external identity validation;
- the 52 canonical acceptance scenarios and the separate Consumer Verification Harness requirement.

These are contract constraints, not suggestions for implementation.

### Choices allowed inside a slice

The implementation may choose exact class names, method names where the public capability is not frozen, internal directories, table/column/index names, exact bounded lengths, timestamp fields, PDO repository decomposition, transaction/locking/isolation/retry mechanics, fixture organization, workflow filenames, and the concrete test-runner configuration. The schema slice must document and enforce any chosen bounds, and the package must use stable Maatify APIs rather than local duplicates where those APIs are part of the contract.

The supported database engine/compatibility contract and the package license decision are not implementation choices. D2 must be resolved before B3 may begin real schema/persistence implementation, and D1 must be resolved before B1 begins package/license implementation. Creating the repository-root `LICENSE` and adding matching `composer.json` metadata are B1 implementation responsibilities; B1's completion gate verifies both against the approved decision. The exact CI service image/version used for reproducibility may be selected only after the supported database contract is frozen; it must represent that contract and must not silently define or broaden it.

If an implementation choice changes observable behavior, public API, package ownership, or a frozen invariant, work stops and the change is recorded as a separate explicit decision/documentation change before implementation continues.

## 6. Dependency graph

The dependency-aware execution sequence is:

```text
B0 Completed foundation
  |
  +--> D1 Owner package-license decision
  |       |
  |       v
  |     B1 Package foundation + typed core model
  |       |
  |       v
  |     B2 Public application contracts + service seams
  |       |
  |       v
  |     D2 Owner supported-DB engine/compatibility decision
  |       |
  |       v
  |     B3 Eligibility schema + direct-PDO persistence foundation
  |       |
  |       v
  |     B4 Complete runtime behavior: evaluation, management, batch, lifecycle, concurrency
  |       |
  |       v
  |     B5 Golden conformance + real integration evidence + Consumer Verification Harness
  |       |
  |       v
  |     B6 CI/quality gates + package presentation + final RC1 readiness audit
  |       |
  |       v
  |     Umbrella integration gate
  |       |
  |       v
  |     Owner-only eligibility review of phase/v1.0.0-rc.1 -> main
```

D1 and D2 are decision gates, not execution batches and not reasons to create artificial PRs. D1 must be resolved before B1 begins package/license implementation; B1 then creates and verifies the repository `LICENSE` and matching Composer metadata. D2 must be satisfied before B3 may begin real schema/persistence implementation. The first four batches are otherwise intentionally ordered because the later boundary must hydrate, mutate, and return the stable types from the earlier boundaries. B4 is one coherent runtime batch even though its Work Units are internally ordered; evaluation and management share the same rule model, persistence boundary, ordering, and error semantics. B5 and B6 are later because a final external-consumer proof cannot be meaningful before a usable Composer package, public API, and real persistence workflow exist.

## 7. RC1 implementation stack

### B1 — Package foundation and typed core model

**Roadmap Phase:** Package foundation and core typed contracts.

**Objective:** Establish an installable PHP 8.4 library boundary and implement the immutable, validated domain types on which every later slice depends.

**Dependencies:** Completed foundation B0 plus D1, the owner-approved package-license decision. D1 must be resolved before B1 begins package/license implementation; B1 may not be considered complete until it has created and verified the required license artifacts.

**Owned Work Units:**

1. Composer/package foundation: package identity, PHP constraint, PSR-4 production autoload, direct Maatify runtime dependencies actually used, development tools, PHPStan max configuration, test-runner foundation, package-required root structure, creation of the owner-approved repository-root `LICENSE`, and matching `composer.json` license metadata.
2. Core value representations: Subject, Context, dimensions, values, Rule/effect/lifecycle representations, typed collections, validation, canonical bytewise ordering, and package exception marker/foundation.
3. Decision representations: Decision, DimensionOutcome, RuleReference, machine reason values, immutable invariants, and serialization/iteration contracts where exposed publicly.
4. Unit proof for all validation, immutability, Context-shape, ordering, and Decision-construction invariants owned by this batch.

**Expected repository areas/files:** `composer.json`, `LICENSE`, `phpstan.neon`, package-root required files where created in this slice, `src/`, and focused `tests/` unit/configuration paths. Exact class and directory names remain implementation choices.

**Explicitly out of scope:** Eligibility schema, SQL, PDO repositories, management orchestration, real database tests, Consumer Verification Harness, final CI matrix, Host integrations, and release publication.

**Acceptance criteria:**

- Composer metadata, PHP 8.4 compatibility, namespace/autoload mapping, direct dependency declarations, and development tooling follow the adopted Composer and Package Building standards; no committed `version` field or `composer.lock` is introduced. The Composer/package foundation cannot be complete while D1 is unresolved.
- D1 is resolved before this implementation begins. B1 creates the repository-root `LICENSE` and adds the same owner-approved license metadata to `composer.json`; B1 cannot be complete until both exist and their consistency is verified. The implementation agent must not choose MIT or any other license.
- Public and internal type suffix rules, `final readonly` DTO/command rules, collection contracts, and PHPStan max configuration are applied where the selected design uses those artifact types.
- Canonical strings reject null/non-string/scalar-coerced input, malformed UTF-8, empty or whitespace-only values, and leading/trailing whitespace without trimming, case conversion, transliteration, or Unicode normalization.
- Context duplicate dimensions, empty present dimensions, duplicate values, and absent dimensions obey the frozen contract; an entirely empty Context remains valid.
- Rule identity/effect/lifecycle representations and Decision/DimensionOutcome/RuleReference invariants cannot express contradictory RC1 states.
- The package marker is exactly `Maatify\\Eligibility\\Exception\\EligibilityExceptionInterface`; shared exception ownership remains with `maatify/exceptions`.
- Unit tests cover the slice's boundary and edge cases; no test uses a weakened production class solely to enable mocking.

**Required verification/evidence:** `composer validate --strict`; dependency resolution without committing a lock; `composer check-platform-reqs`; strict optimized autoload validation; PHP syntax checks; PHPStan level max with zero suppressions; the maintained unit/test-runner checks; formatter dry-run when a formatter is configured; and `git diff --check`.

**Child PR and umbrella integration condition:** The Work Units may share one coherent Draft child PR targeting `phase/v1.0.0-rc.1`. Codex/the implementation agent performs the direct implementation review and presents the complete diff. Jules, when assigned, independently verifies the model, package contract, and required checks; Jules does not implement and independently verify the same boundary. The child PR may enter the umbrella only after its Component Gate passes and its base/head/changed files are verified against the current umbrella.

**Unlocks:** B2 public contracts and service seams.

### B2 — Public application contracts and service seams

**Roadmap Phase:** Public evaluation and management API boundaries.

**Objective:** Define the typed public entry points and orchestration seams that Hosts will use, without coupling them to a framework or to direct SQL.

**Dependencies:** B1's stable model, error, collection, and Decision contracts.

**Owned Work Units:**

1. Single and batch evaluation contracts, including ordered Subject/Decision association and duplicate-input validation.
2. Management commands, criteria/query contracts, lifecycle filters, active-dimension query capability, replacement intent, and cleanup intent.
3. Rule management result representations with explicit lifecycle state and canonical collection ordering.
4. Persistence/repository interfaces only where infrastructure substitution is a genuine runtime boundary; no interface is added solely for one test.
5. Service orchestration seams and contract-level tests using deterministic test doubles at replaceable boundaries.

**Expected repository areas/files:** `src/` public contract and service areas, `tests/` contract/unit areas, and directly affected usage or architecture documentation if required. Concrete filenames are selected during implementation.

**Explicitly out of scope:** SQL, schema identifiers, PDO implementation, Host tables, HTTP controllers/routes, framework bindings, consumer harness, and release-facing claims about unproven runtime behavior.

**Acceptance criteria:**

- Public API models are typed DTOs/value objects/collections rather than associative-array API models.
- Commands validate mutation intent; criteria/query contracts validate management read intent; services orchestrate and do not contain SQL or instantiate repositories.
- The public surface can represent single and ordered batch decisions, management reads of active and inactive Rules, lifecycle filtering, active-dimension introspection, atomic replacement intent, and Subject cleanup intent.
- Contract names and method shapes remain within the frozen capability contract and do not introduce authorization, domain visibility, Host identity lookup, global Subject discovery, or pagination semantics owned by the Host.
- Contract tests prove duplicate Subject rejection, empty batch validity, input-order preservation, typed invalid-input/not-found/conflict boundaries where applicable, and that public results expose lifecycle/order information required by the reference.

**Required verification/evidence:** Focused unit/contract tests; PHPStan max over configured source and tests; syntax and formatter checks where applicable; Composer and autoload checks inherited from B1; and `git diff --check`. Any test double must target a real replaceable contract or use a real deterministic value object.

**Child PR and umbrella integration condition:** This slice may be a separate child PR if its contract boundary is independently reviewable; otherwise it may be a sequential commit set in the B1 Work Branch. The selected boundary must be recorded in the PR body. It targets the umbrella only after direct review, independent Jules verification where assigned, and a clean current-base check. No B2 contract may be accepted if it requires reopening a frozen Package Reference decision.

**Unlocks:** B3 persistence implementation and B4 runtime orchestration.

### B3 — Eligibility schema and direct-PDO persistence foundation

**Roadmap Phase:** Eligibility-owned schema and persistence/integration boundary.

**Objective:** Build the real package-owned storage boundary that preserves exact identity, lifecycle, ordering, bounded reads, and natural-identity uniqueness without Host coupling.

**Dependencies:** B1 typed model plus B2 repository/management seams, and D2, the resolved owner-approved supported database engine/compatibility contract. B3 may not begin real schema/persistence implementation while D2 remains unresolved.

**Owned Work Units:**

1. Schema contract after D2 is resolved: apply and document the owner-approved supported database engine/compatibility contract; then select exact table/column/index names, required `maa_{package_short_name}_` table prefix, explicit bounded lengths, exact-string-safe storage/collation strategy, lifecycle fields, and package-owned comments/policies.
2. Direct-PDO repositories/adapters for typed hydration, create/read/update primitives, management reads, active/inactive filtering, active-dimension reads, canonical ordering, and bounded/bulk Rule loading.
3. Semantic storage error boundary: driver-specific duplicate evidence, typed natural-identity conflict, typed not-found mapping at the service boundary, and unchanged propagation of unknown external failures.
4. Real persistence integration fixtures and cleanup/repeatability checks against the owner-approved supported database contract. The reproducibility image/version is selected as an implementation/CI detail only after D2 is frozen.

**Expected repository areas/files:** `schema/`, `src/` persistence/repository areas, `tests/` real Integration fixtures and repository tests, Composer dependencies for stable shared persistence APIs actually used, and local setup documentation for the real service. Exact schema filenames and SQL identifiers remain open until this slice.

**Explicitly out of scope:** Host foreign keys or joins, ORM/external query builder, framework runtime, final evaluation semantics, complete replacement/concurrency orchestration, global Host queries, and Consumer Verification Harness.

**Acceptance criteria:**

- B3 MUST NOT begin real schema or persistence implementation until the owner-approved supported database engine/compatibility contract is resolved and recorded. Direct PDO is necessary but does not choose the DBMS; the engine must not be inferred from examples or other Maatify projects.
- After D2 is frozen, the exact CI service image/version used for reproducibility may be selected as an implementation/CI choice. It must exercise the frozen compatibility contract and must not silently establish a different or broader supported-engine promise.
- The schema contains only Eligibility-owned objects, uses the required package table-prefix policy, preserves validated UTF-8 sequences exactly, and cannot collapse distinct canonical strings through an unsuitable collation or silent truncation.
- Natural identity is unique independent of effect and active/inactive state; new individual Rules are active; inactive Rules remain readable to management and invisible to evaluation reads.
- Direct PDO is used for the RC1 persistence implementation. SQL is in repositories or package-local SQL support, not services; the same named PDO placeholder is not reused in one statement.
- Management reads are bounded or use the stable `maatify/persistence` capability where an unbounded list requires shared pagination mechanics; no local replacement for a stable shared pagination/ordering API is created.
- Returned repository data is hydrated with PHPStan-safe annotations and normalized to canonical package ordering rather than database row order.
- A known duplicate-key condition is converted only with documented driver-specific evidence; SQLSTATE class `23` alone is never treated as proof of duplication. Unknown `PDOException`/`Throwable` instances propagate unchanged, with `previous` preserved for documented semantic wrappers.
- Integration tests use the real supported service, temporary credentials, package-owned schema/state only, explicit readiness checks, cleanup, no leaked transactions, and repeatability/residue evidence.

**Required verification/evidence:** Schema application and reapplication; real create/read/list/lifecycle-filter tests; exact-string and collation tests; duplicate classification tests; canonical ordering tests; bounded/bulk-read evidence; cleanup and repeated Integration execution; PHP syntax, PHPStan max, Composer/platform checks, and `git diff --check`.

**Child PR and umbrella integration condition:** This is a high-risk integration boundary and should normally be its own coherent Draft child PR targeting `phase/v1.0.0-rc.1`. It may contain schema, runtime adapter, fixtures, and their tests together. It must not begin real schema/persistence implementation or enter the umbrella until D2, the real-service Integration Gate, direct review, and independent Jules persistence/architecture verification pass against the current umbrella HEAD.

**Unlocks:** B4 complete evaluator and management mutation behavior.

### B4 — Complete runtime behavior: evaluation, management, batch, lifecycle, and concurrency

**Roadmap Phases:** Evaluation behavior; management behavior; batch behavior; transaction and concurrency guarantees.

**Objective:** Complete the package-owned Domain Service and persistence orchestration so every frozen RC1 behavior is executable through the public typed contracts.

**Dependencies:** B1, B2, and B3; all model, public API, and persistence boundaries must be stable.

**Owned Work Units, executed in this order within one coherent runtime batch:**

1. Single evaluation: load active Rules, group by dimension, distinguish no-active-rule unrestricted state, apply DENY precedence/ALLOW-list/DENY-only/missing-context semantics, build all outcomes, and preserve complete matched-rule traces and canonical ordering.
2. Batch evaluation: validate duplicate Subjects, accept empty batches, preserve input order, use bounded bulk loading, share semantics with single evaluation, and expose evidence that the canonical path is not one persistence query per Subject.
3. Management reads and mutations: create active Rules, inspect/list/filter active and inactive Rules, update effect, deactivate/reactivate, active-dimension introspection, idempotency, and typed missing/conflict behavior.
4. Atomic dimension replacement: reuse/reactivate existing identities, update effects, create missing identities, deactivate omitted active Rules, preserve omitted inactive Rules, handle empty desired sets, leave other dimensions/Subjects untouched, and remain idempotent.
5. Subject cleanup and transaction participation: idempotent physical cleanup; package-owned transaction behavior when no outer transaction exists; participation without commit/rollback when a Host outer transaction is active; no partial replacement on failure.
6. Concurrency and error behavior: serialize or safely coordinate natural-identity creation and complete-dimension replacement, preserve orthogonal lifecycle/effect mutations, classify unresolved uniqueness/concurrency conditions, and propagate unknown failures.
7. Direct tests for each Work Unit plus real persistence integration and concurrency/invariant tests at the boundary they protect.

**Expected repository areas/files:** `src/` application/domain/persistence orchestration areas, `tests/` unit, regression, Integration, and concurrency areas, and directly affected usage/architecture documentation. No exact class or test filenames are prescribed here.

**Explicitly out of scope:** Host-domain joins/foreign keys, authorization, visibility, search/pagination over the Host's Subject universe, arbitrary rule expressions, framework/HTTP integration, Consumer Harness construction, and release publication.

**Acceptance criteria:**

- All evaluation outcomes and overall Decisions obey the exact reason-code and invariant contract, including `UNRESTRICTED`, `ELIGIBLE`, `DENIED`, missing Context distinctions, complete traces, and deterministic order.
- All 52 canonical behaviors are either directly covered by this batch's focused tests or explicitly owned by the final B5 golden suite without duplicate proof inflation; no scenario is silently omitted.
- Single and batch decisions are equivalent for the same Subject/Context pair; batch order is preserved; duplicates are rejected; empty input returns empty output; bulk loading is bounded and no canonical N+1 path is required.
- Management reads expose inactive Rules; state-setting commands are idempotent when the identity exists and typed not-found when it does not; create never acts as update/reactivate.
- Replacement and cleanup implement the exact lifecycle, atomicity, idempotency, isolation, and physical-deletion semantics in the Package Reference.
- Outer Host transactions are never committed or rolled back by the package; package-owned transactions roll back only while active and rethrow the original `Throwable` unless a documented semantic conversion applies.
- Concurrent creates cannot produce duplicate natural identities; concurrent replacements cannot produce partial or mixed dimension state; reads observe coherent committed states.

**Required verification/evidence:** Focused unit tests for evaluator/grouping/Decision construction; real database Integration for service/repository behavior; transaction participation tests; duplicate and concurrency tests against the supported service; batch query-count/bulk evidence; cleanup/repeatability checks; PHPStan max; complete applicable local tests; and `git diff --check`. A failed or unrun required test is not a passing gate.

**Child PR and umbrella integration condition:** Because these Work Units share the public service, rule repository, transaction boundary, and integration fixtures, they should normally remain one coherent child PR/Work Branch for B4, with internal commits for traceability. The child PR targets the umbrella. Codex implements; Jules independently verifies the accumulated boundary and its evidence. B4 cannot enter the umbrella on unit-test evidence alone.

**Unlocks:** B5 complete golden conformance, external-consumer proof, and final integration verification.

### B5 — Golden conformance, real integration evidence, and Consumer Verification Harness

**Roadmap Phase:** RC1 conformance and external-consumer readiness.

**Objective:** Consolidate complete executable proof of the canonical contract and prove that an external Composer consumer can use the package through production autoload and the real persistence boundary.

**Dependencies:** B4 is complete and its public package workflow is usable.

**Owned Work Units:**

1. Complete 52-scenario golden suite, with each scenario mapped to the applicable unit, Integration, regression, or concurrency proof and with no scenario marked passed merely because it is documented.
2. Real-service Integration closure: schema setup, exact-string behavior, ordering/collation independence, transaction/cleanup/repeatability, bulk reads, replacement atomicity, and concurrency/invariant evidence.
3. Consumer Verification Harness: a separate Composer consumer root that resolves the package as a dependency, uses production PSR-4 autoload and public contracts, executes a realistic management/evaluation workflow, exercises real persistence where applicable, and does not access `src/`, `autoload-dev`, test fixtures, Host namespaces, or Host autoload configuration directly.
4. Clean-state repeatability: two successful Harness runs from clean consumer states with no reliance on prior `vendor/`, generated files, database state, or leftover transactions/locks.
5. Evidence map linking public behavior, real boundary, observable result, and responsible test layer without replacing required unit or Integration coverage with the Harness.

**Expected repository areas/files:** `tests/` golden and Integration areas, a separate consumer fixture/project/script root selected by implementation, schema/fixture setup, and supporting verification documentation. Harness paths and fixture names are intentionally not frozen here.

**Explicitly out of scope:** Tagging/publishing the RC, two independent Host validations required before a future first Stable release, changing the canonical Package Reference contract, and any framework-specific consumer integration.

**Acceptance criteria:**

- Every canonical acceptance scenario 1–52 has executable, repeatable evidence at the correct testing layer; the suite fails closed on missing setup or unexpected skips.
- Real persistence, schema, transaction, cleanup, ordering, and concurrency boundaries are exercised where the contract requires them; mocks are not presented as system or real-service proof.
- The Harness installs/resolves the package as an external consumer, uses only documented public contracts and production autoload, produces an observable result, and completes two clean repeatable runs.
- The Harness proves absence of hidden Host dependencies and remains an additional proof layer rather than a replacement for the package's unit, Integration, regression, or system-level tests.
- The package is consumable through the same public workflow described by `Host Input -> Public API -> Domain Service -> Integration Boundary -> Observable Result`.

**Required verification/evidence:** Full golden suite; real-service Integration suite; concurrency/invariant evidence; clean residue and repeatability checks; two clean Harness runs; Composer resolution/install evidence for the consumer; PHP syntax, PHPStan max, and `git diff --check`. The exact published-tag verification required by the first-Stable release lifecycle is a later post-publication gate and is not falsely claimed here.

**Child PR and umbrella integration condition:** The Harness may be a separate high-risk child PR targeting the umbrella, or may be grouped with the conformance Work Units only if the resulting diff remains reviewable and the dependency boundary is clear. Jules must independently execute/audit the external-consumer proof when assigned; the implementation agent must not be the sole verifier of the Harness.

**Unlocks:** B6 final CI, package presentation, and RC1 readiness audit.

### B6 — CI/quality gates, package presentation, and final RC1 readiness audit

**Roadmap Phases:** CI and quality gates; package presentation/release readiness; final umbrella closure.

**Objective:** Make all required verification reproducible locally and in fail-closed CI, synchronize release-facing claims with proven behavior, and produce the evidence package for owner review of the umbrella.

**Dependencies:** B1–B5, including a passing Harness and complete real-boundary evidence, with D1 and D2 resolved and their dependent gates proven.

**Owned Work Units:**

1. Local parity map and maintained commands for Composer validation/resolution, platform checks, PHP syntax, PHPStan max, code style, whitespace, tests, schema/Integration setup, Harness, audit, and workflow lint.
2. CI architecture: stable workflow names and aggregate gate jobs, safe relevance detection if used, full integration gate at meaningful boundaries, minimum/latest/every released PHP minor covered by the Composer constraint, latest and lowest dependency resolution, real-service Integration, and no hidden skips.
3. CI security/reliability: least-privilege permissions, immutable full-SHA external actions/workflows, pinned service/tool versions, explicit timeouts, concurrency policy, appropriate matrix behavior, no `continue-on-error`/`|| true`, no baseline secrets, and fail-closed required gates.
4. Package presentation: accurate README summary, requirements, installation, public usage, behavior/error/quality claims, documentation links, CHANGELOG state, required release-facing files where applicable, Composer metadata synchronization, schema/docs alignment, and release-facing package identity. The documentation must not claim an RC or Stable version as published before its actual distribution state exists.
5. Final RC1 audit and evidence consolidation by the implementation agent and independent Jules verification, including current umbrella HEAD, changed-file scope, all gates, open blockers, and the exact owner-only closure decision.

**Expected repository areas/files:** `composer.json`, tool configuration and scripts, `.github/workflows/` and related lint/configuration paths, package-root release-facing files, `tests/`, `schema/`, consumer harness paths, and directly affected documentation. Exact workflow and tool filenames are selected from the actual implementation structure and are not invented by this roadmap.

**Explicitly out of scope:** Changing frozen domain/public decisions, refreshing the pinned standards snapshot, merging to `main`, tagging, releasing, publishing to Packagist or another registry, or claiming two independent Host validations before they actually occur.

**Acceptance criteria:**

- Every applicable CI gate has a documented local invocation with equivalent verification semantics.
- Required CI gates always report a conclusion, use stable aggregate names, fail on relevant failure/cancellation/unexpected skip, and do not rely on top-level path filtering that hides a required check.
- Composer validation, dependency resolution, platform checks, PHP syntax, PHPStan max, style/whitespace, complete maintained tests, real-service Integration, Harness, audit, and workflow lint all pass according to the actual repository structure.
- Minimum and latest supported PHP versions and every currently released PHP minor covered by the declared constraint are tested or have an explicit documented architectural exception; latest-compatible and lowest-supported dependencies pass.
- Release-facing files accurately describe the proven package, its dependencies, supported persistence behavior, error propagation, quality status, and pre-release state. No package file claims an unproven feature or a published version that does not exist.
- The package can be installed and used by an external consumer through the documented public workflow, and the schema/reference/README/Composer/CI claims are synchronized.
- There are no unresolved RC1 blockers, incomplete Work Units, unreviewed required fixes, unrun required tests, or untracked runtime changes within the declared scope.

**Required verification/evidence:** Full applicable Integration Gate at the B6 boundary; stable aggregate CI result; latest/lowest dependency evidence; PHP matrix evidence; audit and workflow-lint evidence; two clean Harness runs; final documentation review; current base/head/merge-base and changed-file review; `git diff --check`; and direct review plus independent Jules verification. A green individual child PR is not sufficient without the umbrella evidence.

**Child PR and umbrella integration condition:** CI and release-facing changes may be one final coherent child PR or two genuinely independent child PRs if their ownership and gates are clearer. Both target `phase/v1.0.0-rc.1`. The complete B6 result must be integrated and rechecked on the current umbrella HEAD before the umbrella is presented to the owner. No child PR, B6 gate, or documentation claim authorizes a merge to `main`.

**Unlocks:** Owner final review of the complete `phase/v1.0.0-rc.1` candidate and, after explicit owner authorization, the future RC tag/export workflow.

## 8. Capability coverage map

| Required RC1 capability group | Roadmap ownership |
|---|---|
| Composer contract, PHP 8.4, namespaces/autoload, Maatify dependencies, PHPStan, test tooling, root files | B1, finalized in B6 |
| Subject, Context, dimensions/values, Rule/effect/lifecycle, Decision, reasons, references, ordering, validation, exceptions | B1 |
| Single/batch public contracts, management commands/criteria, result representations, repository contracts | B2 |
| Eligibility schema, exact persistence, natural uniqueness, lifecycle storage, direct PDO, ordering, bounded/bulk reads, transaction/concurrency boundary | B3 and B4 |
| No-active-rule path, grouping, DENY precedence, ALLOW semantics, Context semantics, complete trace, deterministic Decision construction | B4 |
| Create, inspect/list, lifecycle filters, effect/lifecycle mutations, active-dimension introspection, replacement, cleanup, idempotency, concurrency | B4 |
| Ordered batch input/output, duplicate rejection, empty batch, bounded bulk loading, equivalence, no N+1 | B2 and B4, closed by B5 evidence |
| Unit, real persistence Integration, concurrency/invariant proof, 52-scenario executable suite | B1–B5, closed by B5 |
| Separate Composer Consumer Verification Harness, production autoload, real persistence, two clean runs, no hidden Host dependencies | B5 |
| Local parity, Composer/platform checks, syntax, PHPStan max, tests, Integration, Harness, audit, workflow lint, dependency/PHP compatibility, stable fail-closed gates | B1, B3, B5, finalized in B6 |
| README, CHANGELOG, package-reference synchronization, install/usage docs, Composer metadata, schema/docs alignment, RC1 audit and tag/export readiness | B6 |

## 9. Verification strategy and agent ownership

Testing is part of each runtime slice, not a final cleanup activity.

### Slice-level proof

- B1 proves typed validation, immutability, Context shape, ordering, and Decision invariants with focused unit tests and static analysis.
- B2 proves public contract shape, command/criteria validation, result collections, duplicate/empty batch boundaries, and service seams with contract/unit tests.
- B3 proves the real schema and direct-PDO repository boundary with real-service Integration tests, exact-string/collation behavior, lifecycle reads, uniqueness, ordering, cleanup, and repeatability.
- B4 proves evaluator, management, replacement, cleanup, batch, transaction, error, and concurrency behavior through the appropriate unit plus real Integration layers.
- B5 closes the complete 52-scenario golden map and adds the external-consumer proof; it does not replace earlier tests.
- B6 proves local/CI parity, compatibility, security, workflow, presentation, and the final integrated state.

### Full RC1 proof

At the B5/B6 integration boundary, the complete maintained test suite, real persistence Integration, concurrency/invariant evidence, 52-scenario golden suite, Consumer Verification Harness, dependency compatibility, PHP compatibility, static analysis, syntax, style/whitespace, audit, workflow lint, and stable aggregate gate must all be considered together. Missing, skipped, blocked, or unrun required tests are not passing evidence.

### Implementation versus independent verification

- The Codex/implementation agent owns implementation Work Units, their directly related tests, local verification, and the complete scoped diff.
- Jules is the independent verification agent when assigned: architecture/contract audit, PHPStan and test gate review once those tools exist, real-boundary verification, Harness verification, and documentation/CI checks.
- The same agent must not both implement and provide the independent verification gate for the same integration boundary.
- The lead review remains mandatory after Jules: reports, CI, and independent review do not replace direct review of the current diff, contracts, scope, and evidence.

## 10. Final umbrella closure gate

`phase/v1.0.0-rc.1` becomes eligible for owner final review only when all of the following are true:

1. The umbrella contains every required B1–B6 child slice, or has explicit evidence that a scoped item is a valid no-op; no required capability is omitted because it was documented earlier.
2. Every Work Unit is complete, directly reviewed, independently verified where required, and traceable to its acceptance evidence; no partial Work Unit or unreviewed required fix is integrated.
3. The public package contract and runtime behavior conform to the canonical Package Reference, including all frozen identity, Context, Rule, Decision, lifecycle, ordering, batch, management, replacement, cleanup, typed-error, transaction, concurrency, direct-PDO, and Host-boundary rules.
4. D1 is resolved as the owner-approved package-license decision. B1 has created the repository-root `LICENSE`, added the same approved license metadata to `composer.json`, and verified them consistent. Composer metadata, production autoload, PHP 8.4 compatibility, direct runtime dependencies, PHPStan max, test tooling, required root files, and package installability are proven.
5. D2 is closed: the owner-approved supported database engine/compatibility contract is recorded and the real Eligibility-owned schema and direct-PDO adapter are proven against that actual engine, with exact-string preservation, natural-identity uniqueness, canonical ordering, bounded/bulk reads, cleanup, transaction, concurrency, and no Host FK/JOIN behavior. Any exact CI service image/version is reproducibility evidence after D2, not the source of the compatibility decision.
6. The complete executable 52-scenario suite passes; unit, regression, real Integration, concurrency/invariant, and applicable system-level evidence pass at their required boundaries.
7. The Consumer Verification Harness completes two clean repeatable runs as an external Composer consumer using production autoload, public contracts, the real persistence boundary where applicable, and no hidden Host dependencies.
8. The full applicable CI integration gate is green and fail-closed: Composer validation/resolution/platform checks, PHP syntax, PHPStan max, style/whitespace, complete tests, real service, Harness, audit, workflow lint, supported PHP versions, dependency ends, stable aggregate gate, security, and reliability requirements are all satisfied.
9. README, CHANGELOG, usage/install documentation, Composer metadata, schema documentation, and package-reference claims are synchronized with proven behavior and accurately distinguish development, unpublished RC preparation, published RC, and Stable states.
10. The current umbrella base/head, merge-base, child PR states, changed files, checks, review threads, and final accumulated diff have been directly reviewed. `git diff --check` is clean, and no out-of-scope runtime or local artifact is included.
11. No `BLOCKED BY DECISION` item remains: specifically, D1 package license and D2 supported database engine/compatibility contract are resolved. No unresolved contract conflict, unsupported dependency, hidden failure, or unrun required check remains.

This gate makes the umbrella eligible for the owner's final review only. It does not authorize the assistant, an implementation agent, or Jules to merge `phase/v1.0.0-rc.1` into `main`, tag `v1.0.0-rc.1`, publish a release, or publish a Composer artifact.

The first-Stable lifecycle remains separate: after an actual published and externally resolvable RC, the exact published RC must pass the Consumer Verification Harness and then real Host validation in at least two independent projects before a future first Stable release. Those facts must never be claimed before they exist.

## 11. Change-control rule

The Package Reference and adopted standards are frozen inputs for this execution train. A newly discovered implementation question may be resolved inside a slice only when it is an internal choice that preserves those inputs.

Any proposed change to a frozen public/domain decision, observable reason or lifecycle behavior, persistence boundary, transaction/concurrency guarantee, package ownership, or public contract requires a separate explicit, reviewable decision/documentation change. Implementation must pause at that boundary; the choice must not be hidden inside a runtime Work Unit, schema change, test expectation, or CI configuration.

The pinned standards snapshot is likewise frozen during the train. No upstream refresh, additional standard, or manifest change is part of ordinary RC1 implementation unless separately authorized through the adoption/upgrade process.

## 12. Non-merge rule

The umbrella PR MUST NOT be merged into `main` while any RC1 Work Unit, verification gate, documentation requirement, or release-readiness responsibility remains incomplete.

After the Final Umbrella Closure Gate passes, the next state is owner final review. Only the project owner may authorize the `phase/v1.0.0-rc.1 -> main` merge. Tagging, releasing, publishing, Packagist operations, and future first-Stable validation remain separate owner-controlled actions.
