<?php

declare(strict_types=1);

namespace Maatify\Eligibility\ValueObject;

use JsonSerializable;
use Maatify\Eligibility\Common\CanonicalString;

/**
 * Immutable external identity supplied by a Host application.
 *
 * Eligibility stores the two canonical identity components but does not verify
 * that the referenced Host record exists or remains meaningful.
 */
final readonly class Subject implements JsonSerializable
{
    public string $subjectType;

    public string $subjectId;

    /** Rejects non-string, malformed, empty, padded, or over-limit Host identities without coercion. */
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
