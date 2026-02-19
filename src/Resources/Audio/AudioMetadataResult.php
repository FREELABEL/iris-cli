<?php

namespace IRIS\SDK\Resources\Audio;

class AudioMetadataResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $filePath,
        public readonly array $metadata = [],
        public readonly ?string $error = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            success: $data['success'] ?? false,
            filePath: $data['file_path'] ?? null,
            metadata: $data['metadata'] ?? [],
            error: $data['error'] ?? null
        );
    }
}
