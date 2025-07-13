<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * System criticality levels for risk assessment and audit purposes.
 */
enum SystemCriticality: string
{
    case CRITICAL = 'critical';

    case HIGH = 'high';

    case LOW = 'low';

    case MEDIUM = 'medium';
}
