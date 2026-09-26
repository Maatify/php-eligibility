<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Repository;

use Maatify\Eligibility\ValueObject\SubjectCollection;
use Maatify\Eligibility\ValueObject\RuleCollection;

/**
 * Evaluation read boundary for loading active Rules for requested external Subjects.
 * Implementations own persistence access; callers receive no inactive Rules.
 */
interface ActiveRuleReaderInterface
{
    /** Loads active Rules for all supplied Subjects in a bounded bulk operation. */
    public function findActiveForSubjects(SubjectCollection $subjects): RuleCollection;
}
