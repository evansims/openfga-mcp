<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Unit\Resources;

use OpenFGA\MCP\Resources\AbstractResources;

// Concrete implementation of AbstractResources for testing
final readonly class TestableResources extends AbstractResources
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
