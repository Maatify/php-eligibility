<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use Maatify\Eligibility\ValueObject\Subject;

/** Requests atomic removal of all package-owned Rule state for one Subject. */
final readonly class CleanupSubjectCommand
{
    public function __construct(public Subject $subject) {}
}
