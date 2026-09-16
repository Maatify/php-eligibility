# RC1 Readiness Audit

Status document for the `v1.0.0-rc.1` candidate of `maatify/php-eligibility` at
the B6 child-PR boundary. It consolidates the local/CI gate map
(`docs/RC1_CI_QUALITY_GATE_MAP.md`), the actual execution state, open
owner-only actions, and the evidence required by the Final Umbrella Closure
Gate in `docs/RC1_DELIVERY_PLAN.md` (section 10).

This audit does not authorize and has not performed a merge to `main`, a tag,
a release, or any distribution publication.

## 1. Candidate identity

| Item | Value |
|---|---|
| Package | `maatify/php-eligibility` |
| Umbrella | `phase/v1.0.0-rc.1` |
| Umbrella baseline for B6 | `dadc98508247059a010aa5c299337b4344e37033` |
| B6 Work Branch | `build/b6-ci-release-readiness` |
| B6 child PR | Draft PR targeting `phase/v1.0.0-rc.1` |
| Release state | Unpublished RC preparation; no SemVer RC tag exists, no Packagist distribution exists |

## 2. Closure-gate status

| Final Umbrella Closure Gate item | Status | Evidence location |
|---|---|---|
| 1. B1–B6 slices present on the umbrella | `IN PROGRESS` — B1–B5 merged into the umbrella; B6 delivered by this child PR | Umbrella history and this PR |
| 2. Every Work Unit complete, reviewed, traceable | `IN PROGRESS` — B6 requires direct review and independent verification (Jules) | This PR diff and evidence |
| 3. Public contract and runtime conform to the Package Reference | `PROVEN` | 52-scenario Golden suite, Unit/Integration/concurrency suites, Consumer Harness |
| 4. D1 license resolved; LICENSE + Composer metadata consistent | `PROVEN` | `LICENSE` and `composer.json` (`proprietary`) |
| 5. D2 database compatibility contract resolved and proven | `PROVEN` | `ELIGIBILITY_PACKAGE_REFERENCE.md` D2 record; `schema/README.md`; real MySQL Integration evidence |
| 6. Complete executable 52-scenario suite + Unit/Integration/concurrency evidence | `PROVEN` | `tests/Golden/`, `tests/Integration/`, `tests/Unit/` |
| 7. Consumer Verification Harness: two clean repeatable runs | `PROVEN` | `composer test:harness` CI job and local runs |
| 8. Full applicable CI gate green and fail-closed | `PENDING` — requires actual GitHub Actions results on the B6 HEAD | `ci-quality`, `ci-tests`, `ci-integration` gate jobs |
| 9. Presentation synchronized, unpublished state distinguished | `IN PROGRESS` — completed by this PR (README, CHANGELOG, gate map) | This PR |
| 10. Base/head/merge-base, changed files, checks directly reviewed | `PENDING` — owner/lead review of this PR | This PR |
| 11. No `BLOCKED BY DECISION` item remains | `PROVEN` — D1 and D2 resolved; no other decision gate exists in the roadmap | Delivery plan reconciliation |

## 3. Completed implementation state (B1–B5 summary)

- B1 package foundation: PHP `^8.4`, `proprietary` license, PSR-4 production
  autoload, `maatify/exceptions` runtime dependency, PHPStan max, PHPUnit.
- B2 typed public contracts: evaluation and management service interfaces,
  commands, queries, result collections, replaceable repository contracts.
- B3 schema + direct-PDO persistence: `maa_eligibility_rules` and
  `maa_eligibility_subject_locks`, natural-identity uniqueness, exact
  `VARBINARY` storage, bounded reads, driver-error classification.
- B4 runtime behavior: evaluator grouping/DENY precedence/allow-list
  semantics, lifecycle/effect mutations, atomic dimension replacement, batch
  evaluation, package/Host transaction ownership with savepoints, Subject
  coordination locking, concurrency evidence.
- B5 conformance + Harness: 52-scenario evidence map, real-MySQL
  integration/concurrency closure, external-consumer Harness with two clean
  runs and whole-table residue checks.

## 4. B6 delivered by this PR

1. **Local parity**: maintained `tools/` commands (`php-lint.php`,
   `check-whitespace.sh`, `lint-workflows.sh`, `assert-gate.sh`,
   `check-local.sh`), Composer scripts (`analyse`, `check:local`, `lint:php`,
   `test`, `test:unit`, `test:golden`, `test:integration`, `test:harness`),
   and the documented local/CI mapping in the gate map.
2. **Fail-closed CI**: three always-run workflows with stable aggregate gate
   jobs, PHP `8.4`/`8.5` matrix, latest and lowest dependency ends, real
   `mysql:8.4.11` Integration with a repeated run, Golden + Harness, workflow
   lint via pinned `actionlint`, immutable full-SHA actions, least privilege,
   timeouts, and concurrency policy.
3. **Package presentation**: accurate README, CHANGELOG updated for B5 and B6,
   Composer metadata synchronized with reality.
4. **Readiness evidence**: this audit and the gate map.

## 5. Actual verification evidence required for B6 acceptance

- Local aggregate result: `tools/check-local.sh` (quality + tests) and
  `tools/check-local.sh --with-integration` (real MySQL + Harness).
- GitHub Actions evidence on the B6 **HEAD** (not local-only):
  - `ci-quality`: Composer contract, PHPStan max, syntax, whitespace, audit,
    workflow lint, `Quality aggregate gate` success.
  - `ci-tests`: Unit + Golden on PHP `8.4` and `8.5`; lowest-dependency job;
    `Tests aggregate gate` success.
  - `ci-integration`: Integration + repeatability run + Harness on PHP `8.4`
    and `8.5` against real MySQL; `Integration aggregate gate` success.

## 6. Remaining owner-only actions after this PR (not performed here)

- Final review of the accumulated umbrella diff and this child PR
  (item 2 and 10 of the closure gate).
- Owner-only authorization to merge `phase/v1.0.0-rc.1` to `main`.
- The future RC tag/export workflow and actual external publication of
  `v1.0.0-rc.1` (the package is not yet published; no Packagist distribution
  exists).
- After a published RC: Harness verification against the exact published RC,
  then Real Host Validation in two independent projects before any future
  first Stable release. Those facts must not be claimed before they exist.

## 7. Non-blockers and scope boundaries

- `SECURITY.md`, `CONTRIBUTING.md`, and `CODE_OF_CONDUCT.md` are not part of
  this PR; the adopted Presentation Standard treats them as release-facing
  files, and authoring them is tracked as deliberate follow-up work rather
  than a closure-gate requirement.
- No Composer code-style formatter is adopted; style integrity is covered by
  the whitespace gate and PHPStan max (see gate map section 2).
- The pinned standards snapshot and the canonical Package Reference are
  intentionally unchanged by this PR.