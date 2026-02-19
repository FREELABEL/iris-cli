<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Integrations;

/**
 * IntegrationStatus Model
 *
 * Represents the connection status of an integration.
 * Provides convenient methods to check if an integration is ready to use.
 */
class IntegrationStatus
{
    public bool $connected;
    public ?Integration $integration;

    public function __construct(bool $connected, ?Integration $integration = null)
    {
        $this->connected = $connected;
        $this->integration = $integration;
    }

    /**
     * Check if the integration is connected and active.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->integration && $this->integration->isConnected();
    }

    /**
     * Check if the integration is disconnected.
     *
     * @return bool
     */
    public function isDisconnected(): bool
    {
        return !$this->connected || $this->integration === null;
    }

    /**
     * Check if the integration has an error.
     *
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->integration && $this->integration->hasError();
    }

    /**
     * Check if credentials are expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->integration && $this->integration->isExpired();
    }

    /**
     * Get error message if integration has an error.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->integration?->getErrorMessage();
    }

    /**
     * Get the integration ID if connected.
     *
     * @return int|null
     */
    public function getIntegrationId(): ?int
    {
        return $this->integration?->id;
    }

    /**
     * Get a friendly status message.
     *
     * @return string
     */
    public function getStatusMessage(): string
    {
        if ($this->isConnected()) {
            return "Connected and active";
        }

        if ($this->hasError()) {
            $error = $this->getErrorMessage();
            return $error ? "Error: {$error}" : "Connection error";
        }

        if ($this->isExpired()) {
            return "Credentials expired - reconnection required";
        }

        return "Not connected";
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'connected' => $this->connected,
            'integration' => $this->integration?->toArray(),
            'status_message' => $this->getStatusMessage(),
        ];
    }
}
