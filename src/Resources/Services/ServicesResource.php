<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Services;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Services Resource
 *
 * Manage service offerings for profiles.
 *
 * @example
 * ```php
 * // Create a new service
 * $service = $iris->services->create([
 *     'title' => 'Professional Video Editing',
 *     'description' => 'High-quality video editing service',
 *     'price' => 100,
 *     'profile_id' => 69,
 *     'delivery_amount' => 3,
 *     'delivery_frequency' => 'days',
 *     'checklist' => ['Source files provided', 'Multiple file formats'],
 *     'addons' => [
 *         ['title' => 'Extra revision', 'price' => 15]
 *     ]
 * ]);
 *
 * // List services
 * $services = $iris->services->list();
 *
 * // Get a service by ID
 * $service = $iris->services->get(123);
 *
 * // Update a service
 * $service = $iris->services->update(123, [
 *     'price' => 150,
 *     'description' => 'Updated description'
 * ]);
 *
 * // Delete a service
 * $iris->services->delete(123);
 * ```
 */
class ServicesResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List all services for the current user.
     *
     * @param array{
     *     profile_id?: int,
     *     bloq_id?: int,
     *     status?: int
     * } $filters Filter options
     * @return ServiceCollection
     */
    public function list(array $filters = []): ServiceCollection
    {
        $response = $this->http->get("/api/v1/services", $filters);

        return new ServiceCollection(
            array_map(fn($data) => new Service($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Create a new service.
     *
     * @param array{
     *     title: string,
     *     description?: string,
     *     price: float,
     *     price_max?: float,
     *     profile_id?: int,
     *     bloq_id?: int,
     *     photo?: string,
     *     custom_request_required?: bool,
     *     delivery_amount?: int,
     *     delivery_frequency?: string,
     *     status?: int,
     *     payment_recipient_type?: string,
     *     keywords?: string,
     *     checklist?: array,
     *     addons?: array
     * } $data Service data
     * @return Service
     */
    public function create(array $data): Service
    {
        $userId = $this->config->requireUserId();

        // Set defaults
        $payload = array_merge([
            'user_id' => $userId,
            'status' => 1,
            'delivery_amount' => 3,
            'delivery_frequency' => 'days',
            'custom_request_required' => false,
            'payment_recipient_type' => 'auto'
        ], $data);

        // Convert checklist and addons arrays to JSON strings if needed
        if (isset($payload['checklist']) && is_array($payload['checklist'])) {
            $payload['checklist'] = json_encode($payload['checklist']);
        }
        if (isset($payload['addons']) && is_array($payload['addons'])) {
            $payload['addons'] = json_encode($payload['addons']);
        }

        $response = $this->http->post("/api/v1/services", $payload);

        return new Service($response['data']['service'] ?? $response['service'] ?? $response);
    }

    /**
     * Get a single service by ID.
     *
     * @param int $serviceId Service ID
     * @return Service
     */
    public function get(int $serviceId): Service
    {
        $response = $this->http->get("/api/v1/services/{$serviceId}");

        return new Service($response['data']['service'] ?? $response['service'] ?? $response['data'] ?? $response);
    }

    /**
     * Update an existing service.
     *
     * @param int $serviceId Service ID
     * @param array $data Updated service data
     * @return Service
     */
    public function update(int $serviceId, array $data): Service
    {
        // Convert checklist and addons arrays to JSON strings if needed
        if (isset($data['checklist']) && is_array($data['checklist'])) {
            $data['checklist'] = json_encode($data['checklist']);
        }
        if (isset($data['addons']) && is_array($data['addons'])) {
            $data['addons'] = json_encode($data['addons']);
        }

        $response = $this->http->put("/api/v1/services/{$serviceId}", $data);

        return new Service($response['data']['service'] ?? $response['service'] ?? $response);
    }

    /**
     * Delete a service.
     *
     * @param int $serviceId Service ID
     * @return bool
     */
    public function delete(int $serviceId): bool
    {
        $this->http->delete("/api/v1/services/{$serviceId}");

        return true;
    }
}
