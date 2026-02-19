<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Mocks;

use IRIS\SDK\Http\Client;
use IRIS\SDK\Config;
use IRIS\SDK\Exceptions\IRISException;

/**
 * Mock HTTP Client
 *
 * Simulates HTTP requests for testing without making actual API calls.
 */
class MockHttpClient extends Client
{
    /**
     * @var array<string, array> Mocked responses
     */
    protected array $responses = [];

    /**
     * @var array<array> Request history
     */
    protected array $requests = [];

    public function __construct()
    {
        // Don't call parent - we're mocking everything
    }

    /**
     * Add a mocked response.
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint path
     * @param array $response Response data
     * @param int $status HTTP status code
     */
    public function addResponse(string $method, string $endpoint, array $response, int $status = 200): void
    {
        $key = $this->makeKey($method, $endpoint);
        $this->responses[$key] = [
            'body' => $response,
            'status' => $status,
        ];
    }

    /**
     * Make a key for the response map.
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint path
     * @return string
     */
    protected function makeKey(string $method, string $endpoint): string
    {
        return strtoupper($method) . ':' . $endpoint;
    }

    /**
     * Record a request and return mocked response.
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint path
     * @param array $data Request data
     * @return array
     * @throws IRISException
     */
    protected function mockRequest(string $method, string $endpoint, array $data = []): array
    {
        $this->requests[] = [
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $data,
            'timestamp' => time(),
        ];

        $key = $this->makeKey($method, $endpoint);

        // Try exact match first
        if (!isset($this->responses[$key])) {
            // Try partial match for dynamic endpoints (with IDs)
            $key = $this->findMatchingKey($method, $endpoint);
        }

        if (!isset($this->responses[$key])) {
            throw new IRISException("No mock response for: {$method} {$endpoint}");
        }

        $response = $this->responses[$key];

        if ($response['status'] >= 400) {
            throw new IRISException($response['body']['message'] ?? 'Error', $response['status']);
        }

        return $response['body'];
    }

    /**
     * Find a matching key for dynamic endpoints.
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint path
     * @return string|null
     */
    protected function findMatchingKey(string $method, string $endpoint): ?string
    {
        $method = strtoupper($method);
        
        foreach (array_keys($this->responses) as $key) {
            if (strpos($key, $method . ':') === 0) {
                $storedEndpoint = substr($key, strlen($method) + 1);
                
                // Replace numeric IDs with pattern
                $pattern = preg_replace('/\/\d+/', '/\d+', $storedEndpoint);
                $pattern = str_replace('/', '\/', $pattern);
                
                if (preg_match('/^' . $pattern . '$/', $endpoint)) {
                    return $key;
                }
            }
        }

        return null;
    }

    public function get(string $endpoint, array $query = []): array
    {
        return $this->mockRequest('GET', $endpoint, $query);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->mockRequest('POST', $endpoint, $data);
    }

    public function put(string $endpoint, array $data = []): array
    {
        return $this->mockRequest('PUT', $endpoint, $data);
    }

    public function patch(string $endpoint, array $data = []): array
    {
        return $this->mockRequest('PATCH', $endpoint, $data);
    }

    public function delete(string $endpoint): array
    {
        return $this->mockRequest('DELETE', $endpoint);
    }

    public function upload(string $endpoint, string $filePath, array $data = []): array
    {
        return $this->mockRequest('POST', $endpoint, array_merge(['file' => $filePath], $data));
    }

    /**
     * Get all recorded requests.
     *
     * @return array
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Get the last request made.
     *
     * @return array|null
     */
    public function getLastRequest(): ?array
    {
        return $this->requests[count($this->requests) - 1] ?? null;
    }

    /**
     * Clear all recorded requests.
     */
    public function clearRequests(): void
    {
        $this->requests = [];
    }

    /**
     * Clear all mocked responses.
     */
    public function clearResponses(): void
    {
        $this->responses = [];
    }

    /**
     * Assert that a request was made.
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint path (can contain partial match)
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function assertRequestMade(string $method, string $endpoint): void
    {
        foreach ($this->requests as $request) {
            if ($request['method'] === strtoupper($method) && 
                strpos($request['endpoint'], $endpoint) !== false) {
                return;
            }
        }

        throw new \PHPUnit\Framework\AssertionFailedError(
            "Expected request {$method} {$endpoint} was not made"
        );
    }

    /**
     * Assert request count.
     *
     * @param int $count Expected number of requests
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function assertRequestCount(int $count): void
    {
        $actual = count($this->requests);
        if ($actual !== $count) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected {$count} requests, but {$actual} were made"
            );
        }
    }
}
