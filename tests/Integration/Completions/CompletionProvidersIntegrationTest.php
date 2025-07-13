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
            $store = createTestStore('filter-test-store');

            $completions = $this->storeCompletionProvider->getCompletions('filter', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain($store);

            $nonMatchingCompletions = $this->storeCompletionProvider->getCompletions('nomatch', $this->session);
            expect($nonMatchingCompletions)->not->toContain($store);

            deleteTestStore($store);
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

            // Mock session to return store ID
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(['store_id' => $storeId]);

            $completions = $this->modelCompletionProvider->getCompletions('', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('latest')
                ->and($completions)->toContain($modelId);
        });

        it('respects restricted mode', function (): void {
            putenv('OPENFGA_MCP_API_RESTRICT=true');
            putenv('OPENFGA_MCP_API_STORE=allowed-store');

            $restrictedStore = createTestStore('restricted-store');

            // Mock session with restricted store
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(['store_id' => $restrictedStore]);

            $completions = $this->modelCompletionProvider->getCompletions('', $this->session);

            expect($completions)->toBeArray()->toBeEmpty();

            deleteTestStore($restrictedStore);

            // Clean up
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

            // Mock session to return store ID
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(['store_id' => $storeId]);

            $completions = $this->relationCompletionProvider->getCompletions('', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('viewer')
                ->and($completions)->toContain('editor')
                ->and($completions)->toContain('owner');
        });

        it('filters completions correctly', function (): void {
            $setup = setupTestStoreWithModel();
            $storeId = $setup['store'];

            // Mock session to return store ID
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(['store_id' => $storeId]);

            $completions = $this->relationCompletionProvider->getCompletions('read', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('reader');

            $filteredCompletions = $this->relationCompletionProvider->getCompletions('writ', $this->session);

            expect($filteredCompletions)->toBeArray()
                ->and($filteredCompletions)->toContain('writer');
        });

        it('falls back to common relations when no store context', function (): void {
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(null);

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
                tuples: [
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
                ],
            )
                ->failure(function ($error): void {
                    throw new RuntimeException('Failed to write test tuples: ' . $error->getMessage());
                });

            // Give a moment for the tuples to be written
            sleep(1);

            // Mock session to return store ID
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(['store_id' => $storeId]);

            $completions = $this->userCompletionProvider->getCompletions('user:', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('user:alice')
                ->and($completions)->toContain('user:bob');
        });

        it('falls back to common user patterns when no data', function (): void {
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(null);

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
                tuples: [
                    new TupleKey(
                        user: 'user:alice',
                        relation: 'reader',
                        object: 'document:report1',
                    ),
                    new TupleKey(
                        user: 'user:bob',
                        relation: 'writer',
                        object: 'document:report2',
                    ),
                ],
            )
                ->failure(function ($error): void {
                    throw new RuntimeException('Failed to write test tuples: ' . $error->getMessage());
                });

            // Give a moment for the tuples to be written
            sleep(1);

            // Mock session to return store ID
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(['store_id' => $storeId]);

            $completions = $this->objectCompletionProvider->getCompletions('document:', $this->session);

            expect($completions)->toBeArray()
                ->and($completions)->toContain('document:report1')
                ->and($completions)->toContain('document:report2');
        });

        it('filters completions by current value', function (): void {
            $setup = setupTestStoreWithModel();
            $storeId = $setup['store'];

            // Write test tuples with different object patterns
            $client = getTestClient();
            $client->writeTuples(
                store: $storeId,
                tuples: [
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
                ],
            )
                ->failure(function ($error): void {
                    throw new RuntimeException('Failed to write test tuples: ' . $error->getMessage());
                });

            sleep(1);

            // Mock session to return store ID
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(['store_id' => $storeId]);

            $folderCompletions = $this->objectCompletionProvider->getCompletions('folder:', $this->session);
            $documentCompletions = $this->objectCompletionProvider->getCompletions('document:', $this->session);

            expect($folderCompletions)->toBeArray()
                ->and($folderCompletions)->toContain('folder:important')
                ->and($folderCompletions)->not->toContain('document:important');

            expect($documentCompletions)->toBeArray()
                ->and($documentCompletions)->toContain('document:important')
                ->and($documentCompletions)->not->toContain('folder:important');
        });

        it('falls back to common object patterns when no data', function (): void {
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(null);

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
            // Mock session with invalid store ID
            $this->session->shouldReceive('get')
                ->with('completion_context')
                ->andReturn(['store_id' => 'invalid-store-id']);

            $completions = $this->modelCompletionProvider->getCompletions('', $this->session);

            // Should fall back to 'latest' option
            expect($completions)->toBeArray()
                ->and($completions)->toContain('latest');
        });
    });
});
