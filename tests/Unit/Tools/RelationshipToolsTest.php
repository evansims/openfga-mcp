<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Tools\RelationshipTools;
use OpenFGA\Models\Collections\TupleKeys;
use OpenFGA\Models\TupleKey;
use OpenFGA\Responses\{CheckResponseInterface, ListObjectsResponseInterface, WriteTuplesResponseInterface};
use OpenFGA\Results\{FailureInterface, SuccessInterface};

beforeEach(function (): void {
    // Set up online mode for unit tests
    putenv('OPENFGA_MCP_API_URL=http://localhost:8080');
    // Enable write operations for unit tests
    putenv('OPENFGA_MCP_API_WRITEABLE=true');

    $this->client = Mockery::mock(ClientInterface::class);
    $this->relationshipTools = new RelationshipTools($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_URL=');
    putenv('OPENFGA_MCP_API_WRITEABLE=');
    putenv('OPENFGA_MCP_API_RESTRICT=');
    putenv('OPENFGA_MCP_API_STORE=');
    putenv('OPENFGA_MCP_API_MODEL=');
});

describe('checkPermission', function (): void {
    it('checks permission and returns allowed', function (): void {
        $store = 'store-123';
        $model = 'model-456';
        $user = 'user:1';
        $relation = 'reader';
        $object = 'document:1';

        $mockResponse = Mockery::mock(CheckResponseInterface::class);
        $mockResponse->shouldReceive('getAllowed')->andReturn(true);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('check')
            ->withArgs(fn ($s, $m, $t) => $s === $store
                    && $m === $model
                    && $t instanceof TupleKey
                    && $t->getUser() === $user
                    && $t->getRelation() === $relation
                    && $t->getObject() === $object)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipTools->checkPermission($store, $model, $user, $relation, $object);

        expect($result)->toBe('✅ Permission allowed');
    });

    it('checks permission and returns denied', function (): void {
        $store = 'store-123';
        $model = 'model-456';
        $user = 'user:1';
        $relation = 'writer';
        $object = 'document:1';

        $mockResponse = Mockery::mock(CheckResponseInterface::class);
        $mockResponse->shouldReceive('getAllowed')->andReturn(false);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('check')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipTools->checkPermission($store, $model, $user, $relation, $object);

        expect($result)->toBe('❌ Permission denied');
    });

    it('handles check permission failure', function (): void {
        $store = 'store-123';
        $model = 'model-456';
        $user = 'user:1';
        $relation = 'reader';
        $object = 'document:1';
        $errorMessage = 'Network error';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('check')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipTools->checkPermission($store, $model, $user, $relation, $object);

        expect($result)->toContain('❌ Failed to check permission')
            ->and($result)->toContain($errorMessage);
    });

    it('prevents checking permission with non-restricted store', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $this->client->shouldReceive('check')->never();

        $result = $this->relationshipTools->checkPermission('different-store', 'model-123', 'user:1', 'reader', 'doc:1');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than allowed-store in this mode.');
    });

    it('prevents checking permission with non-restricted model', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_MODEL=allowed-model');

        $this->client->shouldReceive('check')->never();

        $result = $this->relationshipTools->checkPermission('store-123', 'different-model', 'user:1', 'reader', 'doc:1');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query using authorization models other than allowed-model in this mode.');
    });
});

describe('grantPermission', function (): void {
    it('grants permission successfully', function (): void {
        $store = 'store-123';
        $model = 'model-456';
        $user = 'user:1';
        $relation = 'writer';
        $object = 'document:1';

        $mockResponse = Mockery::mock(WriteTuplesResponseInterface::class);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('writeTuples')
            ->withArgs(fn ($s, $m, $w, $d = null) => $s === $store
                    && $m === $model
                    && $w instanceof TupleKeys
                    && 1 === $w->count())
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipTools->grantPermission($store, $model, $user, $relation, $object);

        expect($result)->toBe('✅ Permission granted successfully');
    });

    it('handles grant permission failure', function (): void {
        $store = 'store-123';
        $model = 'model-456';
        $user = 'user:1';
        $relation = 'writer';
        $object = 'document:1';
        $errorMessage = 'Invalid tuple';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('writeTuples')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipTools->grantPermission($store, $model, $user, $relation, $object);

        expect($result)->toContain('❌ Failed to grant permission')
            ->and($result)->toContain($errorMessage);
    });

    it('prevents granting permission in read-only mode', function (): void {
        putenv('OPENFGA_MCP_API_WRITEABLE=false');

        $this->client->shouldReceive('writeTuples')->never();

        $result = $this->relationshipTools->grantPermission('store-123', 'model-456', 'user:1', 'writer', 'doc:1');

        expect($result)->toBe('❌ Write operations are disabled for safety. To enable grant permissions, set OPENFGA_MCP_API_WRITEABLE=true.');
    });

    it('prevents granting permission with non-restricted store', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $this->client->shouldReceive('writeTuples')->never();

        $result = $this->relationshipTools->grantPermission('different-store', 'model-123', 'user:1', 'writer', 'doc:1');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than allowed-store in this mode.');
    });
});

describe('revokePermission', function (): void {
    it('revokes permission successfully', function (): void {
        $store = 'store-123';
        $model = 'model-456';
        $user = 'user:1';
        $relation = 'writer';
        $object = 'document:1';

        $mockResponse = Mockery::mock(WriteTuplesResponseInterface::class);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('writeTuples')
            ->withArgs(fn ($s, $m, $w = null, $d = null) => $s === $store
                    && $m === $model
                    && $d instanceof TupleKeys
                    && 1 === $d->count())
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipTools->revokePermission($store, $model, $user, $relation, $object);

        expect($result)->toBe('✅ Permission revoked successfully');
    });

    it('prevents revoking permission in read-only mode', function (): void {
        putenv('OPENFGA_MCP_API_WRITEABLE=false');

        $this->client->shouldReceive('writeTuples')->never();

        $result = $this->relationshipTools->revokePermission('store-123', 'model-456', 'user:1', 'writer', 'doc:1');

        expect($result)->toBe('❌ Write operations are disabled for safety. To enable revoke permissions, set OPENFGA_MCP_API_WRITEABLE=true.');
    });
});

describe('listObjects', function (): void {
    it('lists objects successfully', function (): void {
        $store = 'store-123';
        $model = 'model-456';
        $type = 'document';
        $user = 'user:1';
        $relation = 'reader';
        $objects = ['document:1', 'document:2', 'document:3'];

        $mockResponse = Mockery::mock(ListObjectsResponseInterface::class);
        $mockResponse->shouldReceive('getObjects')->andReturn($objects);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('listObjects')
            ->withArgs(fn ($s, $m, $t, $r, $u) => $s === $store
                    && $m === $model
                    && $t === $type
                    && $r === $relation
                    && $u === $user)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipTools->listObjects($store, $model, $type, $user, $relation);

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(3)
            ->and($result)->toBe($objects);
    });

    it('handles list objects failure', function (): void {
        $store = 'store-123';
        $model = 'model-456';
        $type = 'document';
        $user = 'user:1';
        $relation = 'reader';
        $errorMessage = 'Network error';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('listObjects')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipTools->listObjects($store, $model, $type, $user, $relation);

        expect($result)->toContain('❌ Failed to list objects')
            ->and($result)->toContain($errorMessage);
    });

    it('prevents listing objects with non-restricted store', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $this->client->shouldReceive('listObjects')->never();

        $result = $this->relationshipTools->listObjects('different-store', 'model-123', 'document', 'user:1', 'reader');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than allowed-store in this mode.');
    });
});

describe('listUsers', function (): void {
    it('handles list users failure', function (): void {
        $store = 'store-123';
        $model = 'model-456';
        $object = 'document:1';
        $relation = 'reader';
        $errorMessage = 'Network error';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('listUsers')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipTools->listUsers($store, $model, $object, $relation);

        expect($result)->toContain('❌ Failed to list users')
            ->and($result)->toContain($errorMessage);
    });

    it('prevents listing users with non-restricted model', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_MODEL=allowed-model');

        $this->client->shouldReceive('listUsers')->never();

        $result = $this->relationshipTools->listUsers('store-123', 'different-model', 'document:1', 'reader');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query using authorization models other than allowed-model in this mode.');
    });

    it('prevents listing users with non-restricted store', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $this->client->shouldReceive('listUsers')->never();

        $result = $this->relationshipTools->listUsers('different-store', 'model-123', 'document:1', 'reader');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than allowed-store in this mode.');
    });
});
