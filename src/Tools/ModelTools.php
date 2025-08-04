<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use OpenFGA\ClientInterface;
use OpenFGA\Exceptions\SerializationException;
use OpenFGA\Models\AuthorizationModelInterface;
use OpenFGA\Responses\{CreateAuthorizationModelResponseInterface, GetAuthorizationModelResponseInterface, ListAuthorizationModelsResponseInterface};
use PhpMcp\Server\Attributes\{McpTool};
use Throwable;

use function assert;

final readonly class ModelTools extends AbstractTools
{
    public function __construct(
        private ClientInterface $client,
    ) {
    }

    /**
     * Create a new authorization model using OpenFGA's DSL syntax.
     *
     * @param string $dsl   DSL representing the authorization model to create
     * @param string $store ID of the store to create the authorization model in
     *
     * @throws SerializationException
     * @throws Throwable
     *
     * @return string a success message, or an error message
     */
    #[McpTool(
        name: 'create_model',
    )]
    public function createModel(
        string $dsl,
        string $store,
    ): string {
        $error = $this->checkOfflineMode('Creating authorization models');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';
        $authorizationModel = null;

        $error = $this->checkWritePermission('create authorization models');

        if (null !== $error) {
            return $error;
        }

        $error = $this->checkRestrictedMode(storeId: $store);

        if (null !== $error) {
            return $error;
        }

        $this->client->dsl(dsl: $dsl)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to create authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $model) use (&$authorizationModel): void {
                if ($model instanceof AuthorizationModelInterface) {
                    $authorizationModel = $model;
                }
            });

        if (null !== $failure) {
            return $failure;
        }

        if (! $authorizationModel instanceof AuthorizationModelInterface) {
            return '❌ Failed to create authorization model!';
        }

        $this->client->createAuthorizationModel(store: $store, typeDefinitions: $authorizationModel->getTypeDefinitions(), conditions: $authorizationModel->getConditions())
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to create authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $model) use (&$success): void {
                assert($model instanceof CreateAuthorizationModelResponseInterface);
                $success = '✅ Successfully created authorization model! Model ID: ' . $model->getModel();
            });

        return $failure ?? $success;
    }

    /**
     * Get a specific authorization model from a particular store.
     *
     * @param string $store ID of the store to get the authorization model from
     * @param string $model ID of the authorization model to get
     *
     * @throws Throwable
     *
     * @return string the authorization model, or an error message
     */
    #[McpTool(name: 'get_model')]
    public function getModel(
        string $store,
        string $model,
    ): string {
        $error = $this->checkOfflineMode('Getting authorization model');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';

        $error = $this->checkRestrictedMode(storeId: $store, modelId: $model);

        if (null !== $error) {
            return $error;
        }

        $this->client->getAuthorizationModel(store: $store, model: $model)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to get authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $model) use (&$success): void {
                assert($model instanceof GetAuthorizationModelResponseInterface);
                $authModel = $model->getModel();

                if ($authModel instanceof AuthorizationModelInterface) {
                    $success = '✅ Found authorization model! Model ID: ' . $authModel->getId();
                } else {
                    $success = '❌ Authorization model not found!';
                }
            });

        return $failure ?? $success;
    }

    /**
     * Get the DSL from a specific authorization model from a particular store.
     *
     * @param string $store ID of the store to get the authorization model from
     * @param string $model ID of the authorization model to get
     *
     * @throws Throwable
     *
     * @return string the DSL representation of the authorization model, or an error message
     */
    #[McpTool(name: 'get_model_dsl')]
    public function getModelDsl(
        string $store,
        string $model,
    ): string {
        $error = $this->checkOfflineMode('Getting authorization model DSL');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';

        $error = $this->checkRestrictedMode(storeId: $store, modelId: $model);

        if (null !== $error) {
            return $error;
        }

        $this->client->getAuthorizationModel(store: $store, model: $model)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to get authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $model) use (&$success): void {
                assert($model instanceof GetAuthorizationModelResponseInterface);
                $authModel = $model->getModel();

                $success = $authModel instanceof AuthorizationModelInterface ? $authModel->dsl() : '❌ Authorization model not found!';
            });

        return $failure ?? $success;
    }

    /**
     * List authorization models in a store, sorted in descending order of creation.
     *
     * @param string $store ID of the store to list authorization models for
     *
     * @throws Throwable
     *
     * @return array<array{id: string}>|string A list of authorization models, or an error message
     */
    #[McpTool(name: 'list_models')]
    public function listModels(
        string $store,
    ): string | array {
        $error = $this->checkOfflineMode('Listing authorization models');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = [];

        $error = $this->checkRestrictedMode(storeId: $store);

        if (null !== $error) {
            return $error;
        }

        $this->client->listAuthorizationModels(store: $store)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to list authorization models! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $models) use (&$success): void {
                assert($models instanceof ListAuthorizationModelsResponseInterface);

                foreach ($models->getModels() as $model) {
                    $success[] = [
                        'id' => $model->getId(),
                    ];
                }
            });

        return $failure ?? $success;
    }

    /**
     * Verify a DSL representation of an authorization model.
     *
     * @param string $dsl DSL representation of the authorization model to verify
     *
     * @throws SerializationException
     * @throws Throwable
     *
     * @return string a success message, or an error message
     */
    #[McpTool(name: 'verify_model')]
    public function verifyModel(
        string $dsl,
    ): string {
        $error = $this->checkOfflineMode('Verifying authorization model');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';

        $this->client->dsl(dsl: $dsl)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to verify authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function () use (&$success): void {
                $success = '✅ Successfully verified! This DSL appears to represent a valid authorization model.';
            });

        return $failure ?? $success;
    }
}
