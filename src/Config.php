<?php

declare(strict_types=1);

namespace IRIS\SDK;

use IRIS\SDK\Auth\CredentialStore;

/**
 * SDK Configuration
 *
 * Holds all configuration options for the IRIS SDK.
 * Can auto-load credentials from ~/.iris/credentials.json or environment variables.
 */
class Config
{
    /**
     * API key for authentication
     */
    public string $apiKey;

    /**
     * Base URL for the main API
     */
    public string $baseUrl = 'https://apiv2.heyiris.io';

    /**
     * Base URL for the IRIS API (V5 workflows)
     */
    public string $irisUrl = 'https://heyiris.io';

    /**
     * Base URL for FL-API (leads, deliverables, etc.)
     */
    public string $flApiUrl = 'https://apiv2.heyiris.io';

    /**
     * Request timeout in seconds
     */
    public int $timeout = 30;

    /**
     * Number of retry attempts for failed requests
     */
    public int $retries = 3;

    /**
     * Webhook secret for verifying incoming webhooks
     */
    public ?string $webhookSecret = null;

    /**
     * OAuth2 Client ID for client credentials flow (OPTIONAL)
     * Only needed for advanced machine-to-machine scenarios.
     * Most operations work with just api_key!
     */
    public ?string $clientId = null;

    /**
     * OAuth2 Client Secret for client credentials flow (OPTIONAL)
     * Only needed for advanced machine-to-machine scenarios.
     * Most operations work with just api_key!
     */
    public ?string $clientSecret = null;

    /**
     * Enable debug mode for verbose logging
     */
    public bool $debug = false;

    /**
     * Current user context for API calls
     */
    public ?int $userId = null;

    /**
     * Polling interval for workflow status checks (milliseconds)
     */
    public int $pollingInterval = 500;

    /**
     * Maximum polling duration before timeout (seconds)
     */
    public int $maxPollingDuration = 300;

    /**
     * Create a new configuration instance.
     *
     * @param array{
     *     api_key?: string,
     *     base_url?: string,
     *     iris_url?: string,
     *     user_id?: int,
     *     timeout?: int,
     *     retries?: int,
     *     webhook_secret?: string,
     *     client_id?: string,
     *     client_secret?: string,
     *     debug?: bool,
     *     polling_interval?: int,
     *     max_polling_duration?: int
     * } $options
     *
     * @throws \InvalidArgumentException If api_key is not provided
     */
    public function __construct(array $options)
    {
        // Auto-load from .env if exists
        $envConfig = $this->loadFromEnv();
        
        // Merge options with env config (options take precedence)
        $options = array_merge($envConfig, array_filter($options, fn($v) => $v !== null));
        
        if (empty($options['api_key'])) {
            throw new \InvalidArgumentException(
                'api_key is required. Set IRIS_API_KEY in .env or pass it in options. Get your API key from https://app.freelabel.net/settings/api'
            );
        }

        $this->apiKey = $options['api_key'];
        $this->baseUrl = rtrim($options['base_url'] ?? $this->baseUrl, '/');
        $this->irisUrl = rtrim($options['iris_url'] ?? $this->irisUrl, '/');
        $this->flApiUrl = rtrim($options['fl_api_url'] ?? $this->flApiUrl, '/');
        $this->userId = $options['user_id'] ?? null;
        $this->timeout = $options['timeout'] ?? $this->timeout;
        $this->retries = $options['retries'] ?? $this->retries;
        $this->webhookSecret = $options['webhook_secret'] ?? null;
        $this->clientId = $options['client_id'] ?? null;
        $this->clientSecret = $options['client_secret'] ?? null;
        $this->debug = $options['debug'] ?? false;
        $this->pollingInterval = $options['polling_interval'] ?? $this->pollingInterval;
        $this->maxPollingDuration = $options['max_polling_duration'] ?? $this->maxPollingDuration;
    }

    /**
     * Load configuration from .env file in SDK directory or current directory.
     * Supports production/local environment switching.
     *
     * @return array<string, mixed>
     */
    private function loadFromEnv(): array
    {
        $config = [];
        
        // Check for .env in SDK directory first, then current directory
        $envPaths = [
            __DIR__ . '/../.env',
            getcwd() . '/.env',
        ];
        
        $envPath = null;
        foreach ($envPaths as $path) {
            if (file_exists($path)) {
                $envPath = $path;
                break;
            }
        }
        
        if ($envPath && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $env = [];
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse KEY=VALUE
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $env[$key] = $value;
                }
            }
            
            // Determine environment - command line takes precedence over .env file
            $environment = getenv('IRIS_ENV') ?: ($_ENV['IRIS_ENV'] ?? ($env['IRIS_ENV'] ?? 'production'));
            
            // Map .env variables to config - use environment-specific API key
            if ($environment === 'local' && !empty($env['IRIS_LOCAL_API_KEY'])) {
                $config['api_key'] = $env['IRIS_LOCAL_API_KEY'];
            } elseif ($environment === 'production' && !empty($env['IRIS_PROD_API_KEY'])) {
                $config['api_key'] = $env['IRIS_PROD_API_KEY'];
            } elseif (!empty($env['IRIS_API_KEY'])) {
                // Fallback to generic IRIS_API_KEY for backwards compatibility
                $config['api_key'] = $env['IRIS_API_KEY'];
            }
            
            if (!empty($env['IRIS_USER_ID'])) {
                $config['user_id'] = (int) $env['IRIS_USER_ID'];
            }
            
            // Set environment-specific URLs
            if ($environment === 'local') {
                // Local development URLs
                $config['base_url'] = $env['IRIS_LOCAL_URL'] ?? 'https://local.iris.freelabel.net';
                $config['iris_url'] = $env['IRIS_LOCAL_URL'] ?? 'https://local.iris.freelabel.net';
                $config['fl_api_url'] = $env['FL_API_LOCAL_URL'] ?? 'https://local.raichu.freelabel.net';
            } else {
                // Production URLs
                // IRIS API (V5 workflows, chat) runs at heyiris.io
                // FL-API (leads, deliverables) runs at apiv2.heyiris.io
                $config['base_url'] = $env['IRIS_API_URL'] ?? 'https://apiv2.heyiris.io';
                $config['iris_url'] = $env['IRIS_URL'] ?? 'https://heyiris.io';
                $config['fl_api_url'] = $env['FL_API_URL'] ?? $env['IRIS_API_URL'] ?? 'https://apiv2.heyiris.io';
            }
            
            // Optional fields
            if (!empty($env['IRIS_CLIENT_ID'])) {
                $config['client_id'] = $env['IRIS_CLIENT_ID'];
            }
            
            if (!empty($env['IRIS_CLIENT_SECRET'])) {
                $config['client_secret'] = $env['IRIS_CLIENT_SECRET'];
            }
        }
        
        return $config;
    }

    /**
     * Check if client credentials are configured.
     */
    public function hasClientCredentials(): bool
    {
        return $this->clientId !== null && $this->clientSecret !== null;
    }

    /**
     * Get default HTTP headers for API requests.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'IRIS-PHP-SDK/' . IRIS::VERSION,
        ];
    }

    /**
     * Check if user context is set.
     *
     * @throws \RuntimeException If user_id is not set
     */
    public function requireUserId(): int
    {
        if ($this->userId === null) {
            throw new \RuntimeException(
                'user_id is required for this operation. ' .
                'Set it in constructor options or use $fl->asUser($userId)'
            );
        }

        return $this->userId;
    }

    /**
     * Check if the SDK is in debug mode.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Create a Config instance by auto-loading from credential store.
     *
     * Loads credentials from:
     * 1. ~/.iris/credentials.json (persistent storage)
     * 2. Environment variables (take precedence)
     * 3. Provided options array (highest precedence)
     *
     * @param array $options Additional options to merge
     * @return static
     */
    public static function fromCredentialStore(array $options = []): static
    {
        $store = new CredentialStore();
        $storedConfig = $store->toConfigArray();

        // Merge: stored < options (options take precedence)
        $mergedOptions = array_merge($storedConfig, $options);

        return new static($mergedOptions);
    }

    /**
     * Check if stored credentials exist for auto-loading.
     */
    public static function hasStoredCredentials(): bool
    {
        $store = new CredentialStore();
        return $store->hasMinimumCredentials();
    }
}
