<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * Access control patterns for authorization model design.
 */
enum AccessPattern: string
{
    case ATTRIBUTE_BASED = 'attribute-based';

    case FLAT = 'flat';

    case HIERARCHICAL = 'hierarchical';

    case HYBRID = 'hybrid';

    case MATRIX = 'matrix';

    case ROLE_BASED = 'role-based';
}
