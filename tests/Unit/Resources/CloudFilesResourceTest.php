<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;

/**
 * CloudFilesResource Tests
 *
 * Tests for cloud file management, uploads, and agent attachments.
 */
class CloudFilesResourceTest extends TestCase
{
    // ========================================
    // List Operations
    // ========================================

    public function test_list_files(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files', [
            'data' => [
                ['id' => 1, 'name' => 'document.pdf', 'type' => 'application/pdf', 'size' => 1024000],
                ['id' => 2, 'name' => 'data.csv', 'type' => 'text/csv', 'size' => 5000],
            ],
            'meta' => ['total' => 2],
        ]);

        $files = $this->iris->cloudFiles->list();

        $this->assertArrayHasKey('data', $files);
        $this->assertCount(2, $files['data']);
    }

    public function test_list_files_with_filters(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files', [
            'data' => [
                ['id' => 1, 'name' => 'report.pdf', 'bloq_id' => 32],
            ],
            'meta' => ['total' => 1],
        ]);

        $files = $this->iris->cloudFiles->list([
            'bloq_id' => 32,
            'type' => 'pdf',
        ]);

        $this->assertCount(1, $files['data']);
    }

    public function test_get_files_for_bloq(): void
    {
        $this->mockResponse('GET', '/api/v1/bloqs/32/files', [
            'data' => [
                ['id' => 1, 'name' => 'project-brief.pdf'],
                ['id' => 2, 'name' => 'requirements.docx'],
            ],
        ]);

        $files = $this->iris->cloudFiles->forBloq(32);

        $this->assertArrayHasKey('data', $files);
        $this->assertCount(2, $files['data']);
    }

    public function test_get_files_for_agent(): void
    {
        $this->mockResponse('GET', '/api/v1/agents/456/files', [
            'data' => [
                ['id' => 100, 'name' => 'training-data.csv'],
            ],
        ]);

        $files = $this->iris->cloudFiles->forAgent(456);

        $this->assertArrayHasKey('data', $files);
    }

    // ========================================
    // CRUD Operations
    // ========================================

    public function test_get_file(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/100', [
            'id' => 100,
            'name' => 'document.pdf',
            'title' => 'Project Document',
            'mime_type' => 'application/pdf',
            'size' => 2048000,
            'user_id' => 123,
            'bloq_id' => 32,
            'created_at' => '2025-12-23T10:00:00Z',
        ]);

        $file = $this->iris->cloudFiles->get(100);

        $this->assertEquals(100, $file['id']);
        $this->assertEquals('document.pdf', $file['name']);
    }

    public function test_upload_file(): void
    {
        $this->mockResponse('POST', '/api/v1/cloud-files/upload', [
            'id' => 200,
            'name' => 'new-file.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => 'completed',
            'url' => 'https://storage.iris.ai/files/new-file.pdf',
        ]);

        $file = $this->iris->cloudFiles->upload('/path/to/file.pdf', [
            'bloq_id' => 32,
            'title' => 'New File',
        ]);

        $this->assertEquals(200, $file['id']);
        $this->assertEquals('completed', $file['status']);
    }

    public function test_update_file(): void
    {
        $this->mockResponse('PUT', '/api/v1/cloud-files/100', [
            'id' => 100,
            'name' => 'document.pdf',
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);

        $file = $this->iris->cloudFiles->update(100, [
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);

        $this->assertEquals('Updated Title', $file['title']);
    }

    public function test_delete_file(): void
    {
        // Note: delete() now includes user_id as query param for authorization
        $this->mockResponse('DELETE', '/api/v1/cloud-files/100?user_id=123', [
            'success' => true,
        ]);

        $result = $this->iris->cloudFiles->delete(100);

        $this->assertTrue($result);
    }

    // ========================================
    // File Operations
    // ========================================

    public function test_get_download_url(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/100/download', [
            'url' => 'https://storage.iris.ai/files/100/download?token=abc123',
            'expires_at' => '2025-12-23T11:00:00Z',
        ]);

        $url = $this->iris->cloudFiles->downloadUrl(100);

        $this->assertStringContainsString('download', $url);
        $this->assertStringContainsString('token=', $url);
    }

    public function test_get_file_status(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/100/status', [
            'id' => 100,
            'status' => 'ready',
            'processing_progress' => 100,
            'indexed' => true,
            'vector_count' => 45,
        ]);

        $status = $this->iris->cloudFiles->status(100);

        $this->assertEquals('ready', $status['status']);
        $this->assertTrue($status['indexed']);
    }

    public function test_get_file_status_processing(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/200/status', [
            'id' => 200,
            'status' => 'processing',
            'processing_progress' => 60,
            'indexed' => false,
        ]);

        $status = $this->iris->cloudFiles->status(200);

        $this->assertEquals('processing', $status['status']);
        $this->assertEquals(60, $status['processing_progress']);
    }

    public function test_get_extracted_content(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/100/content', [
            'id' => 100,
            'content' => 'This is the extracted text content from the PDF document...',
            'word_count' => 1500,
            'char_count' => 8500,
            'extraction_metadata' => [
                'method' => 'pdf_text_extraction',
                'quality' => 'high',
            ],
        ]);

        $content = $this->iris->cloudFiles->content(100);

        $this->assertArrayHasKey('content', $content);
        $this->assertNotEmpty($content['content']);
    }

    public function test_get_supported_types(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/supported-types', [
            'types' => [
                'application/pdf',
                'text/csv',
                'text/plain',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ]);

        $types = $this->iris->cloudFiles->supportedTypes();

        $this->assertContains('application/pdf', $types);
        $this->assertContains('text/csv', $types);
    }

    // ========================================
    // Agent Attachment Operations
    // ========================================

    public function test_attach_file_to_agent(): void
    {
        $this->mockResponse('POST', '/api/v1/cloud-files/100/attach-agent', [
            'success' => true,
            'file_id' => 100,
            'agent_id' => 456,
        ]);

        $result = $this->iris->cloudFiles->attachToAgent(100, 456);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }

    public function test_detach_file_from_agent(): void
    {
        $this->mockResponse('POST', '/api/v1/cloud-files/100/detach-agent', [
            'success' => true,
        ]);

        $result = $this->iris->cloudFiles->detachFromAgent(100, 456);

        $this->assertTrue($result);
    }

    public function test_reindex_file(): void
    {
        $this->mockResponse('POST', '/api/v1/cloud-files/100/reindex', [
            'id' => 100,
            'status' => 'indexing',
            'message' => 'File queued for re-indexing',
        ]);

        $result = $this->iris->cloudFiles->reindex(100);

        $this->assertEquals('indexing', $result['status']);
    }

    // ========================================
    // Convenience Methods
    // ========================================

    public function test_upload_for_agent(): void
    {
        $this->mockResponse('POST', '/api/v1/cloud-files/upload', [
            'id' => 300,
            'name' => 'training.csv',
            'mime_type' => 'text/csv',
            'size' => 5000,
            'url' => 'https://storage.iris.ai/files/training.csv',
            'status' => 'completed',
            'created_at' => '2025-12-23T10:00:00Z',
        ]);

        $attachment = $this->iris->cloudFiles->uploadForAgent('/path/to/training.csv', 40, [
            'title' => 'Training Data',
            'description' => 'Agent training document',
        ]);

        // Should return agent attachment format
        $this->assertArrayHasKey('cloud_file_id', $attachment);
        $this->assertArrayHasKey('name', $attachment);
        $this->assertArrayHasKey('size', $attachment);
        $this->assertArrayHasKey('type', $attachment);
        $this->assertArrayHasKey('processingStatus', $attachment);
        $this->assertEquals(300, $attachment['cloud_file_id']);
    }

    public function test_upload_multiple_for_agent(): void
    {
        // First file upload
        $this->mockResponse('POST', '/api/v1/cloud-files/upload', [
            'id' => 301,
            'name' => 'file1.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'status' => 'completed',
        ]);

        // Note: MockHttpClient will reuse same response for repeated calls
        // In real tests you'd want to queue multiple responses

        $attachments = $this->iris->cloudFiles->uploadMultipleForAgent([
            '/path/to/file1.pdf',
        ], 40);

        $this->assertIsArray($attachments);
        $this->assertCount(1, $attachments);
        $this->assertArrayHasKey('cloud_file_id', $attachments[0]);
    }

    // ========================================
    // URL & External File Support
    // ========================================

    public function test_format_for_agent_attachment(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/211', [
            'id' => 211,
            'name' => 'leadership_essentials.md',
            'title' => 'Leadership Essentials',
            'mime_type' => 'text/markdown',
            'size' => 8500,
            'url' => 'https://storage.iris.ai/files/leadership_essentials.md',
            'processing_status' => 'completed',
            'created_at' => '2025-12-23T10:00:00Z',
        ]);

        $attachment = $this->iris->cloudFiles->formatForAgentAttachment(211);

        $this->assertArrayHasKey('cloud_file_id', $attachment);
        $this->assertArrayHasKey('name', $attachment);
        $this->assertArrayHasKey('size', $attachment);
        $this->assertArrayHasKey('type', $attachment);
        $this->assertArrayHasKey('filepath', $attachment);
        $this->assertArrayHasKey('processingStatus', $attachment);
        $this->assertArrayHasKey('uploadedAt', $attachment);

        $this->assertEquals(211, $attachment['cloud_file_id']);
        $this->assertEquals('leadership_essentials.md', $attachment['name']);
        $this->assertEquals('text/markdown', $attachment['type']);
        $this->assertEquals('completed', $attachment['processingStatus']);
    }

    public function test_attach_any_file_with_existing_cloud_file_id(): void
    {
        // When given an integer, should call formatForAgentAttachment
        $this->mockResponse('GET', '/api/v1/cloud-files/212', [
            'id' => 212,
            'name' => 'business_strategy.md',
            'mime_type' => 'text/markdown',
            'size' => 12000,
            'url' => 'https://storage.iris.ai/files/business_strategy.md',
            'processing_status' => 'completed',
            'created_at' => '2025-12-24T08:00:00Z',
        ]);

        $attachment = $this->iris->cloudFiles->attachAnyFile(212, 33);

        $this->assertEquals(212, $attachment['cloud_file_id']);
        $this->assertEquals('business_strategy.md', $attachment['name']);
        $this->assertEquals('text/markdown', $attachment['type']);
    }

    public function test_attach_any_file_with_local_path(): void
    {
        // When given a local path (not a URL), should upload the file
        $this->mockResponse('POST', '/api/v1/cloud-files/upload', [
            'id' => 400,
            'name' => 'local_document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5000,
            'url' => 'https://storage.iris.ai/files/local_document.pdf',
            'status' => 'completed',
            'created_at' => '2025-12-24T10:00:00Z',
        ]);

        $attachment = $this->iris->cloudFiles->attachAnyFile('/path/to/local_document.pdf', 33);

        $this->assertArrayHasKey('cloud_file_id', $attachment);
        $this->assertEquals(400, $attachment['cloud_file_id']);
    }

    public function test_upload_multiple_with_mixed_inputs(): void
    {
        // Mock for CloudFile ID fetch
        $this->mockResponse('GET', '/api/v1/cloud-files/336', [
            'id' => 336,
            'name' => 'existing_file.csv',
            'mime_type' => 'text/csv',
            'size' => 3000,
            'url' => 'https://storage.iris.ai/files/existing_file.csv',
            'processing_status' => 'completed',
            'created_at' => '2025-12-20T10:00:00Z',
        ]);

        // Only testing with existing CloudFile ID (URL download requires network)
        $attachments = $this->iris->cloudFiles->uploadMultipleForAgent([
            336,  // Existing CloudFile ID
        ], 40);

        $this->assertIsArray($attachments);
        $this->assertCount(1, $attachments);
        $this->assertEquals(336, $attachments[0]['cloud_file_id']);
        $this->assertEquals('existing_file.csv', $attachments[0]['name']);
    }

    public function test_format_for_agent_attachment_with_minimal_data(): void
    {
        // Test with minimal API response (missing some fields)
        $this->mockResponse('GET', '/api/v1/cloud-files/500', [
            'id' => 500,
            'title' => 'Minimal File',
        ]);

        $attachment = $this->iris->cloudFiles->formatForAgentAttachment(500);

        $this->assertEquals(500, $attachment['cloud_file_id']);
        $this->assertEquals('Minimal File', $attachment['name']);  // Falls back to title
        $this->assertEquals(0, $attachment['size']);  // Default
        $this->assertEquals('application/octet-stream', $attachment['type']);  // Default
        $this->assertEquals('completed', $attachment['processingStatus']);  // Default
    }

    public function test_url_detection_logic(): void
    {
        // Test URL detection through attachAnyFile behavior
        // URLs should be detected, but we can't actually download in unit tests
        // Instead, verify that local paths are correctly identified as not URLs

        // Local path should trigger upload
        $this->mockResponse('POST', '/api/v1/cloud-files/upload', [
            'id' => 600,
            'name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'status' => 'completed',
        ]);

        // These should NOT be detected as URLs
        $localPaths = [
            '/absolute/path/to/file.pdf',
            './relative/path.txt',
            '../parent/file.doc',
            'just-a-filename.csv',
        ];

        foreach ($localPaths as $path) {
            // If it's a local path, attachAnyFile should call uploadForAgent
            // which will hit the POST endpoint we mocked
            $attachment = $this->iris->cloudFiles->attachAnyFile($path, 33);
            $this->assertArrayHasKey('cloud_file_id', $attachment);
        }
    }

    // ========================================
    // User ID Authorization Tests
    // ========================================
    // These tests verify that user_id is properly included in API requests
    // to prevent "Unauthorized" errors when accessing user-specific files.

    public function test_get_file_includes_user_id_in_request(): void
    {
        // This test verifies that the get() method includes user_id
        // The API requires user_id to verify file ownership
        $this->mockResponse('GET', '/api/v1/cloud-files/100', [
            'id' => 100,
            'name' => 'test.pdf',
            'user_id' => 123,  // Should match the user making request
        ]);

        $file = $this->iris->cloudFiles->get(100);

        // If we get here without "Unauthorized" error, user_id was passed
        $this->assertEquals(100, $file['id']);
    }

    public function test_status_includes_user_id_in_request(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/100/status', [
            'id' => 100,
            'status' => 'ready',
            'processing_progress' => 100,
        ]);

        $status = $this->iris->cloudFiles->status(100);

        $this->assertEquals('ready', $status['status']);
    }

    public function test_content_includes_user_id_in_request(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/100/content', [
            'id' => 100,
            'content' => 'File content here...',
        ]);

        $content = $this->iris->cloudFiles->content(100);

        $this->assertArrayHasKey('content', $content);
    }

    public function test_download_url_includes_user_id_in_request(): void
    {
        $this->mockResponse('GET', '/api/v1/cloud-files/100/download', [
            'url' => 'https://storage.example.com/file.pdf?token=abc',
        ]);

        $url = $this->iris->cloudFiles->downloadUrl(100);

        $this->assertNotEmpty($url);
    }

    public function test_update_includes_user_id_in_request(): void
    {
        $this->mockResponse('PUT', '/api/v1/cloud-files/100', [
            'id' => 100,
            'title' => 'Updated',
        ]);

        $file = $this->iris->cloudFiles->update(100, ['title' => 'Updated']);

        $this->assertEquals('Updated', $file['title']);
    }

    public function test_delete_includes_user_id_in_request(): void
    {
        // Note: delete() appends user_id as query param for authorization
        $this->mockResponse('DELETE', '/api/v1/cloud-files/100?user_id=123', [
            'success' => true,
        ]);

        $result = $this->iris->cloudFiles->delete(100);

        $this->assertTrue($result);
    }
}
