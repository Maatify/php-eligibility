<?php

declare(strict_types=1);

$packageRoot = realpath(__DIR__ . '/..');
if ($packageRoot === false) {
    fail('Package root could not be resolved.');
}

$fixtureComposerPath = __DIR__ . '/composer.json';
$fixtureComposer = file_get_contents($fixtureComposerPath);
if ($fixtureComposer === false) {
    fail('Consumer Composer fixture could not be read.');
}

$decodedComposer = json_decode($fixtureComposer, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($decodedComposer)) {
    fail('Consumer Composer fixture must decode to an object.');
}
$repositories = $decodedComposer['repositories'] ?? null;
if (!is_array($repositories) || !isset($repositories[0]) || !is_array($repositories[0])) {
    fail('Consumer Composer fixture is missing its path repository.');
}
$repositories[0]['url'] = $packageRoot;
$decodedComposer['repositories'] = $repositories;

$phpBinary = PHP_BINARY;
$composerBinary = getenv('COMPOSER_BIN');
$composerBinary = is_string($composerBinary) && $composerBinary !== '' ? $composerBinary : 'composer';

for ($run = 1; $run <= 2; $run++) {
    $runRoot = sys_get_temp_dir() . '/maatify-eligibility-consumer-' . bin2hex(random_bytes(12));
    if (!mkdir($runRoot . '/bin', 0700, true) && !is_dir($runRoot . '/bin')) {
        fail('Could not create a clean consumer root.');
    }

    $failure = null;
    try {
        $environment = getenv();
        $environment['COMPOSER_CACHE_DIR'] = $runRoot . '/composer-cache';
        $environment['ELIGIBILITY_HARNESS_RUN_ID'] = 'b5-' . $run . '-' . bin2hex(random_bytes(6));
        $consumerComposer = json_encode(
            $decodedComposer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;
        writeFile($runRoot . '/composer.json', $consumerComposer);
        if (!copy(__DIR__ . '/bin/verify.php', $runRoot . '/bin/verify.php')) {
            fail('Could not copy the consumer verification entry point.');
        }

        runCommand(
            [$composerBinary, 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress'],
            $runRoot,
            $runRoot . '/composer.stdout.log',
            $runRoot . '/composer.stderr.log',
            $environment,
        );

        $installedPackage = $runRoot . '/vendor/maatify/php-eligibility';
        if (!is_dir($installedPackage) || is_link($installedPackage)) {
            fail('Composer did not produce a copied package dependency.');
        }

        runCommand(
            [$phpBinary, $runRoot . '/bin/verify.php'],
            $runRoot,
            $runRoot . '/verify.stdout.log',
            $runRoot . '/verify.stderr.log',
            $environment,
        );

        echo 'HARNESS_RUN=' . $run . ' RESULT=PASS' . PHP_EOL;
    } catch (Throwable $exception) {
        $failure = $exception;
    } finally {
        removeTree($runRoot);
    }

    if ($failure !== null) {
        throw $failure;
    }
    if (file_exists($runRoot)) {
        fail('Clean consumer root residue remained after the run.');
    }
}

echo "HARNESS_RESULT=PASS RUNS=2 CLEAN_STATES=2\n";

/**
 * @param list<string> $command
 * @param array<string, mixed>|null $environment
 */
function runCommand(
    array $command,
    string $workingDirectory,
    string $stdoutPath,
    string $stderrPath,
    ?array $environment = null,
): void {
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $stdoutPath, 'w'],
        2 => ['file', $stderrPath, 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $workingDirectory, $environment);
    if (!is_resource($process)) {
        fail('Could not start command: ' . json_encode($command, JSON_THROW_ON_ERROR));
    }

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $stdout = file_get_contents($stdoutPath) ?: '';
        $stderr = file_get_contents($stderrPath) ?: '';
        fail(sprintf(
            "Command failed with exit code %d: %s\nstdout:\n%s\nstderr:\n%s",
            $exitCode,
            json_encode($command, JSON_THROW_ON_ERROR),
            $stdout,
            $stderr,
        ));
    }
}

function writeFile(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) === false) {
        fail('Could not write clean consumer file: ' . $path);
    }
}

function removeTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            fail('Could not remove consumer residue: ' . $path);
        }

        return;
    }
    if (!is_dir($path)) {
        return;
    }

    $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $item) {
        $child = $item instanceof SplFileInfo ? $item->getPathname() : (string) $item;
        if (is_dir($child) && !is_link($child)) {
            removeTree($child);
            continue;
        }
        if (!unlink($child)) {
            fail('Could not remove consumer residue: ' . $child);
        }
    }

    if (!rmdir($path)) {
        fail('Could not remove clean consumer root: ' . $path);
    }
}

function fail(string $message): never
{
    throw new RuntimeException($message);
}
