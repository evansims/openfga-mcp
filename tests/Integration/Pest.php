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

// Clean up test stores after each test
afterEach(function (): void {
    if (isset($this->testStoreId)) {
        deleteTestStore($this->testStoreId);
        unset($this->testStoreId);
    }
});
