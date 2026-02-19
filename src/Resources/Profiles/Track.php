<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

/**
 * Track Model
 *
 * Represents an audio track media item in a profile.
 */
class Track
{
    public int $id;
    public ?int $profile_id;
    public string $title;
    public ?string $artist;
    public ?string $album;
    public ?string $description;
    public string $url;
    public ?string $cover_url;
    public ?int $duration;
    public ?int $plays;
    public ?int $likes;
    public ?array $metadata;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->profile_id = isset($data['profile_id']) ? (int) $data['profile_id'] : null;
        $this->title = $data['title'] ?? '';
        $this->artist = $data['artist'] ?? null;
        $this->album = $data['album'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->url = $data['url'] ?? '';
        $this->cover_url = $data['cover_url'] ?? null;
        $this->duration = isset($data['duration']) ? (int) $data['duration'] : null;
        $this->plays = isset($data['plays']) ? (int) $data['plays'] : null;
        $this->likes = isset($data['likes']) ? (int) $data['likes'] : null;
        $this->metadata = $data['metadata'] ?? null;
        $this->created_at = $data['created_at'] ?? '';
        $this->updated_at = $data['updated_at'] ?? '';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'title' => $this->title,
            'artist' => $this->artist,
            'album' => $this->album,
            'description' => $this->description,
            'url' => $this->url,
            'cover_url' => $this->cover_url,
            'duration' => $this->duration,
            'plays' => $this->plays,
            'likes' => $this->likes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
