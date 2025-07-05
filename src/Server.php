<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use OpenFGA\ClientInterface;

final class Server
{
    public function __construct(
        private readonly ?ClientInterface $client = null,
        private readonly ?string $url = null,
        private readonly ?string $store = null,
        private readonly ?string $model = null,
        private readonly ?string $token = null,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
        private readonly ?string $apiIssuer = null,
        private readonly ?string $apiAudience = null,
    ) {
    }
}
