<?php

declare(strict_types=1);

namespace IRIS\SDK\Auth;

use IRIS\SDK\Config;
use IRIS\SDK\Exceptions\AuthenticationException;

/**
 * Authentication Manager for IRIS SDK
 *
 * Simple authentication strategy:
 * - User Token (Bearer) for ALL operations (default)
 * - Client Credentials (optional) for advanced machine-to-machine scenarios
 *
 * The SDK works just like the web app - with user tokens!
 * OAuth client credentials are OPTIONAL and rarely needed.
 */
class AuthManager
{
    protected Config $config;

    /**
     * OAuth2 Client ID for client credentials flow (OPTIONAL)
     */
    protected ?string $clientId = null;

    /**
     * OAuth2 Client Secret for client credentials flow (OPTIONAL)
     */
    protected ?string $clientSecret = null;

    /**
     * Cached client credentials token (OPTIONAL)
     */
    protected ?string $clientToken = null;

    /**
     * Token expiration timestamp
     */
    protected ?int $tokenExpiresAt = null;

    /**
     * User Bearer token (PRIMARY AUTHENTICATION)
     */
    protected ?string $userToken = null;

    /**
     * Routes that COULD use client credentials (but user token works too!)
     * Only used if explicitly configured. User token is tried first.
     */
    protected const OPTIONAL_CLIENT_CREDENTIAL_PATTERNS = [
        // These are listed for backwards compatibility only
        // User tokens work fine for all of these!
    ];

    /**
     * Routes that are public (no authentication required)
     */
    protected const PUBLIC_PATTERNS = [
        '/api/health',
        '/api/v1/leads',
        '/api/v1/integrations/types',
        '/api/v1/bloqs/agents/generate-response',
        '/api/v1/bloqs/agents/ask',
        '/api/v1/public/',
    ];

    /**
     * Create a new AuthManager instance.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->userToken = $config->apiKey;
    }

    /**
     * Configure client credentials for management operations.
     */
    public function setClientCredentials(string $clientId, string $clientSecret): self
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientToken = null; // Reset cached token
        $this->tokenExpiresAt = null;

        return $this;
    }

    /**
     * Check if client credentials are configured.
     */
    public function hasClientCredentials(): bool
    {
        return $this->clientId !== null && $this->clientSecret !== null;
    }

    /**
     * Get the appropriate token for an endpoint.
     * 
     * SIMPLIFIED: Always use user token first.
     * Only falls back to client credentials if explicitly configured and user token fails.
     */
    public function getTokenForEndpoint(string $endpoint): string
    {
        $authStrategy = $this->determineAuthStrategy($endpoint);

        switch ($authStrategy) {
            case 'public':
                // For public routes, still send token if available for better rate limits
                return $this->userToken ?? '';
                
            case 'user_token':
            default:
                // Use user token for everything!
                return $this->userToken ?? throw new AuthenticationException(
                    'API token required. Run: ./bin/iris config setup'
                );
        }
    }

    /**
     * Get HTTP headers for an endpoint.
     */
    public function getHeadersForEndpoint(string $endpoint): array
    {
        $token = $this->getTokenForEndpoint($endpoint);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'IRIS-PHP-SDK/1.0.0',
        ];

        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Determine which authentication strategy to use for an endpoint.
     *
     * @return string 'client_credentials' | 'user_token' | 'public'
     */
    public function determineAuthStrategy(string $endpoint): string
    {
        // Normalize endpoint
        $endpoint = '/' . ltrim($endpoint, '/');

        // Check if public
        foreach (self::PUBLIC_PATTERNS as $pattern) {
            // For patterns ending with '/', do prefix matching
            if (str_ends_with($pattern, '/')) {
                if (str_starts_with($endpoint, $pattern)) {
                    return 'public';
                }
            } else {
                // For exact patterns, check if endpoint exactly matches OR starts with pattern followed by ? (query params)
                // This prevents /api/v1/leads from matching /api/v1/leads/aggregation
                if ($endpoint === $pattern || str_starts_with($endpoint, $pattern . '?')) {
                    return 'public';
                }
            }
        }

        // Everything else uses user token (just like the web app!)
        return 'user_token';
    }

    /**
     * Get or fetch a client credentials token.
     */
    protected function getClientCredentialsToken(): string
    {
        // Check if we have a valid cached token
        if ($this->clientToken !== null && $this->tokenExpiresAt !== null) {
            // Add 60 second buffer for safety
            if (time() < ($this->tokenExpiresAt - 60)) {
                return $this->clientToken;
            }
        }

        // Need to fetch a new token
        if (!$this->hasClientCredentials()) {
            throw new AuthenticationException(
                'Client credentials required but not configured. ' .
                'Call $iris->auth()->setClientCredentials($clientId, $clientSecret) first. ' .
                'See docs/AUTH_GUIDE.md for details.'
            );
        }

        $this->fetchClientCredentialsToken();

        return $this->clientToken;
    }

    /**
     * Fetch a new client credentials token from the OAuth server.
     */
    protected function fetchClientCredentialsToken(): void
    {
        $url = $this->config->baseUrl . '/oauth/token';

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => '*',
            ]),
            CURLOPT_TIMEOUT => $this->config->timeout,
            CURLOPT_SSL_VERIFYPEER => !$this->config->isDebug(),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new AuthenticationException(
                'Failed to connect to OAuth server: ' . $error
            );
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $data['error_description']
                ?? $data['message']
                ?? $data['error']
                ?? 'Unknown error';

            throw new AuthenticationException(
                "Failed to obtain client credentials token (HTTP {$httpCode}): {$errorMessage}"
            );
        }

        if (empty($data['access_token'])) {
            throw new AuthenticationException(
                'OAuth server returned success but no access_token'
            );
        }

        $this->clientToken = $data['access_token'];
        $this->tokenExpiresAt = time() + ($data['expires_in'] ?? 31536000);

        if ($this->config->isDebug()) {
            error_log("[IRIS SDK] Obtained client credentials token, expires in {$data['expires_in']}s");
        }
    }

    /**
     * Invalidate cached tokens (useful for testing or on 401 errors).
     */
    public function invalidateTokens(): void
    {
        $this->clientToken = null;
        $this->tokenExpiresAt = null;
    }

    /**
     * Get the user token.
     */
    public function getUserToken(): ?string
    {
        return $this->userToken;
    }

    /**
     * Set a new user token.
     */
    public function setUserToken(string $token): self
    {
        $this->userToken = $token;
        return $this;
    }
}
