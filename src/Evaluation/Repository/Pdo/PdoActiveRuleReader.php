<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Repository\Pdo;

use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\Evaluation\Repository\ActiveRuleReaderInterface;
use Maatify\Eligibility\Repository\Pdo\PdoRuleHydrationTrait;
use Maatify\Eligibility\ValueObject\SubjectCollection;
use Maatify\Eligibility\ValueObject\RuleCollection;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use PDO;

/**
 * Direct-PDO reader for active Rules used by evaluation.
 *
 * Subject batches are split into bounded chunks and returned in canonical
 * database order; an empty SubjectCollection produces an empty RuleCollection
 * without issuing a query.
 */
final class PdoActiveRuleReader implements ActiveRuleReaderInterface
{
    use PdoRuleHydrationTrait;

    private const TABLE = 'maa_eligibility_rules';

    private const BULK_SUBJECT_CHUNK_SIZE = 100;

    public function __construct(private readonly PDO $pdo) {}

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
