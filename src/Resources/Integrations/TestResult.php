<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Integrations;

/**
 * TestResult Model
 *
 * Represents the result of an integration test.
 */
class TestResult
{
    public bool $success;
    public ?string $message;
    public ?string $error;
    public ?array $details;
    public ?int $latencyMs;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->success = (bool) ($data['success'] ?? false);
        $this->message = $data['message'] ?? null;
        $this->error = $data['error'] ?? null;
        $this->details = isset($data['details']) && is_array($data['details'])
            ? $data['details']
            : null;
        $this->latencyMs = isset($data['latency_ms']) ? (int) $data['latency_ms'] : null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function isFailed(): bool
    {
        return !$this->success;
    }

    public function hasError(): bool
    {
        return $this->isFailed();
    }

    public function getLatencyInSeconds(): ?float
    {
        if ($this->latencyMs === null) {
            return null;
        }

        return $this->latencyMs / 1000;
    }
}
