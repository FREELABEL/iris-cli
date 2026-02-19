<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Products;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Products Resource
 *
 * Manage product listings for profiles.
 *
 * @example
 * ```php
 * $product = $iris->products->create([
 *     'title' => 'Headshot Package',
 *     'description' => 'Professional headshots',
 *     'price' => 150,
 *     'profile_id' => 9203694,
 * ]);
 *
 * $products = $iris->products->list(['profile_id' => 9203694]);
 * ```
 */
class ProductsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List products, optionally filtered by profile.
     */
    public function list(array $filters = []): ProductCollection
    {
        $response = $this->http->get("/api/v1/products", $filters);

        return new ProductCollection(
            array_map(fn($data) => new Product($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Create a new product.
     *
     * @param array{
     *     title: string,
     *     description?: string,
     *     short_description?: string,
     *     price: float,
     *     retail_price?: float,
     *     profile_id?: int,
     *     photo?: string,
     *     quantity?: int,
     *     tags?: string,
     *     currency_code?: string,
     *     is_active?: bool
     * } $data Product data
     */
    public function create(array $data): Product
    {
        $userId = $this->config->requireUserId();

        $payload = array_merge([
            'user_id' => $userId,
            'is_active' => 1,
            'quantity' => 999,
            'currency_code' => 'USD',
        ], $data);

        $response = $this->http->post("/api/v1/products", $payload);

        return new Product($response['data']['product'] ?? $response['product'] ?? $response['data'] ?? $response);
    }

    /**
     * Get a single product by ID.
     */
    public function get(int $productId): Product
    {
        $response = $this->http->get("/api/v1/products/{$productId}");

        return new Product($response['data']['product'] ?? $response['product'] ?? $response['data'] ?? $response);
    }

    /**
     * Update an existing product.
     */
    public function update(int $productId, array $data): Product
    {
        $response = $this->http->put("/api/v1/products/{$productId}", $data);

        return new Product($response['data']['product'] ?? $response['product'] ?? $response['data'] ?? $response);
    }

    /**
     * Delete a product.
     */
    public function delete(int $productId): bool
    {
        $this->http->delete("/api/v1/products/{$productId}");

        return true;
    }
}
