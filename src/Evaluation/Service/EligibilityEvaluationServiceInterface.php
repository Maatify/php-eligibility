<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Service;

use Maatify\Eligibility\Evaluation\DTO\SubjectDecisionCollectionDTO;
use Maatify\Eligibility\Evaluation\ValueObject\EligibilityDecision;
use Maatify\Eligibility\Evaluation\ValueObject\Context;
use Maatify\Eligibility\ValueObject\Subject;
use Maatify\Eligibility\ValueObject\SubjectCollection;

interface EligibilityEvaluationServiceInterface
{
    public function decide(Subject $subject, Context $context): EligibilityDecision;

    public function decideMany(SubjectCollection $subjects, Context $context): SubjectDecisionCollectionDTO;
}
