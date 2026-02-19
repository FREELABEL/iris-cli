<?php

namespace IRIS\SDK\Resources\Audio;

class AudioInfo
{
    public function __construct(
        public readonly string $filePath,
        public readonly float $duration,
        public readonly string $format,
        public readonly int $bitrate,
        public readonly int $sampleRate,
        public readonly int $channels,
        public readonly ?int $fileSize = null,
        public readonly array $metadata = []
    ) {}

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getBitrate(): int
    {
        return $this->bitrate;
    }

    public function getSampleRate(): int
    {
        return $this->sampleRate;
    }

    public function getChannels(): int
    {
        return $this->channels;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            filePath: $data['file_path'],
            duration: $data['duration'],
            format: $data['format'],
            bitrate: $data['bitrate'],
            sampleRate: $data['sample_rate'],
            channels: $data['channels'],
            fileSize: $data['file_size'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }
}
