<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Playlist Collection
 *
 * A collection of Playlist models.
 */
class PlaylistCollection implements IteratorAggregate, Countable
{
    /** @var Playlist[] */
    private array $playlists;
    private array $meta;

    /**
     * @param Playlist[] $playlists
     * @param array $meta
     */
    public function __construct(array $playlists, array $meta = [])
    {
        $this->playlists = $playlists;
        $this->meta = $meta;
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->playlists);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->playlists);
    }

    /**
     * @return Playlist[]
     */
    public function all(): array
    {
        return $this->playlists;
    }

    /**
     * @return Playlist|null
     */
    public function first(): ?Playlist
    {
        return $this->playlists[0] ?? null;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return array_map(fn($playlist) => $playlist->toArray(), $this->playlists);
    }
}