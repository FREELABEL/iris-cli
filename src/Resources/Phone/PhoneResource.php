<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Phone;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Phone Resource
 *
 * Manage phone number provider settings for AI agents.
 * Supports multiple providers: VAPI, Twilio, Telnyx.
 *
 * @example
 * ```php
 * // List available phone numbers
 * $phones = $iris->phone->list('vapi');
 *
 * // Configure phone for an agent
 * $result = $iris->phone->configure('phone-id-123', 335, [
 *     'provider' => 'vapi',
 *     'use_dynamic_assistant' => true,
 * ]);
 *
 * // Release phone from agent
 * $result = $iris->phone->release('phone-id-123', 335);
 *
 * // Get phone details
 * $phone = $iris->phone->get('phone-id-123', 'vapi');
 *
 * // Get available providers
 * $providers = $iris->phone->getProviders();
 * ```
 */
class PhoneResource
{
    /**
     * Supported phone providers
     */
    public const SUPPORTED_PROVIDERS = [
        'vapi',
        'twilio',
        'telnyx',
    ];

    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List available phone numbers from a provider.
     *
     * @param string|null $provider Provider name (vapi, twilio, telnyx). If null, lists from all providers.
     * @return array List of phone numbers
     */
    public function list(?string $provider = null): array
    {
        $userId = $this->config->requireUserId();
        
        $params = ['user_id' => $userId];
        if ($provider !== null) {
            $params['provider'] = $provider;
        }
        
        return $this->http->get('/api/v1/phone/list', $params);
    }

    /**
     * List phone numbers from ALL connected providers.
     *
     * Returns an aggregated list of phone numbers from all providers
     * that are connected to the user's account.
     *
     * @return array Aggregated phone numbers by provider:
     *   - success: bool
     *   - data: array (keyed by provider name)
     *   - warnings: array (optional, contains any provider-specific errors)
     */
    public function listAll(): array
    {
        $userId = $this->config->requireUserId();
        
        return $this->http->get('/api/v1/phone/list-all', ['user_id' => $userId]);
    }

    /**
     * Search available phone numbers for purchase.
     *
     * @param array $filters Search filters:
     *   - provider: string (vapi, twilio, telnyx)
     *   - area_code: string
     *   - country_code: string
     *   - limit: int
     * @return array Available phone numbers matching criteria
     */
    public function search(array $filters = []): array
    {
        $userId = $this->config->requireUserId();
        
        $params = array_merge(['user_id' => $userId], $filters);
        
        return $this->http->get('/api/v1/phone/search', $params);
    }

    /**
     * Purchase/buy a phone number.
     *
     * @param string $phoneNumber Phone number to purchase
     * @param array $options Purchase options:
     *   - provider: string (vapi, twilio, telnyx)
     *   - area_code: string
     *   - country_code: string
     *   - name: string
     * @return array Purchase result
     */
    public function buy(string $phoneNumber, array $options = []): array
    {
        $userId = $this->config->requireUserId();
        
        $payload = array_merge([
            'phone_number' => $phoneNumber,
            'user_id' => $userId,
        ], $options);
        
        return $this->http->post('/api/v1/phone/buy', $payload);
    }

    /**
     * Delete/release a phone number from provider account.
     *
     * @param string $phoneId Phone ID to delete
     * @param string|null $provider Provider name
     * @return array Deletion result
     */
    public function delete(string $phoneId, ?string $provider = null): array
    {
        $userId = $this->config->requireUserId();
        
        $payload = [
            'phone_id' => $phoneId,
            'user_id' => $userId,
        ];
        
        if ($provider !== null) {
            $payload['provider'] = $provider;
        }
        
        return $this->http->delete('/api/v1/phone/delete', $payload);
    }

    /**
     * Configure (assign) a phone number to an agent.
     *
     * @param string $phoneId Phone number ID
     * @param int $agentId Agent ID to assign the phone to
     * @param array $options Configuration options:
     *   - provider: string (vapi, twilio, telnyx)
     *   - use_dynamic_assistant: bool (for VAPI)
     *   - allow_override: bool (allow agent to override settings)
     * @return array Response with success status and configuration
     */
    public function configure(string $phoneId, int $agentId, array $options = []): array
    {
        $userId = $this->config->requireUserId();
        
        $payload = array_merge([
            'phone_id' => $phoneId,
            'agent_id' => $agentId,
            'user_id' => $userId,
        ], $options);
        
        return $this->http->post('/api/v1/phone/configure', $payload);
    }

    /**
     * Release (unassign) a phone number from an agent.
     *
     * @param string $phoneId Phone number ID
     * @param int $agentId Agent ID to release the phone from
     * @param string|null $provider Provider name
     * @return array Response with success status
     */
    public function release(string $phoneId, int $agentId, ?string $provider = null): array
    {
        $userId = $this->config->requireUserId();
        
        $payload = [
            'phone_id' => $phoneId,
            'agent_id' => $agentId,
            'user_id' => $userId,
        ];
        
        if ($provider !== null) {
            $payload['provider'] = $provider;
        }
        
        return $this->http->post('/api/v1/phone/release', $payload);
    }

    /**
     * Get details of a specific phone number.
     *
     * @param string $phoneId Phone number ID
     * @param string|null $provider Provider name
     * @return array Phone number details
     */
    public function get(string $phoneId, ?string $provider = null): array
    {
        $userId = $this->config->requireUserId();
        
        $params = [
            'phone_id' => $phoneId,
            'user_id' => $userId,
        ];
        
        if ($provider !== null) {
            $params['provider'] = $provider;
        }
        
        return $this->http->get('/api/v1/phone/get', $params);
    }

    /**
     * Get all available phone providers.
     *
     * @return array List of providers with their capabilities
     */
    public function getProviders(): array
    {
        return $this->http->get('/api/v1/phone/providers');
    }

    /**
     * Check if a phone provider is available for the user.
     *
     * @param string $provider Provider name
     * @return bool True if provider is connected and available
     */
    public function isProviderAvailable(string $provider): bool
    {
        $userId = $this->config->requireUserId();
        
        $response = $this->http->get('/api/v1/phone/provider-available', [
            'provider' => $provider,
            'user_id' => $userId,
        ]);

        return $response['available'] ?? false;
    }

    /**
     * Get provider status and integration information.
     *
     * @param string $provider Provider name
     * @return array Provider status including connected state and integration details
     */
    public function getProviderStatus(string $provider): array
    {
        $userId = $this->config->requireUserId();
        
        return $this->http->get('/api/v1/phone/provider-available', [
            'provider' => $provider,
            'user_id' => $userId,
        ]);
    }

    /**
     * Assign phone number to agent (alias for configure).
     *
     * @param string $phoneId Phone number ID
     * @param int $agentId Agent ID
     * @param array $options Configuration options
     * @return array Response
     */
    public function assign(string $phoneId, int $agentId, array $options = []): array
    {
        return $this->configure($phoneId, $agentId, $options);
    }

    /**
     * Unassign phone number from agent (alias for release).
     *
     * @param string $phoneId Phone number ID
     * @param int $agentId Agent ID
     * @param string|null $provider Provider name
     * @return array Response
     */
    public function unassign(string $phoneId, int $agentId, ?string $provider = null): array
    {
        return $this->release($phoneId, $agentId, $provider);
    }
}
