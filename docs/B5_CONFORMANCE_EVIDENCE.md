# B5 Conformance Evidence

This file records the executable evidence boundary for RC1 scenarios 1–52. The
machine-readable source is
[`tests/Golden/CanonicalAcceptanceEvidenceMap.php`](../tests/Golden/CanonicalAcceptanceEvidenceMap.php).
`CanonicalAcceptanceScenariosTest` fails when a scenario is missing, duplicated,
or points to a public PHPUnit test method that no longer exists or is no longer
executable.

The map intentionally reuses one focused test for related scenarios. The
Golden map is evidence traceability; it does not replace the Unit, Integration,
or Concurrency tests it references.

| Scenario | Canonical behavior | Executable evidence |
|---:|---|---|
| 1 | No active Rules are `UNRESTRICTED`. | `RuntimeEvaluationServiceTest::noActiveRulesIsUnrestrictedAndInactiveRulesAreIgnored` (unit) |
| 2 | Decision states are mutually consistent. | `DecisionModelTest::invalidDecisionCombinationsAreRejected` (unit) |
| 3 | Matching ALLOW passes. | `RuntimeEvaluationServiceTest::allowMatchPassesAndReturnsMatchingAllowTrace` (unit) |
| 4 | Unsatisfied ALLOW denies. | `RuntimeEvaluationServiceTest::allowListUnsatisfiedAndMissingContextAreDistinctFailures` (unit) |
| 5 | Missing ALLOW Context has its distinct reason. | `RuntimeEvaluationServiceTest::allowListUnsatisfiedAndMissingContextAreDistinctFailures` (unit) |
| 6 | Non-matching DENY-only Context passes. | `RuntimeEvaluationServiceTest::denyOnlyPassesWithAndWithoutContextWhenNoValueMatches` (unit) |
| 7 | Matching DENY-only Context denies. | `RuntimeEvaluationServiceTest::matchingDenyOnlyValueDenies` (unit) |
| 8 | Missing DENY-only Context passes with its distinct reason. | `RuntimeEvaluationServiceTest::denyOnlyPassesWithAndWithoutContextWhenNoValueMatches` (unit) |
| 9 | Duplicate Context dimensions are rejected. | `ContextTest::duplicateDimensionsAreRejected` (unit) |
| 10 | A present Context dimension cannot be empty. | `ContextTest::presentEmptyDimensionsAreRejected` (unit) |
| 11 | Duplicate values in one Context dimension are rejected. | `ContextTest::duplicateValuesAreRejected` (unit) |
| 12 | An entirely empty Context is valid. | `ContextTest::anEmptyContextIsValid` (unit) |
| 13 | One matching value satisfies a multi-value ALLOW Context. | `RuntimeEvaluationServiceTest::oneMatchingContextValueSatisfiesAnAllowList` (unit) |
| 14 | Every matching ALLOW appears in canonical trace order. | `RuntimeEvaluationServiceTest::allowMatchPassesAndReturnsMatchingAllowTrace` (unit) |
| 15 | DENY precedence retains every matching effect in the trace. | `RuntimeEvaluationServiceTest::denyMatchWinsAndTraceContainsEveryMatchingEffect` (unit) |
| 16 | Passing dimensions combine with AND. | `EligibilityRuntimeIntegrationTest::servicesEvaluateAndManageTheRealRuleBoundary` (real MySQL) |
| 17 | More than one failing ruled dimension remains visible in canonical order. | `RuntimeEvaluationServiceTest::dimensionsUseAndSemanticsAndAllOutcomesRemainVisibleAfterFailure` (unit) |
| 18 | Inactive Rules do not participate. | `RuntimeEvaluationServiceTest::noActiveRulesIsUnrestrictedAndInactiveRulesAreIgnored` (unit) |
| 19 | Effect mutation preserves natural identity. | `PdoRuleRepositoryIntegrationTest::lifecycleAndEffectMutationsAreOrthogonalAndIdempotent` (real MySQL) |
| 20 | Individual create persists an active Rule. | `PdoRuleRepositoryIntegrationTest::createReturnsAnActiveRuleAndIdentityReadHydratesIt` (real MySQL) |
| 21 | Individual create has no initial inactive-state input. | `PublicContractTest::commandsRepresentTypedMutationIntentWithoutInitialLifecycle` (unit) |
| 22 | Create conflicts with active or inactive identity. | `PdoRuleRepositoryIntegrationTest::naturalIdentityRejectsDuplicatesWhetherActiveOrInactive` (real MySQL) |
| 23 | Effect mutation preserves inactive lifecycle. | `PdoRuleRepositoryIntegrationTest::lifecycleAndEffectMutationsAreOrthogonalAndIdempotent` (real MySQL) |
| 24 | Lifecycle mutation preserves effect. | `PdoRuleRepositoryIntegrationTest::lifecycleAndEffectMutationsAreOrthogonalAndIdempotent` (real MySQL) |
| 25 | Deactivation is idempotent. | `PdoRuleRepositoryIntegrationTest::lifecycleAndEffectMutationsAreOrthogonalAndIdempotent` (real MySQL) |
| 26 | Reactivation is idempotent. | `PdoRuleRepositoryIntegrationTest::lifecycleAndEffectMutationsAreOrthogonalAndIdempotent` (real MySQL) |
| 27 | Updating to the current effect is idempotent. | `RuntimeManagementServiceTest::lifecycleAndEffectCommandsAreOrthogonalAndIdempotent` (unit; repeats the same effect update) |
| 28 | Missing deactivate/reactivate/update-effect mutations produce typed not-found. | `RuntimeManagementServiceTest::inspectMissingAndMissingMutationsProduceTypedNotFound` (unit) |
| 29 | Management reads expose inactive Rules. | `PdoRuleRepositoryIntegrationTest::criteriaReturnsBothLifecyclesWithFiltersAndCanonicalOrdering` (real MySQL) |
| 30 | Lifecycle filters distinguish active and inactive reads. | `PdoRuleRepositoryIntegrationTest::criteriaReturnsBothLifecyclesWithFiltersAndCanonicalOrdering` (real MySQL) |
| 31 | Canonical identity rejects scalar coercion. | `CanonicalStringTest::itRejectsNonStringValuesInsteadOfCoercingThem` (unit) |
| 32 | Malformed UTF-8 is rejected. | `CanonicalStringTest::itRejectsMalformedUtf8` (unit) |
| 33 | Leading/trailing whitespace is rejected. | `CanonicalStringTest::itRejectsEmptyWhitespaceOnlyAndPaddedValues` (unit) |
| 34 | Case and differently encoded Unicode remain distinct in storage and evaluation. | `PdoRuleRepositoryIntegrationTest::exactCaseUnicodeAndNonAsciiValuesRoundTripDistinctly` (real MySQL) |
| 35 | Extra Context dimensions are ignored. | `RuntimeEvaluationServiceTest::extraContextDimensionsAreIgnored` (unit) |
| 36 | Single and batch Decisions are equivalent. | `RuntimeEvaluationServiceTest::batchIsOrderedEquivalentToSingleAndUsesOneBulkRead` (unit) |
| 37 | Duplicate batch Subjects are rejected. | `PublicContractTest::subjectBatchCollectionRejectsDuplicateNaturalIdentities` (unit) |
| 38 | Batch result order preserves input order. | `RuntimeEvaluationServiceTest::batchIsOrderedEquivalentToSingleAndUsesOneBulkRead` (unit) |
| 39 | Empty batch returns an empty result. | `RuntimeEvaluationServiceTest::emptyBatchReturnsEmptyResultWithoutPerSubjectWork` (unit) |
| 40 | Batch evaluation uses bounded bulk loading. | `RuntimeEvaluationServiceTest::batchIsOrderedEquivalentToSingleAndUsesOneBulkRead` (unit) + `PdoRuleRepositoryIntegrationTest::activeRulesForSubjectsUseBulkLoadingAndExcludeInactiveRules` (real MySQL) |
| 41 | Replacement reuses/updates/reactivates/creates/deactivates atomically. | `EligibilityRuntimeIntegrationTest::realReplacementReactivatesExistingRulesAndEmptyReplacementDeactivatesWithoutDeleting` (state transitions) + `EligibilityRuntimeIntegrationTest::realBoundaryFailureRollsBackTheCompleteReplacementAndKeepsOriginalThrowable` (atomic rollback, real MySQL) |
| 42 | Repeating replacement is idempotent. | `EligibilityRuntimeIntegrationTest::replacementUsesCompleteUnboundedDimensionStateAndIsIdempotent` (real MySQL) |
| 43 | Empty replacement deactivates without hard deletion. | `EligibilityRuntimeIntegrationTest::realReplacementReactivatesExistingRulesAndEmptyReplacementDeactivatesWithoutDeleting` (real MySQL) |
| 44 | Subject cleanup removes only that Subject's Eligibility data. | `EligibilityRuntimeIntegrationTest::managementCleanupPhysicallyRemovesRulesAndCoordinationRows` (real MySQL; verifies another Subject remains) |
| 45 | Subject cleanup is idempotent. | `EligibilityRuntimeIntegrationTest::managementCleanupPhysicallyRemovesRulesAndCoordinationRows` (real MySQL) |
| 46 | Public collections use canonical ordering. | `PdoRuleRepositoryIntegrationTest::activeDimensionKeysExcludeInactiveOnlyDimensionsAndAreCanonical` + `PdoRuleRepositoryIntegrationTest::criteriaReturnsBothLifecyclesWithFiltersAndCanonicalOrdering` (real MySQL), `RuntimeEvaluationServiceTest::allowMatchPassesAndReturnsMatchingAllowTrace`, and `DecisionModelTest::deniedDecisionRetainsEveryOutcomeAndTrace` (unit) |
| 47 | Eligibility evaluates Host candidates and does not own global pagination. | `HostBoundaryContractTest::batchEvaluationAcceptsHostCandidatesInsteadOfOwningGlobalPagination` (unit) |
| 48 | Known duplicate storage errors convert; unknown storage errors propagate. | `PdoRuleRepositoryIntegrationTest::naturalIdentityRejectsDuplicatesWhetherActiveOrInactive` + `PdoRuleRepositoryIntegrationTest::unknownPdoStorageFailurePropagatesUnchanged` (real MySQL) |
| 49 | Replacement participates in an outer Host transaction. | `EligibilityRuntimeIntegrationTest::packageAndHostTransactionOwnershipControlDurability` (real MySQL) |
| 50 | Package-owned failure rolls back and rethrows the original Throwable. | `EligibilityRuntimeIntegrationTest::realBoundaryFailureRollsBackTheCompleteReplacementAndKeepsOriginalThrowable` (real MySQL) |
| 51 | Concurrent create preserves one identity and classifies the loser. | `EligibilityConcurrencyIntegrationTest::concurrentCreateHasOneWinnerAndTypedDuplicateOutcome` (real MySQL concurrency) |
| 52 | Concurrent replacement preserves complete-dimension atomicity. | `EligibilityConcurrencyIntegrationTest::concurrentReplacementOnInitiallyEmptyDimensionSerializesWholeState` + `EligibilityConcurrencyIntegrationTest::concurrentReplacementOnExistingDimensionDoesNotMixDesiredSets` (real MySQL concurrency; Management/Evaluation observer reads only old or new committed state) |

## Harness contract

`consumer-harness/run.php` creates two new temporary Composer roots. Each root:

- resolves `maatify/php-eligibility` through the committed local `path`
  repository with `symlink: false` and the RC1 fixture version;
- runs `composer install --no-dev` and invokes only the installed dependency's
  production `vendor/autoload.php`;
- checks that the installed package is copied, that PSR-4 points to its
  production `src/`, and that the package test namespace is absent;
- uses only the documented public PHP API for management, evaluation, and
  cleanup, with the installed package schema asset as the documented setup
  boundary;
- runs a real MySQL workflow, verifies observable decisions and lifecycle
  reads through the Management Service, and proves the complete package-owned
  Rule and coordination tables are empty before and after the public workflow;
- removes the exact temporary consumer root and fails if any root remains.

Successful runs emit explicit `REAL_MYSQL_PRE_RESIDUE=PASS` and
`REAL_MYSQL_POST_RESIDUE=PASS` markers for the complete package-owned tables.

Run it with:

```bash
docker compose -f docker-compose.integration.yml up -d --wait
composer test:harness
```

The harness is an additional consumer proof layer. It does not replace the
maintained Unit, Integration, or Concurrency suites.
