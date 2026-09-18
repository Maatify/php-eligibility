<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Contract;

use Maatify\Eligibility\Evaluation\Result\SubjectDecisionCollection;
use Maatify\Eligibility\Evaluation\Decision\EligibilityDecision;
use Maatify\Eligibility\Evaluation\Value\Context;
use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Common\Value\SubjectCollection;

interface EligibilityEvaluationServiceInterface
{
    public function decide(Subject $subject, Context $context): EligibilityDecision;

    public function decideMany(SubjectCollection $subjects, Context $context): SubjectDecisionCollection;
}
