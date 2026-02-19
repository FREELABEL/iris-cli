<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

/**
 * Video Model
 *
 * Represents a video media item in a profile.
 */
class Video
{
    public int $id;
    public ?int $profile_id;
    public string $title;
    public ?string $description;
    public string $url;
    public ?string $thumbnail_url;
    public ?int $duration;
    public ?int $views;
    public ?int $likes;
    public ?array $metadata;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->profile_id = isset($data['profile_id']) ? (int) $data['profile_id'] : null;
        $this->title = $data['title'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->url = $data['url'] ?? '';
        $this->thumbnail_url = $data['thumbnail_url'] ?? null;
        $this->duration = isset($data['duration']) ? (int) $data['duration'] : null;
        $this->views = isset($data['views']) ? (int) $data['views'] : null;
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
            'description' => $this->description,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'duration' => $this->duration,
            'views' => $this->views,
            'likes' => $this->likes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
