<?php

declare(strict_types=1);

// Run standalone checks without installing iTop.
try {
    $tests = require __DIR__ . '/lifecycle.php';
    $tests = array_merge($tests, require __DIR__ . '/installer.php');
    $tests = array_merge($tests, require __DIR__ . '/schemas.php');
    $tests = array_merge($tests, require __DIR__ . '/prepare-release.php');
    if (!is_array($tests) || $tests === []) {
        throw new RuntimeException('No tests found.');
    }
    $failures = 0;
    foreach ($tests as $name => $test) {
        try {
            $test();
            echo "PASS: $name\n";
        } catch (Throwable $error) {
            $failures++;
            fwrite(STDERR, "FAIL: $name: {$error->getMessage()}\n");
        }
    }
    echo count($tests) . " tests, $failures failures\n";
    exit($failures === 0 ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, "Test setup failed: {$error->getMessage()}\n");
    exit(1);
}
