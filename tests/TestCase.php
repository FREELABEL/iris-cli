<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;
use IRIS\SDK\Tests\Mocks\MockHttpClient;

/**
 * Base Test Case
 *
 * Provides common test utilities and mocking infrastructure.
 */
abstract class TestCase extends BaseTestCase
{
    protected MockHttpClient $mockHttp;
    protected IRIS $iris;
    protected Config $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new Config([
            'api_key' => 'test_api_key',
            'user_id' => 123,
            'base_url' => 'https://api.test.iris.ai',
        ]);

        $this->mockHttp = new MockHttpClient();
        $this->iris = $this->createClient();
    }

    /**
     * Create a test client instance with mock HTTP client.
     *
     * @param array $options Configuration options
     * @return IRIS
     */
    protected function createClient(array $options = []): IRIS
    {
        return new IRIS(array_merge([
            'api_key' => 'test_api_key',
            'user_id' => 123,
            'base_url' => 'https://api.test.iris.ai',
        ], $options), $this->mockHttp);
    }

    /**
     * Mock an HTTP response.
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint path
     * @param array $response Response data
     * @param int $status HTTP status code
     */
    protected function mockResponse(string $method, string $endpoint, array $response, int $status = 200): void
    {
        $this->mockHttp->addResponse($method, $endpoint, $response, $status);
    }

    /**
     * Mock a successful response.
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint path
     * @param array $data Response data
     */
    protected function mockSuccess(string $method, string $endpoint, array $data = []): void
    {
        $this->mockResponse($method, $endpoint, array_merge(['success' => true], $data), 200);
    }

    /**
     * Mock an error response.
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint path
     * @param string $message Error message
     * @param int $status HTTP status code
     */
    protected function mockError(string $method, string $endpoint, string $message = 'Error', int $status = 400): void
    {
        $this->mockResponse($method, $endpoint, ['message' => $message], $status);
    }

    /**
     * Assert that a request was made.
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint path
     */
    protected function assertRequestMade(string $method, string $endpoint): void
    {
        $this->mockHttp->assertRequestMade($method, $endpoint);
    }

    /**
     * Get the last request made.
     *
     * @return array|null
     */
    protected function getLastRequest(): ?array
    {
        return $this->mockHttp->getLastRequest();
    }
}
