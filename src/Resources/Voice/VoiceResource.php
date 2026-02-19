<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Voice;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Voice Resource
 *
 * Manage voice provider settings for AI agents.
 * Supports multiple providers: VAPI, ElevenLabs, Azure, OpenAI, Twilio.
 *
 * @example
 * ```php
 * // List available voices
 * $voices = $iris->voice->list('vapi');
 *
 * // Set voice for an agent
 * $result = $iris->voice->set(335, 'Lily', 'vapi');
 *
 * // Get current voice configuration
 * $config = $iris->voice->get(335);
 *
 * // Get available providers
 * $providers = $iris->voice->getProviders();
 *
 * // Check if provider is available
 * $available = $iris->voice->isProviderAvailable('vapi');
 * ```
 */
class VoiceResource
{
    /**
     * Supported voice providers
     */
    public const SUPPORTED_PROVIDERS = [
        'vapi',
        'elevenlabs',
        'twilio',
        'azure',
        'openai',
    ];

    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Set voice for an agent.
     *
     * @param int $agentId Agent ID
     * @param string $voiceId Voice ID (e.g., 'Lily', 'eleven_multilingual_v2')
     * @param string|null $provider Provider name (vapi, elevenlabs, azure, openai, twilio)
     * @return array Response with success status and updated settings
     */
    public function set(int $agentId, string $voiceId, ?string $provider = null): array
    {
        $userId = $this->config->requireUserId();
        
        return $this->http->post('/api/v1/voice/set', [
            'user_id' => $userId,
            'agent_id' => $agentId,
            'voice_id' => $voiceId,
            'provider' => $provider,
        ]);
    }

    /**
     * Get current voice configuration for an agent.
     *
     * @param int $agentId Agent ID
     * @return array Voice configuration including voiceId, provider, and settings
     */
    public function get(int $agentId): array
    {
        $userId = $this->config->requireUserId();
        
        return $this->http->get('/api/v1/voice/get', [
            'agent_id' => $agentId,
            'user_id' => $userId,
        ]);
    }

    /**
     * List available voices from a provider.
     *
     * @param string $provider Provider name (vapi, elevenlabs, azure, openai, twilio)
     * @return array List of available voices
     */
    public function list(string $provider): array
    {
        $userId = $this->config->requireUserId();
        
        return $this->http->get('/api/v1/voice/list', [
            'provider' => $provider,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get all available voice providers.
     *
     * @return array List of providers with their capabilities
     */
    public function getProviders(): array
    {
        return $this->http->get('/api/v1/voice/providers');
    }

    /**
     * Check if a voice provider is available for the user.
     *
     * @param string $provider Provider name
     * @return bool True if provider is connected and available
     */
    public function isProviderAvailable(string $provider): bool
    {
        $userId = $this->config->requireUserId();
        
        $response = $this->http->get('/api/v1/voice/provider-available', [
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
        
        return $this->http->get('/api/v1/voice/provider-available', [
            'provider' => $provider,
            'user_id' => $userId,
        ]);
    }
}
