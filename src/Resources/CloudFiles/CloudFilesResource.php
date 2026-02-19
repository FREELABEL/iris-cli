<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\CloudFiles;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;
use IRIS\SDK\Resources\Bloqs\CloudFile;
use IRIS\SDK\Resources\Bloqs\CloudFileCollection;

/**
 * Cloud Files Resource
 *
 * Manage cloud files across all bloqs.
 *
 * @example
 * ```php
 * // List all files for user
 * $files = $iris->cloudFiles->list();
 *
 * // Upload a file
 * $file = $iris->cloudFiles->upload('/path/to/document.pdf', [
 *     'bloq_id' => 32,
 *     'title' => 'Project Brief',
 * ]);
 *
 * // Get file download URL
 * $url = $iris->cloudFiles->downloadUrl(123);
 * ```
 */
class CloudFilesResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List all cloud files for the user.
     *
     * @param array{
     *     bloq_id?: int,
     *     agent_id?: int,
     *     type?: string,
     *     per_page?: int,
     *     page?: int
     * } $options Filter options
     * @return array Files with pagination
     *
     * @example
     * ```php
     * // List all files
     * $files = $iris->cloudFiles->list();
     *
     * // Filter by bloq
     * $files = $iris->cloudFiles->list(['bloq_id' => 32]);
     *
     * // Filter by type
     * $files = $iris->cloudFiles->list(['type' => 'pdf']);
     * ```
     */
    public function list(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $params = array_merge(['user_id' => $userId], $options);

        return $this->http->get("/api/v1/cloud-files", $params);
    }

    /**
     * Get a specific file by ID.
     *
     * @param int $fileId File ID
     * @return array File details
     */
    public function get(int $fileId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/cloud-files/{$fileId}", ['user_id' => $userId]);
    }

    /**
     * Upload a new file.
     *
     * @param string $filePath Path to the file
     * @param array{
     *     bloq_id?: int,
     *     agent_id?: int,
     *     title?: string,
     *     description?: string
     * } $options Upload options
     * @return array Uploaded file details
     *
     * @example
     * ```php
     * $file = $iris->cloudFiles->upload('/path/to/resume.pdf', [
     *     'bloq_id' => 32,
     *     'title' => 'John Doe Resume',
     * ]);
     * ```
     */
    public function upload(string $filePath, array $options = []): array
    {
        // Ensure user_id is included in the upload (required by FL-API)
        if (!isset($options['user_id']) && $this->config->userId) {
            $options['user_id'] = $this->config->userId;
        }

        return $this->http->upload("/api/v1/cloud-files/upload", $filePath, $options);
    }

    /**
     * Update file metadata.
     *
     * @param int $fileId File ID
     * @param array $data Update data
     * @return array Updated file
     */
    public function update(int $fileId, array $data): array
    {
        $userId = $this->config->requireUserId();
        $data['user_id'] = $userId;
        return $this->http->put("/api/v1/cloud-files/{$fileId}", $data);
    }

    /**
     * Delete a file.
     *
     * @param int $fileId File ID
     * @return bool
     */
    public function delete(int $fileId): bool
    {
        $userId = $this->config->requireUserId();
        $this->http->delete("/api/v1/cloud-files/{$fileId}?user_id={$userId}");
        return true;
    }

    /**
     * Get download URL for a file.
     *
     * @param int $fileId File ID
     * @return string Download URL
     */
    public function downloadUrl(int $fileId): string
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/cloud-files/{$fileId}/download", ['user_id' => $userId]);
        return $response['url'] ?? '';
    }

    /**
     * Get file processing status.
     *
     * @param int $fileId File ID
     * @return array Status info (processing, ready, failed)
     */
    public function status(int $fileId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/cloud-files/{$fileId}/status", ['user_id' => $userId]);
    }

    /**
     * Get extracted content from a file.
     *
     * For PDFs and documents, returns the extracted text content.
     *
     * @param int $fileId File ID
     * @return array Content and metadata
     */
    public function content(int $fileId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/cloud-files/{$fileId}/content", ['user_id' => $userId]);
    }

    /**
     * Get supported file types.
     *
     * @return array List of supported MIME types and extensions
     */
    public function supportedTypes(): array
    {
        $response = $this->http->get("/api/v1/cloud-files/supported-types");
        return $response['types'] ?? $response;
    }

    /**
     * Get files for a specific bloq.
     *
     * @param int $bloqId Bloq ID
     * @param array $options Filter options
     * @return array Files for the bloq
     */
    public function forBloq(int $bloqId, array $options = []): array
    {
        return $this->http->get("/api/v1/bloqs/{$bloqId}/files", $options);
    }

    /**
     * Get files for a specific agent.
     *
     * @param int $agentId Agent ID
     * @param array $options Filter options
     * @return array Files attached to the agent
     */
    public function forAgent(int $agentId, array $options = []): array
    {
        return $this->http->get("/api/v1/agents/{$agentId}/files", $options);
    }

    /**
     * Attach a file to an agent for RAG.
     *
     * @param int $fileId File ID
     * @param int $agentId Agent ID
     * @return array Result
     */
    public function attachToAgent(int $fileId, int $agentId): array
    {
        return $this->http->post("/api/v1/cloud-files/{$fileId}/attach-agent", [
            'agent_id' => $agentId,
        ]);
    }

    /**
     * Detach a file from an agent.
     *
     * @param int $fileId File ID
     * @param int $agentId Agent ID
     * @return bool
     */
    public function detachFromAgent(int $fileId, int $agentId): bool
    {
        $this->http->post("/api/v1/cloud-files/{$fileId}/detach-agent", [
            'agent_id' => $agentId,
        ]);
        return true;
    }

    /**
     * Re-index a file for vector search.
     *
     * @param int $fileId File ID
     * @return array Indexing status
     */
    public function reindex(int $fileId): array
    {
        return $this->http->post("/api/v1/cloud-files/{$fileId}/reindex", []);
    }

    /**
     * Upload a file and format it for agent attachment.
     *
     * This is a convenience method that uploads a file and returns the data
     * in the format needed for the agent's fileAttachments array.
     *
     * @param string $filePath Path to the file
     * @param int $bloqId Bloq ID to upload to
     * @param array{
     *     title?: string,
     *     description?: string
     * } $options Upload options
     * @return array File attachment data ready for agent update
     *
     * @example
     * ```php
     * // Upload file and get attachment data
     * $attachment = $iris->cloudFiles->uploadForAgent('/path/to/data.csv', 40, [
     *     'title' => 'Training Data',
     *     'description' => 'Agent training document'
     * ]);
     *
     * // Returns data like:
     * // [
     * //     'cloud_file_id' => 336,
     * //     'name' => 'data.csv',
     * //     'size' => 38936,
     * //     'type' => 'text/csv',
     * //     'filepath' => 'https://...',
     * //     'processingStatus' => 'completed',
     * //     'uploadedAt' => '2025-12-23T04:57:31.844Z'
     * // ]
     * ```
     */
    public function uploadForAgent(string $filePath, int $bloqId, array $options = []): array
    {
        $userId = $this->config->requireUserId();

        // Set default title from filename if not provided
        $filename = basename($filePath);
        $options['title'] = $options['title'] ?? $filename;
        $options['description'] = $options['description'] ?? 'Agent training document';
        $options['bloq_id'] = $bloqId;
        $options['user_id'] = $userId;

        // Upload the file
        $result = $this->upload($filePath, $options);

        // Format for agent's fileAttachments array
        return [
            'cloud_file_id' => $result['id'] ?? $result['cloud_file_id'],
            'name' => $result['name'] ?? $result['title'] ?? $filename,
            'size' => $result['size'] ?? filesize($filePath),
            'type' => $result['mime_type'] ?? $result['type'] ?? mime_content_type($filePath),
            'filepath' => $result['url'] ?? $result['filepath'] ?? '',
            'processingStatus' => $result['processing_status'] ?? $result['status'] ?? 'completed',
            'uploadedAt' => $result['created_at'] ?? date('c'),
        ];
    }

    /**
     * Upload multiple files for agent attachment.
     *
     * Supports mixed inputs: local file paths, URLs, or existing CloudFile IDs.
     *
     * @param array $files Array of file paths, URLs, or CloudFile IDs
     * @param int $bloqId Bloq ID to upload to
     * @param array $options Options applied to all files
     * @return array Array of file attachment data
     *
     * @example
     * ```php
     * $attachments = $iris->cloudFiles->uploadMultipleForAgent([
     *     '/path/to/file1.pdf',              // Local file
     *     'https://example.com/doc.pdf',     // URL
     *     336,                                // Existing CloudFile ID
     * ], 40);
     * ```
     */
    public function uploadMultipleForAgent(array $files, int $bloqId, array $options = []): array
    {
        $attachments = [];
        foreach ($files as $file) {
            $attachments[] = $this->attachAnyFile($file, $bloqId, $options);
        }
        return $attachments;
    }

    /**
     * Upload a file from a URL for agent attachment.
     *
     * Downloads the file from the URL and uploads it to cloud storage.
     *
     * @param string $url URL to download from
     * @param int $bloqId Bloq ID to upload to
     * @param array{
     *     title?: string,
     *     description?: string,
     *     filename?: string
     * } $options Upload options
     * @return array File attachment data ready for agent update
     *
     * @example
     * ```php
     * $attachment = $iris->cloudFiles->uploadFromUrl(
     *     'https://example.com/training-data.pdf',
     *     40,
     *     ['title' => 'External Training Data']
     * );
     * ```
     */
    public function uploadFromUrl(string $url, int $bloqId, array $options = []): array
    {
        // Download the file to a temp location
        $tempFile = $this->downloadToTemp($url, $options['filename'] ?? null);

        try {
            // Upload the temp file
            $result = $this->uploadForAgent($tempFile, $bloqId, $options);

            // Update the name to reflect original URL if no title specified
            if (!isset($options['title'])) {
                $result['name'] = $options['filename'] ?? basename(parse_url($url, PHP_URL_PATH)) ?: 'downloaded_file';
            }

            return $result;
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Format an existing CloudFile for agent attachment.
     *
     * Use this when you already have a CloudFile ID and want to attach it to an agent.
     *
     * @param int $cloudFileId Existing CloudFile ID
     * @return array File attachment data ready for agent update
     *
     * @example
     * ```php
     * // Attach an existing file (ID 336) to an agent
     * $attachment = $iris->cloudFiles->formatForAgentAttachment(336);
     * $agent = $iris->agents->addFileAttachments(12, [$attachment]);
     * ```
     */
    public function formatForAgentAttachment(int $cloudFileId): array
    {
        // Get the file details from the API
        $file = $this->get($cloudFileId);

        return [
            'cloud_file_id' => $file['id'] ?? $cloudFileId,
            'name' => $file['name'] ?? $file['title'] ?? "file_{$cloudFileId}",
            'size' => $file['size'] ?? 0,
            'type' => $file['mime_type'] ?? $file['type'] ?? 'application/octet-stream',
            'filepath' => $file['url'] ?? $file['filepath'] ?? '',
            'processingStatus' => $file['processing_status'] ?? $file['status'] ?? 'completed',
            'uploadedAt' => $file['created_at'] ?? date('c'),
        ];
    }

    /**
     * Smart file attachment that handles any input type.
     *
     * Automatically detects and handles:
     * - Local file paths (e.g., '/path/to/file.pdf')
     * - URLs (e.g., 'https://example.com/doc.pdf')
     * - Existing CloudFile IDs (e.g., 336)
     *
     * @param string|int $file File path, URL, or CloudFile ID
     * @param int $bloqId Bloq ID to upload to (ignored for existing files)
     * @param array $options Upload options
     * @return array File attachment data ready for agent update
     *
     * @example
     * ```php
     * // Local file
     * $a1 = $iris->cloudFiles->attachAnyFile('/path/to/file.pdf', 40);
     *
     * // URL
     * $a2 = $iris->cloudFiles->attachAnyFile('https://example.com/doc.pdf', 40);
     *
     * // Existing CloudFile
     * $a3 = $iris->cloudFiles->attachAnyFile(336, 40);
     * ```
     */
    public function attachAnyFile(string|int $file, int $bloqId, array $options = []): array
    {
        // If it's an integer, treat as existing CloudFile ID
        if (is_int($file)) {
            return $this->formatForAgentAttachment($file);
        }

        // If it's a URL, download and upload
        if ($this->isUrl($file)) {
            return $this->uploadFromUrl($file, $bloqId, $options);
        }

        // Otherwise, treat as local file path
        return $this->uploadForAgent($file, $bloqId, $options);
    }

    /**
     * Check if a string is a URL.
     */
    protected function isUrl(string $str): bool
    {
        return (bool) filter_var($str, FILTER_VALIDATE_URL)
            && in_array(parse_url($str, PHP_URL_SCHEME), ['http', 'https']);
    }

    /**
     * Download a file from URL to a temporary location.
     */
    protected function downloadToTemp(string $url, ?string $filename = null): string
    {
        // Determine filename
        if (!$filename) {
            $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'downloaded_file';
        }

        // Create temp file with proper extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $tempFile = sys_get_temp_dir() . '/iris_upload_' . uniqid() . ($extension ? ".{$extension}" : '');

        // Download the file
        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'user_agent' => 'IRIS-SDK/1.0',
            ],
        ]);

        $content = file_get_contents($url, false, $context);
        if ($content === false) {
            throw new \RuntimeException("Failed to download file from URL: {$url}");
        }

        file_put_contents($tempFile, $content);

        return $tempFile;
    }
}
