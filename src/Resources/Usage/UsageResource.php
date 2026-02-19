<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Usage;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Usage Resource
 *
 * Track API usage, token consumption, and billing metrics.
 *
 * @example
 * ```php
 * // Get usage summary
 * $usage = $iris->usage->summary();
 * echo "Tokens used: {$usage['tokens_used']}\n";
 * echo "API calls: {$usage['api_calls']}\n";
 *
 * // Get detailed breakdown
 * $details = $iris->usage->details(['period' => 'month']);
 * ```
 */
class UsageResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Get usage summary for the user.
     *
     * @return array Usage summary with tokens, API calls, storage, etc.
     *
     * @example
     * ```php
     * $summary = $iris->usage->summary();
     *
     * // Typical response:
     * // {
     * //   'tokens_used': 150000,
     * //   'tokens_limit': 500000,
     * //   'api_calls': 1250,
     * //   'storage_used_mb': 45.5,
     * //   'agents_count': 12,
     * //   'workflows_count': 8,
     * //   'period': 'current_month'
     * // }
     * ```
     */
    public function summary(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/usage/summary");
    }

    /**
     * Get detailed usage breakdown.
     *
     * @param array{
     *     period?: string,
     *     start_date?: string,
     *     end_date?: string,
     *     group_by?: string
     * } $options Query options
     * @return array Detailed usage data
     *
     * @example
     * ```php
     * // Get this month's usage by day
     * $details = $iris->usage->details([
     *     'period' => 'month',
     *     'group_by' => 'day'
     * ]);
     *
     * // Get usage for date range
     * $details = $iris->usage->details([
     *     'start_date' => '2025-01-01',
     *     'end_date' => '2025-01-31'
     * ]);
     * ```
     */
    public function details(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/usage/details", $options);
    }

    /**
     * Get usage by agent.
     *
     * @param array $options Filter options
     * @return array Usage per agent
     *
     * @example
     * ```php
     * $agentUsage = $iris->usage->byAgent();
     *
     * foreach ($agentUsage as $agent) {
     *     echo "{$agent['name']}: {$agent['tokens_used']} tokens\n";
     * }
     * ```
     */
    public function byAgent(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/usage/by-agent", $options);
    }

    /**
     * Get usage by model.
     *
     * @param array $options Filter options
     * @return array Usage per AI model
     *
     * @example
     * ```php
     * $modelUsage = $iris->usage->byModel();
     *
     * // Response:
     * // {
     * //   'gpt-4o-mini': { 'tokens': 80000, 'cost': 1.20 },
     * //   'gpt-5-nano': { 'tokens': 50000, 'cost': 0.50 },
     * //   'claude-3-sonnet': { 'tokens': 20000, 'cost': 0.60 }
     * // }
     * ```
     */
    public function byModel(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/usage/by-model", $options);
    }

    /**
     * Get billing information.
     *
     * @return array Current billing status and limits
     */
    public function billing(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/billing");
    }

    /**
     * Get current subscription/package info.
     *
     * @return array Package details and limits
     */
    public function package(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/package");
    }

    /**
     * Get quota status.
     *
     * @return array Remaining quotas for various features
     *
     * @example
     * ```php
     * $quota = $iris->usage->quota();
     *
     * if ($quota['tokens_remaining'] < 1000) {
     *     echo "Warning: Low on tokens!\n";
     * }
     * ```
     */
    public function quota(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/usage/quota");
    }

    /**
     * Get usage history.
     *
     * @param int $months Number of months to retrieve
     * @return array Historical usage data
     */
    public function history(int $months = 6): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/usage/history", [
            'months' => $months,
        ]);
    }

    /**
     * Get workflow execution statistics.
     *
     * @param array $options Filter options
     * @return array Workflow usage stats
     */
    public function workflowStats(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/usage/workflows", $options);
    }

    /**
     * Get storage usage details.
     *
     * @return array Storage breakdown by file type
     */
    public function storage(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/usage/storage");
    }

    /**
     * Get credit status for the user.
     *
     * Returns current credit balance, usage, and limits.
     *
     * @return array Credit status information
     *
     * @example
     * ```php
     * $credits = $iris->usage->creditStatus();
     *
     * echo "Credits remaining: {$credits['credits_remaining']}\n";
     * echo "Credits used: {$credits['credits_used']}\n";
     * echo "Plan limit: {$credits['plan_limit']}\n";
     *
     * if ($credits['credits_remaining'] < 100) {
     *     echo "Warning: Low credits!\n";
     * }
     * ```
     */
    public function creditStatus(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/billing/credit-status", [
            'user_id' => $userId,
        ]);
    }

    /**
     * Get credit transaction history.
     *
     * @param array{
     *     limit?: int,
     *     offset?: int,
     *     start_date?: string,
     *     end_date?: string
     * } $options Filter options
     * @return array Transaction history
     */
    public function creditHistory(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $params = array_merge(['user_id' => $userId], $options);

        return $this->http->get("/api/v1/billing/credit-history", $params);
    }

    /**
     * Get current subscription details.
     *
     * @return array Subscription information
     */
    public function subscription(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/billing/subscription", [
            'user_id' => $userId,
        ]);
    }

    /**
     * Get available plans for upgrade.
     *
     * @return array Available subscription plans
     */
    public function availablePlans(): array
    {
        return $this->http->get("/api/v1/billing/plans");
    }
}
