<?php

namespace IRIS\SDK\Resources\Social;

class SocialStatusResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $status,
        public readonly int $completed,
        public readonly int $total,
        public readonly array $platforms = [],
        public readonly ?string $error = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getCompleted(): int
    {
        return $this->completed;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPlatforms(): array
    {
        return $this->platforms;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Check if all uploads are complete.
     */
    public function isComplete(): bool
    {
        return $this->completed >= $this->total && $this->total > 0;
    }

    /**
     * Get completion percentage.
     */
    public function getPercentage(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }

        return ($this->completed / $this->total) * 100;
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            success: $data['success'] ?? true,
            status: $data['status'] ?? null,
            completed: $data['completed'] ?? 0,
            total: $data['total'] ?? 0,
            platforms: $data['platforms'] ?? [],
            error: $data['error'] ?? null
        );
    }
}
