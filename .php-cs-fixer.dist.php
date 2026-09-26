<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// Normative baseline: PHP-FIG PER Coding Style 3.1.
// @PER-CS3x0 is the stable PHP-CS-Fixer implementation of the PER 3.x family.
$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/examples',
        __DIR__ . '/tools',
        __DIR__ . '/consumer-harness',
    ])
    ->append([__FILE__]);

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS3x0' => true,
    ])
    ->setFinder($finder);
