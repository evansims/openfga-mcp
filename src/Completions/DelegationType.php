<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * Delegation types for secure delegation patterns.
 */
enum DelegationType: string
{
    case CONDITIONAL = 'conditional';

    case EMERGENCY = 'emergency';

    case PERMANENT = 'permanent';

    case ROLE_BASED = 'role-based';

    case TEMPORARY = 'temporary';

    case TIME_BOUND = 'time-bound';
}
