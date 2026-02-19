<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Activities Sub-Resource
 *
 * Manage activities for a lead.
 */
class ActivitiesResource
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
     * Get all activities for this lead.
     *
     * @return LeadActivityCollection
     */
    public function all(): LeadActivityCollection
    {
        $response = $this->http->get("/api/v1/leads/{$this->leadId}/activities");

        $activities = array_map(
            fn($data) => new LeadActivity($data),
            $response['data'] ?? $response
        );
        
        return new LeadActivityCollection($activities, $response['meta'] ?? []);
    }

    /**
     * Create a new activity for this lead.
     *
     * @param array{
     *     type: string,
     *     content: string,
     *     metadata?: array
     * } $data Activity data
     * @return LeadActivity
     */
    public function create(array $data): LeadActivity
    {
        $response = $this->http->post("/api/v1/leads/{$this->leadId}/activities", $data);

        return new LeadActivity($response);
    }

    /**
     * Add an AI message activity.
     *
     * @param string $content Message content
     * @return LeadActivity
     */
    public function addAiMessage(string $content): LeadActivity
    {
        $response = $this->http->post(
            "/api/v1/leads/{$this->leadId}/activities/ai-message",
            ['content' => $content]
        );

        return new LeadActivity($response);
    }

    /**
     * Delete an activity.
     *
     * @param int $activityId Activity ID
     * @return bool
     */
    public function delete(int $activityId): bool
    {
        $this->http->delete("/api/v1/leads/{$this->leadId}/activities/{$activityId}");

        return true;
    }
}
