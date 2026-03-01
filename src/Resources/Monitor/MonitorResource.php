<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Monitor;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Monitor Resource
 *
 * Platform monitoring and heartbeat diagnostics via iris-api's
 * PlatformMonitorController. Provides overview dashboards, per-agent
 * deep dives, loop detection, and emergency kill switches.
 */
class MonitorResource
{
    protected Client $http;

    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Get platform-wide monitoring overview.
     *
     * Returns jobs by status, heartbeat agents, top agents by token burn,
     * execution rates, alerts, and currently active workflows.
     *
     * @param int $hours Time window in hours (default 24)
     * @return array Platform overview data
     */
    public function overview(int $hours = 24): array
    {
        return $this->http->get('/api/v1/monitor/overview', ['hours' => $hours]);
    }

    /**
     * Get deep-dive diagnostics for a specific agent.
     *
     * Returns agent profile (heartbeat_mode, last_heartbeat_at), scheduled
     * jobs, recent executions, rapid-fire detection, and aggregate stats.
     *
     * @param int $agentId The agent ID
     * @param int $hours Time window in hours (default 48)
     * @return array Agent diagnostic data
     */
    public function agent(int $agentId, int $hours = 48): array
    {
        return $this->http->get("/api/v1/monitor/agents/{$agentId}", ['hours' => $hours]);
    }

    /**
     * Get loop detection results.
     *
     * Returns duplicate tasks, high run count jobs, rapid-fire agents,
     * and stuck running jobs.
     *
     * @param int $hours Time window in hours (default 24)
     * @return array Loop detection data
     */
    public function loops(int $hours = 24): array
    {
        return $this->http->get('/api/v1/monitor/loops', ['hours' => $hours]);
    }

    /**
     * Emergency kill switch — disable heartbeat and pause all jobs for an agent.
     *
     * @param int $agentId The agent ID to kill
     * @return array Kill result (jobs_paused, heartbeat_disabled, etc.)
     */
    public function kill(int $agentId): array
    {
        return $this->http->post('/api/v1/monitor/kill', ['agent_id' => $agentId]);
    }
}
