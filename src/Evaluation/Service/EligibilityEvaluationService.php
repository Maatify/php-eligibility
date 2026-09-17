<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Service;

use Maatify\Eligibility\Evaluation\Engine\EligibilityRuleEvaluator;
use Maatify\Eligibility\Evaluation\Contract\EligibilityEvaluationServiceInterface;
use Maatify\Eligibility\Evaluation\Result\SubjectDecisionCollection;
use Maatify\Eligibility\Evaluation\Result\SubjectDecisionResult;
use Maatify\Eligibility\Evaluation\Decision\EligibilityDecision;
use Maatify\Eligibility\Rule\Repository\RuleRepositoryInterface;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Evaluation\Value\Context;
use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Common\Value\SubjectCollection;

final class EligibilityEvaluationService implements EligibilityEvaluationServiceInterface
{
    private readonly EligibilityRuleEvaluator $evaluator;

    public function __construct(private readonly RuleRepositoryInterface $repository)
    {
        $this->evaluator = new EligibilityRuleEvaluator();
    }

    public function decide(Subject $subject, Context $context): EligibilityDecision
    {
        $rules = $this->repository->findActiveForSubjects(new SubjectCollection($subject));

        return $this->evaluator->evaluate($subject, $context, $rules);
    }

    public function decideMany(SubjectCollection $subjects, Context $context): SubjectDecisionCollection
    {
        $rules = $this->repository->findActiveForSubjects($subjects);
        $results = [];

        foreach ($subjects as $subject) {
            $results[] = new SubjectDecisionResult(
                $subject,
                $this->evaluator->evaluate($subject, $context, $rules),
            );
        }

        return new SubjectDecisionCollection(...$results);
    }
}
