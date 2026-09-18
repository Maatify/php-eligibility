<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Common\Validation\CanonicalString;
use Maatify\Eligibility\Common\Value\SubjectCollection;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use PDO;

final class PdoActiveRuleReader implements ActiveRuleReaderInterface
{
    use PdoRuleHydrationTrait;

    private const TABLE = 'maa_eligibility_rules';

    private const BULK_SUBJECT_CHUNK_SIZE = 100;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findActiveForSubjects(SubjectCollection $subjects): RuleCollection
    {
        $subjectItems = $subjects->items();
        if ($subjectItems === []) {
            return new RuleCollection();
        }

        $rules = [];
        $subjectCount = count($subjectItems);
        for ($offset = 0; $offset < $subjectCount; $offset += self::BULK_SUBJECT_CHUNK_SIZE) {
            $chunk = array_slice($subjectItems, $offset, self::BULK_SUBJECT_CHUNK_SIZE);
            $subjectConditions = [];
            $parameters = [RuleLifecycleEnum::ACTIVE->value];

            foreach ($chunk as $subject) {
                $subjectConditions[] = '(`subject_type` = ? AND `subject_id` = ?)';
                $parameters[] = CanonicalString::validateSubjectType($subject->subjectType);
                $parameters[] = CanonicalString::validateSubjectId($subject->subjectId);
            }

            $rows = $this->fetchRows(
                'SELECT `subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle` '
                . 'FROM `' . self::TABLE . '` WHERE `lifecycle` = ? AND ('
                . implode(' OR ', $subjectConditions) . ') '
                . 'ORDER BY `subject_type`, `subject_id`, `dimension_key`, `dimension_value`',
                $parameters,
            );

            foreach ($rows as $row) {
                $rules[] = $this->hydrate($row);
            }
        }

        return new RuleCollection(...$rules);
    }
}
