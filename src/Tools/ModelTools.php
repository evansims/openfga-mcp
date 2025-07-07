<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use OpenFGA\Client;
use OpenFGA\Models\AuthorizationModelInterface;
use OpenFGA\Responses\{CreateAuthorizationModelResponseInterface, CreateStoreResponseInterface, GetAuthorizationModelResponseInterface, GetStoreResponseInterface, ListAuthorizationModelsResponseInterface, ListStoresResponseInterface};
use PhpMcp\Server\Attributes\{McpTool, McpResource, McpResourceTemplate, McpPrompt, Schema};
use Throwable;

class ModelTools
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * List authorization models in a store, sorted in descending order of creation.
     *
     * @param string $store ID of the store to list authorization models for.
     *
     * @return string | array A list of authorization models, or an error message.
     */
    #[McpTool(name: 'list_models')]
    public function listModels(
        string $store,
    ): string | array
    {
        $failure = null;
        $success = [];

        $this->client->listAuthorizationModels(store: $store)
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to list authorization models! Error: {$e->getMessage()}";
            })
            ->success(function (ListAuthorizationModelsResponseInterface $models) use (&$success) {
                foreach ($models->getModels() as $model) {
                    $success[] = [
                        'id' => $model->getId(),
                    ];
                }
            });

        return $failure ?? $success;
    }

    /**
     * Create a new authorization model using OpenFGA's DSL syntax.
     *
     * @param string $dsl DSL representing the authorization model to create.
     * @param string $store ID of the store to create the authorization model in.
     *
     * @return string A success message, or an error message.
     */
    #[McpTool(name: 'create_model')]
    public function createModel(
        string $dsl,
        string $store,
    ): string {
        $failure = null;
        $success = '';
        $authorizationModel = null;

        $this->client->dsl(dsl: $dsl)
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to create authorization model! Error: {$e->getMessage()}";
            })
            ->success(function (AuthorizationModelInterface $model) use (&$authorizationModel) {
                $authorizationModel = $model;
            });

        if ($failure || $authorizationModel === null) {
            return $failure ?? '❌ Failed to create authorization model!';
        }

        $this->client->createAuthorizationModel(store: $store, typeDefinitions: $authorizationModel->getTypeDefinitions(), conditions: $authorizationModel->getConditions())
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to create authorization model! Error: {$e->getMessage()}";
            })
            ->success(function (CreateAuthorizationModelResponseInterface $model) use (&$success) {
                $success = "✅ Successfully created authorization model! Model ID: {$model->getModel()}";
            });

        return $failure ?? $success;
    }

    /**
     * Get a specific authorization model from a particular store.
     *
     * @param string $store ID of the store to get the authorization model from.
     * @param string $model ID of the authorization model to get.
     *
     * @return string The authorization model, or an error message.
     */
    #[McpTool(name: 'get_model')]
    public function getModel(
        string $store,
        string $model,
    ): string
    {
        $failure = null;
        $success = '';

        $this->client->getAuthorizationModel(store: $store, model: $model)
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to get authorization model! Error: {$e->getMessage()}";
            })
            ->success(function (GetAuthorizationModelResponseInterface $model) use (&$success) {
                $success = "✅ Found authorization model! Model ID: {$model->getModel()->getId()}";
            });

        return $failure ?? $success;
    }

    /**
     * Verify a DSL representation of an authorization model.
     *
     * @param string $dsl DSL representation of the authorization model to verify.
     *
     * @return string A success message, or an error message.
     */
    #[McpTool(name: 'verify_model')]
    public function verifyModel(
        string $dsl,
    ): string
    {
        $failure = null;
        $success = '';

        $this->client->dsl(dsl: $dsl)
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to verify authorization model! Error: {$e->getMessage()}";
            })
            ->success(function () use (&$success) {
                $success = "✅ Successfully verified! This DSL appears to represent a valid authorization model.";
            });

        return $failure ?? $success;
    }

    /**
     * Get the DSL from a specific authorization model from a particular store.
     *
     * @param string $store ID of the store to get the authorization model from.
     * @param string $model ID of the authorization model to get.
     *
     * @return string The DSL representation of the authorization model, or an error message.
     */
    #[McpTool(name: 'get_dsl_from_model')]
    public function getDslFromModel(
        string $store,
        string $model,
    ): string
    {
        $failure = null;
        $success = '';

        $this->client->getAuthorizationModel(store: $store, model: $model)
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to get authorization model! Error: {$e->getMessage()}";
            })
            ->success(function (GetAuthorizationModelResponseInterface $model) use (&$success) {
                $success = $model->getModel()->dsl();
            });

        return $failure ?? $success;
    }
}