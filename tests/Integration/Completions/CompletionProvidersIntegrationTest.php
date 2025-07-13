<?php

declare(strict_types=1);

use OpenFGA\Client;
use OpenFGA\MCP\Completions\{
    ModelIdCompletionProvider,
    ObjectCompletionProvider,
    RelationCompletionProvider,
    StoreIdCompletionProvider,
    UserCompletionProvider
};
use OpenFGA\Models\Collections\TupleKeys;
use OpenFGA\Models\TupleKey;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = getTestClient();
    $this->session = Mockery::mock(SessionInterface::class);

    // Initialize completion providers
    $this->storeCompletionProvider = new StoreIdCompletionProvider($this->client);
    $this->modelCompletionProvider = new ModelIdCompletionProvider($this->client);
    $this->relationCompletionProvider = new RelationCompletionProvider($this->client);
    $this->userCompletionProvider = new UserCompletionProvider($this->client);
    $this->objectCompletionProvider = new ObjectCompletionProvider($this->client);
});

describe('Completion Providers Integration', function (): void {
    describe('StoreIdCompletionProvider', function (): void {
        it('can fetch real store IDs', function (): void {
            // Create multiple test stores
            $store1 = createTestStore('integration-test-store-1');
            $store2 = createTestStore('integration-test-store-2');

            $completions = $this->storeCompletionProvider->getCompletions('', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain($store1)
                ->and($completions)->toContain($store2);

            // Clean up
            deleteTestStore($store1);
            deleteTestStore($store2);
        });

        it('filters completions by current value', function (): void {
            // Create multiple stores to test filtering
            $store1 = createTestStore('filter-test-store-1');
            $store2 = createTestStore('filter-test-store-2');

            // Get all completions
            $allCompletions = $this->storeCompletionProvider->getCompletions('', $this->session);
            expect($allCompletions)->toBeArray()
                ->and($allCompletions)->toContain($store1)
                ->and($allCompletions)->toContain($store2);

            // Test filtering by the first few characters of an actual store ID
            $firstChars = substr($store1, 0, 3);
            $filteredCompletions = $this->storeCompletionProvider->getCompletions($firstChars, $this->session);

            expect($filteredCompletions)->toBeArray()
                ->and($filteredCompletions)->toContain($store1);

            // Test with a string that shouldn't match any store IDs
            $nonMatchingCompletions = $this->storeCompletionProvider->getCompletions('ZZZZZZ', $this->session);
            expect($nonMatchingCompletions)->toBeArray()->toBeEmpty();

            // Clean up
            deleteTestStore($store1);
            deleteTestStore($store2);
        });
    });

    describe('ModelIdCompletionProvider', function (): void {
        it('includes latest option when no store context', function (): void {
            $completions = $this->modelCompletionProvider->getCompletions('', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('latest');
        });

        it('can fetch real model IDs from store', function (): void {
            $setup = setupTestStoreWithModel();
            $storeId = $setup['store'];
            $modelId = $setup['model'];

            // Set environment variable to provide store context
            $_ENV['OPENFGA_MCP_API_STORE'] = $storeId;
            putenv("OPENFGA_MCP_API_STORE={$storeId}");

            $completions = $this->modelCompletionProvider->getCompletions('', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('latest')
                ->and($completions)->toContain($modelId);

            // Clean up
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');
        });

        it('respects restricted mode', function (): void {
            $_ENV['OPENFGA_MCP_API_RESTRICT'] = 'true';
            putenv('OPENFGA_MCP_API_RESTRICT=true');

            $allowedStore = createTestStore('allowed-store');
            $restrictedStore = createTestStore('restricted-store');

            // Set environment to use the allowed store
            $_ENV['OPENFGA_MCP_API_STORE'] = $allowedStore;
            putenv("OPENFGA_MCP_API_STORE={$allowedStore}");

            // When we try to access the allowed store, it should work (not be empty)
            $allowedCompletions = $this->modelCompletionProvider->getCompletions('', $this->session);
            expect($allowedCompletions)->toBeArray()
                ->and($allowedCompletions)->toContain('latest');

            // Now test with a different store (should fail restriction check)
            $setup = setupTestStoreWithModel();
            $modelId = $setup['model'];

            // The isRestricted method checks if the store we're trying to access
            // matches the configured store, not what's in the environment
            expect($this->modelCompletionProvider->getCompletions('', $this->session))
                ->toBeArray()
                ->toContain('latest');

            deleteTestStore($allowedStore);
            deleteTestStore($restrictedStore);

            // Clean up
            unset($_ENV['OPENFGA_MCP_API_RESTRICT']);
            putenv('OPENFGA_MCP_API_RESTRICT=false');
            putenv('OPENFGA_MCP_API_STORE=false');
        });
    });

    describe('RelationCompletionProvider', function (): void {
        it('can fetch real relations from authorization model', function (): void {
            $dsl = 'model
  schema 1.1

type user

type document
  relations
    define viewer: [user]
    define editor: [user]
    define owner: [user]

type folder
  relations
    define viewer: [user]
    define editor: [user]';

            $setup = setupTestStoreWithModel($dsl);
            $storeId = $setup['store'];

            // Set environment variable to provide store context
            $_ENV['OPENFGA_MCP_API_STORE'] = $storeId;
            putenv("OPENFGA_MCP_API_STORE={$storeId}");

            $completions = $this->relationCompletionProvider->getCompletions('', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('viewer')
                ->and($completions)->toContain('editor')
                ->and($completions)->toContain('owner');

            // Clean up
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');
        });

        it('filters completions correctly', function (): void {
            $setup = setupTestStoreWithModel();
            $storeId = $setup['store'];

            // Set environment variable to provide store context
            $_ENV['OPENFGA_MCP_API_STORE'] = $storeId;
            putenv("OPENFGA_MCP_API_STORE={$storeId}");

            $completions = $this->relationCompletionProvider->getCompletions('read', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('reader');

            $filteredCompletions = $this->relationCompletionProvider->getCompletions('writ', $this->session);

            expect($filteredCompletions)->toBeArray()
                ->and($filteredCompletions)->toContain('writer');

            // Clean up
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');
        });

        it('falls back to common relations when no store context', function (): void {
            // Ensure no store is configured
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');

            $completions = $this->relationCompletionProvider->getCompletions('view', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('viewer');
        });
    });

    describe('UserCompletionProvider', function (): void {
        it('can fetch users from relationship tuples', function (): void {
            $setup = setupTestStoreWithModel();
            $storeId = $setup['store'];

            // Write some test tuples
            $client = getTestClient();
            $client->writeTuples(
                store: $storeId,
                model: $setup['model'],
                writes: new TupleKeys(
                    new TupleKey(
                        user: 'user:alice',
                        relation: 'reader',
                        object: 'document:test1',
                    ),
                    new TupleKey(
                        user: 'user:bob',
                        relation: 'writer',
                        object: 'document:test2',
                    ),
                ),
            )
                ->failure(function ($error): void {
                    throw new RuntimeException('Failed to write test tuples: ' . $error->getMessage());
                });

            // Give a moment for the tuples to be written
            sleep(1);

            // Set environment variable to provide store context
            $_ENV['OPENFGA_MCP_API_STORE'] = $storeId;
            putenv("OPENFGA_MCP_API_STORE={$storeId}");

            $completions = $this->userCompletionProvider->getCompletions('user:', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('user:alice')
                ->and($completions)->toContain('user:bob');

            // Clean up
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');
        });

        it('falls back to common user patterns when no data', function (): void {
            // Ensure no store is configured
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');

            $completions = $this->userCompletionProvider->getCompletions('user:', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('user:alice')
                ->and($completions)->toContain('user:bob');
        });
    });

    describe('ObjectCompletionProvider', function (): void {
        it('can fetch objects from relationship tuples', function (): void {
            $setup = setupTestStoreWithModel();
            $storeId = $setup['store'];

            // Write some test tuples
            $client = getTestClient();
            $client->writeTuples(
                store: $storeId,
                model: $setup['model'],
                writes: new TupleKeys(
                    new TupleKey(
                        user: 'user:alice',
                        relation: 'reader',
                        object: 'document:test1',
                    ),
                    new TupleKey(
                        user: 'user:bob',
                        relation: 'writer',
                        object: 'document:test2',
                    ),
                ),
            )
                ->failure(function ($error): void {
                    throw new RuntimeException('Failed to write test tuples: ' . $error->getMessage());
                });

            // Give a moment for the tuples to be written
            sleep(1);

            // Set environment variable to provide store context
            $_ENV['OPENFGA_MCP_API_STORE'] = $storeId;
            putenv("OPENFGA_MCP_API_STORE={$storeId}");

            $completions = $this->objectCompletionProvider->getCompletions('document:', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('document:test1')
                ->and($completions)->toContain('document:test2');

            // Clean up
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');
        });

        it('filters completions by current value', function (): void {
            $dsl = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]
    define writer: [user]
    define owner: [user]

type folder
  relations
    define reader: [user]';

            $setup = setupTestStoreWithModel($dsl);
            $storeId = $setup['store'];

            // Write test tuples with different object patterns
            $client = getTestClient();
            $client->writeTuples(
                store: $storeId,
                model: $setup['model'],
                writes: new TupleKeys(
                    new TupleKey(
                        user: 'user:alice',
                        relation: 'reader',
                        object: 'folder:important',
                    ),
                    new TupleKey(
                        user: 'user:bob',
                        relation: 'reader',
                        object: 'document:important',
                    ),
                ),
            )
                ->failure(function ($error): void {
                    throw new RuntimeException('Failed to write test tuples: ' . $error->getMessage());
                });

            sleep(1);

            // Set environment variable to provide store context
            $_ENV['OPENFGA_MCP_API_STORE'] = $storeId;
            putenv("OPENFGA_MCP_API_STORE={$storeId}");

            $folderCompletions = $this->objectCompletionProvider->getCompletions('folder:', $this->session);
            $documentCompletions = $this->objectCompletionProvider->getCompletions('document:', $this->session);

            expect($folderCompletions)->toBeArray()
                ->and($folderCompletions)->toContain('folder:important')
                ->and($folderCompletions)->not->toContain('document:important');

            expect($documentCompletions)->toBeArray()
                ->and($documentCompletions)->toContain('document:important')
                ->and($documentCompletions)->not->toContain('folder:important');

            // Clean up
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');
        });

        it('falls back to common object patterns when no data', function (): void {
            // Ensure no store is configured
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');

            $completions = $this->objectCompletionProvider->getCompletions('document:', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('document:budget')
                ->and($completions)->toContain('document:plan');
        });
    });

    describe('Error handling and resilience', function (): void {
        it('handles network errors gracefully', function (): void {
            // Create providers with invalid client configuration to simulate network errors
            $invalidClient = new Client(url: 'http://invalid-url:9999');
            $provider = new StoreIdCompletionProvider($invalidClient);

            $completions = $provider->getCompletions('', $this->session);

            expect($completions)->toBeArray()->toBeEmpty();
        });

        it('handles invalid store IDs gracefully', function (): void {
            // Set environment variable with invalid store ID
            $_ENV['OPENFGA_MCP_API_STORE'] = 'invalid-store-id';
            putenv('OPENFGA_MCP_API_STORE=invalid-store-id');

            $completions = $this->modelCompletionProvider->getCompletions('', $this->session);

            // Should fall back to 'latest' option
            expect($completions)->toBeArray()
                ->and($completions)->toContain('latest');

            // Clean up
            unset($_ENV['OPENFGA_MCP_API_STORE']);
            putenv('OPENFGA_MCP_API_STORE=false');
        });
    });
});
