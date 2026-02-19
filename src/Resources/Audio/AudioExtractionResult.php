<?php

namespace IRIS\SDK\Resources\Audio;

class AudioExtractionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $outputPath,
        public readonly ?float $startTime,
        public readonly ?float $duration,
        public readonly ?string $error = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getOutputPath(): ?string
    {
        return $this->outputPath;
    }

    public function getStartTime(): ?float
    {
        return $this->startTime;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            success: $data['success'] ?? false,
            outputPath: $data['output_path'] ?? null,
            startTime: $data['start_time'] ?? null,
            duration: $data['duration'] ?? null,
            error: $data['error'] ?? null
        );
    }
}
