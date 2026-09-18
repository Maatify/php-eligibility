<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Management\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Management\Result\ActiveDimensionKeyCollection;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleIdentity;

interface RuleManagementQueryInterface
{
    /** Includes both active and inactive Rules. */
    public function findByIdentity(RuleIdentity $identity): ?Rule;

    /** Applies the bounded management criteria, including lifecycle filtering. */
    public function findByCriteria(RuleCriteria $criteria): RuleCollection;

    /** Returns active dimension keys for one Subject in canonical order. */
    public function findActiveDimensionKeys(ActiveDimensionKeysQuery $query): ActiveDimensionKeyCollection;
}
