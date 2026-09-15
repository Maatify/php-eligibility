<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use InvalidArgumentException;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Value\Subject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuleModelTest extends TestCase
{
    #[Test]
    public function naturalIdentityExcludesEffectAndLifecycle(): void
    {
        $activeAllow = Rule::active(new Subject('product', '150'), 'country', 'EG', RuleEffectEnum::ALLOW);
        $inactiveDeny = $activeAllow
            ->withEffect(RuleEffectEnum::DENY)
            ->withLifecycle(RuleLifecycleEnum::INACTIVE);

        self::assertTrue($activeAllow->naturalIdentity()->equals($inactiveDeny->naturalIdentity()));
        self::assertSame(RuleEffectEnum::ALLOW, $activeAllow->effect);
        self::assertSame(RuleLifecycleEnum::INACTIVE, $inactiveDeny->lifecycle);
    }

    #[Test]
    public function effectAndLifecycleMutationsRemainOrthogonal(): void
    {
        $rule = Rule::active(new Subject('product', '150'), 'country', 'EG', RuleEffectEnum::ALLOW);

        $inactive = $rule->withLifecycle(RuleLifecycleEnum::INACTIVE);
        $changedEffect = $inactive->withEffect(RuleEffectEnum::DENY);

        self::assertSame(RuleLifecycleEnum::INACTIVE, $changedEffect->lifecycle);
        self::assertSame(RuleEffectEnum::DENY, $changedEffect->effect);
    }

    #[Test]
    public function ruleCollectionsRejectDuplicateNaturalIdentities(): void
    {
        $subject = new Subject('product', '150');

        $this->expectException(InvalidArgumentException::class);

        new RuleCollection(
            Rule::active($subject, 'country', 'EG', RuleEffectEnum::ALLOW),
            Rule::active($subject, 'country', 'EG', RuleEffectEnum::DENY),
        );
    }

    #[Test]
    public function ruleCollectionsUseCanonicalNaturalIdentityOrdering(): void
    {
        $subject = new Subject('product', '150');
        $rules = new RuleCollection(
            Rule::active($subject, 'country', 'SA', RuleEffectEnum::ALLOW),
            Rule::active($subject, 'country', 'EG', RuleEffectEnum::ALLOW),
            Rule::active($subject, 'customer_type', 'retail', RuleEffectEnum::DENY),
        );

        $ordered = $rules->items();

        self::assertSame('country', $ordered[0]->dimensionKey);
        self::assertSame('EG', $ordered[0]->dimensionValue);
        self::assertSame('SA', $ordered[1]->dimensionValue);
        self::assertSame('customer_type', $ordered[2]->dimensionKey);
    }
}
