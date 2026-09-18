# RC1 Architecture and Persistence Alignment Blueprint

## Current State

`maatify/php-eligibility` RC1 currently exposes a working Eligibility runtime with Evaluation, Management, Rule persistence, canonical ordering/validation, schema, concurrency coordination, tests, and consumer verification. The current source layout is partially layer-first (`Application/`), while the Rule persistence boundary separates command/mutation, management-query, evaluation-read, and Eligibility-specific coordination support; generic transaction/savepoint mechanics are delegated to the released `maatify/persistence` API.

The package is currently stacked on `phase/v1.0.0-rc.1`. This phase is an architecture/persistence alignment phase and does not change Eligibility business semantics unless a separately approved contract decision requires it.

## Gaps

1. Source organization does not fully follow the package-building direction `Domain -> Capability -> Layer`.
2. Rule persistence responsibilities are mixed: Command mutations, Management Query/List/Filter reads, and Consumer/Evaluation reads share the same repository contract/implementation.
3. **Resolved by WU6:** generic transaction/savepoint mechanics are owned by the released shared Persistence API rather than Eligibility-local contracts or orchestration.
4. Mutation-specific persistence support is coupled to the broad repository contract instead of being a narrow internal capability.
5. Package reference, tests, examples, namespaces, and consumer wiring will need alignment after the architecture changes.

## Decisions / Contracts

### Architecture direction

Eligibility is the package/domain boundary. The runtime should organize directly around real package capabilities, then layers within each capability. The intended capability direction is:

- Evaluation
- Management
- Rule
- Common only for genuinely shared framework-neutral primitives
- Exception at the canonical package exception boundary

`Application/` is not retained as a generic top-level layer unless later evidence proves a required package capability boundary.

Actor folders such as `Admin`, `Website`, or `Customer` are not introduced unless they are proven real Eligibility domain boundaries.

### Persistence responsibility separation

The Rule persistence boundary must separate at least these responsibilities:

- Command/mutation persistence
- Management query/list/filter persistence
- Consumer/Evaluation read persistence

A SELECT used only as part of a mutation/atomic replacement flow may remain an internal mutation-support read and must not be forced into a public management query API.

### Shared persistence mechanics

Reusable persistence mechanics must use stable shared Maatify APIs rather than package-local duplicate engines.

Before any work that removes Eligibility-local transaction mechanics begins, two prerequisites are mandatory:

1. Refresh the repository's pinned engineering-standards snapshot/adoption to the latest approved upstream standards state and resolve the resulting applicable standards set.
2. Upgrade `maatify/persistence` to the latest stable released version that contains the required transaction/savepoint capability, then update Eligibility Composer metadata and integration contracts accordingly.

The transaction migration work is blocked until both prerequisites are complete and verified.

Eligibility must not introduce a package-local generic TransactionRunner/TransactionManager replacement for a stable shared persistence capability.

### Behavioral preservation

The phase must preserve the existing Eligibility business contract, including:

- exact canonical string semantics and bounds
- Rule natural identity
- allow/deny evaluation semantics
- lifecycle semantics
- deterministic ordering
- typed exception behavior
- atomic replace-dimension behavior
- subject cleanup semantics
- concurrency/coordination guarantees

Any public behavioral change requires an explicit documentation decision before implementation.

## Scope

- Reorganize `src/` around real Eligibility capabilities and layers.
- Split Rule persistence command/query/read responsibilities and concrete PDO implementations where required.
- Narrow internal mutation-support persistence contracts.
- Refresh the pinned standards snapshot/adoption before transaction migration.
- Upgrade `maatify/persistence` to the latest stable version containing the required transaction/savepoint API before transaction migration.
- Replace Eligibility-owned generic transaction/savepoint mechanics with the stable shared Persistence API once available and adopted.
- Update namespaces, service wiring, tests, consumer harness, package reference, README/integration documentation, and schema documentation where affected.

## Out of Scope

- New Eligibility business semantics.
- New Subject/Context/Rule expression features.
- New actor-specific `Admin`/`Website`/`Customer` APIs without a separately proven domain requirement.
- Duplicating shared Ordering, Pagination, Transaction, or Savepoint engines locally.
- Unrelated CI or presentation redesign beyond what the architecture migration requires.

## Work Units

1. **Architecture capability alignment**
   - Move from generic layer-first organization to capability-first organization.
   - Preserve public behavior and identify any public namespace impact before implementation.

2. **Persistence CQ/read separation**
   - Separate Command/mutation persistence from Management Query/List/Filter reads and Consumer/Evaluation reads.
   - Split contracts and concrete PDO implementations where responsibility boundaries require it.

3. **Mutation-support persistence cleanup**
   - Replace broad replacement-repository inheritance with narrow internal mutation-support capabilities for coordination locking, complete mutation reads, and coordination cleanup.

4. **Standards snapshot refresh** — prerequisite for WU5
   - Re-resolve selective pinned standards adoption from the latest approved upstream standards commit.
   - Update manifest/snapshot only according to the Adoption Standard.
   - Verify integrity and applicability before continuing.

5. **Persistence dependency upgrade** — prerequisite for WU6
   - Upgrade to the latest stable `maatify/persistence` release that exposes the required transaction/savepoint semantics.
   - Update Composer constraints and verify the exact public API actually released.

6. **Shared transaction migration**
   - Remove Eligibility-owned generic transaction/savepoint mechanics.
   - Consume the stable `maatify/persistence` transaction/savepoint API.
   - Preserve outer-transaction ownership, operation atomicity, rollback behavior, and original Throwable visibility.

7. **Contract and documentation migration**
   - Align `ELIGIBILITY_PACKAGE_REFERENCE.md`, README/integration material, namespaces, examples, and consumer wiring with the final architecture.

8. **Verification and final review**
   - Execute applicable unit, golden, integration, concurrency, static-analysis, consumer-harness, and real-MySQL checks.
   - Perform final accumulated review against latest parent/base and confirm no divergence or stale claims.

## Test Matrix

The phase must preserve or add executable evidence for:

- Evaluation results and deterministic trace/order behavior.
- Rule create/update/deactivate/reactivate semantics.
- Management inspect/list/filter behavior.
- Evaluation bulk active-rule reads.
- Natural-identity conflict classification.
- Atomic `replaceDimensionRules()` success and rollback.
- Subject cleanup atomicity.
- Concurrent create/replace/lifecycle conflict behavior.
- No outer transaction: shared runner owns begin/commit/rollback.
- Existing outer transaction: operation participates without committing/rolling back the Host transaction.
- Operation-local savepoint success inside an outer transaction.
- Failure rollback to operation savepoint while outer transaction remains active.
- Savepoint release behavior.
- Original operation Throwable preservation even if cleanup fails.
- Repeated/nested operation behavior supported by the released Persistence contract.
- Real MySQL verification of transaction/savepoint semantics.
- Consumer Harness remains valid after namespace/wiring changes.
- PHPStan max and full maintained test suites remain green.

## Documentation Impact

At minimum review/update as applicable:

- `ELIGIBILITY_PACKAGE_REFERENCE.md`
- `README.md`
- `schema/README.md`
- integration/consumer documentation under `docs/`
- standards manifest/snapshot under `docs/php-engineering-standards/`
- Composer dependency documentation and examples

Documentation must describe stable behavior and boundaries rather than preserve obsolete implementation structure.

## Definition of Done

- Source organization is justified by `Domain -> Capability -> Layer` and contains no ceremonial generic layer.
- Command/mutation persistence, management query/list/filter persistence, and consumer/evaluation reads have clear separate responsibilities/contracts.
- Internal mutation-support persistence is narrow and does not expose generic transaction mechanics.
- The pinned standards snapshot is refreshed and integrity-verified before transaction migration.
- `maatify/persistence` is upgraded to the latest stable release containing the required transaction/savepoint API before transaction migration.
- Eligibility contains no package-local duplicate generic Transaction/Savepoint engine where the stable shared API owns that capability.
- Existing Eligibility behavioral, concurrency, and atomicity contracts remain preserved and verified.
- Package Reference, code, schema, tests, examples, and consumer harness agree with the final architecture.
- Verification passes on the accumulated Draft branch.
- Final review is performed against the latest parent/base with no unresolved divergence or stale documentation.
