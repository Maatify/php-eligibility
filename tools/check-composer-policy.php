<?php

declare(strict_types=1);

$path = dirname(__DIR__) . '/composer.json';
$contents = file_get_contents($path);
if ($contents === false) {
    fail('composer.json could not be read.');
}
$decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($decoded)) {
    fail('composer.json must decode to an object.');
}
/** @var array<string, mixed> $composer */
$composer = $decoded;
$config = $composer['config'] ?? null;
if (!is_array($config)) {
    fail('config must be an object.');
}
$policy = $config['policy'] ?? null;
if (!is_array($policy) || $policy === []) {
    fail('config.policy must be present and enabled.');
}
$advisories = $policy['advisories'] ?? null;
$malware = $policy['malware'] ?? null;
$abandoned = $policy['abandoned'] ?? null;
if (!is_array($advisories) || ($advisories['block'] ?? null) !== true || ($advisories['audit'] ?? null) !== 'fail') {
    fail('advisories policy must block and audit with fail.');
}
if (!is_array($malware) || ($malware['block'] ?? null) !== true || ($malware['block-scope'] ?? null) !== 'all' || ($malware['audit'] ?? null) !== 'fail') {
    fail('malware policy must block all and audit with fail.');
}
if (!is_array($abandoned) || ($abandoned['block'] ?? null) !== false || ($abandoned['audit'] ?? null) !== 'fail') {
    fail('abandoned policy must allow installation but audit with fail.');
}
if (array_key_exists('audit', $config)) {
    fail('config.audit is not permitted; policy owns the contract.');
}
$forbiddenKeys = ['ignore', 'ignore-severity', 'ignore-unreachable', 'ignored-advisories', 'ignored-packages', 'ignored-severities', 'malware-ignore', 'abandoned-ignore'];
if (findForbiddenKey($composer, $forbiddenKeys) !== null) {
    fail('Composer policy weakening or ignore configuration is not permitted.');
}
$overrides = [
    'COMPOSER_POLICY' => ['0'],
    'COMPOSER_NO_BLOCKING' => ['1'],
    'COMPOSER_POLICY_ADVISORIES_BLOCK' => ['0'],
    'COMPOSER_POLICY_MALWARE_BLOCK' => ['0'],
    'COMPOSER_NO_AUDIT' => ['1'],
    'COMPOSER_AUDIT_ABANDONED' => ['ignore', 'report'],
];
foreach ($overrides as $name => $values) {
    $value = getenv($name);
    if (is_string($value) && in_array(strtolower($value), $values, true)) {
        fail(sprintf('Environment override %s=%s weakens required policy.', $name, $value));
    }
}
echo "COMPOSER_POLICY=PASS\n";

/** @param list<string> $keys */
function findForbiddenKey(mixed $value, array $keys): ?string
{
    if (!is_array($value)) {
        return null;
    }
    foreach ($value as $key => $child) {
        if (is_string($key) && in_array(strtolower($key), $keys, true)) {
            return $key;
        }
        $found = findForbiddenKey($child, $keys);
        if ($found !== null) {
            return $found;
        }
    }
    return null;
}

function fail(string $message): never
{
    fwrite(STDERR, 'COMPOSER_POLICY_ERROR=' . $message . PHP_EOL);
    exit(1);
}
