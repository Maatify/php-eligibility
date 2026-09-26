<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Repository;

use Maatify\Eligibility\Management\Criteria\ActiveDimensionKeysCriteria;
use Maatify\Eligibility\Management\Criteria\RuleCriteria;
use Maatify\Eligibility\Management\DTO\ActiveDimensionKeyCollectionDTO;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\ValueObject\RuleCollection;
use Maatify\Eligibility\ValueObject\RuleIdentity;

interface RuleManagementQueryInterface
{
    /** Includes both active and inactive Rules. */
    public function findByIdentity(RuleIdentity $identity): ?Rule;

    /** Applies the bounded management criteria, including lifecycle filtering. */
    public function findByCriteria(RuleCriteria $criteria): RuleCollection;

    /** Returns active dimension keys for one Subject in canonical order. */
    public function findActiveDimensionKeys(ActiveDimensionKeysCriteria $query): ActiveDimensionKeyCollectionDTO;
}
