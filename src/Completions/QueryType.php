<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * OpenFGA query types for performance optimization and troubleshooting.
 */
enum QueryType: string
{
    case CHECK = 'check';

    case EXPAND = 'expand';

    case LIST_OBJECTS = 'list_objects';

    case LIST_USERS = 'list_users';

    case READ_TUPLES = 'read_tuples';

    case WRITE_TUPLES = 'write_tuples';
}
