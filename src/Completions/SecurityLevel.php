<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * Security requirement levels for authorization models and systems.
 */
enum SecurityLevel: string
{
    case CRITICAL = 'critical';

    case ENTERPRISE = 'enterprise';

    case GOVERNMENT = 'government';

    case HIGH = 'high';

    case STANDARD = 'standard';
}
