<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

/**
 * Media Counts Model
 *
 * Represents media content counts for a profile.
 */
class MediaCounts
{
    public ?int $videos;
    public ?int $tracks;
    public ?int $images;
    public ?int $total;

    public function __construct(array $data)
    {
        $this->videos = $data['videos'] ?? null;
        $this->tracks = $data['tracks'] ?? null;
        $this->images = $data['images'] ?? null;
        $this->total = $data['total'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'videos' => $this->videos,
            'tracks' => $this->tracks,
            'images' => $this->images,
            'total' => $this->total,
        ];
    }
}
