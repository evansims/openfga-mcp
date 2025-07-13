<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\AbstractCompletions;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
});

describe('AbstractCompletions', function (): void {
    it('filters completions based on current value', function (): void {
        $completion = new class($this->client) extends AbstractCompletions {
            public function getCompletions(string $currentValue, SessionInterface $session): array
            {
                $completions = ['apple', 'banana', 'apricot', 'cherry'];

                return $this->filterCompletions($completions, $currentValue);
            }
        };

        // Test empty current value returns all completions
        $result = $completion->getCompletions('', $this->session);
        expect($result)->toBe(['apple', 'banana', 'apricot', 'cherry']);

        // Test filtering with prefix
        $result = $completion->getCompletions('ap', $this->session);
        expect($result)->toBe(['apple', 'apricot']);

        // Test case insensitive filtering
        $result = $completion->getCompletions('AP', $this->session);
        expect($result)->toBe(['apple', 'apricot']);

        // Test no matches
        $result = $completion->getCompletions('xyz', $this->session);
        expect($result)->toBe([]);
    });

    it('handles restricted mode correctly', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $completion = new class($this->client) extends AbstractCompletions {
            public function getCompletions(string $currentValue, SessionInterface $session): array
            {
                return [];
            }

            public function testRestricted(?string $storeId = null): bool
            {
                return $this->isRestricted($storeId);
            }
        };

        // Test unrestricted store
        expect($completion->testRestricted('test-store'))->toBeFalse();

        // Test restricted store
        expect($completion->testRestricted('other-store'))->toBeTrue();

        // Test no store ID provided
        expect($completion->testRestricted())->toBeFalse();

        // Clean up
        putenv('OPENFGA_MCP_API_RESTRICT=false');
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('extracts store ID from session correctly', function (): void {
        $completion = new class($this->client) extends AbstractCompletions {
            public function getCompletions(string $currentValue, SessionInterface $session): array
            {
                return [];
            }

            public function testExtractStoreId(SessionInterface $session): ?string
            {
                return $this->extractStoreIdFromSession($session);
            }
        };

        // Test with configured store
        putenv('OPENFGA_MCP_API_STORE=configured-store');
        expect($completion->testExtractStoreId($this->session))->toBe('configured-store');

        // Test without configured store
        putenv('OPENFGA_MCP_API_STORE=false');
        expect($completion->testExtractStoreId($this->session))->toBeNull();

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });
});
