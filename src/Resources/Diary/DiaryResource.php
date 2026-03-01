<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Diary;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Diary Resource
 *
 * View and manage agent daily diary entries. Provides a unified timeline
 * that merges project diary entries (BloqItems) with heartbeat memory
 * summaries (AgentSurfaceMemory) into a single chronological view.
 *
 * @example
 * ```php
 * // View today's diary
 * $diary = $iris->diary->today(11);
 *
 * // List recent entries
 * $entries = $iris->diary->list(11, ['days' => 7]);
 *
 * // Add a manual entry
 * $iris->diary->add(11, 'Deployed v2.1 to production');
 * ```
 */
class DiaryResource
{
    protected Client $http;

    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Get today's unified diary for an agent.
     *
     * Returns diary entries and heartbeat memories merged into a timeline.
     *
     * @param int $agentId The agent ID
     * @param array{bloq_id?: int} $options Optional bloq_id for direct access
     * @return array Today's diary with timeline
     */
    public function today(int $agentId, array $options = []): array
    {
        $params = array_merge(['agent_id' => $agentId], $options);

        return $this->http->get('/api/v6/diary', $params);
    }

    /**
     * List recent diary entries grouped by date.
     *
     * @param int $agentId The agent ID
     * @param array{days?: int, bloq_id?: int} $options
     * @return array List of dated entries with summaries
     */
    public function list(int $agentId, array $options = []): array
    {
        $params = array_merge(['agent_id' => $agentId], $options);

        return $this->http->get('/api/v6/diary/list', $params);
    }

    /**
     * Get a specific day's diary entry.
     *
     * @param int $agentId The agent ID
     * @param string $date Date in YYYY-MM-DD format
     * @param array{bloq_id?: int} $options
     * @return array Day's diary with timeline
     */
    public function show(int $agentId, string $date, array $options = []): array
    {
        $params = array_merge(['agent_id' => $agentId], $options);

        return $this->http->get("/api/v6/diary/{$date}", $params);
    }

    /**
     * Append a manual entry to today's diary.
     *
     * @param int $agentId The agent ID
     * @param string $content The diary entry content
     * @param array{bloq_id?: int} $options
     * @return array Result with entry details
     */
    public function add(int $agentId, string $content, array $options = []): array
    {
        $data = array_merge([
            'agent_id' => $agentId,
            'content' => $content,
        ], $options);

        return $this->http->post('/api/v6/diary', $data);
    }
}
