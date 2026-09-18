<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Evaluation\Contract\EligibilityEvaluationServiceInterface;
use Maatify\Eligibility\Evaluation\Result\SubjectDecisionCollection;
use Maatify\Eligibility\Evaluation\Value\Context;
use Maatify\Eligibility\Common\Value\SubjectCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class HostBoundaryContractTest extends TestCase
{
    #[Test]
    public function batchEvaluationAcceptsHostCandidatesInsteadOfOwningGlobalPagination(): void
    {
        $contract = new ReflectionClass(EligibilityEvaluationServiceInterface::class);
        $decideMany = $contract->getMethod('decideMany');

        $subjectType = $decideMany->getParameters()[0]->getType();
        $contextType = $decideMany->getParameters()[1]->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $subjectType);
        self::assertInstanceOf(ReflectionNamedType::class, $contextType);
        self::assertSame(SubjectCollection::class, $subjectType->getName());
        self::assertSame(Context::class, $contextType->getName());

        $returnType = $decideMany->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(SubjectDecisionCollection::class, $returnType->getName());

        self::assertFalse($contract->hasMethod('getEligibleSubjectIds'));
        self::assertFalse($contract->hasMethod('getIneligibleSubjectIds'));
        self::assertFalse($contract->hasMethod('paginateEligibleSubjects'));
    }
}
