<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/../../src/Helpers.php';

// Ensure clean environment at bootstrap

use OpenFGA\{Client, ClientInterface};

// Wait for OpenFGA to be ready
function waitForOpenFGA(string $url, int $maxAttempts = 60): void
{
    $attempt = 0;

    while ($attempt < $maxAttempts) {
        try {
            $ch = curl_init($url . '/healthz');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if (200 === $httpCode) {
                echo "OpenFGA is ready!\n";
                // Give it a moment to fully initialize
                sleep(2);

                return;
            }

            if ($error) {
                echo "Connection error: {$error}\n";
            } else {
                echo "Health check returned HTTP {$httpCode}\n";
            }
        } catch (Exception $e) {
            echo 'Exception during health check: ' . $e->getMessage() . "\n";
        }

        $attempt++;
        echo "Waiting for OpenFGA... (attempt {$attempt}/{$maxAttempts})\n";
        sleep(2);
    }

    throw new RuntimeException('OpenFGA failed to start within ' . ($maxAttempts * 2) . ' seconds');
}

// Get OpenFGA URL from environment
$openfgaUrl = getenv('OPENFGA_MCP_API_URL') ?: 'http://localhost:8080';

// Wait for OpenFGA to be ready
waitForOpenFGA($openfgaUrl);

// Create a shared OpenFGA client for tests
$GLOBALS['openfga_client'] = new Client(url: $openfgaUrl);

// Helper function to get the test client
function getTestClient(): ClientInterface
{
    return $GLOBALS['openfga_client'];
}

// Helper function to create a test store
function createTestStore(?string $name = null): string
{
    $name ??= 'test-store-' . uniqid();
    $client = getTestClient();
    $storeId = null;

    $client->createStore($name)
        ->success(function ($response) use (&$storeId): void {
            $storeId = $response->getId();
        })
        ->failure(function ($error): void {
            throw new RuntimeException('Failed to create test store: ' . $error->getMessage());
        });

    if (! $storeId) {
        throw new RuntimeException('Failed to create test store');
    }

    return $storeId;
}

// Helper function to delete a test store
function deleteTestStore(string $storeId): void
{
    $client = getTestClient();

    $client->deleteStore($storeId)
        ->failure(function ($error): void {
            // Ignore deletion errors in cleanup
            echo 'Warning: Failed to delete test store: ' . $error->getMessage() . "\n";
        });
}

// Helper function to create a test model
function createTestModel(string $storeId, ?string $dsl = null): string
{
    $dsl ??= 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]
    define writer: [user]
    define owner: [user]';

    $client = getTestClient();
    $modelId = null;
    $authModel = null;

    // Parse DSL
    $client->dsl($dsl)
        ->success(function ($model) use (&$authModel): void {
            $authModel = $model;
        })
        ->failure(function ($error): void {
            throw new RuntimeException('Failed to parse DSL: ' . $error->getMessage());
        });

    if (! $authModel) {
        throw new RuntimeException('Failed to parse DSL');
    }

    // Create model
    $client->createAuthorizationModel(
        store: $storeId,
        typeDefinitions: $authModel->getTypeDefinitions(),
        conditions: $authModel->getConditions(),
    )
        ->success(function ($response) use (&$modelId): void {
            $modelId = $response->getModel();
        })
        ->failure(function ($error): void {
            throw new RuntimeException('Failed to create test model: ' . $error->getMessage());
        });

    if (! $modelId) {
        throw new RuntimeException('Failed to create test model');
    }

    return $modelId;
}

// Helper to get a fresh test store for each test
function setupTestStore(): string
{
    $storeId = createTestStore();
    // Store the ID for cleanup in Pest's afterEach hook
    test()->testStoreId = $storeId;

    return $storeId;
}

// Helper to get a test store with a model
function setupTestStoreWithModel(?string $dsl = null): array
{
    $storeId = setupTestStore();
    $modelId = createTestModel($storeId, $dsl);

    return ['store' => $storeId, 'model' => $modelId];
}
