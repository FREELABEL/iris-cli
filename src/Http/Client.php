<?php

declare(strict_types=1);

namespace IRIS\SDK\Http;

use IRIS\SDK\Config;
use IRIS\SDK\Auth\AuthManager;
use IRIS\SDK\Exceptions\AuthenticationException;
use IRIS\SDK\Exceptions\IRISException;
use IRIS\SDK\Exceptions\RateLimitException;
use IRIS\SDK\Exceptions\ValidationException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP Client for IRIS API
 *
 * Handles all HTTP communication with the IRIS/FreeLABEL API including:
 * - Dual authentication (client credentials + user token)
 * - Automatic retries with exponential backoff
 * - Rate limit handling
 * - Error normalization
 */
class Client
{
    protected Config $config;
    protected AuthManager $authManager;
    protected GuzzleClient $client;
    protected ?string $lastRequestId = null;

    /**
     * Create a new HTTP client instance.
     */
    public function __construct(Config $config, ?GuzzleClient $client = null, ?AuthManager $authManager = null)
    {
        $this->config = $config;
        $this->authManager = $authManager ?? new AuthManager($config);

        // Configure client credentials if provided
        if ($config->hasClientCredentials()) {
            $this->authManager->setClientCredentials($config->clientId, $config->clientSecret);
        }

        $this->client = $client ?? $this->createClient();
    }

    /**
     * Get the authentication manager.
     */
    public function auth(): AuthManager
    {
        return $this->authManager;
    }

    /**
     * Create a configured Guzzle client.
     */
    protected function createClient(): GuzzleClient
    {
        $stack = HandlerStack::create();

        // Add retry middleware
        $stack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));

        // Add logging middleware in debug mode
        if ($this->config->isDebug()) {
            $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
                error_log("[IRIS SDK] Request: {$request->getMethod()} {$request->getUri()}");
                $body = (string) $request->getBody();
                if ($body) {
                    error_log("[IRIS SDK] Request Body: " . $body);
                }
                return $request;
            }));
            
            $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
                error_log("[IRIS SDK] Response: {$response->getStatusCode()}");
                return $response;
            }));
        }

        return new GuzzleClient([
            'handler' => $stack,
            'timeout' => $this->config->timeout,
            'verify' => false,  // Disable SSL verification for local development
            // Headers are set per-request via AuthManager for endpoint-specific auth
            'http_errors' => true,
        ]);
    }

    /**
     * Make a GET request.
     *
     * @param string $endpoint API endpoint
     * @param array $query Query parameters
     * @return array Response data
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    /**
     * Make a POST request.
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body
     * @return array Response data
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    /**
     * Make a PUT request.
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body
     * @return array Response data
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    /**
     * Build the full URL for an API endpoint.
     *
     * Routes to the correct API based on endpoint:
     * - IRIS API: workflows, chat (V5 system)
     * - FL-API: leads, deliverables, profiles, services, agents management, integrations, bloqs
     */
    protected function buildUrl(string $endpoint): string
    {
        // Use FL-API URL for leads, deliverables, profiles, services, agents management, cloud-files, articles, bloqs, programs, courses, pages, user registration
        // Check /users/ FIRST because /users/{id}/bloqs/agents needs to go to FL-API
        if (str_contains($endpoint, '/users/')
            || str_contains($endpoint, '/user/')  // Singular /user/ for registration endpoint
            || str_contains($endpoint, '/leads')
            || str_contains($endpoint, '/deliverables')
            || str_contains($endpoint, '/profile')
            || str_contains($endpoint, '/services')
            || str_contains($endpoint, '/integrations')
            || str_contains($endpoint, '/cloud-files')
            || str_contains($endpoint, '/articles')
            || str_contains($endpoint, '/bloqs/')
            || str_contains($endpoint, '/programs')
            || str_contains($endpoint, '/program-enrollments')
            || str_contains($endpoint, '/user-programs')
            || str_contains($endpoint, '/courses')
            || str_contains($endpoint, '/pages')
            || str_contains($endpoint, '/videos')
            || str_contains($endpoint, '/collections')
            || str_contains($endpoint, '/a2p/')
        ) {
            return $this->config->flApiUrl . '/' . ltrim($endpoint, '/');
        }

        // Use IRIS URL for workflow/chat endpoints (V5 system ONLY)
        if (str_contains($endpoint, '/iris/')
            || str_contains($endpoint, '/chat/')
            || str_contains($endpoint, '/workflows/')
        ) {
            return $this->config->irisUrl . '/' . ltrim($endpoint, '/');
        }

        return $this->config->baseUrl . '/' . ltrim($endpoint, '/');
    }

    /**
     * Make a PATCH request.
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body
     * @return array Response data
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, [
            'json' => $data,
        ]);
    }

    /**
     * Make a DELETE request.
     *
     * @param string $endpoint API endpoint
     * @return array Response data
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Upload a file via multipart form data.
     *
     * @param string $endpoint API endpoint
     * @param string $filePath Path to the file
     * @param array $data Additional form data
     * @return array Response data
     */
    public function upload(string $endpoint, string $filePath, array $data = []): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $multipart = [
            [
                'name' => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
            ],
        ];

        foreach ($data as $key => $value) {
            $multipart[] = [
                'name' => $key,
                'contents' => is_array($value) ? json_encode($value) : (string) $value,
            ];
        }

        return $this->request('POST', $endpoint, [
            'multipart' => $multipart,
            'headers' => [
                // Remove Content-Type header to let Guzzle set it with boundary
                'Content-Type' => null,
            ],
        ]);
    }

    /**
     * Make an HTTP request.
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $options Guzzle options
     * @return array Response data
     * @throws IRISException
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->buildUrl($endpoint);

        // Get endpoint-specific headers from AuthManager
        $authHeaders = $this->authManager->getHeadersForEndpoint($endpoint);

        // Merge auth headers with any custom headers in options
        $options['headers'] = array_merge($authHeaders, $options['headers'] ?? []);

        try {
            $response = $this->client->request($method, $url, $options);
            $this->lastRequestId = $response->getHeaderLine('X-Request-Id');

            return $this->parseResponse($response);
        } catch (ClientException $e) {
            $exception = $this->handleClientException($e);

            // If we get a 401 and have client credentials, try invalidating the token
            if ($exception instanceof AuthenticationException && $this->authManager->hasClientCredentials()) {
                $this->authManager->invalidateTokens();
            }

            throw $exception;
        } catch (ServerException $e) {
            throw new IRISException(
                'Server error: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new IRISException(
                'Request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Parse the response body.
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if (empty($body)) {
            return ['success' => true];
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new IRISException('Invalid JSON response: ' . json_last_error_msg());
        }

        // Handle wrapped responses
        if (isset($data['data'])) {
            return $data['data'];
        }

        return $data;
    }

    /**
     * Handle client exceptions (4xx errors).
     */
    protected function handleClientException(ClientException $e): IRISException
    {
        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true) ?? [];

        $message = $body['message'] ?? $body['error'] ?? $e->getMessage();
        
        // Ensure message is a string
        if (is_array($message)) {
            $message = json_encode($message);
        }
        
        $errors = $body['errors'] ?? null;

        return match ($statusCode) {
            401, 403 => new AuthenticationException($message, $statusCode),
            429 => $this->createRateLimitException($response, $message),
            422 => new ValidationException($message, $errors),
            default => new IRISException($message, $statusCode),
        };
    }

    /**
     * Create a rate limit exception with retry information.
     */
    protected function createRateLimitException(ResponseInterface $response, string $message): RateLimitException
    {
        $retryAfter = (int) ($response->getHeaderLine('Retry-After') ?: 60);
        $exception = new RateLimitException($message, 429);
        $exception->retryAfter = $retryAfter;
        return $exception;
    }

    /**
     * Create retry decider for Guzzle middleware.
     */
    protected function retryDecider(): callable
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Exception $exception = null
        ): bool {
            // Don't retry if we've hit max retries
            if ($retries >= $this->config->retries) {
                return false;
            }

            // Retry on server errors
            if ($response && $response->getStatusCode() >= 500) {
                return true;
            }

            // Retry on rate limits
            if ($response && $response->getStatusCode() === 429) {
                return true;
            }

            // Retry on connection errors
            if ($exception instanceof GuzzleException) {
                return true;
            }

            return false;
        };
    }

    /**
     * Create retry delay calculator for exponential backoff.
     */
    protected function retryDelay(): callable
    {
        return function (int $retries, ?ResponseInterface $response = null): int {
            // Use Retry-After header if present
            if ($response && $retryAfter = $response->getHeaderLine('Retry-After')) {
                return (int) $retryAfter * 1000;
            }

            // Exponential backoff: 1s, 2s, 4s...
            return (int) pow(2, $retries) * 1000;
        };
    }

    /**
     * Get the last request ID for debugging.
     */
    public function getLastRequestId(): ?string
    {
        return $this->lastRequestId;
    }

    /**
     * Make a request to the IRIS API (V5 workflows).
     */
    public function iris(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->config->irisUrl . '/' . ltrim($endpoint, '/');

        try {
            $response = $this->client->request($method, $url, array_merge($options, [
                'headers' => $this->config->getHeaders(),
            ]));

            return $this->parseResponse($response);
        } catch (ClientException $e) {
            throw $this->handleClientException($e);
        }
    }
}
