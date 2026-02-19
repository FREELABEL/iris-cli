<?php

namespace IRIS\SDK\Resources\Social;

class SocialPublishResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $requestId,
        public readonly ?string $message,
        public readonly array $platformResults = [],
        public readonly ?string $error = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getPlatformResults(): array
    {
        return $this->platformResults;
    }

    public function getPlatformResult(string $platform): ?array
    {
        return $this->platformResults[$platform] ?? null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Check if a specific platform succeeded.
     */
    public function platformSucceeded(string $platform): bool
    {
        $result = $this->getPlatformResult($platform);
        if (!$result) {
            return false;
        }

        return ($result['success'] ?? false) || ($result['status'] ?? '') === 'success';
    }

    /**
     * Get post URL for a specific platform.
     */
    public function getPostUrl(string $platform): ?string
    {
        $result = $this->getPlatformResult($platform);
        return $result['url'] ?? $result['post_url'] ?? null;
    }

    /**
     * Get post ID for a specific platform.
     */
    public function getPostId(string $platform): ?string
    {
        $result = $this->getPlatformResult($platform);
        return $result['id'] ?? $result['post_id'] ?? null;
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            success: $data['success'] ?? false,
            requestId: $data['request_id'] ?? null,
            message: $data['message'] ?? null,
            platformResults: $data['data'] ?? [],
            error: $data['error'] ?? null
        );
    }
}
