<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * Complexity levels for authorization models and systems.
 */
enum ComplexityLevel: string
{
    case COMPLEX = 'complex';

    case ENTERPRISE = 'enterprise';

    case HIGHLY_NESTED = 'highly nested';

    case MODERATE = 'moderate';

    case SIMPLE = 'simple';
}
