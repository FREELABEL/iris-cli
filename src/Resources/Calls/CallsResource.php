<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Calls;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Calls Resource
 *
 * Initiate and manage outbound phone calls via VAPI (AI voice agent) or Twilio.
 *
 * @example
 * ```php
 * // Initiate a call to a lead
 * $result = $iris->calls->make([
 *     'lead_id' => 9805,
 *     'agent_id' => 335,
 *     'purpose' => 'Follow-up call',
 * ]);
 *
 * // Call an arbitrary phone number
 * $result = $iris->calls->make([
 *     'phone_number' => '+15125200221',
 *     'provider' => 'twilio',
 * ]);
 *
 * // List recent calls
 * $calls = $iris->calls->list(['lead_id' => 9805]);
 *
 * // Get call status
 * $call = $iris->calls->get('CA1234567890');
 * ```
 */
class CallsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Initiate an outbound call.
     *
     * @param array{
     *     lead_id?: int,
     *     phone_number?: string,
     *     agent_id?: int,
     *     provider?: string,
     *     purpose?: string,
     *     script?: string,
     * } $params Call parameters (lead_id or phone_number required)
     * @return array Call result with call_sid, status, provider
     */
    public function make(array $params): array
    {
        $params['user_id'] = $this->config->requireUserId();

        return $this->http->post('/api/v1/calls/make', $params);
    }

    /**
     * Get call status by SID.
     *
     * @param string $callSid The call SID
     * @return array Call details
     */
    public function get(string $callSid): array
    {
        $userId = $this->config->requireUserId();

        return $this->http->get("/api/v1/calls/{$callSid}", [
            'user_id' => $userId,
        ]);
    }

    /**
     * List recent calls.
     *
     * @param array{
     *     lead_id?: int,
     *     limit?: int,
     * } $filters Optional filters
     * @return array List of calls
     */
    public function list(array $filters = []): array
    {
        $filters['user_id'] = $this->config->requireUserId();

        return $this->http->get('/api/v1/calls', $filters);
    }
}
