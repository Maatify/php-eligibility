<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Service;

use Maatify\Eligibility\Application\Result\SubjectDecisionCollection;
use Maatify\Eligibility\Decision\EligibilityDecision;
use Maatify\Eligibility\Value\Context;
use Maatify\Eligibility\Value\Subject;
use Maatify\Eligibility\Value\SubjectCollection;

interface EligibilityEvaluationServiceInterface
{
    public function decide(Subject $subject, Context $context): EligibilityDecision;

    public function decideMany(SubjectCollection $subjects, Context $context): SubjectDecisionCollection;
}
