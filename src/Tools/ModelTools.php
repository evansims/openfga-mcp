<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use OpenFGA\Client;
use OpenFGA\Exceptions\SerializationException;
use OpenFGA\Models\AuthorizationModelInterface;
use OpenFGA\Responses\{CreateAuthorizationModelResponseInterface, GetAuthorizationModelResponseInterface, ListAuthorizationModelsResponseInterface};
use PhpMcp\Server\Attributes\{McpTool};
use Throwable;

use function assert;

final readonly class ModelTools extends AbstractTools
{
    public function __construct(
        private Client $client,
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
    #[McpTool(name: 'create_model')]
    public function createModel(
        string $dsl,
        string $store,
    ): string {
        $failure = null;
        $success = '';
        $authorizationModel = null;

        if (getConfiguredString('OPENFGA_MCP_API_READONLY', 'false') === 'true') {
            return '❌ The MCP server is configured in read only mode. You cannot create authorization models in this mode.';
        }

        if (getConfiguredString('OPENFGA_MCP_API_RESTRICT', 'false') === 'true') {
            $restrictedStore = getConfiguredString('OPENFGA_MCP_API_STORE', '');

            if ($restrictedStore !== '' && $restrictedStore !== $store) {
                return '❌ The MCP server is configured in restricted mode. You cannot query stores other than ' . $restrictedStore . ' in this mode.';
            }
        }

        $this->client->dsl(dsl: $dsl)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to create authorization model! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $model) use (&$authorizationModel): void {
                assert($model instanceof AuthorizationModelInterface);
                $authorizationModel = $model;
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
        $failure = null;
        $success = '';

        if (getConfiguredString('OPENFGA_MCP_API_RESTRICT', 'false') === 'true') {
            $restrictedStore = getConfiguredString('OPENFGA_MCP_API_STORE', '');

            if ($restrictedStore !== '' && $restrictedStore !== $store) {
                return '❌ The MCP server is configured in restricted mode. You cannot query stores other than ' . $restrictedStore . ' in this mode.';
            }

            $restrictedModel = getConfiguredString('OPENFGA_MCP_API_MODEL', '');

            if ($restrictedModel !== '' && $restrictedModel !== $model) {
                return '❌ The MCP server is configured in restricted mode. You cannot query authorization models other than ' . $restrictedModel . ' in this mode.';
            }
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
        $failure = null;
        $success = '';

        if (getConfiguredString('OPENFGA_MCP_API_RESTRICT', 'false') === 'true') {
            $restrictedStore = getConfiguredString('OPENFGA_MCP_API_STORE', '');

            if ($restrictedStore !== '' && $restrictedStore !== $store) {
                return '❌ The MCP server is configured in restricted mode. You cannot query stores other than ' . $restrictedStore . ' in this mode.';
            }

            $restrictedModel = getConfiguredString('OPENFGA_MCP_API_MODEL', '');

            if ($restrictedModel !== '' && $restrictedModel !== $model) {
                return '❌ The MCP server is configured in restricted mode. You cannot query using authorization models other than ' . $restrictedModel . ' in this mode.';
            }
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
    /**
     * @param  string                          $store
     * @return array<array{id: string}>|string
     */
    public function listModels(
        string $store,
    ): string | array {
        $failure = null;
        $success = [];

        if (getConfiguredString('OPENFGA_MCP_API_RESTRICT', 'false') === 'true') {
            $restrictedStore = getConfiguredString('OPENFGA_MCP_API_STORE', '');

            if ($restrictedStore !== '' && $restrictedStore !== $store) {
                return '❌ The MCP server is configured in restricted mode. You cannot query stores other than ' . $restrictedStore . ' in this mode.';
            }
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
