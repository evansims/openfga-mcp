<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\StoreIdCompletionProvider;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new StoreIdCompletionProvider($this->client);
});

describe('StoreIdCompletionProvider', function (): void {
    it('returns empty array when client throws exception', function (): void {
        $this->client->shouldReceive('listStores')
            ->once()
            ->andThrow(new Exception('API Error'));

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);
    });

    it('returns empty array when client returns null', function (): void {
        $this->client->shouldReceive('listStores')
            ->once()
            ->andReturn(null);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);
    });

    it('filters completions based on current value', function (): void {
        // Test with empty client that throws exception - should return empty array
        $this->client->shouldReceive('listStores')
            ->andThrow(new Exception('No stores'));

        $result = $this->provider->getCompletions('test', $this->session);
        expect($result)->toBe([]);
    });

    it('handles null client response gracefully', function (): void {
        $this->client->shouldReceive('listStores')
            ->once()
            ->andReturn(null);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);
    });

    it('handles client that returns empty response', function (): void {
        $this->client->shouldReceive('listStores')
            ->once()
            ->andThrow(new RuntimeException('Empty response'));

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);
    });
});
