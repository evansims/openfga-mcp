#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Configure fuzzing parameters
$maxIterations = 10000;
$timeout = (int) ($_ENV['FUZZING_DURATION'] ?? 300);
$corpusDir = __DIR__ . '/corpus';
$crashDir = __DIR__ . '/crashes';

// Ensure directories exist
if (! is_dir($corpusDir)) {
    mkdir($corpusDir, 0o755, true);
}

if (! is_dir($crashDir)) {
    mkdir($crashDir, 0o755, true);
}

// Get all fuzzing targets
$targetFiles = glob(__DIR__ . '/Targets/*Target.php');
$exitCode = 0;
$totalCrashes = 0;

echo "Starting fuzzing session for {$timeout} seconds...\n";
echo 'Found ' . count($targetFiles) . " fuzzing targets\n\n";

// Simple fuzzing implementation since php-fuzzer is meant to be used as CLI tool
function generateRandomInput(int $maxLen = 1000): string
{
    $len = random_int(0, $maxLen);
    $input = '';

    // Mix of different input types
    $strategy = random_int(0, 4);

    switch ($strategy) {
        case 0: // Random bytes
            for ($i = 0; $i < $len; $i++) {
                $input .= chr(random_int(0, 255));
            }

            break;

        case 1: // Printable ASCII
            for ($i = 0; $i < $len; $i++) {
                $input .= chr(random_int(32, 126));
            }

            break;

        case 2: // Alphanumeric with special chars
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';

            for ($i = 0; $i < $len; $i++) {
                $input .= $chars[random_int(0, strlen($chars) - 1)];
            }

            break;

        case 3: // Unicode mix
            for ($i = 0; $i < $len / 3; $i++) {
                $input .= mb_chr(random_int(0x80, 0x10FFFF));
            }

            break;

        case 4: // Structured data patterns
            $patterns = [
                'user:' . str_repeat('a', random_int(0, 100)) . '#reader@doc:test',
                'model\n  schema 1.1\ntype ' . str_repeat('x', random_int(0, 50)),
                str_repeat('nested(', random_int(0, 20)) . 'value' . str_repeat(')', random_int(0, 20)),
                '{"key":"' . str_repeat('v', random_int(0, 100)) . '"}',
            ];
            $input = $patterns[random_int(0, count($patterns) - 1)];

            break;
    }

    return $input;
}

function mutateInput(string $input): string
{
    if (empty($input)) {
        return generateRandomInput();
    }

    $strategy = random_int(0, 5);

    switch ($strategy) {
        case 0: // Bit flip
            $pos = random_int(0, strlen($input) - 1);
            $input[$pos] = chr(ord($input[$pos]) ^ (1 << random_int(0, 7)));

            break;

        case 1: // Insert random byte
            $pos = random_int(0, strlen($input));
            $input = substr($input, 0, $pos) . chr(random_int(0, 255)) . substr($input, $pos);

            break;

        case 2: // Delete byte
            if (1 < strlen($input)) {
                $pos = random_int(0, strlen($input) - 1);
                $input = substr($input, 0, $pos) . substr($input, $pos + 1);
            }

            break;

        case 3: // Duplicate section
            $len = strlen($input);

            if (0 < $len) {
                $start = random_int(0, $len - 1);
                $end = random_int($start, $len - 1);
                $section = substr($input, $start, $end - $start + 1);
                $input .= $section;
            }

            break;

        case 4: // Replace with interesting values
            $interesting = ["\x00", "\xff", "\n", "\r\n", '\\', "'", '"', "\t", str_repeat('A', 1000)];
            $input = $interesting[random_int(0, count($interesting) - 1)];

            break;

        case 5: // Shuffle parts
            $parts = str_split($input, max(1, (int) (strlen($input) / 4)));
            shuffle($parts);
            $input = implode('', $parts);

            break;
    }

    return $input;
}

foreach ($targetFiles as $targetFile) {
    $targetName = basename($targetFile, '.php');
    echo "Running fuzzer: {$targetName}\n";

    // Build the target class name
    $targetClass = "OpenFGA\\MCP\\Tests\\Fuzzing\\Targets\\{$targetName}";

    // Check if class already exists (autoloader should handle this)
    if (! class_exists($targetClass)) {
        echo "  ERROR: Target class {$targetClass} not found\n";

        continue;
    }

    $target = new $targetClass;

    // Create corpus directory for this target
    $targetCorpusDir = "{$corpusDir}/{$targetName}";

    if (! is_dir($targetCorpusDir)) {
        mkdir($targetCorpusDir, 0o755, true);
    }

    // Load initial corpus
    $corpus = [];

    if (method_exists($target, 'getInitialCorpus')) {
        $corpus = $target->getInitialCorpus();
    }

    // Load saved corpus
    $corpusFiles = glob("{$targetCorpusDir}/*.txt");

    foreach ($corpusFiles as $file) {
        $corpus[] = file_get_contents($file);
    }

    // If no corpus, generate some random inputs
    if (empty($corpus)) {
        for ($i = 0; 10 > $i; $i++) {
            $corpus[] = generateRandomInput();
        }
    }

    // Run fuzzing for a portion of the total time
    $targetTimeout = (int) ($timeout / count($targetFiles));
    $startTime = time();
    $endTime = $startTime + $targetTimeout;
    $iterations = 0;
    $crashes = 0;

    echo "  Fuzzing for {$targetTimeout} seconds...\n";
    echo '  Initial corpus size: ' . count($corpus) . "\n";

    while (time() < $endTime && $iterations < $maxIterations) {
        // Pick an input from corpus or generate new one
        if (! empty($corpus) && 0 === random_int(0, 1)) {
            $input = $corpus[array_rand($corpus)];
            $input = mutateInput($input);
        } else {
            $input = generateRandomInput();
        }

        // Run the fuzzing target
        try {
            $target->fuzz($input);

            // If it didn't crash, maybe add to corpus
            if (0 === random_int(0, 100)) {
                $corpus[] = $input;

                // Save interesting inputs
                if (0 === count($corpus) % 10) {
                    $hash = substr(md5($input), 0, 8);
                    $corpusFile = "{$targetCorpusDir}/corpus_{$hash}.txt";
                    file_put_contents($corpusFile, $input);
                }
            }
        } catch (Throwable $e) {
            // Check if this is an expected error
            if (method_exists($target, 'isExpectedError') && $target->isExpectedError($e)) {
                // Expected error, continue
            } else {
                // Unexpected crash - save it
                $crashes++;
                $crashHash = substr(md5($input . $e->getMessage()), 0, 8);
                $crashFile = "{$crashDir}/{$targetName}_{$crashHash}.crash";

                if (! file_exists($crashFile)) {
                    file_put_contents($crashFile, json_encode([
                        'target' => $targetName,
                        'error' => $e->getMessage(),
                        'type' => $e::class,
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'input' => $input,
                        'input_hex' => bin2hex($input),
                        'input_length' => strlen($input),
                    ], JSON_PRETTY_PRINT));

                    echo '  💥 Found crash: ' . substr($e->getMessage(), 0, 50) . "...\n";
                }
            }
        }

        $iterations++;
    }

    $elapsed = time() - $startTime;
    echo "  ✓ Completed {$iterations} iterations in {$elapsed}s\n";

    if (0 < $crashes) {
        echo "  ⚠️  Found {$crashes} crashes\n";
        $totalCrashes += $crashes;
        $exitCode = 1;
    }
    echo "\n";
}

echo "Fuzzing session completed\n";

// Check for any crashes
$allCrashes = glob("{$crashDir}/*.crash");

if (! empty($allCrashes)) {
    echo "\n⚠️  Total crashes found: " . count($allCrashes) . "\n";

    foreach ($allCrashes as $crash) {
        echo '  - ' . basename($crash) . "\n";
    }

    exit(1);
}

exit($exitCode);
