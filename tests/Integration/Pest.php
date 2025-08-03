<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Integration Test Configuration
|--------------------------------------------------------------------------
|
| This file configures Pest for integration tests that require a running
| OpenFGA instance.
|
*/

// Function to completely clear environment variables
function clearTestEnvironment(): void
{
    // Clear from both $_ENV and system environment
    $vars = ['OPENFGA_MCP_API_RESTRICT', 'OPENFGA_MCP_API_STORE', 'OPENFGA_MCP_API_MODEL'];

    foreach ($vars as $var) {
        // Set to explicit 'false' string which will be treated as false by our helper functions
        putenv($var . '=false');
        $_ENV[$var] = 'false';
    }

    // Enable write operations by default for integration tests
    putenv('OPENFGA_MCP_API_WRITEABLE=true');
    $_ENV['OPENFGA_MCP_API_WRITEABLE'] = 'true';
}

// Ensure clean environment before each test
beforeEach(function (): void {
    clearTestEnvironment();
});

// Clean up test stores after each test
afterEach(function (): void {
    if (isset($this->testStoreId)) {
        deleteTestStore($this->testStoreId);
        unset($this->testStoreId);
    }

    // Clean up any environment variables that might have been set during tests
    clearTestEnvironment();
});
