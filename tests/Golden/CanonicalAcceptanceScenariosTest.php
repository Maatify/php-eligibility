<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Golden;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class CanonicalAcceptanceScenariosTest extends TestCase
{
    #[Test]
    public function allCanonicalScenariosHaveExecutableEvidence(): void
    {
        $scenarios = CanonicalAcceptanceEvidenceMap::scenarios();
        $ids = array_map(static fn (array $scenario): int => $scenario['id'], $scenarios);

        self::assertSame(range(1, 52), $ids);
        self::assertCount(52, $scenarios);

        foreach ($scenarios as $scenario) {
            self::assertNotSame('', $scenario['behavior']);
            self::assertNotSame([], $scenario['evidence']);

            foreach ($scenario['evidence'] as $evidence) {
                $this->assertExecutableTestReference($evidence['class'], $evidence['method']);
                self::assertContains($evidence['layer'], ['unit', 'integration', 'concurrency', 'regression']);
            }
        }
    }

    /** @param class-string $class */
    private function assertExecutableTestReference(string $class, string $method): void
    {
        self::assertTrue(class_exists($class), sprintf('Evidence class %s does not exist.', $class));
        $testClass = new ReflectionClass($class);
        self::assertTrue($testClass->isSubclassOf(TestCase::class));
        self::assertTrue($testClass->hasMethod($method), sprintf('Evidence method %s::%s does not exist.', $class, $method));

        $testMethod = $testClass->getMethod($method);
        self::assertTrue($testMethod->isPublic());
        self::assertNotSame([], $testMethod->getAttributes(Test::class));
        $returnType = $testMethod->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('void', $returnType->getName());
        $testsRoot = realpath(__DIR__ . '/..');
        $testFile = $testClass->getFileName();
        if (!is_string($testsRoot) || !is_string($testFile)) {
            self::fail('Evidence test source could not be resolved.');
        }
        self::assertTrue(str_starts_with($testFile, $testsRoot));
    }
}
