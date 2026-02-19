<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Collection of Track objects
 */
class TrackCollection implements IteratorAggregate, Countable
{
    /** @var Track[] */
    private array $tracks;
    private array $meta;

    /**
     * @param Track[] $tracks
     * @param array $meta
     */
    public function __construct(array $tracks, array $meta = [])
    {
        $this->tracks = $tracks;
        $this->meta = $meta;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->tracks);
    }

    public function count(): int
    {
        return count($this->tracks);
    }

    /**
     * @return Track[]
     */
    public function all(): array
    {
        return $this->tracks;
    }

    public function first(): ?Track
    {
        return $this->tracks[0] ?? null;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return array_map(fn($track) => $track->toArray(), $this->tracks);
    }
}
