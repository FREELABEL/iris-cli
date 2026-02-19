<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * CloudFile Collection
 *
 * A collection of CloudFile instances.
 */
class CloudFileCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<CloudFile>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<CloudFile> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<CloudFile>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?CloudFile
    {
        return $this->items[0] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return array_map(fn(CloudFile $file) => $file->toArray(), $this->items);
    }

    /**
     * Filter files by status
     */
    public function byStatus(string $status): self
    {
        return new self(
            array_filter($this->items, fn(CloudFile $file) => $file->status === $status),
            $this->meta
        );
    }

    /**
     * Get ready files only
     */
    public function ready(): self
    {
        return $this->byStatus('ready');
    }

    /**
     * Get processing files only
     */
    public function processing(): self
    {
        return $this->byStatus('processing');
    }

    /**
     * Get failed files only
     */
    public function failed(): self
    {
        return $this->byStatus('failed');
    }

    /**
     * Calculate total size of all files in bytes
     */
    public function totalSize(): int
    {
        return array_sum(array_map(fn(CloudFile $file) => $file->size, $this->items));
    }

    /**
     * Calculate total size in MB
     */
    public function totalSizeInMB(): float
    {
        return round($this->totalSize() / 1024 / 1024, 2);
    }
}
