<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Audio;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Audio Resource
 *
 * Audio processing and manipulation via FFMPEG service.
 * 
 * Features:
 * - Merge audio files with professional crossfade
 * - Convert between formats
 * - Extract audio from video
 * - Add metadata (ID3 tags)
 * - Create compilations with radio drops
 *
 * @example
 * ```php
 * // Merge audio files with crossfade
 * $result = $iris->audio->mergeWithCrossfade([
 *     '/path/to/track1.mp3',
 *     '/path/to/track2.mp3',
 *     '/path/to/track3.mp3',
 * ], 'compilation.mp3', 3); // 3 second crossfade
 *
 * // Add metadata
 * $iris->audio->addMetadata('/path/to/track.mp3', [
 *     'artist' => 'Texas Artist',
 *     'title' => 'Song Title',
 *     'album' => 'EMC Music Series',
 *     'year' => '2026',
 * ]);
 *
 * // Convert format
 * $iris->audio->convert('/path/to/input.wav', 'output.mp3', [
 *     'bitrate' => '320k',
 *     'quality' => 'high',
 * ]);
 * ```
 */
class AudioResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Merge multiple audio files with professional crossfade.
     *
     * @param array<string> $audioFiles Array of audio file paths
     * @param string $outputFile Output filename
     * @param int $crossfadeDuration Crossfade duration in seconds (default: 3)
     * @return AudioMergeResult
     */
    public function mergeWithCrossfade(
        array $audioFiles,
        string $outputFile,
        int $crossfadeDuration = 3
    ): AudioMergeResult {
        $response = $this->http->post('/api/audio/merge', [
            'audio_files' => $audioFiles,
            'output_file' => $outputFile,
            'crossfade_duration' => $crossfadeDuration,
        ]);

        return AudioMergeResult::fromResponse($response);
    }

    /**
     * Add or update metadata (ID3 tags) for an audio file.
     *
     * @param string $filePath Path to audio file
     * @param array{
     *     artist?: string,
     *     title?: string,
     *     album?: string,
     *     year?: string,
     *     genre?: string,
     *     track?: string,
     *     comment?: string,
     *     cover_art?: string
     * } $metadata Metadata to add
     * @return AudioMetadataResult
     */
    public function addMetadata(string $filePath, array $metadata): AudioMetadataResult
    {
        $response = $this->http->post('/api/audio/metadata', [
            'file_path' => $filePath,
            'metadata' => $metadata,
        ]);

        return AudioMetadataResult::fromResponse($response);
    }

    /**
     * Convert audio file to different format.
     *
     * @param string $inputFile Input file path
     * @param string $outputFile Output file path
     * @param array{
     *     bitrate?: string,
     *     quality?: string,
     *     format?: string
     * } $options Conversion options
     * @return AudioConversionResult
     */
    public function convert(
        string $inputFile,
        string $outputFile,
        array $options = []
    ): AudioConversionResult {
        $response = $this->http->post('/api/audio/convert', [
            'input_file' => $inputFile,
            'output_file' => $outputFile,
            'options' => $options,
        ]);

        return AudioConversionResult::fromResponse($response);
    }

    /**
     * Extract audio from video file.
     *
     * @param string $videoFile Video file path
     * @param string $outputFile Output audio file path
     * @param array{
     *     bitrate?: string,
     *     format?: string
     * } $options Extraction options
     * @return AudioExtractionResult
     */
    public function extractFromVideo(
        string $videoFile,
        string $outputFile,
        array $options = []
    ): AudioExtractionResult {
        $response = $this->http->post('/api/audio/extract', [
            'video_file' => $videoFile,
            'output_file' => $outputFile,
            'options' => $options,
        ]);

        return AudioExtractionResult::fromResponse($response);
    }

    /**
     * Get audio file information (duration, bitrate, etc).
     *
     * @param string $filePath Audio file path
     * @return AudioInfo
     */
    public function getInfo(string $filePath): AudioInfo
    {
        $response = $this->http->get('/api/audio/info', [
            'file_path' => $filePath,
        ]);

        return AudioInfo::fromResponse($response);
    }

    /**
     * Validate FFMPEG is installed and working.
     *
     * @return array{
     *     ffmpeg: array{status: string, path: string},
     *     assets: array
     * }
     */
    public function validateSystem(): array
    {
        return $this->http->get('/api/audio/validate-system');
    }

    /**
     * Create a compilation with radio drops and crossfades.
     * 
     * This is a high-level method for music series compilations.
     *
     * @param array<string> $tracks Track file paths
     * @param string $outputFile Output compilation file
     * @param array{
     *     crossfade_duration?: int,
     *     add_radio_drops?: bool,
     *     radio_drop_file?: string,
     *     metadata?: array
     * } $options Compilation options
     * @return AudioCompilationResult
     */
    public function createCompilation(
        array $tracks,
        string $outputFile,
        array $options = []
    ): AudioCompilationResult {
        $response = $this->http->post('/api/audio/compilation', [
            'tracks' => $tracks,
            'output_file' => $outputFile,
            'options' => $options,
        ]);

        return AudioCompilationResult::fromResponse($response);
    }
}
