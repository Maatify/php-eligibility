<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Query;

use Maatify\Eligibility\Common\Value\Subject;

final readonly class ActiveDimensionKeysQuery
{
    public function __construct(public Subject $subject)
    {
    }
}
