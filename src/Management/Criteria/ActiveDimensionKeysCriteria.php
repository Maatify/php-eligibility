<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Criteria;

use Maatify\Eligibility\ValueObject\Subject;

final readonly class ActiveDimensionKeysCriteria
{
    public function __construct(public Subject $subject)
    {
    }
}
