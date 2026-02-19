<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Vapi;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * VAPI Resource
 *
 * Manage Voice AI phone numbers, assistants, and call handoff settings.
 * VAPI enables AI-powered phone calls with your agents.
 *
 * @example
 * ```php
 * // List phone numbers
 * $numbers = $iris->vapi->phoneNumbers();
 *
 * // Configure a phone number for an agent
 * $iris->vapi->configurePhoneNumber('dd3905f2-08d6-4dc2-a50f-f0c937ada251', [
 *     'agent_id' => 335,
 *     'use_dynamic_assistant' => true,
 * ]);
 *
 * // Sync agent with VAPI
 * $iris->vapi->syncAssistant(335);
 * ```
 */
class VapiResource
{
    protected Client $http;
    protected Config $config;

    /**
     * IRIS API base URL (for VAPI endpoints on iris-api)
     */
    protected ?string $irisApiUrl;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
        $this->irisApiUrl = $config->irisUrl;
    }

    /**
     * List all phone numbers for the user.
     *
     * @return array List of phone numbers with their configurations
     *
     * @example
     * ```php
     * $numbers = $iris->vapi->phoneNumbers();
     * foreach ($numbers as $number) {
     *     echo "{$number['phone_number']} - Agent: {$number['agent_id']}\n";
     * }
     * ```
     */
    public function phoneNumbers(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/vapi/phone-numbers", [
            'user_id' => $userId,
        ]);
    }

    /**
     * Get a specific phone number by ID.
     *
     * @param string $phoneNumberId Phone number UUID
     * @return array Phone number details
     */
    public function getPhoneNumber(string $phoneNumberId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/vapi/phone-numbers/{$phoneNumberId}", [
            'user_id' => $userId,
        ]);
    }

    /**
     * Configure a phone number to use a specific agent.
     *
     * @param string $phoneNumberId Phone number UUID
     * @param array{
     *     agent_id: int,
     *     use_dynamic_assistant?: bool,
     *     allow_override?: bool
     * } $config Configuration options
     * @return array Updated phone number configuration
     *
     * @example
     * ```php
     * $iris->vapi->configurePhoneNumber('dd3905f2-08d6-4dc2-a50f-f0c937ada251', [
     *     'agent_id' => 335,
     *     'use_dynamic_assistant' => true,
     *     'allow_override' => true,
     * ]);
     * ```
     */
    public function configurePhoneNumber(string $phoneNumberId, array $config): array
    {
        $userId = $this->config->requireUserId();
        $data = array_merge(['user_id' => $userId], $config);

        return $this->http->post("/api/v1/vapi/phone-numbers/{$phoneNumberId}/configure", $data);
    }

    /**
     * Disconnect a phone number from its agent.
     *
     * @param string $phoneNumberId Phone number UUID
     * @return array Result
     */
    public function disconnectPhoneNumber(string $phoneNumberId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->post("/api/v1/vapi/phone-numbers/{$phoneNumberId}/disconnect", [
            'user_id' => $userId,
        ]);
    }

    /**
     * Sync an agent with VAPI (create/update VAPI assistant).
     *
     * This creates or updates the VAPI assistant configuration
     * based on the agent's settings.
     *
     * @param int $agentId Agent ID
     * @return array Sync result with assistant_id
     *
     * @example
     * ```php
     * $result = $iris->vapi->syncAssistant(335);
     * echo "VAPI Assistant ID: {$result['assistant_id']}\n";
     * ```
     */
    public function syncAssistant(int $agentId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->post("/api/v1/vapi/sync-assistant", [
            'agent_id' => $agentId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Update call handoff settings for an agent.
     *
     * Handoff settings control what happens when the AI needs
     * to transfer to a human (e.g., transfer to phone number).
     *
     * @param int $agentId Agent ID
     * @param array{
     *     enabled: bool,
     *     phone_number?: string,
     *     mode?: string,
     *     message?: string,
     *     sms_notifications?: bool
     * } $handoff Handoff configuration
     * @return array Updated handoff settings
     *
     * @example
     * ```php
     * $iris->vapi->updateHandoff(335, [
     *     'enabled' => true,
     *     'phone_number' => '8788765657',
     *     'mode' => 'blind',  // 'blind' or 'warm'
     *     'message' => 'Transferring you to a human agent...',
     *     'sms_notifications' => true,
     * ]);
     * ```
     */
    public function updateHandoff(int $agentId, array $handoff): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->post("/api/v1/vapi/assistant/update-handoff", [
            'agent_id' => $agentId,
            'user_id' => $userId,
            'handoff' => $handoff,
        ]);
    }

    /**
     * Get handoff settings for an agent.
     *
     * @param int $agentId Agent ID
     * @return array Current handoff settings
     */
    public function getHandoff(int $agentId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/vapi/assistant/handoff", [
            'agent_id' => $agentId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get VAPI assistant details for an agent.
     *
     * @param int $agentId Agent ID
     * @return array Assistant details including voice settings
     */
    public function getAssistant(int $agentId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/vapi/assistant", [
            'agent_id' => $agentId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Update VAPI assistant voice settings.
     *
     * @param int $agentId Agent ID
     * @param array{
     *     voice?: string,
     *     language?: string,
     *     speed?: float,
     *     pitch?: float
     * } $voiceSettings Voice configuration
     * @return array Updated assistant
     *
     * @example
     * ```php
     * $iris->vapi->updateVoice(335, [
     *     'voice' => 'Lily',
     *     'language' => 'en-US',
     *     'speed' => 1.0,
     * ]);
     * ```
     */
    public function updateVoice(int $agentId, array $voiceSettings): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->post("/api/v1/vapi/assistant/update-voice", [
            'agent_id' => $agentId,
            'user_id' => $userId,
            'voice_settings' => $voiceSettings,
        ]);
    }

    /**
     * Get available VAPI voices.
     *
     * @return array List of available voice options
     */
    public function voices(): array
    {
        return $this->http->get("/api/v1/vapi/voices");
    }

    /**
     * Get call history for the user.
     *
     * @param array{
     *     agent_id?: int,
     *     phone_number_id?: string,
     *     start_date?: string,
     *     end_date?: string,
     *     limit?: int,
     *     offset?: int
     * } $options Filter options
     * @return array Call history
     *
     * @example
     * ```php
     * // Get recent calls
     * $calls = $iris->vapi->callHistory(['limit' => 50]);
     *
     * // Get calls for specific agent
     * $calls = $iris->vapi->callHistory(['agent_id' => 335]);
     * ```
     */
    public function callHistory(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $params = array_merge(['user_id' => $userId], $options);

        return $this->http->get("/api/v1/vapi/calls", $params);
    }

    /**
     * Get details for a specific call.
     *
     * @param string $callId Call UUID
     * @return array Call details including transcript
     */
    public function getCall(string $callId): array
    {
        return $this->http->get("/api/v1/vapi/calls/{$callId}");
    }

    /**
     * Get call transcript.
     *
     * @param string $callId Call UUID
     * @return array Transcript with timestamps
     */
    public function getTranscript(string $callId): array
    {
        return $this->http->get("/api/v1/vapi/calls/{$callId}/transcript");
    }

    /**
     * Get call recording URL.
     *
     * @param string $callId Call UUID
     * @return string Recording URL
     */
    public function getRecording(string $callId): string
    {
        $response = $this->http->get("/api/v1/vapi/calls/{$callId}/recording");
        return $response['url'] ?? '';
    }

    /**
     * Initiate an outbound call.
     *
     * @param int $agentId Agent ID to use for the call
     * @param string $toPhoneNumber Phone number to call
     * @param array{
     *     from_phone_number_id?: string,
     *     context?: array,
     *     metadata?: array
     * } $options Call options
     * @return array Call details
     *
     * @example
     * ```php
     * $call = $iris->vapi->initiateCall(335, '+15551234567', [
     *     'context' => [
     *         'lead_id' => 412,
     *         'purpose' => 'Follow-up on proposal',
     *     ],
     * ]);
     * ```
     */
    public function initiateCall(int $agentId, string $toPhoneNumber, array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $data = array_merge([
            'agent_id' => $agentId,
            'user_id' => $userId,
            'to_phone_number' => $toPhoneNumber,
        ], $options);

        return $this->http->post("/api/v1/vapi/calls/initiate", $data);
    }

    /**
     * End an active call.
     *
     * @param string $callId Call UUID
     * @return array Result
     */
    public function endCall(string $callId): array
    {
        return $this->http->post("/api/v1/vapi/calls/{$callId}/end", []);
    }

    /**
     * Get VAPI usage statistics.
     *
     * @param array{
     *     start_date?: string,
     *     end_date?: string
     * } $options Date range
     * @return array Usage statistics (minutes, calls, costs)
     */
    public function usage(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $params = array_merge(['user_id' => $userId], $options);

        return $this->http->get("/api/v1/vapi/usage", $params);
    }
}
