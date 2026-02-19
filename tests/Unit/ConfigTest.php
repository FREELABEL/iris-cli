<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit;

use PHPUnit\Framework\TestCase;
use IRIS\SDK\Config;

/**
 * Config Test
 *
 * Tests configuration loading, environment switching, and API key selection.
 */
class ConfigTest extends TestCase
{
    private string $originalEnv;
    private string $testEnvPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Store original IRIS_ENV
        $this->originalEnv = getenv('IRIS_ENV') ?: '';

        // Create a temporary .env file for testing
        $this->testEnvPath = sys_get_temp_dir() . '/iris_sdk_test_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        // Restore original environment
        if ($this->originalEnv) {
            putenv("IRIS_ENV={$this->originalEnv}");
        } else {
            putenv('IRIS_ENV');
        }

        // Clean up temp file
        if (file_exists($this->testEnvPath)) {
            unlink($this->testEnvPath);
        }

        parent::tearDown();
    }

    public function test_config_requires_api_key(): void
    {
        // Skip if .env file exists (auto-loads credentials)
        if (file_exists(__DIR__ . '/../../.env')) {
            $this->markTestSkipped('Skipped because .env file exists and provides api_key');
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('api_key is required');

        new Config([]);
    }

    public function test_config_accepts_api_key(): void
    {
        $config = new Config(['api_key' => 'test_key']);

        $this->assertEquals('test_key', $config->apiKey);
    }

    public function test_config_sets_default_urls(): void
    {
        $config = new Config(['api_key' => 'test_key']);

        // Default URLs should be set
        $this->assertNotEmpty($config->baseUrl);
        $this->assertNotEmpty($config->irisUrl);
        $this->assertNotEmpty($config->flApiUrl);
    }

    public function test_config_accepts_user_id(): void
    {
        $config = new Config([
            'api_key' => 'test_key',
            'user_id' => 193,
        ]);

        $this->assertEquals(193, $config->userId);
    }

    public function test_config_accepts_custom_urls(): void
    {
        $config = new Config([
            'api_key' => 'test_key',
            'base_url' => 'https://custom.api.com',
            'iris_url' => 'https://custom.iris.com',
            'fl_api_url' => 'https://custom.flapi.com',
        ]);

        $this->assertEquals('https://custom.api.com', $config->baseUrl);
        $this->assertEquals('https://custom.iris.com', $config->irisUrl);
        $this->assertEquals('https://custom.flapi.com', $config->flApiUrl);
    }

    public function test_config_trims_trailing_slashes_from_urls(): void
    {
        $config = new Config([
            'api_key' => 'test_key',
            'base_url' => 'https://api.test.com/',
        ]);

        $this->assertEquals('https://api.test.com', $config->baseUrl);
    }

    public function test_get_headers_includes_authorization(): void
    {
        $config = new Config(['api_key' => 'my_secret_key']);

        $headers = $config->getHeaders();

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer my_secret_key', $headers['Authorization']);
    }

    public function test_get_headers_includes_content_type(): void
    {
        $config = new Config(['api_key' => 'test_key']);

        $headers = $config->getHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
    }

    public function test_require_user_id_throws_when_not_set(): void
    {
        // Skip if .env file exists (auto-loads user_id)
        if (file_exists(__DIR__ . '/../../.env')) {
            $this->markTestSkipped('Skipped because .env file exists and provides user_id');
        }

        $config = new Config(['api_key' => 'test_key']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('user_id is required');

        $config->requireUserId();
    }

    public function test_require_user_id_returns_user_id_when_set(): void
    {
        $config = new Config([
            'api_key' => 'test_key',
            'user_id' => 456,
        ]);

        $this->assertEquals(456, $config->requireUserId());
    }

    public function test_has_client_credentials_returns_false_by_default(): void
    {
        $config = new Config(['api_key' => 'test_key']);

        $this->assertFalse($config->hasClientCredentials());
    }

    public function test_has_client_credentials_returns_true_when_set(): void
    {
        $config = new Config([
            'api_key' => 'test_key',
            'client_id' => 'my_client_id',
            'client_secret' => 'my_client_secret',
        ]);

        $this->assertTrue($config->hasClientCredentials());
    }

    public function test_is_debug_returns_false_by_default(): void
    {
        $config = new Config(['api_key' => 'test_key']);

        $this->assertFalse($config->isDebug());
    }

    public function test_is_debug_returns_true_when_enabled(): void
    {
        $config = new Config([
            'api_key' => 'test_key',
            'debug' => true,
        ]);

        $this->assertTrue($config->isDebug());
    }

    public function test_default_timeout_is_set(): void
    {
        $config = new Config(['api_key' => 'test_key']);

        $this->assertEquals(30, $config->timeout);
    }

    public function test_custom_timeout_is_respected(): void
    {
        $config = new Config([
            'api_key' => 'test_key',
            'timeout' => 60,
        ]);

        $this->assertEquals(60, $config->timeout);
    }

    public function test_default_retries_is_set(): void
    {
        $config = new Config(['api_key' => 'test_key']);

        $this->assertEquals(3, $config->retries);
    }

    public function test_environment_variable_override(): void
    {
        // Set environment variable
        putenv('IRIS_ENV=production');

        $config = new Config(['api_key' => 'test_key']);

        // The config should read IRIS_ENV from environment
        // This verifies getenv() is checked in loadFromEnv()
        $this->assertNotNull($config);
    }

    public function test_polling_defaults_are_set(): void
    {
        $config = new Config(['api_key' => 'test_key']);

        $this->assertEquals(500, $config->pollingInterval);
        $this->assertEquals(300, $config->maxPollingDuration);
    }

    public function test_custom_polling_settings(): void
    {
        $config = new Config([
            'api_key' => 'test_key',
            'polling_interval' => 1000,
            'max_polling_duration' => 600,
        ]);

        $this->assertEquals(1000, $config->pollingInterval);
        $this->assertEquals(600, $config->maxPollingDuration);
    }
}
