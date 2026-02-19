<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

/**
 * Profile Model
 *
 * Represents a user profile with media content.
 */
class Profile
{
    public int $pk;              // Numeric primary key
    public string $id;           // Username/slug
    public ?int $user_id;
    public ?string $name;
    public ?string $username;
    public ?string $display_name;
    public ?string $bio;
    public ?string $avatar_url;
    public ?string $photo;
    public ?string $city;
    public ?string $state;
    public ?string $country;
    public ?string $country_code;
    public ?string $email;
    public ?string $phone;
    public ?string $website_url;
    public ?string $instagram;
    public ?string $twitter;
    public ?string $tiktok;
    public ?string $youtube;
    public ?string $spotify;
    public ?string $facebook;
    public ?string $linkedin;
    public ?string $github;
    public ?string $twitch;
    public ?string $soundcloud;
    public ?int $views;
    public ?int $followers;
    public ?int $following;
    public ?array $stats;
    public ?string $created_at;
    public ?string $updated_at;

    public function __construct(array $data)
    {
        $this->pk = (int) ($data['pk'] ?? $data['id'] ?? 0);
        $this->id = (string) ($data['id'] ?? '');
        $this->user_id = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $this->name = $data['name'] ?? null;
        $this->username = $data['username'] ?? $data['id'] ?? '';
        $this->display_name = $data['display_name'] ?? $data['name'] ?? null;
        $this->bio = $data['bio'] ?? null;
        $this->avatar_url = $data['avatar_url'] ?? null;
        $this->photo = $data['photo'] ?? $data['avatar_url'] ?? null;
        $this->city = $data['city'] ?? null;
        $this->state = $data['state'] ?? null;
        $this->country = $data['country'] ?? null;
        $this->country_code = $data['country_code'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->phone = isset($data['phone']) ? (string) $data['phone'] : null;
        $this->website_url = $data['website_url'] ?? null;
        $this->instagram = $data['instagram'] ?? null;
        $this->twitter = $data['twitter'] ?? null;
        $this->tiktok = $data['tiktok'] ?? null;
        $this->youtube = $data['youtube'] ?? null;
        $this->spotify = $data['spotify'] ?? null;
        $this->facebook = $data['facebook'] ?? null;
        $this->linkedin = $data['linkedin'] ?? null;
        $this->github = $data['github'] ?? null;
        $this->twitch = $data['twitch'] ?? null;
        $this->soundcloud = $data['soundcloud'] ?? null;
        $this->views = isset($data['views']) ? (int) $data['views'] : null;
        $this->followers = isset($data['followers']) ? (int) $data['followers'] : null;
        $this->following = isset($data['following']) ? (int) $data['following'] : null;
        $this->stats = $data['stats'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'pk' => $this->pk,
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'photo' => $this->photo,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'email' => $this->email,
            'phone' => $this->phone,
            'website_url' => $this->website_url,
            'instagram' => $this->instagram,
            'twitter' => $this->twitter,
            'tiktok' => $this->tiktok,
            'youtube' => $this->youtube,
            'spotify' => $this->spotify,
            'facebook' => $this->facebook,
            'linkedin' => $this->linkedin,
            'github' => $this->github,
            'twitch' => $this->twitch,
            'soundcloud' => $this->soundcloud,
            'views' => $this->views,
            'followers' => $this->followers,
            'following' => $this->following,
            'stats' => $this->stats,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the public profile URL.
     *
     * @param string|null $baseUrl Optional base URL (defaults to production)
     * @return string
     */
    public function getPublicUrl(?string $baseUrl = null): string
    {
        $base = $baseUrl ?? 'https://freelabel.net';
        return $base . '/' . $this->id;
    }
}
