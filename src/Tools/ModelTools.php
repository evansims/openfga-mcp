<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use OpenFGA\Client;
use OpenFGA\Models\AuthorizationModelInterface;
use OpenFGA\Responses\{CreateAuthorizationModelResponseInterface, GetAuthorizationModelResponseInterface, ListAuthorizationModelsResponseInterface};
use PhpMcp\Server\Attributes\{McpTool};
use Throwable;

final class ModelTools
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    /**
     * Create a new authorization model using OpenFGA's DSL syntax.
     *
     * @param  string $dsl   DSL representing the authorization model to create
     * @param  string $store ID of the store to create the authorization model in
     * @return string a success message, or an error message
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
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to create authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function (AuthorizationModelInterface $model) use (&$authorizationModel): void {
                $authorizationModel = $model;
            });

        if ($failure || ! $authorizationModel instanceof AuthorizationModelInterface) {
            return $failure ?? '❌ Failed to create authorization model!';
        }

        $this->client->createAuthorizationModel(store: $store, typeDefinitions: $authorizationModel->getTypeDefinitions(), conditions: $authorizationModel->getConditions())
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to create authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function (CreateAuthorizationModelResponseInterface $model) use (&$success): void {
                $success = '✅ Successfully created authorization model! Model ID: ' . $model->getModel();
            });

        return $failure ?? $success;
    }

    /**
     * Get a specific authorization model from a particular store.
     *
     * @param  string $store ID of the store to get the authorization model from
     * @param  string $model ID of the authorization model to get
     * @return string the authorization model, or an error message
     */
    #[McpTool(name: 'get_model')]
    public function getModel(
        string $store,
        string $model,
    ): string {
        $failure = null;
        $success = '';

        $this->client->getAuthorizationModel(store: $store, model: $model)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to get authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function (GetAuthorizationModelResponseInterface $model) use (&$success): void {
                $success = '✅ Found authorization model! Model ID: ' . $model->getModel()->getId();
            });

        return $failure ?? $success;
    }

    /**
     * Get the DSL from a specific authorization model from a particular store.
     *
     * @param  string $store ID of the store to get the authorization model from
     * @param  string $model ID of the authorization model to get
     * @return string the DSL representation of the authorization model, or an error message
     */
    #[McpTool(name: 'get_model_dsl')]
    public function getModelDsl(
        string $store,
        string $model,
    ): string {
        $failure = null;
        $success = '';

        $this->client->getAuthorizationModel(store: $store, model: $model)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to get authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function (GetAuthorizationModelResponseInterface $model) use (&$success): void {
                $success = $model->getModel()->dsl();
            });

        return $failure ?? $success;
    }

    /**
     * List authorization models in a store, sorted in descending order of creation.
     *
     * @param  string       $store ID of the store to list authorization models for
     * @return array|string A list of authorization models, or an error message
     */
    #[McpTool(name: 'list_models')]
    public function listModels(
        string $store,
    ): string | array {
        $failure = null;
        $success = [];

        $this->client->listAuthorizationModels(store: $store)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to list authorization models! Error: ' . $e->getMessage();
            })
            ->success(static function (ListAuthorizationModelsResponseInterface $models) use (&$success): void {
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
     * @param  string $dsl DSL representation of the authorization model to verify
     * @return string a success message, or an error message
     */
    #[McpTool(name: 'verify_model')]
    public function verifyModel(
        string $dsl,
    ): string {
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
