<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * System types for authorization model design and implementation.
 */
enum SystemType: string
{
    case API = 'API';

    case DESKTOP_APP = 'desktop app';

    case ENTERPRISE = 'enterprise';

    case MICROSERVICES = 'microservices';

    case MOBILE_APP = 'mobile app';

    case SAAS_PLATFORM = 'SaaS platform';

    case WEB_APP = 'web app';
}
