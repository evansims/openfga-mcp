<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Unit\Completions;

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\AbstractCompletions;
use PhpMcp\Server\Contracts\SessionInterface;

final readonly class AbstractCompletionsTest extends AbstractCompletions
{
    #[Override]
    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        $completions = ['apple', 'banana', 'apricot', 'cherry'];

        return $this->filterCompletions($completions, $currentValue);
    }

    public function testExtractStoreId(SessionInterface $session): ?string
    {
        return $this->extractStoreIdFromSession($session);
    }

    public function testRestricted(?string $storeId = null): bool
    {
        return $this->isRestricted($storeId);
    }
}

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->completion = new AbstractCompletionsTest($this->client);
});

describe('AbstractCompletions', function (): void {
    it('filters completions based on current value', function (): void {
        // Test empty current value returns all completions
        $result = $this->completion->getCompletions('', $this->session);
        expect($result)->toBe(['apple', 'banana', 'apricot', 'cherry']);

        // Test filtering with prefix
        $result = $this->completion->getCompletions('ap', $this->session);
        expect($result)->toBe(['apple', 'apricot']);

        // Test case insensitive filtering
        $result = $this->completion->getCompletions('AP', $this->session);
        expect($result)->toBe(['apple', 'apricot']);

        // Test no matches
        $result = $this->completion->getCompletions('xyz', $this->session);
        expect($result)->toBe([]);
    });

    it('handles restricted mode correctly', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=test-store');

        // Test unrestricted store
        expect($this->completion->testRestricted('test-store'))->toBeFalse();

        // Test restricted store
        expect($this->completion->testRestricted('other-store'))->toBeTrue();

        // Test no store ID provided
        expect($this->completion->testRestricted())->toBeFalse();

        // Clean up
        putenv('OPENFGA_MCP_API_RESTRICT=false');
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('extracts store ID from session correctly', function (): void {
        // Test with configured store
        putenv('OPENFGA_MCP_API_STORE=configured-store');
        expect($this->completion->testExtractStoreId($this->session))->toBe('configured-store');

        // Test without configured store
        putenv('OPENFGA_MCP_API_STORE=false');
        expect($this->completion->testExtractStoreId($this->session))->toBeNull();

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });
});
