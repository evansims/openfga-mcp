<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Basic fuzzing smoke test for CI.
 */
final class FuzzingTest extends TestCase
{
    private string $fuzzingDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fuzzingDir = __DIR__ . '/../Fuzzing';

        // Check if php-fuzzer is installed
        if (! class_exists('PhpFuzzer\\Fuzzer')) {
            $this->markTestSkipped('nikic/php-fuzzer is not installed');
        }
    }

    public function testFuzzingTargetsExist(): void
    {
        $targets = glob($this->fuzzingDir . '/Targets/*Target.php');
        $this->assertNotEmpty($targets, 'No fuzzing targets found');

        foreach ($targets as $target) {
            $this->assertFileExists($target);

            // Verify the target class exists
            require_once $target;
            $className = 'OpenFGA\\MCP\\Tests\\Fuzzing\\Targets\\' . basename($target, '.php');
            $this->assertTrue(
                class_exists($className),
                "Target class {$className} not found",
            );

            // Verify it has the required fuzz method
            $this->assertTrue(
                method_exists($className, 'fuzz'),
                "Target {$className} missing fuzz() method",
            );
        }
    }

    /**
     * Run a very short fuzzing session as a smoke test.
     */
    public function testQuickFuzzingSession(): void
    {
        if (getenv('CI') && ! getenv('FUZZING_ENABLED')) {
            $this->markTestSkipped('Fuzzing disabled in CI unless explicitly enabled');
        }

        $runFuzzer = $this->fuzzingDir . '/run-fuzzers.php';
        $this->assertFileExists($runFuzzer);

        // Run fuzzing for just 5 seconds as a smoke test
        putenv('FUZZING_DURATION=5');

        $output = [];
        $exitCode = 0;
        exec("php {$runFuzzer} 2>&1", $output, $exitCode);

        // Check that fuzzing ran without critical errors
        // Exit code 124 is timeout (expected for time-limited fuzzing)
        $this->assertContains($exitCode, [0, 124], 'Fuzzing failed with unexpected exit code');

        // Check output contains expected messages
        $outputStr = implode("\n", $output);
        $this->assertStringContainsString('Starting fuzzing session', $outputStr);
        $this->assertStringContainsString('fuzzing targets', $outputStr);

        // Check no crashes were found
        $crashes = glob($this->fuzzingDir . '/crashes/*.crash');
        $this->assertEmpty($crashes, 'Fuzzing found crashes: ' . implode(', ', array_map('basename', $crashes)));
    }

    /**
     * Test that the SARIF generator works.
     */
    public function testSARIFGenerator(): void
    {
        $generator = $this->fuzzingDir . '/generate-sarif.php';
        $this->assertFileExists($generator);

        // Create a fake crash for testing
        $crashDir = $this->fuzzingDir . '/crashes';

        if (! is_dir($crashDir)) {
            mkdir($crashDir, 0o755, true);
        }

        $testCrash = $crashDir . '/test.crash';
        file_put_contents($testCrash, json_encode([
            'target' => 'TestTarget',
            'error' => 'Test error message',
            'trace' => '#0 /src/Test.php(42): testFunction()',
            'input' => 'test input',
        ]));

        // Run the generator
        $output = shell_exec("php {$generator}");
        $this->assertNotEmpty($output);

        // Validate SARIF format
        $sarif = json_decode($output, true);
        $this->assertIsArray($sarif);
        $this->assertEquals('2.1.0', $sarif['version']);
        $this->assertArrayHasKey('runs', $sarif);
        $this->assertNotEmpty($sarif['runs'][0]['results']);

        // Clean up
        unlink($testCrash);
    }
}
