<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Documentation;

use Throwable;

/**
 * Singleton wrapper for DocumentationIndex to ensure single initialization.
 */
final class DocumentationIndexSingleton
{
    private static ?DocumentationIndex $instance = null;

    private function __construct()
    {
        // Private constructor to prevent direct instantiation
    }

    public static function getInstance(): DocumentationIndex
    {
        if (! self::$instance instanceof DocumentationIndex) {
            self::$instance = new DocumentationIndex;

            // Try to initialize eagerly
            try {
                self::$instance->initialize();
            } catch (Throwable) {
                // Initialization will happen on first use if it fails here
            }
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance (mainly for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
