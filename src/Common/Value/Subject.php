<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Common\Value;

use JsonSerializable;
use Maatify\Eligibility\Common\Validation\CanonicalString;

final readonly class Subject implements JsonSerializable
{
    public string $subjectType;

    public string $subjectId;

    public function __construct(mixed $subjectType, mixed $subjectId)
    {
        $this->subjectType = CanonicalString::validateSubjectType($subjectType);
        $this->subjectId = CanonicalString::validateSubjectId($subjectId);
    }

    /** @return array{subjectType: string, subjectId: string} */
    public function jsonSerialize(): array
    {
        return [
            'subjectType' => $this->subjectType,
            'subjectId' => $this->subjectId,
        ];
    }
}
