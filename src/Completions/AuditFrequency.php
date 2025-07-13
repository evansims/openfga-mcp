<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * Audit frequency intervals for compliance reporting.
 */
enum AuditFrequency: string
{
    case ANNUAL = 'annual';

    case BIANNUAL = 'biannual';

    case DAILY = 'daily';

    case MONTHLY = 'monthly';

    case QUARTERLY = 'quarterly';

    case WEEKLY = 'weekly';
}
