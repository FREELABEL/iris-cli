<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Items Sub-Resource
 *
 * Manage items within a list.
 */
class ItemsResource
{
    protected Client $http;
    protected Config $config;
    protected int $listId;

    public function __construct(Client $http, Config $config, int $listId)
    {
        $this->http = $http;
        $this->config = $config;
        $this->listId = $listId;
    }

    /**
     * List all items in this list.
     *
     * @param array{
     *     type?: string,
     *     status?: string,
     *     page?: int,
     *     per_page?: int
     * } $filters Filter options
     * @return BloqItemCollection
     */
    public function list(array $filters = []): BloqItemCollection
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get(
            "/api/v1/users/{$userId}/bloqs/{$this->listId}/items",
            $filters
        );

        $items = array_map(
            fn($data) => new BloqItem($data),
            $response['data'] ?? $response
        );

        return new BloqItemCollection($items, $response['meta'] ?? []);
    }

    /**
     * Get a single item by ID.
     *
     * @param int $itemId Item ID
     * @return BloqItem
     */
    public function get(int $itemId): BloqItem
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get(
            "/api/v1/user/{$userId}/bloqs/list/item/{$itemId}"
        );

        return new BloqItem($response['data'] ?? $response);
    }

    /**
     * Create a new item in this list.
     *
     * @param array{
     *     title: string,
     *     content?: string,
     *     type?: string,
     *     position?: int,
     *     is_public?: bool,
     *     metadata?: array
     * } $data Item data
     * @return BloqItem
     */
    public function create(array $data): BloqItem
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post(
            "/api/v1/user/{$userId}/bloqs/lists/{$this->listId}/items",
            $data
        );

        return new BloqItem($response);
    }

    /**
     * Update an item.
     *
     * @param int $itemId Item ID
     * @param array $data Update data
     * @return BloqItem
     */
    public function update(int $itemId, array $data): BloqItem
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->patch(
            "/api/v1/user/{$userId}/bloqs/list/item/{$itemId}",
            $data
        );

        return new BloqItem($response);
    }

    /**
     * Delete an item.
     *
     * @param int $itemId Item ID
     * @return bool
     */
    public function delete(int $itemId): bool
    {
        $userId = $this->config->requireUserId();
        $this->http->delete("/api/v1/user/{$userId}/bloqs/list/item/{$itemId}");

        return true;
    }

    /**
     * Add a text item.
     *
     * @param string $title Item title
     * @param string $content Plain text content
     * @param array $options Additional options (position, is_public, metadata)
     * @return BloqItem
     */
    public function addText(string $title, string $content, array $options = []): BloqItem
    {
        return $this->create(array_merge([
            'title' => $title,
            'content' => $content,
            'type' => 'default',
        ], $options));
    }

    /**
     * Add a markdown item.
     *
     * @param string $title Item title
     * @param string $content Markdown content
     * @param array $options Additional options (position, is_public, metadata)
     * @return BloqItem
     */
    public function addMarkdown(string $title, string $content, array $options = []): BloqItem
    {
        return $this->create(array_merge([
            'title' => $title,
            'content' => $content,
            'type' => 'default',
            'metadata' => array_merge(['contentType' => 'markdown'], $options['metadata'] ?? []),
        ], array_diff_key($options, ['metadata' => true])));
    }

    /**
     * Add a spreadsheet/tabular data item.
     *
     * @param string $title Item title
     * @param array $data Array of rows, each row is an associative array (column => value)
     * @param array $options Additional options (position, is_public)
     * @return BloqItem
     *
     * @example
     * ```php
     * $items->addSpreadsheet('Q1 Revenue', [
     *     ['month' => 'Jan', 'revenue' => 50000, 'growth' => '12%'],
     *     ['month' => 'Feb', 'revenue' => 55000, 'growth' => '10%'],
     * ]);
     * ```
     */
    public function addSpreadsheet(string $title, array $data, array $options = []): BloqItem
    {
        $columns = !empty($data) ? array_keys($data[0]) : [];

        return $this->create(array_merge([
            'title' => $title,
            'content' => json_encode([
                'contentType' => 'spreadsheet',
                'dataset' => [
                    'name' => $title,
                    'columns' => $columns,
                    'rows' => $data,
                ],
            ]),
            'type' => 'default',
            'metadata' => array_merge([
                'contentType' => 'spreadsheet',
                'columns' => $columns,
            ], $options['metadata'] ?? []),
        ], array_diff_key($options, ['metadata' => true])));
    }

    /**
     * Add a mixed content item (text + structured data).
     *
     * @param string $title Item title
     * @param string $content Text/markdown content
     * @param array $metadata Structured metadata (datasets, attachments, etc.)
     * @param array $options Additional options (position, is_public)
     * @return BloqItem
     */
    public function addMixed(string $title, string $content, array $metadata = [], array $options = []): BloqItem
    {
        return $this->create(array_merge([
            'title' => $title,
            'content' => $content,
            'type' => 'default',
            'metadata' => array_merge(['contentType' => 'mixed'], $metadata),
        ], array_diff_key($options, ['metadata' => true])));
    }

    /**
     * Get chat messages for an item.
     *
     * @param int $itemId Item ID
     * @return array
     */
    public function getMessages(int $itemId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get(
            "/api/v1/user/{$userId}/bloqs/list/{$itemId}/chat/messages"
        );

        return $response['messages'] ?? $response;
    }

    /**
     * Add a chat message to an item.
     *
     * @param int $itemId Item ID
     * @param array{
     *     role: string,
     *     content: string
     * } $message Message data
     * @return array
     */
    public function addMessage(int $itemId, array $message): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->post(
            "/api/v1/user/{$userId}/bloqs/list/{$itemId}/chat/messages",
            $message
        );
    }

    /**
     * Make an item public.
     *
     * @param int $itemId Item ID
     * @return BloqItem
     */
    public function makePublic(int $itemId): BloqItem
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post(
            "/api/v1/user/{$userId}/bloqs/list/item/{$itemId}/make-public"
        );

        return new BloqItem($response);
    }

    /**
     * Make an item private.
     *
     * @param int $itemId Item ID
     * @return BloqItem
     */
    public function makePrivate(int $itemId): BloqItem
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post(
            "/api/v1/user/{$userId}/bloqs/list/item/{$itemId}/make-private"
        );

        return new BloqItem($response);
    }
}
