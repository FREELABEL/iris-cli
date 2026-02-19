<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Feature;

use IRIS\SDK\IRIS;
use IRIS\SDK\Resources\Articles\ArticlesResource;
use IRIS\SDK\Resources\Profiles\ProfilesResource;
use IRIS\SDK\Resources\Services\ServicesResource;
use IRIS\SDK\Resources\Videos\VideosResource;
use PHPUnit\Framework\TestCase;

/**
 * SDK Consistency Tests
 *
 * Monitors that all resources follow consistent CRUD patterns.
 * This ensures the SDK maintains uniform behavior across all resources.
 */
class SDKConsistencyTest extends TestCase
{
    private IRIS $iris;

    protected function setUp(): void
    {
        $this->iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);
    }

    /**
     * Test that core resources with full CRUD operations exist and are accessible
     */
    public function test_core_crud_resources_are_accessible(): void
    {
        $this->assertInstanceOf(ProfilesResource::class, $this->iris->profiles);
        $this->assertInstanceOf(ServicesResource::class, $this->iris->services);
        $this->assertInstanceOf(ArticlesResource::class, $this->iris->articles);
        $this->assertInstanceOf(VideosResource::class, $this->iris->videos);
    }

    /**
     * Test that profiles resource has consistent CRUD methods
     */
    public function test_profiles_resource_has_crud_methods(): void
    {
        $resource = $this->iris->profiles;

        $this->assertTrue(method_exists($resource, 'list'), 'ProfilesResource should have list() method');
        $this->assertTrue(method_exists($resource, 'create'), 'ProfilesResource should have create() method');
        $this->assertTrue(method_exists($resource, 'get'), 'ProfilesResource should have get() method');
        $this->assertTrue(method_exists($resource, 'update'), 'ProfilesResource should have update() method');
        $this->assertTrue(method_exists($resource, 'delete'), 'ProfilesResource should have delete() method');
    }

    /**
     * Test that services resource has consistent CRUD methods
     */
    public function test_services_resource_has_crud_methods(): void
    {
        $resource = $this->iris->services;

        $this->assertTrue(method_exists($resource, 'list'), 'ServicesResource should have list() method');
        $this->assertTrue(method_exists($resource, 'create'), 'ServicesResource should have create() method');
        $this->assertTrue(method_exists($resource, 'get'), 'ServicesResource should have get() method');
        $this->assertTrue(method_exists($resource, 'update'), 'ServicesResource should have update() method');
        $this->assertTrue(method_exists($resource, 'delete'), 'ServicesResource should have delete() method');
    }

    /**
     * Test that articles resource has consistent CRUD methods
     */
    public function test_articles_resource_has_crud_methods(): void
    {
        $resource = $this->iris->articles;

        $this->assertTrue(method_exists($resource, 'list'), 'ArticlesResource should have list() method');
        $this->assertTrue(method_exists($resource, 'create'), 'ArticlesResource should have create() method');
        $this->assertTrue(method_exists($resource, 'get'), 'ArticlesResource should have get() method');
        $this->assertTrue(method_exists($resource, 'update'), 'ArticlesResource should have update() method');
        $this->assertTrue(method_exists($resource, 'delete'), 'ArticlesResource should have delete() method');
    }

    /**
     * Test that videos resource has consistent CRUD methods
     */
    public function test_videos_resource_has_crud_methods(): void
    {
        $resource = $this->iris->videos;

        $this->assertTrue(method_exists($resource, 'list'), 'VideosResource should have list() method');
        $this->assertTrue(method_exists($resource, 'create'), 'VideosResource should have create() method');
        $this->assertTrue(method_exists($resource, 'get'), 'VideosResource should have get() method');
        $this->assertTrue(method_exists($resource, 'update'), 'VideosResource should have update() method');
        $this->assertTrue(method_exists($resource, 'delete'), 'VideosResource should have delete() method');
        $this->assertTrue(method_exists($resource, 'upload'), 'VideosResource should have upload() method for file uploads');
    }

    /**
     * Test that profiles resource has playlist management methods
     */
    public function test_profiles_resource_has_playlist_methods(): void
    {
        $resource = $this->iris->profiles;

        $this->assertTrue(method_exists($resource, 'getPlaylists'), 'ProfilesResource should have getPlaylists() method');
        $this->assertTrue(method_exists($resource, 'createPlaylist'), 'ProfilesResource should have createPlaylist() method');
        $this->assertTrue(method_exists($resource, 'getPlaylist'), 'ProfilesResource should have getPlaylist() method');
        $this->assertTrue(method_exists($resource, 'updatePlaylist'), 'ProfilesResource should have updatePlaylist() method');
        $this->assertTrue(method_exists($resource, 'deletePlaylist'), 'ProfilesResource should have deletePlaylist() method');
        $this->assertTrue(method_exists($resource, 'addToPlaylist'), 'ProfilesResource should have addToPlaylist() method');
        $this->assertTrue(method_exists($resource, 'removeFromPlaylist'), 'ProfilesResource should have removeFromPlaylist() method');
    }

    /**
     * Test that all resources are properly initialized
     */
    public function test_all_resources_are_initialized(): void
    {
        $expectedResources = [
            'agents', 'workflows', 'bloqs', 'leads', 'integrations', 'rag',
            'cloudFiles', 'usage', 'vapi', 'models', 'chat', 'profiles',
            'services', 'tools', 'articles', 'schedules', 'servisAi',
            'programs', 'courses', 'audio', 'social', 'voice', 'videos',
            'phone', 'automations', 'users', 'pages'
        ];

        foreach ($expectedResources as $resourceName) {
            $this->assertObjectHasProperty($resourceName, $this->iris, "IRIS client should have {$resourceName} property");
            $this->assertNotNull($this->iris->$resourceName, "IRIS client {$resourceName} property should not be null");
        }
    }

    /**
     * Test that CRUD resources return expected object types
     */
    public function test_crud_resources_return_expected_types(): void
    {
        // Test that methods exist and are callable (without executing HTTP requests)
        // This ensures the API contract is maintained

        $reflection = new \ReflectionClass($this->iris->profiles);
        $this->assertTrue($reflection->hasMethod('list'));
        $this->assertTrue($reflection->hasMethod('create'));
        $this->assertTrue($reflection->hasMethod('get'));
        $this->assertTrue($reflection->hasMethod('update'));
        $this->assertTrue($reflection->hasMethod('delete'));

        $reflection = new \ReflectionClass($this->iris->services);
        $this->assertTrue($reflection->hasMethod('list'));
        $this->assertTrue($reflection->hasMethod('create'));
        $this->assertTrue($reflection->hasMethod('get'));
        $this->assertTrue($reflection->hasMethod('update'));
        $this->assertTrue($reflection->hasMethod('delete'));

        $reflection = new \ReflectionClass($this->iris->articles);
        $this->assertTrue($reflection->hasMethod('list'));
        $this->assertTrue($reflection->hasMethod('create'));
        $this->assertTrue($reflection->hasMethod('get'));
        $this->assertTrue($reflection->hasMethod('update'));
        $this->assertTrue($reflection->hasMethod('delete'));

        $reflection = new \ReflectionClass($this->iris->videos);
        $this->assertTrue($reflection->hasMethod('list'));
        $this->assertTrue($reflection->hasMethod('create'));
        $this->assertTrue($reflection->hasMethod('get'));
        $this->assertTrue($reflection->hasMethod('update'));
        $this->assertTrue($reflection->hasMethod('delete'));
        $this->assertTrue($reflection->hasMethod('upload'));
    }
}