<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Models;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Models Resource
 *
 * List and manage available AI models.
 *
 * @example
 * ```php
 * // List basic models
 * $models = $iris->models->list(['is_basic' => true]);
 *
 * // Get popular models
 * $popular = $iris->models->popular();
 *
 * // Get model details
 * $model = $iris->models->get('gpt-4o-mini-2024-07-18');
 * ```
 */
class ModelsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List available AI models.
     *
     * @param array{
     *     is_basic?: bool,
     *     popular?: bool,
     *     limit?: int,
     *     offset?: int,
     *     selected_model_id?: string,
     *     provider?: string,
     *     category?: string
     * } $options Filter options
     * @return array List of models
     *
     * @example
     * ```php
     * // Get basic models (fast, low-cost)
     * $basic = $iris->models->list(['is_basic' => true, 'limit' => 6]);
     *
     * // Get all models with pagination
     * $all = $iris->models->list(['limit' => 20, 'offset' => 0]);
     *
     * // Get models from specific provider
     * $openai = $iris->models->list(['provider' => 'openai']);
     * ```
     */
    public function list(array $options = []): array
    {
        return $this->http->get("/api/v1/models", $options);
    }

    /**
     * Get basic/fast models.
     *
     * These are typically nano/mini models optimized for speed and cost.
     *
     * @param int $limit Number of models to return
     * @return array List of basic models
     *
     * @example
     * ```php
     * $basic = $iris->models->basic();
     * // Returns: gpt-5-nano, gpt-4.1-nano, gpt-4o-mini, etc.
     * ```
     */
    public function basic(int $limit = 6): array
    {
        return $this->list([
            'is_basic' => true,
            'popular' => false,
            'limit' => $limit,
        ]);
    }

    /**
     * Get popular models.
     *
     * @param int $limit Number of models to return
     * @return array List of popular models
     *
     * @example
     * ```php
     * $popular = $iris->models->popular();
     * ```
     */
    public function popular(int $limit = 6): array
    {
        return $this->list([
            'is_basic' => false,
            'popular' => true,
            'limit' => $limit,
        ]);
    }

    /**
     * Get a specific model by ID.
     *
     * @param string $modelId Model identifier
     * @return array Model details
     *
     * @example
     * ```php
     * $model = $iris->models->get('gpt-4o-mini-2024-07-18');
     * echo "Model: {$model['name']}\n";
     * echo "Provider: {$model['provider']}\n";
     * echo "Cost per 1K tokens: {$model['cost_per_1k']}\n";
     * ```
     */
    public function get(string $modelId): array
    {
        return $this->http->get("/api/v1/models/{$modelId}");
    }

    /**
     * Get models by provider.
     *
     * @param string $provider Provider name (openai, anthropic, google, etc.)
     * @param array $options Additional filter options
     * @return array List of models from provider
     *
     * @example
     * ```php
     * $openai = $iris->models->byProvider('openai');
     * $anthropic = $iris->models->byProvider('anthropic');
     * ```
     */
    public function byProvider(string $provider, array $options = []): array
    {
        return $this->list(array_merge(['provider' => $provider], $options));
    }

    /**
     * Get recommended model for a use case.
     *
     * @param string $useCase Use case (chat, coding, analysis, creative, etc.)
     * @return array Recommended model
     *
     * @example
     * ```php
     * $model = $iris->models->recommended('coding');
     * ```
     */
    public function recommended(string $useCase): array
    {
        return $this->http->get("/api/v1/models/recommended", [
            'use_case' => $useCase,
        ]);
    }

    /**
     * Get available providers.
     *
     * @return array List of AI providers
     */
    public function providers(): array
    {
        return $this->http->get("/api/v1/models/providers");
    }

    /**
     * Sync models from external providers.
     *
     * Admin/system endpoint to refresh model list.
     *
     * @return array Sync result
     */
    public function sync(): array
    {
        return $this->http->post("/api/v1/models/sync", []);
    }

    /**
     * Get model pricing information.
     *
     * @param string|null $modelId Specific model or null for all
     * @return array Pricing data
     */
    public function pricing(?string $modelId = null): array
    {
        $endpoint = $modelId
            ? "/api/v1/models/{$modelId}/pricing"
            : "/api/v1/models/pricing";

        return $this->http->get($endpoint);
    }

    /**
     * Get nano models (fastest, cheapest).
     *
     * Convenience method for getting the lightest models.
     *
     * @return array List of nano models
     *
     * @example
     * ```php
     * $nano = $iris->models->nano();
     * // Returns: gpt-5-nano, gpt-4.1-nano
     * ```
     */
    public function nano(): array
    {
        return $this->list([
            'category' => 'nano',
            'limit' => 10,
        ]);
    }
}
