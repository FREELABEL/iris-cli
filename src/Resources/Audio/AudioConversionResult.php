<?php

namespace IRIS\SDK\Resources\Audio;

class AudioConversionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $outputPath,
        public readonly ?string $format,
        public readonly ?int $fileSize,
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

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
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
            format: $data['format'] ?? null,
            fileSize: $data['file_size'] ?? null,
            error: $data['error'] ?? null
        );
    }
}
