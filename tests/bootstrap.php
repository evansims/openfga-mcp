<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../src/helpers.php';

// If running integration tests (detected by environment or by checking if we have OPENFGA_MCP_API_URL),
// include the integration bootstrap
if (getenv('RUNNING_INTEGRATION_TESTS') === 'true' || getenv('OPENFGA_MCP_API_URL')) {
    require_once __DIR__ . '/Integration/bootstrap.php';
}
