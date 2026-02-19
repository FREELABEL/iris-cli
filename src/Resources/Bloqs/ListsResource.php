<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Lists Sub-Resource
 *
 * Manage lists within a Bloq.
 */
class ListsResource
{
    protected Client $http;
    protected Config $config;
    protected int $bloqId;

    public function __construct(Client $http, Config $config, int $bloqId)
    {
        $this->http = $http;
        $this->config = $config;
        $this->bloqId = $bloqId;
    }

    /**
     * Get all lists for this bloq.
     *
     * @return BloqListCollection
     */
    public function all(): BloqListCollection
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/user/{$userId}/bloqs/{$this->bloqId}/lists");

        $lists = array_map(fn($data) => new BloqList($data), $response['data'] ?? $response);
        
        return new BloqListCollection($lists, $response['meta'] ?? []);
    }

    /**
     * Create a new list in this bloq.
     *
     * @param array{
     *     title: string,
     *     type?: string,
     *     position?: int
     * } $data List data
     * @return BloqList
     */
    public function create(array $data): BloqList
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post(
            "/api/v1/user/{$userId}/bloqs/{$this->bloqId}/lists",
            $data
        );

        return new BloqList($response);
    }

    /**
     * Get a specific list by ID.
     *
     * @param int $listId List ID
     * @return BloqList
     */
    public function get(int $listId): BloqList
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/user/{$userId}/bloqs/list/{$listId}");

        return new BloqList($response);
    }

    /**
     * Update a list.
     *
     * @param int $listId List ID
     * @param array $data Update data
     * @return BloqList
     */
    public function update(int $listId, array $data): BloqList
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->patch(
            "/api/v1/user/{$userId}/bloqs/list/{$listId}",
            $data
        );

        return new BloqList($response);
    }

    /**
     * Delete a list.
     *
     * @param int $listId List ID
     * @return bool
     */
    public function delete(int $listId): bool
    {
        $userId = $this->config->requireUserId();
        $this->http->delete("/api/v1/user/{$userId}/bloqs/list/{$listId}");

        return true;
    }

    /**
     * Update the position of a list.
     *
     * @param int $listId List ID
     * @param int $position New position
     * @return BloqList
     */
    public function updatePosition(int $listId, int $position): BloqList
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->patch(
            "/api/v1/user/{$userId}/bloqs/list/{$listId}/position",
            ['position' => $position]
        );

        return new BloqList($response);
    }
}
