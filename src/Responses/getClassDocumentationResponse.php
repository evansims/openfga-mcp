<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

use function sprintf;

final readonly class getClassDocumentationResponse extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string        $className   The class name
     * @param string        $sdk         The SDK identifier
     * @param string        $content     The documentation content
     * @param string|null   $namespace   The class namespace
     * @param array<string> $methods     List of method names in the class
     * @param int           $methodCount Number of methods in the class
     * @param string        $status      Status message
     */
    public function __construct(
        private string $className,
        private string $sdk,
        private string $content,
        private ?string $namespace,
        private array $methods,
        private int $methodCount,
        private string $status = '✅ Class Documentation',
    ) {
    }

    /**
     * Create and serialize the response in one step.
     *
     * @param  string                                                                                                                                                 $className
     * @param  string                                                                                                                                                 $sdk
     * @param  string                                                                                                                                                 $content
     * @param  string|null                                                                                                                                            $namespace
     * @param  array<string>                                                                                                                                          $methods
     * @param  int                                                                                                                                                    $methodCount
     * @param  string                                                                                                                                                 $status
     * @return array{status: string, content: string, metadata: array{class: string, sdk: string, namespace: string|null, methods: array<string>, method_count: int}}
     */
    public static function create(
        string $className,
        string $sdk,
        string $content,
        ?string $namespace,
        array $methods,
        int $methodCount,
        string $status = '✅ Class Documentation',
    ): array {
        return (new self(
            $className,
            $sdk,
            $content,
            $namespace,
            $methods,
            $methodCount,
            $status,
        ))->jsonSerialize();
    }

    /**
     * @return array{status: string, content: string, metadata: array{class: string, sdk: string, namespace: string|null, methods: array<string>, method_count: int}}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => sprintf('%s: %s', $this->status, $this->className),
            'content' => $this->content,
            'metadata' => [
                'class' => $this->className,
                'sdk' => $this->sdk,
                'namespace' => $this->namespace,
                'methods' => $this->methods,
                'method_count' => $this->methodCount,
            ],
        ];
    }
}
