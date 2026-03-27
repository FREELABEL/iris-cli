<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Packages;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Packages Resource
 *
 * Manage platform pricing packages (subscriptions + pay-as-you-go).
 * Supports pull/push workflow — local JSON as source of truth.
 */
class PackagesResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List all public packages with optional filtering.
     *
     * @param array{
     *     platform?: string,
     *     payment_type?: string,
     *     enable_free_trial?: string
     * } $params Filter parameters
     * @return array List of packages
     */
    public function list(array $params = []): array
    {
        return $this->http->get('/api/v1/platform/packages', $params);
    }

    /**
     * Get a single package by ID or slug.
     *
     * @param string $idOrSlug Package ID or slug
     * @return array Package data
     */
    public function get(string $idOrSlug): array
    {
        return $this->http->get("/api/v1/platform/packages/{$idOrSlug}");
    }

    /**
     * Bulk sync packages to the database.
     * Upserts by slug. Stripe sync off by default.
     *
     * @param array $packages Array of package definitions
     * @param bool $syncStripe Whether to create/update Stripe prices
     * @return array Sync results (created, updated, errors)
     */
    public function sync(array $packages, bool $syncStripe = false): array
    {
        return $this->http->post('/api/v1/platform/packages/sync', [
            'packages' => $packages,
            'sync_stripe' => $syncStripe,
        ]);
    }

    /**
     * Set a value at a dot-notation path on a package.
     *
     * @param string $slug Package slug
     * @param string $path Dot-notation path (e.g. "features.displayFeatures.0")
     * @param mixed $value Value to set
     * @return array Updated package
     */
    public function setPath(string $slug, string $path, $value): array
    {
        return $this->http->post("/api/v1/platform/packages/{$slug}/set-path", [
            'path' => $path,
            'value' => $value,
        ]);
    }
}
