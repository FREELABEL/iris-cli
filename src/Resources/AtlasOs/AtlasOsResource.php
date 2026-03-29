<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\AtlasOs;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Atlas OS Resource
 *
 * Business operations integration — inventory, budget, staff, events,
 * content calendar, market research, and document management.
 *
 * @example
 * ```php
 * $iris->atlasOs->inventory('list', ['_bloq_id' => 217]);
 * $iris->atlasOs->budget('log_expense', ['_bloq_id' => 217, 'description' => 'Ads', 'amount' => 500]);
 * $iris->atlasOs->events('create', ['_bloq_id' => 217, 'name' => 'Launch', 'date' => '2026-04-15']);
 * ```
 */
class AtlasOsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Execute any Atlas OS function.
     *
     * @param string $function Function name: manage_inventory, manage_budget, manage_staff,
     *                         manage_events, manage_content_calendar, market_research, store_document
     * @param array $params Function parameters (must include _bloq_id)
     * @return array Result data
     */
    public function execute(string $function, array $params = []): array
    {
        $userId = $this->config->userId;
        $endpoint = "/api/v1/users/{$userId}/integrations/execute-direct";

        return $this->http->post("{$endpoint}?user_id={$userId}", [
            'integration' => 'atlas-os',
            'action' => $function,
            'params' => $params,
        ]);
    }

    /**
     * Manage inventory items.
     * Actions: create, read, update, delete, list, search
     */
    public function inventory(string $action, array $params = []): array
    {
        return $this->execute('manage_inventory', ['action' => $action] + $params);
    }

    /**
     * Manage budget (expenses & revenue).
     * Actions: log_expense, log_revenue, get_summary, search, list, delete
     */
    public function budget(string $action, array $params = []): array
    {
        return $this->execute('manage_budget', ['action' => $action] + $params);
    }

    /**
     * Manage staff members.
     * Actions: create, read, update, delete, list, search, assign_to_event,
     *          create_task, list_tasks, complete_task, list_assignments
     */
    public function staff(string $action, array $params = []): array
    {
        return $this->execute('manage_staff', ['action' => $action] + $params);
    }

    /**
     * Manage events.
     * Actions: create, read, update, delete, list, search, add_vendor, remove_vendor,
     *          list_vendors, add_ticket, remove_ticket, list_tickets, rsvp, list_rsvps
     */
    public function events(string $action, array $params = []): array
    {
        return $this->execute('manage_events', ['action' => $action] + $params);
    }

    /**
     * Manage content calendar (social posts).
     * Actions: schedule_post, list_posts, update_post, delete_post, publish_now
     */
    public function calendar(string $action, array $params = []): array
    {
        return $this->execute('manage_content_calendar', ['action' => $action] + $params);
    }

    /**
     * Market research via web search + RAG storage.
     * Actions: research, search_stored, list
     */
    public function research(string $action, array $params = []): array
    {
        return $this->execute('market_research', ['action' => $action] + $params);
    }

    /**
     * Store and search documents with vector embedding.
     * Actions: store, search, update, delete, list
     */
    public function documents(string $action, array $params = []): array
    {
        return $this->execute('store_document', ['action' => $action] + $params);
    }

    /**
     * Manage contracts — contractor agreements, vendor terms, compliance.
     * Actions: create_agreement, update_status, set_compliance, get_contract,
     *          list_contracts, check_compliance, create_vendor_terms, get_committed_budget
     */
    public function contracts(string $action, array $params = []): array
    {
        return $this->execute('manage_contracts', ['action' => $action] + $params);
    }
}
