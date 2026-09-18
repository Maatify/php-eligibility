<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Result;

use JsonSerializable;
use Maatify\Eligibility\Evaluation\Decision\EligibilityDecision;
use Maatify\Eligibility\Common\Value\Subject;

final readonly class SubjectDecisionResult implements JsonSerializable
{
    public function __construct(
        public Subject $subject,
        public EligibilityDecision $decision,
    ) {
    }

    /** @return array{subject: Subject, decision: EligibilityDecision} */
    public function jsonSerialize(): array
    {
        return [
            'subject' => $this->subject,
            'decision' => $this->decision,
        ];
    }
}
