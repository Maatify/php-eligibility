<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Golden;

use Maatify\Eligibility\Tests\Integration\EligibilityConcurrencyIntegrationTest;
use Maatify\Eligibility\Tests\Integration\EligibilityRuntimeIntegrationTest;
use Maatify\Eligibility\Tests\Integration\PdoRuleRepositoryIntegrationTest;
use Maatify\Eligibility\Tests\Unit\CanonicalStringTest;
use Maatify\Eligibility\Tests\Unit\ContextTest;
use Maatify\Eligibility\Tests\Unit\DecisionModelTest;
use Maatify\Eligibility\Tests\Unit\HostBoundaryContractTest;
use Maatify\Eligibility\Tests\Unit\PublicContractTest;
use Maatify\Eligibility\Tests\Unit\RuntimeEvaluationServiceTest;
use Maatify\Eligibility\Tests\Unit\RuntimeManagementServiceTest;

final class CanonicalAcceptanceEvidenceMap
{
    /**
     * @return list<array{
     *     id: int,
     *     behavior: string,
     *     evidence: list<array{class: class-string, method: string, layer: string}>
     * }>
     */
    public static function scenarios(): array
    {
        return [
            self::scenario(1, 'No active Rules produce UNRESTRICTED.', RuntimeEvaluationServiceTest::class, 'noActiveRulesIsUnrestrictedAndInactiveRulesAreIgnored', 'unit'),
            self::scenario(2, 'Decision state combinations are mutually consistent.', DecisionModelTest::class, 'invalidDecisionCombinationsAreRejected', 'unit'),
            self::scenario(3, 'A matching ALLOW produces an eligible dimension.', RuntimeEvaluationServiceTest::class, 'allowMatchPassesAndReturnsMatchingAllowTrace', 'unit'),
            self::scenario(4, 'An unsatisfied ALLOW list denies.', RuntimeEvaluationServiceTest::class, 'allowListUnsatisfiedAndMissingContextAreDistinctFailures', 'unit'),
            self::scenario(5, 'A missing ALLOW dimension has its distinct reason.', RuntimeEvaluationServiceTest::class, 'allowListUnsatisfiedAndMissingContextAreDistinctFailures', 'unit'),
            self::scenario(6, 'A non-matching DENY-only dimension passes.', RuntimeEvaluationServiceTest::class, 'denyOnlyPassesWithAndWithoutContextWhenNoValueMatches', 'unit'),
            self::scenario(7, 'A matching DENY-only value denies.', RuntimeEvaluationServiceTest::class, 'matchingDenyOnlyValueDenies', 'unit'),
            self::scenario(8, 'A missing DENY-only dimension passes fail-open with its distinct reason.', RuntimeEvaluationServiceTest::class, 'denyOnlyPassesWithAndWithoutContextWhenNoValueMatches', 'unit'),
            self::scenario(9, 'Duplicate Context dimensions are rejected.', ContextTest::class, 'duplicateDimensionsAreRejected', 'unit'),
            self::scenario(10, 'A present Context dimension cannot be empty.', ContextTest::class, 'presentEmptyDimensionsAreRejected', 'unit'),
            self::scenario(11, 'Duplicate values within a Context dimension are rejected.', ContextTest::class, 'duplicateValuesAreRejected', 'unit'),
            self::scenario(12, 'An entirely empty Context is valid.', ContextTest::class, 'anEmptyContextIsValid', 'unit'),
            self::scenario(13, 'One matching value among multiple Context values satisfies ALLOW.', RuntimeEvaluationServiceTest::class, 'oneMatchingContextValueSatisfiesAnAllowList', 'unit'),
            self::scenario(14, 'All matching ALLOW Rules appear in canonical trace order.', RuntimeEvaluationServiceTest::class, 'allowMatchPassesAndReturnsMatchingAllowTrace', 'unit'),
            self::scenario(15, 'DENY takes precedence and the trace retains both effects.', RuntimeEvaluationServiceTest::class, 'denyMatchWinsAndTraceContainsEveryMatchingEffect', 'unit'),
            self::scenario(16, 'All ruled dimensions combine with AND when they pass.', EligibilityRuntimeIntegrationTest::class, 'servicesEvaluateAndManageTheRealRuleBoundary', 'integration'),
            self::scenario(17, 'Every ruled dimension remains visible after failures.', RuntimeEvaluationServiceTest::class, 'dimensionsUseAndSemanticsAndAllOutcomesRemainVisibleAfterFailure', 'unit'),
            self::scenario(18, 'Inactive Rules do not participate in evaluation.', RuntimeEvaluationServiceTest::class, 'noActiveRulesIsUnrestrictedAndInactiveRulesAreIgnored', 'unit'),
            self::scenario(19, 'Effect changes preserve one natural Rule identity.', PdoRuleRepositoryIntegrationTest::class, 'lifecycleAndEffectMutationsAreOrthogonalAndIdempotent', 'integration'),
            self::scenario(20, 'Individual create persists a new active Rule.', PdoRuleRepositoryIntegrationTest::class, 'createReturnsAnActiveRuleAndIdentityReadHydratesIt', 'integration'),
            self::scenario(21, 'Individual create has no initial inactive-state input.', PublicContractTest::class, 'commandsRepresentTypedMutationIntentWithoutInitialLifecycle', 'unit'),
            self::scenario(22, 'Create conflicts with an existing identity in either lifecycle state.', PdoRuleRepositoryIntegrationTest::class, 'naturalIdentityRejectsDuplicatesWhetherActiveOrInactive', 'integration'),
            self::scenario(23, 'Effect updates preserve an inactive lifecycle.', PdoRuleRepositoryIntegrationTest::class, 'lifecycleAndEffectMutationsAreOrthogonalAndIdempotent', 'integration'),
            self::scenario(24, 'Lifecycle mutations preserve the current effect.', PdoRuleRepositoryIntegrationTest::class, 'lifecycleAndEffectMutationsAreOrthogonalAndIdempotent', 'integration'),
            self::scenario(25, 'Deactivation is idempotent for an existing inactive Rule.', PdoRuleRepositoryIntegrationTest::class, 'lifecycleAndEffectMutationsAreOrthogonalAndIdempotent', 'integration'),
            self::scenario(26, 'Reactivation is idempotent for an existing active Rule.', PdoRuleRepositoryIntegrationTest::class, 'lifecycleAndEffectMutationsAreOrthogonalAndIdempotent', 'integration'),
            self::scenario(27, 'Updating to the current effect is idempotent.', RuntimeManagementServiceTest::class, 'lifecycleAndEffectCommandsAreOrthogonalAndIdempotent', 'unit'),
            self::scenario(28, 'Missing mutation targets produce typed not-found.', RuntimeManagementServiceTest::class, 'inspectMissingAndMissingMutationsProduceTypedNotFound', 'unit'),
            self::scenario(29, 'Management reads expose inactive Rules and lifecycle state.', PdoRuleRepositoryIntegrationTest::class, 'criteriaReturnsBothLifecyclesWithFiltersAndCanonicalOrdering', 'integration'),
            self::scenario(30, 'Lifecycle filters distinguish active and inactive reads.', PdoRuleRepositoryIntegrationTest::class, 'criteriaReturnsBothLifecyclesWithFiltersAndCanonicalOrdering', 'integration'),
            self::scenario(31, 'Canonical identity rejects scalar coercion.', CanonicalStringTest::class, 'itRejectsNonStringValuesInsteadOfCoercingThem', 'unit'),
            self::scenario(32, 'Malformed UTF-8 is rejected.', CanonicalStringTest::class, 'itRejectsMalformedUtf8', 'unit'),
            self::scenario(33, 'Leading and trailing whitespace is rejected.', CanonicalStringTest::class, 'itRejectsEmptyWhitespaceOnlyAndPaddedValues', 'unit'),
            self::scenario(34, 'Case and differently encoded Unicode remain distinct.', PdoRuleRepositoryIntegrationTest::class, 'exactCaseUnicodeAndNonAsciiValuesRoundTripDistinctly', 'integration'),
            self::scenario(35, 'Extra Context dimensions without Rules are ignored.', RuntimeEvaluationServiceTest::class, 'extraContextDimensionsAreIgnored', 'unit'),
            self::scenario(36, 'Single and batch Decisions are equivalent.', RuntimeEvaluationServiceTest::class, 'batchIsOrderedEquivalentToSingleAndUsesOneBulkRead', 'unit'),
            self::scenario(37, 'Duplicate Subjects in a batch are rejected.', PublicContractTest::class, 'subjectBatchCollectionRejectsDuplicateNaturalIdentities', 'unit'),
            self::scenario(38, 'Batch results preserve accepted Subject order.', RuntimeEvaluationServiceTest::class, 'batchIsOrderedEquivalentToSingleAndUsesOneBulkRead', 'unit'),
            self::scenario(39, 'An empty batch returns an empty result.', RuntimeEvaluationServiceTest::class, 'emptyBatchReturnsEmptyResultWithoutPerSubjectWork', 'unit'),
            self::scenarioWithEvidence(40, 'Batch evaluation uses bounded bulk loading rather than per-Subject reads.', [
                self::evidence(RuntimeEvaluationServiceTest::class, 'batchIsOrderedEquivalentToSingleAndUsesOneBulkRead', 'unit'),
                self::evidence(PdoRuleRepositoryIntegrationTest::class, 'activeRulesForSubjectsUseBulkLoadingAndExcludeInactiveRules', 'integration'),
            ]),
            self::scenarioWithEvidence(41, 'Replacement reuses, updates, reactivates, creates, and deactivates atomically.', [
                self::evidence(EligibilityRuntimeIntegrationTest::class, 'realReplacementReactivatesExistingRulesAndEmptyReplacementDeactivatesWithoutDeleting', 'integration'),
                self::evidence(EligibilityRuntimeIntegrationTest::class, 'realBoundaryFailureRollsBackTheCompleteReplacementAndKeepsOriginalThrowable', 'integration'),
            ]),
            self::scenario(42, 'Repeating a replacement is idempotent.', EligibilityRuntimeIntegrationTest::class, 'replacementUsesCompleteUnboundedDimensionStateAndIsIdempotent', 'integration'),
            self::scenario(43, 'Empty replacement deactivates without hard deletion.', EligibilityRuntimeIntegrationTest::class, 'realReplacementReactivatesExistingRulesAndEmptyReplacementDeactivatesWithoutDeleting', 'integration'),
            self::scenario(44, 'Subject cleanup physically removes only its Eligibility data.', EligibilityRuntimeIntegrationTest::class, 'managementCleanupPhysicallyRemovesRulesAndCoordinationRows', 'integration'),
            self::scenario(45, 'Subject cleanup is idempotent when no Rules remain.', EligibilityRuntimeIntegrationTest::class, 'managementCleanupPhysicallyRemovesRulesAndCoordinationRows', 'integration'),
            self::scenarioWithEvidence(46, 'Public collections use canonical ordering independent of DB row order.', [
                self::evidence(PdoRuleRepositoryIntegrationTest::class, 'activeDimensionKeysExcludeInactiveOnlyDimensionsAndAreCanonical', 'integration'),
                self::evidence(PdoRuleRepositoryIntegrationTest::class, 'criteriaReturnsBothLifecyclesWithFiltersAndCanonicalOrdering', 'integration'),
                self::evidence(RuntimeEvaluationServiceTest::class, 'allowMatchPassesAndReturnsMatchingAllowTrace', 'unit'),
                self::evidence(DecisionModelTest::class, 'deniedDecisionRetainsEveryOutcomeAndTrace', 'unit'),
            ]),
            self::scenario(47, 'Eligibility evaluates Host-supplied candidates and does not own global pagination.', HostBoundaryContractTest::class, 'batchEvaluationAcceptsHostCandidatesInsteadOfOwningGlobalPagination', 'unit'),
            self::scenarioWithEvidence(48, 'Known duplicate storage errors convert safely; unknown storage errors propagate.', [
                self::evidence(PdoRuleRepositoryIntegrationTest::class, 'naturalIdentityRejectsDuplicatesWhetherActiveOrInactive', 'integration'),
                self::evidence(PdoRuleRepositoryIntegrationTest::class, 'unknownPdoStorageFailurePropagatesUnchanged', 'integration'),
            ]),
            self::scenario(49, 'Replacement participates in an outer Host transaction without owning it.', EligibilityRuntimeIntegrationTest::class, 'packageAndHostTransactionOwnershipControlDurability', 'integration'),
            self::scenario(50, 'Package-owned failure rolls back active work and rethrows the original Throwable.', EligibilityRuntimeIntegrationTest::class, 'realBoundaryFailureRollsBackTheCompleteReplacementAndKeepsOriginalThrowable', 'integration'),
            self::scenario(51, 'Concurrent creates preserve one natural identity and classify the loser.', EligibilityConcurrencyIntegrationTest::class, 'concurrentCreateHasOneWinnerAndTypedDuplicateOutcome', 'concurrency'),
            self::scenarioWithEvidence(52, 'Concurrent replacements preserve complete-dimension atomicity.', [
                self::evidence(EligibilityConcurrencyIntegrationTest::class, 'concurrentReplacementOnInitiallyEmptyDimensionSerializesWholeState', 'concurrency'),
                self::evidence(EligibilityConcurrencyIntegrationTest::class, 'concurrentReplacementOnExistingDimensionDoesNotMixDesiredSets', 'concurrency'),
            ]),
        ];
    }

    /**
     * @param class-string $class
     * @return array{id: int, behavior: string, evidence: list<array{class: class-string, method: string, layer: string}>}
     */
    private static function scenario(
        int $id,
        string $behavior,
        string $class,
        string $method,
        string $layer,
    ): array {
        return [
            'id' => $id,
            'behavior' => $behavior,
            'evidence' => [self::evidence($class, $method, $layer)],
        ];
    }

    /**
     * @param list<array{class: class-string, method: string, layer: string}> $evidence
     * @return array{id: int, behavior: string, evidence: list<array{class: class-string, method: string, layer: string}>}
     */
    private static function scenarioWithEvidence(int $id, string $behavior, array $evidence): array
    {
        return [
            'id' => $id,
            'behavior' => $behavior,
            'evidence' => $evidence,
        ];
    }

    /**
     * @param class-string $class
     * @return array{class: class-string, method: string, layer: string}
     */
    private static function evidence(string $class, string $method, string $layer): array
    {
        return [
            'class' => $class,
            'method' => $method,
            'layer' => $layer,
        ];
    }
}
