<?php

namespace IRIS\SDK\Resources\Audio;

class AudioCompilationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $outputPath,
        public readonly ?float $totalDuration,
        public readonly int $trackCount,
        public readonly array $tracks = [],
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

    public function getTotalDuration(): ?float
    {
        return $this->totalDuration;
    }

    public function getTrackCount(): int
    {
        return $this->trackCount;
    }

    public function getTracks(): array
    {
        return $this->tracks;
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
            totalDuration: $data['total_duration'] ?? null,
            trackCount: $data['track_count'] ?? 0,
            tracks: $data['tracks'] ?? [],
            error: $data['error'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }
}
