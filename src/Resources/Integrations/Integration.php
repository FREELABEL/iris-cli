<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Integrations;

/**
 * Integration Model
 *
 * Represents a third-party integration.
 */
class Integration
{
    public int $id;
    public string $type;
    public string $name;
    public string $status;
    public ?array $capabilities;
    public ?array $config;
    public bool $isOAuth;
    public ?string $lastSyncedAt;
    public ?string $createdAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->type = $data['type'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->status = $data['status'] ?? 'disconnected';
        $this->capabilities = isset($data['capabilities']) && is_array($data['capabilities'])
            ? $data['capabilities']
            : null;
        $this->config = isset($data['config']) && is_array($data['config'])
            ? $data['config']
            : null;
        $this->isOAuth = (bool) ($data['is_oauth'] ?? false);
        $this->lastSyncedAt = $data['last_synced_at'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' || $this->status === 'active';
    }

    public function isDisconnected(): bool
    {
        return $this->status === 'disconnected';
    }

    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    public function hasCapability(string $capability): bool
    {
        if ($this->capabilities === null) {
            return false;
        }

        return in_array($capability, $this->capabilities, true);
    }

    /**
     * Get the error message if integration has an error.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->getAttribute('error_message');
    }

    /**
     * Check if the integration token/credentials are expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->hasError();
    }
}
