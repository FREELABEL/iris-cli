<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Feature;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\IRIS;

class IRISClientTest extends TestCase
{
    public function test_can_create_client_with_config(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(IRIS::class, $iris);
    }

    public function test_has_all_resource_properties(): void
    {
        $iris = new IRIS(['api_key' => 'test_key']);

        $this->assertObjectHasProperty('agents', $iris);
        $this->assertObjectHasProperty('workflows', $iris);
        $this->assertObjectHasProperty('bloqs', $iris);
        $this->assertObjectHasProperty('leads', $iris);
        $this->assertObjectHasProperty('integrations', $iris);
        $this->assertObjectHasProperty('rag', $iris);
    }

    public function test_as_user_creates_new_instance(): void
    {
        $iris1 = new IRIS(['api_key' => 'test_key', 'user_id' => 123]);
        $iris2 = $iris1->asUser(456);

        $this->assertNotSame($iris1, $iris2);
    }

    public function test_sdk_version_constant(): void
    {
        $this->assertIsString(IRIS::VERSION);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', IRIS::VERSION);
    }
}
