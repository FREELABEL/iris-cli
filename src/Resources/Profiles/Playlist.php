<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

/**
 * Playlist Model
 *
 * Represents a playlist/collection of media items.
 */
class Playlist
{
    public int $id;
    public string $unique_id;
    public int $user_id;
    public ?int $profile_id;
    public string $title;
    public ?string $description;
    public array $items;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->unique_id = $data['unique_id'];
        $this->user_id = $data['user_id'];
        $this->profile_id = $data['profile_id'] ?? null;
        $this->title = $data['title'];
        $this->description = $data['description'] ?? null;
        $this->items = $data['items'] ?? [];
        $this->created_at = $data['created_at'];
        $this->updated_at = $data['updated_at'];
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'unique_id' => $this->unique_id,
            'user_id' => $this->user_id,
            'profile_id' => $this->profile_id,
            'title' => $this->title,
            'description' => $this->description,
            'items' => $this->items,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}