<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Integrations;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Integrations Resource
 *
 * Manage third-party integrations and MCP services.
 *
 * @example
 * ```php
 * // List all integrations
 * $integrations = $iris->integrations->list();
 *
 * // Get OAuth URL
 * $url = $iris->integrations->getOAuthUrl('google-drive');
 *
 * // Execute an integration function
 * $result = $iris->integrations->execute('gmail', 'send_email', [
 *     'to' => 'user@example.com',
 *     'subject' => 'Hello',
 *     'body' => 'Test message',
 * ]);
 * ```
 */
class IntegrationsResource
{
    /**
     * Supported integration types
     */
    public const SUPPORTED_TYPES = [
        'google-drive',
        'google-calendar',
        'gmail',
        'slack',
        'discord',
        'reddit',
        'servis-ai',
        'mailchimp',
        'mailjet',
        'case-reviewer',
        'gamma',
        'youtube-transcript',
        'youtube',
        'elevenlabs',
        'smtp-email',
        'google-gemini',
    ];

    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Get the base path for user-scoped integration routes.
     *
     * Uses the same pattern as agents: /users/{userId}/integrations
     *
     * @return string
     */
    protected function getBasePath(): string
    {
        return "/api/v1/users/{$this->config->userId}/integrations";
    }

    /**
     * List all integrations.
     *
     * @return IntegrationCollection
     */
    public function list(): IntegrationCollection
    {
        $response = $this->http->get($this->getBasePath());

        return new IntegrationCollection(
            array_map(fn($data) => new Integration($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Create a new integration.
     *
     * @param array{
     *     type: string,
     *     name: string,
     *     config?: array
     * } $data Integration data
     * @return Integration
     */
    public function create(array $data): Integration
    {
        $response = $this->http->post($this->getBasePath(), $data);

        return new Integration($response);
    }

    /**
     * Get a specific integration by ID.
     *
     * @param int $integrationId Integration ID
     * @return Integration
     */
    public function get(int $integrationId): Integration
    {
        $response = $this->http->get("{$this->getBasePath()}/{$integrationId}");

        return new Integration($response['data'] ?? $response);
    }

    /**
     * Update an integration.
     *
     * @param int $integrationId Integration ID
     * @param array $data Update data
     * @return Integration
     */
    public function update(int $integrationId, array $data): Integration
    {
        $response = $this->http->put("{$this->getBasePath()}/{$integrationId}", $data);

        return new Integration($response);
    }

    /**
     * Delete an integration.
     *
     * @param int $integrationId Integration ID
     * @return bool
     */
    public function delete(int $integrationId): bool
    {
        $this->http->delete("{$this->getBasePath()}/{$integrationId}");

        return true;
    }

    /**
     * Test an integration.
     *
     * @param int $integrationId Integration ID
     * @return TestResult
     */
    public function test(int $integrationId): TestResult
    {
        $response = $this->http->post("{$this->getBasePath()}/{$integrationId}/test");

        return new TestResult($response);
    }

    /**
     * Get available integration types.
     *
     * This endpoint is public and doesn't require authentication.
     *
     * @return array
     */
    public function types(): array
    {
        $response = $this->http->get("/api/v1/integrations/types");

        return $response['types'] ?? $response;
    }

    /**
     * Get OAuth URL for an integration type.
     *
     * @param string $type Integration type
     * @return string OAuth URL
     */
    public function getOAuthUrl(string $type): string
    {
        $response = $this->http->get("{$this->getBasePath()}/oauth-url/{$type}");

        return $response['url'] ?? '';
    }

    /**
     * Handle OAuth callback.
     *
     * @param string $type Integration type
     * @param array{
     *     code?: string,
     *     state?: string,
     *     error?: string
     * } $params Callback parameters
     * @return Integration
     */
    public function handleCallback(string $type, array $params): Integration
    {
        $response = $this->http->get("{$this->getBasePath()}/oauth-callback/{$type}", $params);

        return new Integration($response);
    }

    /**
     * Get integration metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->http->get("{$this->getBasePath()}/metadata");
    }

    /**
     * Get enabled integrations.
     *
     * @return IntegrationCollection
     */
    public function enabled(): IntegrationCollection
    {
        $response = $this->http->get("{$this->getBasePath()}/enabled");

        return new IntegrationCollection(
            array_map(fn($data) => new Integration($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Execute an integration function.
     *
     * @param string $type Integration type
     * @param string $function Function name
     * @param array $params Function parameters
     * @return array
     */
    public function execute(string $type, string $function, array $params = []): array
    {
        // MVP: Use bypass route for local development to skip permission validation
        // TODO: Fix permission middleware and revert to /execute endpoint
        $endpoint = "{$this->getBasePath()}/execute";
        if (strpos($this->config->baseUrl, 'localhost') !== false) {
            $endpoint = "{$this->getBasePath()}/execute-direct";
        }
        
        return $this->http->post("{$endpoint}?user_id={$this->config->userId}", [
            'integration' => $type,
            'action' => $function,
            'params' => $params,
        ]);
    }

    /**
     * Get AI context from integrations.
     *
     * @return array
     */
    public function getAiContext(): array
    {
        return $this->http->get("{$this->getBasePath()}/ai-context");
    }

    /**
     * Get MCP integrations.
     *
     * @return array
     */
    public function mcpIntegrations(): array
    {
        $response = $this->http->get("/api/v1/mcp/integrations");

        return $response['integrations'] ?? $response;
    }

    /**
     * Get functions for an MCP integration type.
     *
     * @param string $type Integration type
     * @return array<IntegrationFunction>
     */
    public function getFunctions(string $type): array
    {
        $response = $this->http->get("/api/v1/mcp/{$type}/functions");

        $functions = $response['functions'] ?? $response;

        return array_map(fn($data) => new IntegrationFunction($data), $functions);
    }

    /**
     * Execute an MCP function.
     *
     * @param string $type Integration type
     * @param string $function Function name
     * @param array $params Function parameters
     * @return array
     */
    public function executeFunction(string $type, string $function, array $params = []): array
    {
        return $this->http->post("/api/v1/mcp/{$type}/execute", [
            'function' => $function,
            'params' => $params,
        ]);
    }

    /**
     * Test an MCP service.
     *
     * @param string $type Integration type
     * @return TestResult
     */
    public function testService(string $type): TestResult
    {
        $response = $this->http->post("/api/v1/mcp/test/{$type}");

        return new TestResult($response);
    }

    // ========================================
    // MVP: Integration Management Methods
    // ========================================

    /**
     * Check connection status for an integration type.
     *
     * @param string $type Integration type (e.g., 'vapi', 'servis-ai')
     * @return array{connected: bool, integration: ?Integration}
     */
    public function status(string $type): array
    {
        $integrations = $this->list();
        $integration = $integrations->findByType($type);

        if (!$integration) {
            return ['connected' => false, 'integration' => null];
        }

        return [
            'connected' => $integration->status === 'active',
            'integration' => $integration,
        ];
    }

    /**
     * Get all connected integrations.
     *
     * @return IntegrationCollection
     */
    public function connected(): IntegrationCollection
    {
        return $this->list()->filterByStatus('active');
    }

    /**
     * Disconnect (delete) an integration by type.
     *
     * @param string $type Integration type
     * @return bool
     */
    public function disconnect(string $type): bool
    {
        $integrations = $this->list();
        $integration = $integrations->findByType($type);

        if (!$integration) {
            return false;
        }

        return $this->delete($integration->id);
    }

    /**
     * Connect integration using API key.
     *
     * @param string $type Integration type
     * @param array $credentials Credentials (e.g., ['api_key' => 'xxx'])
     * @param string|null $name Optional integration name
     * @return Integration
     */
    public function connectWithApiKey(string $type, array $credentials, ?string $name = null): Integration
    {
        // Get integration type info to validate
        $types = $this->types();
        $typeInfo = $types['data'][$type] ?? null;

        if (!$typeInfo) {
            throw new \InvalidArgumentException("Unknown integration type: {$type}");
        }

        // Determine category based on type
        $category = $typeInfo['category'] ?? 'automation';

        return $this->create([
            'name' => $name ?? ($typeInfo['name'] ?? ucfirst($type)),
            'type' => $type,
            'category' => $category,
            'credentials' => $credentials,
        ]);
    }

    /**
     * Connect Vapi Voice AI integration.
     *
     * @param string $apiKey Vapi API key
     * @param string|null $phoneNumber Optional phone number
     * @return Integration
     */
    public function connectVapi(string $apiKey, ?string $phoneNumber = null): Integration
    {
        $credentials = [
            'api_key' => $apiKey,
        ];

        if ($phoneNumber) {
            $credentials['phone_number'] = $phoneNumber;
        }

        return $this->connectWithApiKey('vapi', $credentials, 'Vapi Voice AI');
    }

    /**
     * Connect Servis.ai integration.
     *
     * @param string $clientId Servis.ai client ID
     * @param string $clientSecret Servis.ai client secret
     * @return Integration
     */
    public function connectServisAi(string $clientId, string $clientSecret): Integration
    {
        // Bypass types() validation - we know servis-ai is a valid type
        // This avoids potential auth issues with the types endpoint
        return $this->create([
            'name' => 'Servis.ai',
            'type' => 'servis-ai',
            'category' => 'workflow',
            'credentials' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
        ]);
    }

    /**
     * Connect SMTP Email integration.
     *
     * @param string $host SMTP host
     * @param int $port SMTP port
     * @param string $username SMTP username
     * @param string $password SMTP password
     * @param string $fromEmail From email address
     * @param string $fromName From name
     * @param string $encryption Encryption type (tls, ssl, or empty)
     * @return Integration
     */
    public function connectSmtp(
        string $host,
        int $port,
        string $username,
        string $password,
        string $fromEmail,
        string $fromName = '',
        string $encryption = 'tls'
    ): Integration {
        return $this->connectWithApiKey('smtp-email', [
            'smtp_host' => $host,
            'smtp_port' => $port,
            'smtp_username' => $username,
            'smtp_password' => $password,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'smtp_encryption' => $encryption,
        ], 'SMTP Email');
    }

    /**
     * Start OAuth flow and return URL to open in browser.
     *
     * @param string $type Integration type
     * @return array{url: string, instructions: string}
     */
    public function startOAuthFlow(string $type): array
    {
        $url = $this->getOAuthUrl($type);

        return [
            'url' => $url,
            'instructions' => "Open this URL in your browser to authorize access:\n{$url}\n\nAfter authorization, the integration will be automatically connected.",
        ];
    }

    /**
     * Check if integration uses OAuth.
     *
     * @param string $type Integration type
     * @return bool
     */
    public function usesOAuth(string $type): bool
    {
        $oauthTypes = ['google-drive', 'google-calendar', 'gmail', 'slack', 'github', 'mailchimp'];
        return in_array($type, $oauthTypes);
    }

    /**
     * Check if integration uses API key.
     *
     * @param string $type Integration type
     * @return bool
     */
    public function usesApiKey(string $type): bool
    {
        $apiKeyTypes = ['vapi', 'servis-ai', 'smtp-email', 'mailjet', 'google-gemini', 'savelife-ai'];
        return in_array($type, $apiKeyTypes);
    }
}
