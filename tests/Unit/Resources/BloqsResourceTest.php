<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Resources\Bloqs\Bloq;
use IRIS\SDK\Resources\Bloqs\BloqCollection;
use IRIS\SDK\Resources\Bloqs\BloqList;
use IRIS\SDK\Resources\Bloqs\CloudFile;

class BloqsResourceTest extends TestCase
{
    public function test_list_bloqs(): void
    {
        $this->mockResponse('GET', '/api/v1/user/123/bloqs', [
            'data' => [
                ['id' => 1, 'title' => 'Bloq 1', 'item_count' => 5],
                ['id' => 2, 'title' => 'Bloq 2', 'item_count' => 3],
            ],
            'meta' => ['total' => 2],
        ]);

        $bloqs = $this->iris->bloqs->list();

        $this->assertInstanceOf(BloqCollection::class, $bloqs);
        $this->assertCount(2, $bloqs);
        $this->assertEquals('Bloq 1', $bloqs->first()->title);
    }

    public function test_create_bloq(): void
    {
        $this->mockResponse('POST', '/api/v1/user/123/bloqs', [
            'id' => 789,
            'title' => 'New Bloq',
            'description' => 'Test description',
            'user_id' => 123,
            'is_pinned' => false,
            'item_count' => 0,
            'list_count' => 0,
        ]);

        $bloq = $this->iris->bloqs->create('New Bloq', [
            'description' => 'Test description',
        ]);

        $this->assertInstanceOf(Bloq::class, $bloq);
        $this->assertEquals(789, $bloq->id);
        $this->assertEquals('New Bloq', $bloq->title);
    }

    public function test_get_bloq(): void
    {
        $this->mockResponse('GET', '/api/v1/user/123/bloqs/456', [
            'id' => 456,
            'title' => 'Test Bloq',
            'item_count' => 10,
        ]);

        $bloq = $this->iris->bloqs->get(456);

        $this->assertInstanceOf(Bloq::class, $bloq);
        $this->assertEquals(456, $bloq->id);
        $this->assertEquals(10, $bloq->itemCount);
    }

    public function test_update_bloq(): void
    {
        $this->mockResponse('PUT', '/api/v1/user/123/bloqs/456', [
            'id' => 456,
            'title' => 'Updated Bloq',
        ]);

        $bloq = $this->iris->bloqs->update(456, ['title' => 'Updated Bloq']);

        $this->assertEquals('Updated Bloq', $bloq->title);
    }

    public function test_delete_bloq(): void
    {
        $this->mockResponse('DELETE', '/api/v1/user/123/bloqs/456', [
            'success' => true,
        ]);

        $result = $this->iris->bloqs->delete(456);

        $this->assertTrue($result);
    }

    public function test_toggle_pin(): void
    {
        $this->mockResponse('POST', '/api/v1/users/123/bloqs/456/pin', [
            'id' => 456,
            'title' => 'Test Bloq',
            'is_pinned' => true,
        ]);

        $bloq = $this->iris->bloqs->togglePin(456);

        $this->assertTrue($bloq->isPinned);
    }

    public function test_upload_file(): void
    {
        $this->mockResponse('POST', '/api/v1/cloud-files/upload', [
            'id' => 999,
            'bloq_id' => 456,
            'filename' => 'test.pdf',
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'url' => 'https://example.com/test.pdf',
            'status' => 'ready',
        ]);

        $file = $this->iris->bloqs->uploadFile(456, '/path/to/test.pdf');

        $this->assertInstanceOf(CloudFile::class, $file);
        $this->assertEquals('test.pdf', $file->filename);
        $this->assertTrue($file->isReady());
    }

    public function test_lists_sub_resource(): void
    {
        $this->mockResponse('GET', '/api/v1/user/123/bloqs/456/lists', [
            'data' => [
                ['id' => 1, 'bloq_id' => 456, 'title' => 'List 1'],
            ],
        ]);

        $lists = $this->iris->bloqs->lists(456)->all();

        $this->assertCount(1, $lists);
        $this->assertInstanceOf(BloqList::class, $lists->first());
    }
}
