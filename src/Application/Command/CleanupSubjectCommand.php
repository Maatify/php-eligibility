<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Command;

use Maatify\Eligibility\Value\Subject;

final readonly class CleanupSubjectCommand
{
    public function __construct(public Subject $subject)
    {
    }
}
