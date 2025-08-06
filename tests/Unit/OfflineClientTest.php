<?php

declare(strict_types=1);

namespace Tests\Unit;

use OpenFGA\ClientInterface;
use OpenFGA\MCP\OfflineClient;
use OpenFGA\Models\Collections\{Assertions, BatchCheckItems, TypeDefinitions, UserTypeFilters};
use OpenFGA\Models\TupleKey;
use OpenFGA\Results\{FailureInterface, SuccessInterface};
use ReflectionClass;
use RuntimeException;

beforeEach(function (): void {
    $this->client = new OfflineClient;
});

describe('OfflineClient', function (): void {
    describe('read operations', function (): void {
        it('returns success for batchCheck', function (): void {
            // Create an empty BatchCheckItems instance
            $checks = new BatchCheckItems;

            $result = $this->client->batchCheck('store-id', 'model-id', $checks);

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for check', function (): void {
            // Create a real TupleKey instance
            $tuple = new TupleKey(
                user: 'user:1',
                relation: 'viewer',
                object: 'document:1',
            );

            $result = $this->client->check(
                store: 'store-id',
                model: 'model-id',
                tuple: $tuple,
            );

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for expand', function (): void {
            // Create a real TupleKey instance
            $tuple = new TupleKey(
                user: 'user:1',
                relation: 'viewer',
                object: 'document:1',
            );

            $result = $this->client->expand(
                store: 'store-id',
                tuple: $tuple,
                model: 'model-id',
            );

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for getAuthorizationModel', function (): void {
            $result = $this->client->getAuthorizationModel('store-id', 'model-id');

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for getStore', function (): void {
            $result = $this->client->getStore('store-id');

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for listAuthorizationModels', function (): void {
            $result = $this->client->listAuthorizationModels('store-id');

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for listObjects', function (): void {
            $result = $this->client->listObjects(
                store: 'store-id',
                model: 'model-id',
                type: 'document',
                relation: 'viewer',
                user: 'user:1',
            );

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for listStores', function (): void {
            $result = $this->client->listStores();

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for listTupleChanges', function (): void {
            $result = $this->client->listTupleChanges('store-id', 'document');

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for listUsers', function (): void {
            // Create an empty UserTypeFilters instance
            $userFilters = new UserTypeFilters;

            $result = $this->client->listUsers(
                store: 'store-id',
                model: 'model-id',
                object: 'document:1',
                relation: 'viewer',
                userFilters: $userFilters,
            );

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for readAssertions', function (): void {
            $result = $this->client->readAssertions('store-id', 'model-id');

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for readTuples', function (): void {
            $result = $this->client->readTuples('store-id');

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for streamedListObjects', function (): void {
            $result = $this->client->streamedListObjects(
                store: 'store-id',
                model: 'model-id',
                type: 'document',
                relation: 'viewer',
                user: 'user:1',
            );

            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });

        it('returns success for dsl parsing', function (): void {
            $dsl = 'model
  schema 1.1
type user
type document
  relations
    define viewer: [user]';

            $result = $this->client->dsl($dsl);

            // DSL parsing returns success in offline mode
            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });
    });

    describe('write operations', function (): void {
        it('returns failure for createAuthorizationModel', function (): void {
            // Create an empty TypeDefinitions instance
            $typeDefinitions = new TypeDefinitions;

            $result = $this->client->createAuthorizationModel('store-id', $typeDefinitions);

            expect($result)->toBeInstanceOf(FailureInterface::class);
        });

        it('returns failure for createStore', function (): void {
            $result = $this->client->createStore('Test Store');

            expect($result)->toBeInstanceOf(FailureInterface::class);
        });

        it('returns failure for deleteStore', function (): void {
            $result = $this->client->deleteStore('store-id');

            expect($result)->toBeInstanceOf(FailureInterface::class);
        });

        it('returns failure for writeAssertions', function (): void {
            // Create an empty Assertions instance
            $assertions = new Assertions;

            $result = $this->client->writeAssertions('store-id', 'model-id', $assertions);

            expect($result)->toBeInstanceOf(FailureInterface::class);
        });

        it('returns failure for writeTuples', function (): void {
            $result = $this->client->writeTuples('store-id', 'model-id');

            expect($result)->toBeInstanceOf(FailureInterface::class);
        });
    });

    describe('HTTP request/response methods', function (): void {
        it('returns null for getLastRequest', function (): void {
            $result = $this->client->getLastRequest();

            expect($result)->toBeNull();
        });

        it('returns null for getLastResponse', function (): void {
            $result = $this->client->getLastResponse();

            expect($result)->toBeNull();
        });
    });

    describe('interface implementation', function (): void {
        it('implements ClientInterface', function (): void {
            expect($this->client)->toBeInstanceOf(ClientInterface::class);
        });

        it('is a final class', function (): void {
            $reflection = new ReflectionClass(OfflineClient::class);
            expect($reflection->isFinal())->toBeTrue();
        });
    });

    describe('error messages', function (): void {
        it('provides helpful error message for write operations', function (): void {
            $result = $this->client->createStore('Test Store');

            expect($result)->toBeInstanceOf(FailureInterface::class);

            $error = null;
            $result->failure(function ($e) use (&$error): void {
                $error = $e;
            });

            expect($error)->toBeInstanceOf(RuntimeException::class);
            expect($error->getMessage())->toContain('OpenFGA instance');
            expect($error->getMessage())->toContain('OPENFGA_MCP_API_URL');
        });
    });

    describe('parameter handling', function (): void {
        it('handles various parameter types correctly', function (): void {
            // Test with string parameters
            $result = $this->client->getStore('store-123');
            expect($result)->toBeInstanceOf(SuccessInterface::class);

            // Test with null parameters
            $result = $this->client->listStores(null, null);
            expect($result)->toBeInstanceOf(SuccessInterface::class);
        });
    });
});
