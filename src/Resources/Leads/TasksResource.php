<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Tasks Sub-Resource
 *
 * Manage tasks for a lead.
 */
class TasksResource
{
    protected Client $http;
    protected Config $config;
    protected int $leadId;

    public function __construct(Client $http, Config $config, int $leadId)
    {
        $this->http = $http;
        $this->config = $config;
        $this->leadId = $leadId;
    }

    /**
     * Get all tasks for this lead.
     *
     * @return LeadTaskCollection
     */
    public function all(): LeadTaskCollection
    {
        $response = $this->http->get("/api/v1/leads/{$this->leadId}/tasks");

        $tasks = array_map(
            fn($data) => new LeadTask($data),
            $response['data'] ?? $response
        );
        
        return new LeadTaskCollection($tasks, $response['meta'] ?? []);
    }

    /**
     * Create a new task for this lead.
     *
     * @param array{
     *     title: string,
     *     description?: string,
     *     status?: string,
     *     due_date?: string,
     *     position?: int
     * } $data Task data
     * @return LeadTask
     */
    public function create(array $data): LeadTask
    {
        $response = $this->http->post("/api/v1/leads/{$this->leadId}/tasks", $data);

        return new LeadTask($response);
    }

    /**
     * Update a task.
     *
     * @param int $taskId Task ID
     * @param array $data Update data
     * @return LeadTask
     */
    public function update(int $taskId, array $data): LeadTask
    {
        $response = $this->http->put(
            "/api/v1/leads/{$this->leadId}/tasks/{$taskId}",
            $data
        );

        return new LeadTask($response);
    }

    /**
     * Delete a task.
     *
     * @param int $taskId Task ID
     * @return bool
     */
    public function delete(int $taskId): bool
    {
        $this->http->delete("/api/v1/leads/{$this->leadId}/tasks/{$taskId}");

        return true;
    }

    /**
     * Reorder tasks.
     *
     * @param array<int> $order Array of task IDs in desired order
     * @return bool
     */
    public function reorder(array $order): bool
    {
        $this->http->post("/api/v1/leads/{$this->leadId}/tasks/reorder", ['order' => $order]);

        return true;
    }
}
