<?php

namespace IRIS\SDK\Resources\Audio;

class AudioMergeResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $outputPath,
        public readonly ?float $duration,
        public readonly ?string $error = null,
        public readonly array $metadata = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getOutputPath(): ?string
    {
        return $this->outputPath;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            success: $data['success'] ?? false,
            outputPath: $data['output_path'] ?? null,
            duration: $data['duration'] ?? null,
            error: $data['error'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }
}
