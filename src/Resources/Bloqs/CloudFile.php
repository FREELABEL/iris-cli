<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

/**
 * CloudFile Model
 *
 * Represents a file stored in the cloud and associated with a Bloq.
 */
class CloudFile
{
    public int $id;
    public ?int $bloqId;
    public string $filename;
    public string $originalFilename;
    public string $mimeType;
    public int $size;
    public string $url;
    public string $status;
    public ?array $extractionMetadata;
    public ?string $createdAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->bloqId = isset($data['bloq_id']) ? (int) $data['bloq_id'] : null;
        $this->filename = $data['filename'] ?? '';
        $this->originalFilename = $data['original_filename'] ?? '';
        $this->mimeType = $data['mime_type'] ?? '';
        $this->size = (int) ($data['size'] ?? 0);
        $this->url = $data['url'] ?? '';
        $this->status = $data['status'] ?? 'pending';
        $this->extractionMetadata = isset($data['extraction_metadata']) && is_array($data['extraction_metadata'])
            ? $data['extraction_metadata']
            : null;
        $this->createdAt = $data['created_at'] ?? null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getSizeInMB(): float
    {
        return round($this->size / 1024 / 1024, 2);
    }
}
