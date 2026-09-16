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
| 8. Full applicable CI gate green and fail-closed | `PROVEN` | The `ci-quality`, `ci-tests`, and `ci-integration` check suites are green on this PR's current head, including their stable aggregate gates; the final head SHA and final run IDs are recorded in the PR description at evidence time (see section 5). |
| 9. Presentation synchronized, unpublished state distinguished | `IN PROGRESS` — completed by this PR (README, CHANGELOG, gate map, SECURITY/CONTRIBUTING/CODE_OF_CONDUCT) | This PR |
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
   Composer metadata synchronized with reality, and the release-facing
   governance set (`SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`)
   present per the Presentation Standard.
4. **Readiness evidence**: this audit and the gate map.

## 5. Verification evidence

This audit intentionally does not embed individual commit SHAs or workflow run
IDs: a printed head SHA becomes stale as the pull-request head moves, and
embedding it would create a self-referential loop with the final head recorded
in the PR description. The authoritative evidence is therefore:

- **Local/CI parity**: `tools/check-local.sh` (quality + tests) and
  `tools/check-local.sh --with-integration` (real MySQL + Harness) rerun before
  the evidence is submitted; the mapping is documented in
  `docs/RC1_CI_QUALITY_GATE_MAP.md`.
- **GitHub Actions**: the check suites of this PR itself. `ci-quality`
  (including the `Quality aggregate gate`), `ci-tests` (including the `Tests
  aggregate gate`), and `ci-integration` (including the `Integration aggregate
  gate`) must be green on the current head.
- **Final record**: the final head SHA and the three final workflow run IDs are
  recorded in the PR description when the evidence is submitted and are not
  duplicated here, so the audit cannot fall out of sync with reality.

The earlier failing runs in this PR's history (the actionlint SC2016 workflow
finding, aggregate-gate jobs that ran without a checkout, and the
lowest-dependency PHPStan resolution) were repaired and re-proven on later
runs; that remediation history is visible in the PR.

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

- No Composer code-style formatter is adopted; style integrity is covered by
  the whitespace gate and PHPStan max (see gate map section 2).
- The pinned standards snapshot and the canonical Package Reference are
  intentionally unchanged by this PR.
