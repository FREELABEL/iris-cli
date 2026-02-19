<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Users;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Users Resource
 *
 * Manage user accounts in FL-API.
 *
 * @example
 * ```php
 * // Search for users
 * $users = $iris->users->search('juan');
 *
 * // Get a specific user
 * $user = $iris->users->get(456);
 *
 * // Create a new user
 * $user = $iris->users->create([
 *     'email' => 'user@example.com',
 *     'full_name' => 'John Doe',
 *     'phone' => '555-1234',
 *     'password' => 'secure_password',
 * ]);
 *
 * // Update user
 * $user = $iris->users->update(456, [
 *     'full_name' => 'Jane Doe',
 * ]);
 * ```
 */
class UsersResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Search for users by name or email.
     *
     * @param string $query Search query
     * @return array
     */
    public function search(string $query): array
    {
        $response = $this->http->get('/api/v1/users/search', [
            'query' => $query,
        ]);

        return $response['data'] ?? $response;
    }

    /**
     * Get a specific user by ID.
     *
     * @param int $userId User ID
     * @return array
     */
    public function get(int $userId): array
    {
        $response = $this->http->get("/api/v1/users/{$userId}");

        return $response['data'] ?? $response;
    }

    /**
     * Create a new user.
     *
     * @param array{
     *     email: string,
     *     full_name: string,
     *     phone?: string,
     *     password?: string,
     *     account_type?: string,
     *     dashboard_type?: string,
     *     status?: string
     * } $data User data
     * @return array
     */
    public function create(array $data): array
    {
        $payload = array_merge([
            'status' => 'active',
            'account_type' => 'business',
            'dashboard_type' => 'business',
        ], $data);

        $response = $this->http->post('/api/v1/users', $payload);

        return $response['data'] ?? $response;
    }

    /**
     * Update an existing user.
     *
     * @param int $userId User ID
     * @param array $data Updated user data
     * @return array
     */
    public function update(int $userId, array $data): array
    {
        $response = $this->http->put("/api/v1/users/{$userId}", $data);

        return $response['data'] ?? $response;
    }

    /**
     * Delete a user.
     *
     * @param int $userId User ID
     * @return bool
     */
    public function delete(int $userId): bool
    {
        $this->http->delete("/api/v1/users/{$userId}");

        return true;
    }

    /**
     * List all users (paginated).
     *
     * @param array{
     *     page?: int,
     *     per_page?: int,
     *     status?: string
     * } $filters Filter options
     * @return array
     */
    public function list(array $filters = []): array
    {
        $response = $this->http->get('/api/v1/users', $filters);

        return $response['data'] ?? $response;
    }

    /**
     * Get the current authenticated user.
     *
     * @return array
     */
    public function me(): array
    {
        $response = $this->http->get('/api/v1/user/me');

        return $response['data'] ?? $response;
    }

    /**
     * Register a new user account.
     *
     * Auto-generates secure password and phone number if not provided.
     *
     * @param array{
     *     email: string,
     *     phone?: string,
     *     password?: string,
     *     password_confirmation?: string,
     *     full_name?: string
     * } $data Registration data
     * @return array User data with credentials
     */
    public function register(array $data): array
    {
        // Validate email is provided
        if (empty($data['email'])) {
            throw new \InvalidArgumentException('Email is required');
        }

        // Auto-generate phone number if not provided
        // Format: (555) 555-5555
        if (empty($data['phone'])) {
            $data['phone'] = sprintf(
                '(%03d) %03d-%04d',
                rand(200, 999),
                rand(200, 999),
                rand(1000, 9999)
            );
        }

        // Auto-generate secure password if not provided
        if (empty($data['password'])) {
            // Generate 16-character password with letters, numbers, and symbols
            $data['password'] = $this->generateSecurePassword();
        }

        // Auto-add password_confirmation if not provided
        if (empty($data['password_confirmation'])) {
            $data['password_confirmation'] = $data['password'];
        }

        $response = $this->http->post('/api/v1/web/user/register', $data);

        $result = $response['data'] ?? $response;

        // Add generated credentials to response for user reference
        $result['_credentials'] = [
            'password' => $data['password'],
            'phone' => $data['phone'],
        ];

        return $result;
    }

    /**
     * Generate a secure random password.
     *
     * @return string 16-character password with letters, numbers, and symbols
     */
    private function generateSecurePassword(): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*';

        // Ensure at least one of each type
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        // Fill remaining characters randomly
        $allChars = $uppercase . $lowercase . $numbers . $symbols;
        for ($i = 4; $i < 16; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password
        return str_shuffle($password);
    }
}
