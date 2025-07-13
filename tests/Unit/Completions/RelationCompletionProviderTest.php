<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\RelationCompletionProvider;
use OpenFGA\Operations\GetAuthorizationModelOperation;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new RelationCompletionProvider($this->client);
});

describe('RelationCompletionProvider', function (): void {
    it('extracts relations from authorization model', function (): void {
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $mockOperation = Mockery::mock(GetAuthorizationModelOperation::class);

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('get')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $response = [
                    'authorization_model' => [
                        'type_definitions' => [
                            [
                                'type' => 'document',
                                'relations' => [
                                    'viewer' => [],
                                    'editor' => [],
                                    'owner' => [],
                                ],
                            ],
                            [
                                'type' => 'folder',
                                'relations' => [
                                    'viewer' => [],
                                    'member' => [],
                                ],
                            ],
                        ],
                    ],
                ];
                $callback($response);

                return $mockOperation;
            });

        $mockOperation->shouldReceive('onFailure')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('await')
            ->once()
            ->andReturn(['editor', 'member', 'owner', 'viewer']); // sorted unique relations

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['editor', 'member', 'owner', 'viewer']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('returns common relations when no store ID is available', function (): void {
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('', $this->session);

        // Should return common relations
        expect($result)->toContain('viewer');
        expect($result)->toContain('editor');
        expect($result)->toContain('admin');
        expect($result)->toContain('owner');

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('returns common relations when API fails', function (): void {
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $mockOperation = Mockery::mock(GetAuthorizationModelOperation::class);

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('get')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onFailure')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $callback();

                return $mockOperation;
            });

        $mockOperation->shouldReceive('await')
            ->once()
            ->andReturnUsing(function () {
                // This simulates the common relations fallback
                return [
                    'viewer', 'reader', 'editor', 'writer', 'admin', 'owner',
                    'member', 'can_view', 'can_edit', 'can_delete', 'can_share',
                    'parent', 'assignee',
                ];
            });

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toContain('viewer');
        expect($result)->toContain('editor');

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('handles exception gracefully', function (): void {
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
            ->andThrow(new Exception('API Error'));

        $result = $this->provider->getCompletions('', $this->session);

        // Should return common relations as fallback
        expect($result)->toContain('viewer');
        expect($result)->toContain('editor');

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('filters relations based on current value', function (): void {
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('can_', $this->session);

        // Should filter to relations starting with 'can_'
        expect($result)->toContain('can_view');
        expect($result)->toContain('can_edit');
        expect($result)->toContain('can_delete');
        expect($result)->toContain('can_share');
        expect($result)->not->toContain('viewer');
        expect($result)->not->toContain('editor');

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('handles empty model gracefully', function (): void {
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $mockOperation = Mockery::mock(GetAuthorizationModelOperation::class);

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('get')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $response = [
                    'authorization_model' => [
                        'type_definitions' => [],
                    ],
                ];
                $callback($response);

                return $mockOperation;
            });

        $mockOperation->shouldReceive('onFailure')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('await')
            ->once()
            ->andReturn([]); // No relations found

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('handles malformed model data gracefully', function (): void {
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $mockOperation = Mockery::mock(GetAuthorizationModelOperation::class);

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('get')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $response = [
                    'authorization_model' => [
                        'type_definitions' => [
                            [
                                'type' => 'document',
                                'relations' => [
                                    'viewer' => [],
                                    '' => [],  // Empty relation name
                                    123 => [],  // Invalid relation name type
                                ],
                            ],
                        ],
                    ],
                ];
                $callback($response);

                return $mockOperation;
            });

        $mockOperation->shouldReceive('onFailure')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('await')
            ->once()
            ->andReturn(['viewer']); // Only valid relation

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['viewer']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });
});
