<?php

namespace Maatify\Eligibility\Tests\Support;

use Maatify\Eligibility\Application\Command\CreateRuleCommand;
use Maatify\Eligibility\Application\Query\RuleCriteria;
use Maatify\Eligibility\Ordering\CanonicalOrdering;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Value\ContextValueCollection;
use Maatify\Eligibility\Value\Subject;

final class NonStrictConsumer
{
    public static function contains(ContextValueCollection $values, mixed $value): bool
    {
        return $values->contains($value);
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return CanonicalOrdering::compareStrings($left, $right);
    }

    public static function createRuleCommand(
        Subject $subject,
        mixed $dimensionKey,
        mixed $dimensionValue,
    ): CreateRuleCommand {
        return new CreateRuleCommand($subject, $dimensionKey, $dimensionValue, RuleEffectEnum::ALLOW);
    }

    public static function ruleCriteria(
        Subject $subject,
        mixed $dimensionKey,
        mixed $maxResults,
    ): RuleCriteria {
        return new RuleCriteria($subject, $dimensionKey, null, $maxResults);
    }
}
