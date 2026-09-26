<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Repository;

use Maatify\Eligibility\ValueObject\SubjectCollection;
use Maatify\Eligibility\ValueObject\RuleCollection;

interface ActiveRuleReaderInterface
{
    /** Loads active Rules for all supplied Subjects in a bounded bulk operation. */
    public function findActiveForSubjects(SubjectCollection $subjects): RuleCollection;
}
