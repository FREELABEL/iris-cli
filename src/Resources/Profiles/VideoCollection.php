<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Collection of Video objects
 */
class VideoCollection implements IteratorAggregate, Countable
{
    /** @var Video[] */
    private array $videos;
    private array $meta;

    /**
     * @param Video[] $videos
     * @param array $meta
     */
    public function __construct(array $videos, array $meta = [])
    {
        $this->videos = $videos;
        $this->meta = $meta;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->videos);
    }

    public function count(): int
    {
        return count($this->videos);
    }

    /**
     * @return Video[]
     */
    public function all(): array
    {
        return $this->videos;
    }

    public function first(): ?Video
    {
        return $this->videos[0] ?? null;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return array_map(fn($video) => $video->toArray(), $this->videos);
    }
}
