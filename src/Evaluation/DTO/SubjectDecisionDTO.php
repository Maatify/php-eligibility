<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\DTO;

use JsonSerializable;
use Maatify\Eligibility\Evaluation\ValueObject\EligibilityDecision;
use Maatify\Eligibility\ValueObject\Subject;

final readonly class SubjectDecisionDTO implements JsonSerializable
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
