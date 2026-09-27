<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Service;

use Maatify\Eligibility\Evaluation\DTO\SubjectDecisionCollectionDTO;
use Maatify\Eligibility\Evaluation\ValueObject\EligibilityDecision;
use Maatify\Eligibility\Evaluation\ValueObject\Context;
use Maatify\Eligibility\ValueObject\Subject;
use Maatify\Eligibility\ValueObject\SubjectCollection;

/**
 * Public evaluation boundary for single-Subject and bulk eligibility decisions.
 */
interface EligibilityEvaluationServiceInterface
{
    /** Returns unrestricted eligibility when the Subject has no active Rules. */
    public function decide(Subject $subject, Context $context): EligibilityDecision;

    /**
     * Evaluates all Subjects from one bulk read and returns results in input order.
     */
    public function decideMany(SubjectCollection $subjects, Context $context): SubjectDecisionCollectionDTO;
}
