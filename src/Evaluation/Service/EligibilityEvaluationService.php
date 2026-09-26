<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Service;

use Maatify\Eligibility\Evaluation\Service\EligibilityRuleEvaluator;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationServiceInterface;
use Maatify\Eligibility\Evaluation\DTO\SubjectDecisionCollectionDTO;
use Maatify\Eligibility\Evaluation\DTO\SubjectDecisionDTO;
use Maatify\Eligibility\Evaluation\ValueObject\EligibilityDecision;
use Maatify\Eligibility\Evaluation\Repository\ActiveRuleReaderInterface;
use Maatify\Eligibility\ValueObject\RuleCollection;
use Maatify\Eligibility\Evaluation\ValueObject\Context;
use Maatify\Eligibility\ValueObject\Subject;
use Maatify\Eligibility\ValueObject\SubjectCollection;

final class EligibilityEvaluationService implements EligibilityEvaluationServiceInterface
{
    private readonly EligibilityRuleEvaluator $evaluator;

    public function __construct(private readonly ActiveRuleReaderInterface $activeRuleReader)
    {
        $this->evaluator = new EligibilityRuleEvaluator();
    }

    public function decide(Subject $subject, Context $context): EligibilityDecision
    {
        $rules = $this->activeRuleReader->findActiveForSubjects(new SubjectCollection($subject));

        return $this->evaluator->evaluate($subject, $context, $rules);
    }

    public function decideMany(SubjectCollection $subjects, Context $context): SubjectDecisionCollectionDTO
    {
        $rules = $this->activeRuleReader->findActiveForSubjects($subjects);
        $results = [];

        foreach ($subjects as $subject) {
            $results[] = new SubjectDecisionDTO(
                $subject,
                $this->evaluator->evaluate($subject, $context, $rules),
            );
        }

        return new SubjectDecisionCollectionDTO(...$results);
    }
}
