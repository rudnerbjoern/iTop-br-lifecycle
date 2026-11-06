<?php

declare(strict_types=1);

/** Validate all inputs before producing either updated file. */
function prepareReleaseContents(string $manifest, string $module, string $version, string $date): array
{
    if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version)) {
        throw new RuntimeException('Version must use X.Y.Z format without leading zeros.');
    }
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
        throw new RuntimeException('Date must be a valid YYYY-MM-DD date.');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($manifest, SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false || $xml->getName() !== 'extension'
            || count($xml->version) !== 1 || count($xml->release_date) !== 1
            || trim((string) $xml->extension_code) !== 'br-lifecycle') {
            throw new RuntimeException('Expected one version and release_date in the lifecycle extension manifest.');
        }
        $currentVersion = trim((string) $xml->version);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $modulePattern = "~'br-lifecycle/([^']+)'~";
    if (preg_match_all($modulePattern, $module, $matches) !== 1) {
        throw new RuntimeException('Expected exactly one lifecycle module version.');
    }
    if ($matches[1][0] !== $currentVersion) {
        throw new RuntimeException('Extension and module versions must match before preparing a release.');
    }
    if (!version_compare($version, $currentVersion, '>')) {
        throw new RuntimeException('The release version must be newer than ' . $currentVersion . '.');
    }
    foreach (['version' => $version, 'release_date' => $date] as $element => $value) {
        $manifest = preg_replace_callback(
            '~<' . $element . '>.*?</' . $element . '>~s',
            static fn (): string => "<$element><![CDATA[$value]]></$element>",
            $manifest,
            -1,
            $count
        );
        if ($manifest === null || $count !== 1) {
            throw new RuntimeException('Expected exactly one editable ' . $element . ' element.');
        }
    }
    $module = preg_replace_callback(
        $modulePattern,
        static fn (): string => "'br-lifecycle/$version'",
        $module
    );
    return [$manifest, $module];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if ($argc < 2 || $argc > 3) {
            throw new RuntimeException('Usage: php scripts/prepare-release.php X.Y.Z [YYYY-MM-DD]');
        }
        $date = $argv[2] ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
        $root = dirname(__DIR__);
        $paths = [$root . '/extension.xml', $root . '/br-lifecycle/module.br-lifecycle.php'];
        $originals = [];
        foreach ($paths as $path) {
            $content = file_get_contents($path);
            if ($content === false || !is_writable($path)) {
                throw new RuntimeException('Cannot read and write ' . $path);
            }
            $originals[] = $content;
        }
        $updated = prepareReleaseContents($originals[0], $originals[1], $argv[1], $date);
        try {
            foreach ($paths as $index => $path) {
                if (file_put_contents($path, $updated[$index], LOCK_EX) !== strlen($updated[$index])) {
                    throw new RuntimeException('Cannot write ' . $path);
                }
            }
        } catch (Throwable $error) {
            foreach ($paths as $index => $path) {
                if (file_put_contents($path, $originals[$index], LOCK_EX) !== strlen($originals[$index])) {
                    fwrite(STDERR, "Could not restore $path; inspect it manually.\n");
                }
            }
            throw $error;
        }
        echo "Prepared version {$argv[1]} with release date $date. Running tests...\n";
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/run.php'), $status);
        if ($status !== 0) {
            fwrite(STDERR, "Tests failed. Version edits are retained for inspection; do not publish yet.\n");
        }
        exit($status);
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
}
