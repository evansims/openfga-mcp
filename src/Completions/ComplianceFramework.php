<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

/**
 * Compliance frameworks and standards commonly used in security and audit contexts.
 */
enum ComplianceFramework: string
{
    case CCPA = 'CCPA';

    case FEDRAMP = 'FedRAMP';

    case FISMA = 'FISMA';

    case GDPR = 'GDPR';

    case HIPAA = 'HIPAA';

    case ISO27001 = 'ISO27001';

    case NIST = 'NIST';

    case NONE = 'none';

    case PCI_DSS = 'PCI-DSS';

    case SOC2 = 'SOC2';

    case SOX = 'SOX';
}
