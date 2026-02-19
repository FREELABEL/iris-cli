<?php

declare(strict_types=1);

namespace IRIS\SDK\Auth;

use Dotenv\Dotenv;

/**
 * Credential Store - Loads from .env file ONLY
 *
 * Uses IRIS_ENV to determine which credentials to use:
 * - IRIS_ENV=local → uses IRIS_LOCAL_API_KEY, FL_API_LOCAL_URL, IRIS_LOCAL_URL
 * - IRIS_ENV=production (default) → uses IRIS_API_KEY, FL_API_URL, IRIS_API_URL
 *
 * @example
 * ```php
 * $store = new CredentialStore();
 * $config = $store->toConfigArray();
 * $iris = new IRIS($config);
 * ```
 */
class CredentialStore
{
    protected array $credentials = [];
    protected string $environment;

    public function __construct()
    {
        $this->loadDotenv();
        $this->environment = $this->getEnv('IRIS_ENV', 'production');
        $this->loadCredentials();
    }

    /**
     * Load .env file from SDK directory
     */
    protected function loadDotenv(): void
    {
        // Find SDK root directory (where .env file is)
        $dir = dirname(__DIR__, 2); // Go up from src/Auth to sdk/php

        if (file_exists($dir . '/.env')) {
            $dotenv = Dotenv::createImmutable($dir);
            $dotenv->safeLoad();
        }
    }

    /**
     * Get environment variable with fallback
     */
    protected function getEnv(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return ($value !== false && $value !== '') ? $value : $default;
    }

    /**
     * Load credentials based on IRIS_ENV
     */
    protected function loadCredentials(): void
    {
        $isLocal = $this->environment === 'local';

        // API Key - use local or production
        $apiKey = $isLocal
            ? $this->getEnv('IRIS_LOCAL_API_KEY')
            : $this->getEnv('IRIS_API_KEY') ?? $this->getEnv('IRIS_PROD_API_KEY');

        if ($apiKey) {
            $this->credentials['api_key'] = $apiKey;
        }

        // User ID
        $userId = $this->getEnv('IRIS_USER_ID');
        if ($userId) {
            $this->credentials['user_id'] = $userId;
        }

        // Base URL (FL-API) - use local or production
        $baseUrl = $isLocal
            ? $this->getEnv('FL_API_LOCAL_URL', 'https://local.raichu.freelabel.net')
            : $this->getEnv('FL_API_URL', 'https://apiv2.heyiris.io');

        $this->credentials['base_url'] = $baseUrl;

        // IRIS URL - use local or production
        $irisUrl = $isLocal
            ? $this->getEnv('IRIS_LOCAL_URL', 'https://local.iris.freelabel.net')
            : $this->getEnv('IRIS_URL', 'https://heyiris.io');

        $this->credentials['iris_url'] = $irisUrl;

        // Optional OAuth credentials
        if ($clientId = $this->getEnv('IRIS_CLIENT_ID')) {
            $this->credentials['client_id'] = $clientId;
        }
        if ($clientSecret = $this->getEnv('IRIS_CLIENT_SECRET')) {
            $this->credentials['client_secret'] = $clientSecret;
        }
        if ($webhookSecret = $this->getEnv('IRIS_WEBHOOK_SECRET')) {
            $this->credentials['webhook_secret'] = $webhookSecret;
        }
    }

    /**
     * Get the current environment
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Get a credential value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    /**
     * Check if a credential exists
     */
    public function has(string $key): bool
    {
        return isset($this->credentials[$key]) && $this->credentials[$key] !== '';
    }

    /**
     * Get all credentials
     */
    public function all(): array
    {
        return $this->credentials;
    }

    /**
     * Convert to SDK config array
     */
    public function toConfigArray(): array
    {
        $config = [];

        if ($this->has('api_key')) {
            $config['api_key'] = $this->get('api_key');
        }

        if ($this->has('user_id')) {
            $config['user_id'] = (int) $this->get('user_id');
        }

        if ($this->has('base_url')) {
            $config['base_url'] = $this->get('base_url');
        }

        if ($this->has('iris_url')) {
            $config['iris_url'] = $this->get('iris_url');
        }

        if ($this->has('client_id')) {
            $config['client_id'] = $this->get('client_id');
        }

        if ($this->has('client_secret')) {
            $config['client_secret'] = $this->get('client_secret');
        }

        if ($this->has('webhook_secret')) {
            $config['webhook_secret'] = $this->get('webhook_secret');
        }

        return $config;
    }

    /**
     * Check if minimum credentials exist
     */
    public function hasMinimumCredentials(): bool
    {
        return $this->has('api_key') && $this->has('user_id');
    }

    /**
     * Get masked credentials for display
     */
    public function getMaskedCredentials(): array
    {
        $masked = [];

        foreach ($this->credentials as $key => $value) {
            if (is_string($value) && strlen($value) > 8) {
                $masked[$key] = substr($value, 0, 4) . '****' . substr($value, -4);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
