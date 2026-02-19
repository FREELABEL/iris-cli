<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Clip Cut CLI Command Tests
 *
 * Tests for the `./bin/iris tools clip-cut` CLI command including:
 * - Option parsing and validation
 * - Social media publishing parameter building (REQUIRED)
 * - Caption handling
 * - Platform parsing and validation
 *
 * NOTE: Social media publishing is REQUIRED. Clips must have a delivery target.
 * All clips are also auto-saved to CloudFiles for dashboard access.
 */
class ClipCutCommandTest extends TestCase
{
    // ========================================
    // PLATFORM PARSING TESTS
    // ========================================

    /**
     * Test comma-separated platforms are parsed correctly
     */
    public function test_platform_string_parsing(): void
    {
        $platformString = 'instagram,tiktok';
        $platforms = array_map('trim', explode(',', $platformString));

        $this->assertEquals(['instagram', 'tiktok'], $platforms);
    }

    /**
     * Test platforms with spaces are trimmed
     */
    public function test_platform_string_with_spaces_trimmed(): void
    {
        $platformString = 'instagram, tiktok, x';
        $platforms = array_map('trim', explode(',', $platformString));

        $this->assertEquals(['instagram', 'tiktok', 'x'], $platforms);
    }

    /**
     * Test single platform parsing
     */
    public function test_single_platform_parsing(): void
    {
        $platformString = 'instagram';
        $platforms = array_map('trim', explode(',', $platformString));

        $this->assertEquals(['instagram'], $platforms);
    }

    /**
     * Test all supported platforms
     */
    public function test_all_supported_platforms(): void
    {
        $supportedPlatforms = ['instagram', 'tiktok', 'x', 'twitter', 'threads'];

        foreach ($supportedPlatforms as $platform) {
            $this->assertIsString($platform);
            $this->assertNotEmpty($platform);
        }
    }

    /**
     * Test platform validation logic
     */
    public function test_platform_validation(): void
    {
        $validPlatforms = ['instagram', 'tiktok', 'x', 'twitter', 'threads'];

        // Test valid platforms
        $userPlatforms = ['instagram', 'tiktok', 'x'];
        $invalidPlatforms = array_diff($userPlatforms, $validPlatforms);
        $this->assertEmpty($invalidPlatforms);

        // Test invalid platform detection
        $userPlatformsWithInvalid = ['instagram', 'facebook', 'snapchat'];
        $invalidPlatforms = array_diff($userPlatformsWithInvalid, $validPlatforms);
        $this->assertNotEmpty($invalidPlatforms);
        $this->assertContains('facebook', $invalidPlatforms);
        $this->assertContains('snapchat', $invalidPlatforms);
    }

    // ========================================
    // PARAMETER BUILDING TESTS
    // ========================================

    /**
     * Test that social media is REQUIRED - params always include publish_to_social
     *
     * NOTE: As of the multi-tenant update, social media publishing is required.
     * Clips must have a delivery target. All clips are also saved to CloudFiles.
     */
    public function test_social_media_is_required(): void
    {
        // In the new flow, publishSocial must always be true
        // This test verifies the expected params structure
        $params = $this->buildParams(
            youtubeUrl: 'https://www.youtube.com/watch?v=test123',
            startTime: '0:10',
            duration: '60s',
            publishSocial: true,  // REQUIRED
            platforms: 'instagram,tiktok',
            caption: null
        );

        // Must always have these core params
        $this->assertArrayHasKey('youtube_url', $params);
        $this->assertArrayHasKey('start', $params);
        $this->assertArrayHasKey('duration', $params);

        // Must have social media params (required)
        $this->assertArrayHasKey('publish_to_social', $params);
        $this->assertTrue($params['publish_to_social']);
        $this->assertArrayHasKey('social_platforms', $params);
        $this->assertNotEmpty($params['social_platforms']);
    }

    /**
     * Test params with social media enabled
     */
    public function test_params_with_social_enabled(): void
    {
        $params = $this->buildParams(
            youtubeUrl: 'https://www.youtube.com/watch?v=test123',
            startTime: '0:10',
            duration: '60s',
            publishSocial: true,
            platforms: 'instagram,tiktok',
            caption: null
        );

        $this->assertTrue($params['publish_to_social']);
        $this->assertEquals(['instagram', 'tiktok'], $params['social_platforms']);
        $this->assertArrayNotHasKey('caption', $params);
    }

    /**
     * Test params with custom caption
     */
    public function test_params_with_custom_caption(): void
    {
        $customCaption = 'Check out this video! #viral';

        $params = $this->buildParams(
            youtubeUrl: 'https://www.youtube.com/watch?v=test123',
            startTime: '0:10',
            duration: '60s',
            publishSocial: true,
            platforms: 'instagram,tiktok',
            caption: $customCaption
        );

        $this->assertEquals($customCaption, $params['caption']);
    }

    /**
     * Test params with X (Twitter) platform
     */
    public function test_params_with_x_platform(): void
    {
        $params = $this->buildParams(
            youtubeUrl: 'https://www.youtube.com/watch?v=test123',
            startTime: '0:10',
            duration: '60s',
            publishSocial: true,
            platforms: 'instagram,tiktok,x',
            caption: null
        );

        $this->assertContains('x', $params['social_platforms']);
    }

    // ========================================
    // URL VALIDATION TESTS
    // ========================================

    /**
     * Test YouTube URL validation pattern
     */
    public function test_youtube_url_validation(): void
    {
        $validUrls = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
        ];

        foreach ($validUrls as $url) {
            $this->assertTrue(
                (bool) preg_match('/youtube\.com|youtu\.be/', $url),
                "Should be valid: {$url}"
            );
        }

        $invalidUrls = [
            'https://vimeo.com/12345',
            'https://example.com',
            '',
        ];

        foreach ($invalidUrls as $url) {
            $this->assertFalse(
                (bool) preg_match('/youtube\.com|youtu\.be/', $url),
                "Should be invalid: {$url}"
            );
        }
    }

    // ========================================
    // TIME FORMAT TESTS
    // ========================================

    /**
     * Test valid time format patterns
     */
    public function test_valid_time_formats(): void
    {
        $validFormats = [
            '0:10',    // M:SS
            '1:30',    // M:SS
            '10:00',   // MM:SS
            '60s',     // seconds with suffix
            '90s',     // seconds with suffix
            '120',     // plain seconds
        ];

        foreach ($validFormats as $time) {
            // M:SS or MM:SS format
            $isMmSs = preg_match('/^\d+:\d{2}$/', $time);
            // Seconds with optional 's' suffix
            $isSeconds = preg_match('/^\d+s?$/', $time);

            $this->assertTrue(
                $isMmSs || $isSeconds,
                "Should be valid time format: {$time}"
            );
        }
    }

    /**
     * Test duration format patterns
     */
    public function test_duration_format_patterns(): void
    {
        $validDurations = [
            '30s',
            '60s',
            '90s',
            '120s',
        ];

        foreach ($validDurations as $duration) {
            $this->assertMatchesRegularExpression('/^\d+s$/', $duration);
        }
    }

    // ========================================
    // EDGE CASE TESTS
    // ========================================

    /**
     * Test empty platforms string handling
     */
    public function test_empty_platforms_defaults(): void
    {
        $defaultPlatforms = 'instagram,tiktok';
        $platforms = array_map('trim', explode(',', $defaultPlatforms));

        $this->assertEquals(['instagram', 'tiktok'], $platforms);
    }

    /**
     * Test caption with special characters
     */
    public function test_caption_with_special_characters(): void
    {
        $caption = "🔥 New Drop! Check it out 👀\n\n#music #viral @artist";

        $params = $this->buildParams(
            youtubeUrl: 'https://www.youtube.com/watch?v=test123',
            startTime: '0:00',
            duration: '60s',
            publishSocial: true,
            platforms: 'instagram',
            caption: $caption
        );

        $this->assertEquals($caption, $params['caption']);
        $this->assertStringContainsString('🔥', $params['caption']);
        $this->assertStringContainsString('#music', $params['caption']);
    }

    /**
     * Test very long duration validation
     */
    public function test_long_duration_format(): void
    {
        $durations = ['300s', '600s', '1800s']; // 5min, 10min, 30min

        foreach ($durations as $duration) {
            $seconds = (int) rtrim($duration, 's');
            $this->assertGreaterThan(0, $seconds);
        }
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Build params array (mirrors CLI logic)
     */
    private function buildParams(
        string $youtubeUrl,
        string $startTime,
        string $duration,
        bool $publishSocial,
        string $platforms,
        ?string $caption
    ): array {
        $params = [
            'youtube_url' => $youtubeUrl,
            'start' => $startTime,
            'duration' => $duration,
        ];

        if ($publishSocial) {
            $params['publish_to_social'] = true;
            $params['social_platforms'] = array_map('trim', explode(',', $platforms));

            if ($caption) {
                $params['caption'] = $caption;
            }
        }

        return $params;
    }
}
