<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Query;

use Maatify\Eligibility\Value\Subject;

final readonly class ActiveDimensionKeysQuery
{
    public function __construct(public Subject $subject)
    {
    }
}
