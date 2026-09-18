<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Common\Value\SubjectCollection;
use Maatify\Eligibility\Rule\RuleCollection;

interface ActiveRuleReaderInterface
{
    /** Loads active Rules for all supplied Subjects in a bounded bulk operation. */
    public function findActiveForSubjects(SubjectCollection $subjects): RuleCollection;
}
