<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * Risk levels for security assessment and delegation patterns.
 */
enum RiskLevel: string
{
    case CRITICAL = 'critical';

    case HIGH = 'high';

    case LOW = 'low';

    case MEDIUM = 'medium';
}
