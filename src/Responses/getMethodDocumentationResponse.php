<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

use function sprintf;

final readonly class getMethodDocumentationResponse extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string       $methodName The method name
     * @param string       $className  The class name
     * @param string       $sdk        The SDK identifier
     * @param string       $content    The documentation content
     * @param string|null  $signature  The method signature
     * @param array<mixed> $parameters The method parameters
     * @param string|null  $returns    The return type information
     * @param string       $status     Status message
     */
    public function __construct(
        private string $methodName,
        private string $className,
        private string $sdk,
        private string $content,
        private ?string $signature,
        private array $parameters,
        private ?string $returns,
        private string $status = '✅ Method Documentation',
    ) {
    }

    /**
     * Create and serialize the response in one step.
     *
     * @param  string                                                                                                                                                                      $methodName
     * @param  string                                                                                                                                                                      $className
     * @param  string                                                                                                                                                                      $sdk
     * @param  string                                                                                                                                                                      $content
     * @param  string|null                                                                                                                                                                 $signature
     * @param  array<mixed>                                                                                                                                                                $parameters
     * @param  string|null                                                                                                                                                                 $returns
     * @param  string                                                                                                                                                                      $status
     * @return array{status: string, content: string, metadata: array{method: string, class: string, sdk: string, signature: string|null, parameters: array<mixed>, returns: string|null}}
     */
    public static function create(
        string $methodName,
        string $className,
        string $sdk,
        string $content,
        ?string $signature,
        array $parameters,
        ?string $returns,
        string $status = '✅ Method Documentation',
    ): array {
        return (new self(
            $methodName,
            $className,
            $sdk,
            $content,
            $signature,
            $parameters,
            $returns,
            $status,
        ))->jsonSerialize();
    }

    /**
     * @return array{status: string, content: string, metadata: array{method: string, class: string, sdk: string, signature: string|null, parameters: array<mixed>, returns: string|null}}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => sprintf('%s: %s::%s', $this->status, $this->className, $this->methodName),
            'content' => $this->content,
            'metadata' => [
                'method' => $this->methodName,
                'class' => $this->className,
                'sdk' => $this->sdk,
                'signature' => $this->signature,
                'parameters' => $this->parameters,
                'returns' => $this->returns,
            ],
        ];
    }
}
