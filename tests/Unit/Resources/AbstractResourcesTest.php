<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use OpenFGA\MCP\Resources\AbstractResources;
use ReflectionClass;

use function in_array;

// Create a concrete implementation for testing
final readonly class AbstractResourcesTest extends AbstractResources
{
    public function testCheckOfflineMode(string $operation): ?array
    {
        return $this->checkOfflineMode($operation);
    }

    public function testCheckRestrictedMode(?string $storeId = null, ?string $modelId = null): ?array
    {
        return $this->checkRestrictedMode($storeId, $modelId);
    }
}

beforeEach(function (): void {
    $this->resources = new TestableResources;
});

afterEach(function (): void {
    // Clean up environment variables
    putenv('OPENFGA_MCP_API_URL');
    putenv('OPENFGA_MCP_API_TOKEN');
    putenv('OPENFGA_MCP_API_CLIENT_ID');
    putenv('OPENFGA_MCP_API_RESTRICT');
    putenv('OPENFGA_MCP_API_STORE');
    putenv('OPENFGA_MCP_API_MODEL');
});

describe('AbstractResources', function (): void {
    describe('class structure', function (): void {
        it('is an abstract class', function (): void {
            $reflection = new ReflectionClass(AbstractResources::class);
            expect($reflection->isAbstract())->toBeTrue();
        });

        it('is readonly', function (): void {
            $reflection = new ReflectionClass(AbstractResources::class);
            expect($reflection->isReadOnly())->toBeTrue();
        });

        it('has protected methods', function (): void {
            $reflection = new ReflectionClass(AbstractResources::class);

            $checkOfflineMode = $reflection->getMethod('checkOfflineMode');
            expect($checkOfflineMode->isProtected())->toBeTrue();

            $checkRestrictedMode = $reflection->getMethod('checkRestrictedMode');
            expect($checkRestrictedMode->isProtected())->toBeTrue();
        });
    });

    describe('checkOfflineMode', function (): void {
        it('returns null when not in offline mode', function (): void {
            // Set up online mode
            putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

            $result = $this->resources->testCheckOfflineMode('Test Operation');

            expect($result)->toBeNull();
        });

        it('returns error array when in offline mode (no URL)', function (): void {
            // Clear all connection settings for offline mode
            putenv('OPENFGA_MCP_API_URL=');
            putenv('OPENFGA_MCP_API_TOKEN=');
            putenv('OPENFGA_MCP_API_CLIENT_ID=');

            $result = $this->resources->testCheckOfflineMode('Test Operation');

            expect($result)->toBeArray();
            expect($result)->toHaveKey('error');
            expect($result['error'])->toContain('❌');
            expect($result['error'])->toContain('Test Operation');
            expect($result['error'])->toContain('requires a live OpenFGA instance');
            expect($result['error'])->toContain('OPENFGA_MCP_API_URL');
        });

        it('returns error with custom operation name', function (): void {
            putenv('OPENFGA_MCP_API_URL=');

            $result = $this->resources->testCheckOfflineMode('Fetching authorization models');

            expect($result)->toBeArray();
            expect($result['error'])->toContain('Fetching authorization models');
        });

        it('returns error when URL is empty string', function (): void {
            putenv('OPENFGA_MCP_API_URL=');

            $result = $this->resources->testCheckOfflineMode('Operation');

            expect($result)->toBeArray();
            expect($result)->toHaveKey('error');
        });

        it('returns null when token is set without URL', function (): void {
            // Token with no URL is considered online mode (intent to connect)
            putenv('OPENFGA_MCP_API_URL=');
            putenv('OPENFGA_MCP_API_TOKEN=some-token');

            $result = $this->resources->testCheckOfflineMode('Operation');

            // Should return null because having a token indicates online mode
            expect($result)->toBeNull();
        });

        it('returns null when client ID is set without URL', function (): void {
            // Client ID with no URL is considered online mode (intent to connect)
            putenv('OPENFGA_MCP_API_URL=');
            putenv('OPENFGA_MCP_API_CLIENT_ID=client-123');

            $result = $this->resources->testCheckOfflineMode('Operation');

            // Should return null because having a client ID indicates online mode
            expect($result)->toBeNull();
        });
    });

    describe('checkRestrictedMode', function (): void {
        describe('when not in restricted mode', function (): void {
            it('returns null when restricted mode is disabled (default)', function (): void {
                putenv('OPENFGA_MCP_API_RESTRICT=');

                $result = $this->resources->testCheckRestrictedMode('any-store', 'any-model');

                expect($result)->toBeNull();
            });

            it('returns null when restricted mode is explicitly false', function (): void {
                putenv('OPENFGA_MCP_API_RESTRICT=false');

                $result = $this->resources->testCheckRestrictedMode('store-123', 'model-456');

                expect($result)->toBeNull();
            });

            it('returns null for any value other than "true"', function (): void {
                putenv('OPENFGA_MCP_API_RESTRICT=yes');
                $result = $this->resources->testCheckRestrictedMode('store', 'model');
                expect($result)->toBeNull();

                putenv('OPENFGA_MCP_API_RESTRICT=1');
                $result = $this->resources->testCheckRestrictedMode('store', 'model');
                expect($result)->toBeNull();

                putenv('OPENFGA_MCP_API_RESTRICT=TRUE');
                $result = $this->resources->testCheckRestrictedMode('store', 'model');
                expect($result)->toBeNull();
            });
        });

        describe('when in restricted mode', function (): void {
            beforeEach(function (): void {
                putenv('OPENFGA_MCP_API_RESTRICT=true');
            });

            describe('store restrictions', function (): void {
                it('returns null when querying the configured store', function (): void {
                    putenv('OPENFGA_MCP_API_STORE=allowed-store');

                    $result = $this->resources->testCheckRestrictedMode('allowed-store', null);

                    expect($result)->toBeNull();
                });

                it('returns error when querying a different store', function (): void {
                    putenv('OPENFGA_MCP_API_STORE=allowed-store');

                    $result = $this->resources->testCheckRestrictedMode('different-store', null);

                    expect($result)->toBeArray();
                    expect($result)->toHaveKey('error');
                    expect($result['error'])->toContain('❌');
                    expect($result['error'])->toContain('restricted mode');
                    expect($result['error'])->toContain('allowed-store');
                    expect($result['error'])->toContain('cannot query stores other than');
                });

                it('returns null when no store restriction is configured', function (): void {
                    putenv('OPENFGA_MCP_API_STORE=');

                    $result = $this->resources->testCheckRestrictedMode('any-store', null);

                    expect($result)->toBeNull();
                });

                it('returns null when store ID is null', function (): void {
                    putenv('OPENFGA_MCP_API_STORE=restricted-store');

                    $result = $this->resources->testCheckRestrictedMode(null, null);

                    expect($result)->toBeNull();
                });

                it('handles empty string store restriction', function (): void {
                    putenv('OPENFGA_MCP_API_STORE=');

                    $result = $this->resources->testCheckRestrictedMode('some-store', null);

                    expect($result)->toBeNull();
                });
            });

            describe('model restrictions', function (): void {
                it('returns null when querying the configured model', function (): void {
                    putenv('OPENFGA_MCP_API_MODEL=allowed-model');

                    $result = $this->resources->testCheckRestrictedMode(null, 'allowed-model');

                    expect($result)->toBeNull();
                });

                it('returns error when querying a different model', function (): void {
                    putenv('OPENFGA_MCP_API_MODEL=allowed-model');

                    $result = $this->resources->testCheckRestrictedMode(null, 'different-model');

                    expect($result)->toBeArray();
                    expect($result)->toHaveKey('error');
                    expect($result['error'])->toContain('❌');
                    expect($result['error'])->toContain('restricted mode');
                    expect($result['error'])->toContain('allowed-model');
                    expect($result['error'])->toContain('cannot query using authorization models other than');
                });

                it('returns null when no model restriction is configured', function (): void {
                    putenv('OPENFGA_MCP_API_MODEL=');

                    $result = $this->resources->testCheckRestrictedMode(null, 'any-model');

                    expect($result)->toBeNull();
                });

                it('returns null when model ID is null', function (): void {
                    putenv('OPENFGA_MCP_API_MODEL=restricted-model');

                    $result = $this->resources->testCheckRestrictedMode(null, null);

                    expect($result)->toBeNull();
                });

                it('handles empty string model restriction', function (): void {
                    putenv('OPENFGA_MCP_API_MODEL=');

                    $result = $this->resources->testCheckRestrictedMode(null, 'some-model');

                    expect($result)->toBeNull();
                });
            });

            describe('combined store and model restrictions', function (): void {
                it('returns null when both store and model match', function (): void {
                    putenv('OPENFGA_MCP_API_STORE=allowed-store');
                    putenv('OPENFGA_MCP_API_MODEL=allowed-model');

                    $result = $this->resources->testCheckRestrictedMode('allowed-store', 'allowed-model');

                    expect($result)->toBeNull();
                });

                it('returns error when store does not match', function (): void {
                    putenv('OPENFGA_MCP_API_STORE=allowed-store');
                    putenv('OPENFGA_MCP_API_MODEL=allowed-model');

                    $result = $this->resources->testCheckRestrictedMode('wrong-store', 'allowed-model');

                    expect($result)->toBeArray();
                    expect($result['error'])->toContain('allowed-store');
                });

                it('returns error when model does not match', function (): void {
                    putenv('OPENFGA_MCP_API_STORE=allowed-store');
                    putenv('OPENFGA_MCP_API_MODEL=allowed-model');

                    $result = $this->resources->testCheckRestrictedMode('allowed-store', 'wrong-model');

                    expect($result)->toBeArray();
                    expect($result['error'])->toContain('allowed-model');
                });

                it('returns store error first when both do not match', function (): void {
                    putenv('OPENFGA_MCP_API_STORE=allowed-store');
                    putenv('OPENFGA_MCP_API_MODEL=allowed-model');

                    $result = $this->resources->testCheckRestrictedMode('wrong-store', 'wrong-model');

                    expect($result)->toBeArray();
                    // Store check happens first, so we get store error
                    expect($result['error'])->toContain('allowed-store');
                    expect($result['error'])->not->toContain('allowed-model');
                });

                it('allows partial restrictions', function (): void {
                    // Only store restricted
                    putenv('OPENFGA_MCP_API_STORE=allowed-store');
                    putenv('OPENFGA_MCP_API_MODEL=');

                    $result = $this->resources->testCheckRestrictedMode('allowed-store', 'any-model');
                    expect($result)->toBeNull();

                    // Only model restricted
                    putenv('OPENFGA_MCP_API_STORE=');
                    putenv('OPENFGA_MCP_API_MODEL=allowed-model');

                    $result = $this->resources->testCheckRestrictedMode('any-store', 'allowed-model');
                    expect($result)->toBeNull();
                });
            });
        });

        describe('edge cases', function (): void {
            it('handles special characters in store/model names', function (): void {
                putenv('OPENFGA_MCP_API_RESTRICT=true');
                putenv('OPENFGA_MCP_API_STORE=store-with-special_chars.123');

                $result = $this->resources->testCheckRestrictedMode('store-with-special_chars.123', null);
                expect($result)->toBeNull();

                $result = $this->resources->testCheckRestrictedMode('different-store', null);
                expect($result)->toBeArray();
                expect($result['error'])->toContain('store-with-special_chars.123');
            });

            it('handles case-sensitive comparisons', function (): void {
                putenv('OPENFGA_MCP_API_RESTRICT=true');
                putenv('OPENFGA_MCP_API_STORE=MyStore');

                // Case must match exactly
                $result = $this->resources->testCheckRestrictedMode('mystore', null);
                expect($result)->toBeArray();
                expect($result['error'])->toContain('MyStore');

                $result = $this->resources->testCheckRestrictedMode('MyStore', null);
                expect($result)->toBeNull();
            });

            it('handles whitespace in configuration', function (): void {
                putenv('OPENFGA_MCP_API_RESTRICT=true');
                putenv('OPENFGA_MCP_API_STORE= '); // Space

                $result = $this->resources->testCheckRestrictedMode('any-store', null);
                // Space is trimmed by getConfiguredString, becomes empty string
                expect($result)->toBeNull();
            });
        });
    });

    describe('method visibility and inheritance', function (): void {
        it('provides protected methods to child classes', function (): void {
            // Use TestableResources instead of anonymous class to avoid Pest crash
            $childClass = new TestableResources;

            // Use reflection to verify methods exist
            $reflection = new ReflectionClass($childClass);
            $methods = $reflection->getMethods();
            $methodNames = array_map(fn ($m) => $m->getName(), $methods);

            expect(in_array('checkOfflineMode', $methodNames, true))->toBeTrue();
            expect(in_array('checkRestrictedMode', $methodNames, true))->toBeTrue();
        });

        it('methods return expected types', function (): void {
            $reflection = new ReflectionClass(AbstractResources::class);

            $checkOfflineMode = $reflection->getMethod('checkOfflineMode');
            $returnType = $checkOfflineMode->getReturnType();
            expect($returnType)->not->toBeNull();
            expect($returnType->allowsNull())->toBeTrue();

            $checkRestrictedMode = $reflection->getMethod('checkRestrictedMode');
            $returnType = $checkRestrictedMode->getReturnType();
            expect($returnType)->not->toBeNull();
            expect($returnType->allowsNull())->toBeTrue();
        });
    });
});
