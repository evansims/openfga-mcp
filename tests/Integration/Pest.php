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

// Ensure clean environment before each test
beforeEach(function (): void {
    // Reset environment variables to ensure clean state
    unset($_ENV['OPENFGA_MCP_API_READONLY'], $_ENV['OPENFGA_MCP_API_RESTRICT'], $_ENV['OPENFGA_MCP_API_STORE'], $_ENV['OPENFGA_MCP_API_MODEL']);
});

// Clean up test stores after each test
afterEach(function (): void {
    if (isset($this->testStoreId)) {
        deleteTestStore($this->testStoreId);
        unset($this->testStoreId);
    }

    // Clean up any environment variables that might have been set during tests
    unset($_ENV['OPENFGA_MCP_API_READONLY'], $_ENV['OPENFGA_MCP_API_RESTRICT'], $_ENV['OPENFGA_MCP_API_STORE'], $_ENV['OPENFGA_MCP_API_MODEL']);
});
