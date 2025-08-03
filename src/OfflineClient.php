<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use DateTimeImmutable;
use OpenFGA\ClientInterface;
use OpenFGA\Models\{AuthorizationModelInterface, StoreInterface, TupleKeyInterface};
use OpenFGA\Models\Collections\{AssertionsInterface, ConditionsInterface, TupleKeysInterface, TypeDefinitionsInterface, UserTypeFiltersInterface};
use OpenFGA\Models\Collections\BatchCheckItemsInterface;
use OpenFGA\Models\Enums\{Consistency, SchemaVersion};
use OpenFGA\Results\{Failure, FailureInterface, Success, SuccessInterface};
use Override;
use Psr\Http\Message\{RequestInterface as HttpRequestInterface, ResponseInterface as HttpResponseInterface};
use RuntimeException;

/**
 * Offline client implementation for OpenFGA MCP Server.
 *
 * This client allows the MCP server to function without a live OpenFGA instance,
 * enabling planning and coding features while returning appropriate responses
 * for operations that would normally require a live connection.
 *
 * Read operations return minimal empty success responses, while write operations
 * return failures, effectively preventing actual API calls while satisfying
 * the interface contract.
 */
final class OfflineClient implements ClientInterface
{
    private const string ERROR_MESSAGE = 'This operation requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.';

    #[Override]
    public function batchCheck(
        StoreInterface | string $store,
        AuthorizationModelInterface | string $model,
        BatchCheckItemsInterface $checks,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['results' => []]);
    }

    #[Override]
    public function check(
        StoreInterface | string $store,
        AuthorizationModelInterface | string $model,
        TupleKeyInterface $tuple,
        ?bool $trace = null,
        ?object $context = null,
        ?TupleKeysInterface $contextualTuples = null,
        ?Consistency $consistency = null,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['allowed' => false, 'resolution' => 'offline']);
    }

    #[Override]
    public function createAuthorizationModel(
        StoreInterface | string $store,
        TypeDefinitionsInterface $typeDefinitions,
        ?ConditionsInterface $conditions = null,
        ?SchemaVersion $schemaVersion = null,
    ): FailureInterface | SuccessInterface {
        // Write operation - returns failure in offline mode
        if ($this->shouldFail(true)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(null);
    }

    #[Override]
    public function createStore(string $name): FailureInterface | SuccessInterface
    {
        // Write operation - returns failure in offline mode
        if ($this->shouldFail(true)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(null);
    }

    #[Override]
    public function deleteStore(StoreInterface | string $store): FailureInterface | SuccessInterface
    {
        // Write operation - returns failure in offline mode
        if ($this->shouldFail(true)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(null);
    }

    #[Override]
    public function dsl(string $dsl): FailureInterface | SuccessInterface
    {
        // DSL parsing can work offline - returns minimal success
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['valid' => false, 'message' => 'DSL validation requires a live OpenFGA instance']);
    }

    #[Override]
    public function expand(
        StoreInterface | string $store,
        TupleKeyInterface $tuple,
        AuthorizationModelInterface | string | null $model = null,
        ?TupleKeysInterface $contextualTuples = null,
        ?Consistency $consistency = null,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['tree' => []]);
    }

    #[Override]
    public function getAuthorizationModel(
        StoreInterface | string $store,
        AuthorizationModelInterface | string $model,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(null);
    }

    #[Override]
    public function getLastRequest(): ?HttpRequestInterface
    {
        return null;
    }

    #[Override]
    public function getLastResponse(): ?HttpResponseInterface
    {
        return null;
    }

    #[Override]
    public function getStore(StoreInterface | string $store): FailureInterface | SuccessInterface
    {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(null);
    }

    #[Override]
    public function listAuthorizationModels(
        StoreInterface | string $store,
        ?string $continuationToken = null,
        ?int $pageSize = null,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['models' => []]);
    }

    #[Override]
    public function listObjects(
        StoreInterface | string $store,
        AuthorizationModelInterface | string $model,
        string $type,
        string $relation,
        string $user,
        ?object $context = null,
        ?TupleKeysInterface $contextualTuples = null,
        ?Consistency $consistency = null,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['objects' => []]);
    }

    #[Override]
    public function listStores(
        ?string $continuationToken = null,
        ?int $pageSize = null,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['stores' => []]);
    }

    #[Override]
    public function listTupleChanges(
        StoreInterface | string $store,
        ?string $continuationToken = null,
        ?int $pageSize = null,
        ?string $type = null,
        ?DateTimeImmutable $startTime = null,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['changes' => []]);
    }

    #[Override]
    public function listUsers(
        StoreInterface | string $store,
        AuthorizationModelInterface | string $model,
        string $object,
        string $relation,
        UserTypeFiltersInterface $userFilters,
        ?object $context = null,
        ?TupleKeysInterface $contextualTuples = null,
        ?Consistency $consistency = null,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['users' => []]);
    }

    #[Override]
    public function readAssertions(
        StoreInterface | string $store,
        AuthorizationModelInterface | string $model,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['assertions' => []]);
    }

    #[Override]
    public function readTuples(
        StoreInterface | string $store,
        ?TupleKeyInterface $tuple = null,
        ?string $continuationToken = null,
        ?int $pageSize = null,
        ?Consistency $consistency = null,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['tuples' => []]);
    }

    #[Override]
    public function streamedListObjects(
        StoreInterface | string $store,
        AuthorizationModelInterface | string $model,
        string $type,
        string $relation,
        string $user,
        ?object $context = null,
        ?TupleKeysInterface $contextualTuples = null,
        ?Consistency $consistency = null,
    ): FailureInterface | SuccessInterface {
        // Read operation - returns success in offline mode
        if ($this->shouldFail(false)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(['objects' => []]);
    }

    #[Override]
    public function writeAssertions(
        StoreInterface | string $store,
        AuthorizationModelInterface | string $model,
        AssertionsInterface $assertions,
    ): FailureInterface | SuccessInterface {
        // Write operation - returns failure in offline mode
        if ($this->shouldFail(true)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(null);
    }

    #[Override]
    public function writeTuples(
        StoreInterface | string $store,
        AuthorizationModelInterface | string $model,
        ?TupleKeysInterface $writes = null,
        ?TupleKeysInterface $deletes = null,
        bool $transactional = true,
        int $maxParallelRequests = 1,
        int $maxTuplesPerChunk = 100,
        int $maxRetries = 0,
        float $retryDelaySeconds = 1.0,
        bool $stopOnFirstError = false,
    ): FailureInterface | SuccessInterface {
        // Write operation - returns failure in offline mode
        if ($this->shouldFail(true)) {
            return new Failure(new RuntimeException(self::ERROR_MESSAGE));
        }

        return new Success(null);
    }

    /**
     * Determines if an operation should fail (write operations) or succeed (read operations).
     *
     * @param  bool $isWriteOperation Whether the operation modifies state
     * @return bool True if the operation should fail
     */
    private function shouldFail(bool $isWriteOperation): bool
    {
        // In offline mode, write operations always fail, read operations always succeed
        return $isWriteOperation;
    }
}
