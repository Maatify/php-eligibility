<?php

declare(strict_types=1);

/*
 * Maintained PHP syntax-lint command for maatify/php-eligibility.
 *
 * Usage:
 *   php tools/php-lint.php [<directory> ...]
 *
 * With no arguments, lints every package-owned PHP path (src, tests, examples,
 * consumer-harness, this tools root, and the PHP-CS-Fixer configuration).
 * vendor/ is never scanned. A non-zero exit code indicates at least one syntax
 * error; CI and the local aggregate gate rely on this fail-closed behavior.
 */

$roots = array_slice($argv ?? [], 1);
if ($roots === []) {
    $roots = [
        __DIR__ . '/../src',
        __DIR__ . '/../tests',
        __DIR__ . '/../examples',
        __DIR__ . '/../consumer-harness',
        __DIR__,
        __DIR__ . '/../.php-cs-fixer.dist.php',
    ];
}

$discovered = 0;
$failures = [];

foreach ($roots as $root) {
    $path = realpath($root);
    if ($path === false || (!is_dir($path) && !is_file($path))) {
        fwrite(STDERR, sprintf("error: PHP lint path is not a file or directory: %s\n", $root));
        exit(1);
    }

    $files = is_file($path)
        ? [new SplFileInfo($path)]
        : new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );
    foreach ($files as $file) {
        if (!($file instanceof SplFileInfo) || $file->getExtension() !== 'php') {
            continue;
        }

        $discovered++;
        $output = [];
        $exitCode = 0;
        exec(sprintf(
            '%s -l %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($file->getPathname()),
        ), $output, $exitCode);

        if ($exitCode !== 0) {
            $failures[] = $file->getPathname() . PHP_EOL . implode(PHP_EOL, $output);
        }
    }
}

if ($discovered === 0) {
    fwrite(STDERR, "error: no PHP files found to lint.\n");
    exit(1);
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    fwrite(STDERR, sprintf("PHP syntax lint failed for %d file(s).\n", count($failures)));
    exit(1);
}

printf("PHP syntax lint passed: %d file(s) checked.\n", $discovered);
