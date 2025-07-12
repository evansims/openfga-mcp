# Integration Tests

This directory contains integration tests that verify the OpenFGA MCP server works correctly with a real OpenFGA instance.

## Requirements

- Docker and Docker Compose
- PHP 8.3+ with Xdebug (for coverage)
- A running OpenFGA instance (or use Docker Compose)

## Running Tests

### Using Docker Compose (Recommended)

This will start OpenFGA and run the tests in an isolated environment, then automatically clean up:

```bash
composer test:integration
```

The test command automatically:
- Builds a Docker container with all dependencies
- Starts an OpenFGA instance
- Runs the integration tests
- Generates coverage reports
- Cleans up all containers and volumes afterwards

### Running Tests Manually

If you need to run tests against a specific OpenFGA instance:

1. Ensure OpenFGA is running and accessible
2. Set the OpenFGA URL environment variable
3. Run the integration test suite directly

```bash
# With OpenFGA on localhost:8080 (default)
vendor/bin/pest --testsuite integration

# With OpenFGA on a different URL
OPENFGA_MCP_API_URL=http://localhost:3000 vendor/bin/pest --testsuite integration
```

## Test Coverage

Integration tests cover:

### StoreTools

- Creating stores
- Listing stores
- Getting store details
- Deleting stores
- Read-only mode enforcement
- Restricted mode enforcement

### ModelTools

- Creating authorization models
- Getting models and model DSL
- Listing models
- Verifying DSL syntax
- Complex models with conditions
- Read-only mode enforcement
- Restricted mode enforcement

### RelationshipTools

- Granting permissions
- Checking permissions
- Revoking permissions
- Listing objects by user
- Listing users by object
- Hierarchical permissions
- Batch operations
- Read-only mode enforcement
- Restricted mode enforcement

## Writing New Integration Tests

1. Create test files in the appropriate `Tools/` subdirectory
2. Use the helper functions from `bootstrap.php`:

   - `getTestClient()` - Get the shared OpenFGA client
   - `setupTestStore()` - Create a test store (auto-cleaned)
   - `setupTestStoreWithModel()` - Create store with a model
   - `createTestStore()` - Manually create a store
   - `deleteTestStore()` - Manually delete a store
   - `createTestModel()` - Create a model in a store

3. Follow the Pest testing format:

```php
describe('Feature', function () {
    it('does something', function () {
        $storeId = setupTestStore();

        // Your test code here

        expect($result)->toBe('expected');
    });
});
```

4. Test stores are automatically cleaned up after each test via the `afterEach` hook.

## Troubleshooting

### Tests fail with connection errors

Ensure OpenFGA is running and accessible:

```bash
curl http://localhost:8080/healthz
```

### Docker Compose issues

Clean up and rebuild:

```bash
docker-compose -f docker-compose.test.yml down -v
docker-compose -f docker-compose.test.yml build --no-cache
```

### Coverage not generated

Ensure Xdebug is installed and enabled:

```bash
php -m | grep xdebug
```
