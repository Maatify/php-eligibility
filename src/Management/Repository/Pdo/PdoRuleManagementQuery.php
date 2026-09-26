<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Repository\Pdo;

use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\Management\Criteria\ActiveDimensionKeysCriteria;
use Maatify\Eligibility\Management\Criteria\RuleCriteria;
use Maatify\Eligibility\Management\DTO\ActiveDimensionKeyCollectionDTO;
use Maatify\Eligibility\Management\Repository\RuleManagementQueryInterface;
use Maatify\Eligibility\Repository\Pdo\PdoRuleHydrationTrait;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\ValueObject\RuleCollection;
use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use PDO;

final class PdoRuleManagementQuery implements RuleManagementQueryInterface
{
    use PdoRuleHydrationTrait;

    private const TABLE = 'maa_eligibility_rules';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByIdentity(RuleIdentity $identity): ?Rule
    {
        $rows = $this->fetchRows(
            'SELECT `subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle` '
            . 'FROM `' . self::TABLE . '` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? AND `dimension_value` = ? '
            . 'LIMIT 1',
            [
                CanonicalString::validateSubjectType($identity->subjectType),
                CanonicalString::validateSubjectId($identity->subjectId),
                CanonicalString::validateDimensionKey($identity->dimensionKey),
                CanonicalString::validateDimensionValue($identity->dimensionValue),
            ],
        );

        $row = $rows[0] ?? null;

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByCriteria(RuleCriteria $criteria): RuleCollection
    {
        $conditions = [
            '`subject_type` = ?',
            '`subject_id` = ?',
        ];
        $parameters = [
            CanonicalString::validateSubjectType($criteria->subject->subjectType),
            CanonicalString::validateSubjectId($criteria->subject->subjectId),
        ];

        if ($criteria->dimensionKey !== null) {
            $conditions[] = '`dimension_key` = ?';
            $parameters[] = CanonicalString::validateDimensionKey($criteria->dimensionKey);
        }

        if ($criteria->lifecycle !== null) {
            $conditions[] = '`lifecycle` = ?';
            $parameters[] = $criteria->lifecycle->value;
        }

        $parameters[] = $criteria->maxResults;
        $rows = $this->fetchRows(
            'SELECT `subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle` '
            . 'FROM `' . self::TABLE . '` WHERE ' . implode(' AND ', $conditions) . ' '
            . 'ORDER BY `subject_type`, `subject_id`, `dimension_key`, `dimension_value` LIMIT ?',
            $parameters,
        );

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = $this->hydrate($row);
        }

        return new RuleCollection(...$rules);
    }

    public function findActiveDimensionKeys(ActiveDimensionKeysCriteria $query): ActiveDimensionKeyCollectionDTO
    {
        $rows = $this->fetchRows(
            'SELECT DISTINCT `dimension_key` FROM `' . self::TABLE . '` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `lifecycle` = ?',
            [
                CanonicalString::validateSubjectType($query->subject->subjectType),
                CanonicalString::validateSubjectId($query->subject->subjectId),
                RuleLifecycleEnum::ACTIVE->value,
            ],
        );

        $dimensionKeys = [];
        foreach ($rows as $row) {
            $dimensionKeys[] = CanonicalString::validateDimensionKey(
                $this->rowString($row, 'dimension_key'),
            );
        }

        return new ActiveDimensionKeyCollectionDTO(...$dimensionKeys);
    }
}
