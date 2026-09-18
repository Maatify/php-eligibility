<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use Maatify\Eligibility\Common\Value\Subject;

final readonly class CleanupSubjectCommand
{
    public function __construct(public Subject $subject)
    {
    }
}
