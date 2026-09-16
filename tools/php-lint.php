<?php

declare(strict_types=1);

/*
 * Maintained PHP syntax-lint command for maatify/php-eligibility.
 *
 * Usage:
 *   php tools/php-lint.php [<directory> ...]
 *
 * With no arguments, lints every package-owned PHP path (src, tests,
 * consumer-harness, and this tools root). vendor/ is never scanned. A non-zero
 * exit code indicates at least one syntax error; CI and the local aggregate
 * gate rely on this fail-closed behavior.
 */

$roots = array_slice($argv, 1);
if ($roots === []) {
    $roots = [
        __DIR__ . '/../src',
        __DIR__ . '/../tests',
        __DIR__ . '/../consumer-harness',
        __DIR__,
    ];
}

$discovered = 0;
$failures = [];

foreach ($roots as $root) {
    $path = realpath($root);
    if ($path === false || !is_dir($path)) {
        fwrite(STDERR, sprintf("error: PHP lint root is not a directory: %s\n", $root));
        exit(1);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
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