<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;

/**
 * Cloud Upload Command
 *
 * Upload any file (including large files) to cloud storage and get share + CDN URLs.
 * Small files (<100MB) go through the API. Large files upload directly to DO Spaces
 * via the AWS CLI, then register the record via the API.
 *
 * Usage:
 *   iris cloud:upload /path/to/file.zip --title="Client Deliverable" --expires=1
 *   iris cloud:upload /path/to/large-archive.zip --title="Video Files" --expires=1
 *   iris cloud:upload --url=https://example.com/file.zip --expires=180
 */
class CloudUploadCommand extends Command
{
    private const DIRECT_UPLOAD_THRESHOLD = 95 * 1024 * 1024; // 95MB — use direct S3 upload above this

    protected function configure(): void
    {
        $this
            ->setName('cloud:upload')
            ->setDescription('Upload a file to cloud storage and get CDN + share URLs')
            ->setHelp(<<<'HELP'
Upload a file to DigitalOcean Spaces cloud storage.

Returns both the CDN URL (direct file download) and a share URL
(preview page with metadata in the IRIS app).

Handles any file size — small files go through the API, large files
upload directly to DO Spaces via the AWS CLI.

<info>Upload from local file:</info>
  iris cloud:upload /path/to/document.pdf
  iris cloud:upload ./deliverable.zip --title="Client Files" --expires=90
  iris cloud:upload "/path/to/big file.zip" --title="Videos" --expires=1

<info>Upload from URL:</info>
  iris cloud:upload --url=https://example.com/file.zip --expires=180

<info>Options:</info>
  --title       Custom display name for the file
  --description Description for the file
  --expires     Time until auto-deletion. Accepts flexible formats:
                  1d, 1day, 1days, "1 day", 12h, 12hours, 2w, 2weeks,
                  90, 90d (default: 180 days, 0 or "never" for permanent)
  --bloq        Associate with a knowledge base (Bloq ID)
  --json        Output as JSON for scripting/automation

<info>Large file requirements:</info>
  Files over 95MB are uploaded directly to DO Spaces. This requires:
  - AWS CLI installed (brew install awscli)
  - DO Spaces credentials in your SDK .env or environment:
    DO_SPACES_KEY, DO_SPACES_SECRET
HELP
            )
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to local file to upload')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Download from URL instead of local file')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Custom file title')
            ->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'File description')
            ->addOption('expires', 'e', InputOption::VALUE_REQUIRED, 'Days until expiration (0 = permanent)', '180')
            ->addOption('bloq', 'b', InputOption::VALUE_REQUIRED, 'Bloq ID to associate with')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filePath = $input->getArgument('file');
        $url = $input->getOption('url');

        if (!$filePath && !$url) {
            $io->error('Provide either a file path or --url option.');
            $io->text([
                'Usage: iris cloud:upload /path/to/file.zip',
                '   or: iris cloud:upload --url=https://example.com/file.zip',
            ]);
            return Command::FAILURE;
        }

        try {
            // Build config
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int) $userId;
            }

            $iris = new IRIS($configOptions);
            $jsonOutput = $input->getOption('json');

            $expiration = $this->parseExpiration($input->getOption('expires'));
            $expiresDays = $expiration['days'];
            $expiresHours = $expiration['hours'];
            $expiresLabel = $expiration['label'];

            // Resolve the file to upload
            $localPath = null;
            $originalFilename = null;
            $tempFile = null;

            if ($url) {
                if (!$jsonOutput) {
                    $io->title('Uploading to Cloud Storage');
                    $io->text("Downloading from: <info>{$url}</info>");
                }
                $tempFile = $this->downloadFromUrl($url);
                $localPath = $tempFile;
                $originalFilename = $input->getOption('title') ?: basename(strtok(parse_url($url, PHP_URL_PATH) ?: 'file', '?'));
                if (!$jsonOutput) {
                    $io->text("Downloaded: <info>{$this->formatBytes(filesize($tempFile))}</info>");
                }
            } else {
                if (!file_exists($filePath)) {
                    $io->error("File not found: {$filePath}");
                    return Command::FAILURE;
                }
                $localPath = $filePath;
                $originalFilename = basename($filePath);
            }

            $fileSize = filesize($localPath);

            if (!$jsonOutput && !$url) {
                $io->title('Uploading to Cloud Storage');
                $io->text([
                    "File: <info>{$originalFilename}</info>",
                    "Size: <info>{$this->formatBytes($fileSize)}</info>",
                ]);
            }

            // Route: large files go direct to S3, small files through API
            if ($fileSize > self::DIRECT_UPLOAD_THRESHOLD) {
                $result = $this->uploadLargeFile($io, $iris, $localPath, $originalFilename, $input, $jsonOutput);
            } else {
                $result = $this->uploadSmallFile($iris, $localPath, $input);
            }

            // Clean up temp file
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }

            if (!$result) {
                return Command::FAILURE;
            }

            // Extract URLs from result
            $fileId = $result['id'] ?? $result['data']['id'] ?? null;
            $cdnUrl = $result['cdn_url'] ?? $result['url'] ?? $result['filepath'] ?? $result['data']['cdn_url'] ?? $result['data']['url'] ?? $result['data']['filepath'] ?? '';
            $shareUrl = $result['share_url'] ?? $result['data']['share_url'] ?? '';
            if (!$shareUrl && $fileId) {
                $shareUrl = "https://elon.freelabel.net/content/Cloud/file/{$fileId}";
            }
            $fileTitle = $result['title'] ?? $result['data']['title'] ?? $originalFilename;
            $resultSize = $result['file_size'] ?? $result['size'] ?? $result['data']['file_size'] ?? $fileSize;
            $expiresAt = $result['expires_at'] ?? $result['data']['expires_at'] ?? null;

            if ($jsonOutput) {
                $output->writeln(json_encode([
                    'file_id' => $fileId,
                    'title' => $fileTitle,
                    'cdn_url' => $cdnUrl,
                    'share_url' => $shareUrl,
                    'size' => $resultSize,
                    'expires_at' => $expiresAt,
                    'expires' => $expiresLabel,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $io->newLine();
                $io->success('File uploaded successfully!');

                $io->definitionList(
                    ['File ID' => $fileId ?? '—'],
                    ['Title' => $fileTitle],
                    ['Size' => $this->formatBytes($resultSize)],
                    ['Expires' => $expiresLabel . ($expiresAt ? " ({$expiresAt})" : '')],
                );

                $io->newLine();
                $io->section('CDN URL (direct download)');
                $io->text($cdnUrl);

                $io->newLine();
                $io->section('Share URL (preview in app)');
                $io->text($shareUrl);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            if (isset($tempFile) && $tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }

            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    /**
     * Small files (<95MB): upload through the API endpoint.
     */
    private function uploadSmallFile(IRIS $iris, string $localPath, InputInterface $input): array
    {
        $options = $this->buildUploadOptions($input);
        $options['type'] = 'digital_product';

        return $iris->cloudFiles->upload($localPath, $options);
    }

    /**
     * Large files (>95MB): upload directly to DO Spaces via AWS CLI, then register via API.
     */
    private function uploadLargeFile(SymfonyStyle $io, IRIS $iris, string $localPath, string $originalFilename, InputInterface $input, bool $jsonOutput): ?array
    {
        // Check AWS CLI is available
        $awsPath = trim(shell_exec('which aws 2>/dev/null') ?? '');
        if (!$awsPath) {
            $io->error('AWS CLI is required for large file uploads (>95MB). Install with: brew install awscli');
            return null;
        }

        // Get DO Spaces credentials
        $spacesKey = $this->getEnvValue('DO_SPACES_KEY');
        $spacesSecret = $this->getEnvValue('DO_SPACES_SECRET');
        $spacesRegion = $this->getEnvValue('DO_SPACES_REGION') ?: 'nyc3';
        $spacesBucket = $this->getEnvValue('DO_SPACES_BUCKET') ?: 'fl-iris-space';
        $spacesEndpoint = "https://{$spacesRegion}.digitaloceanspaces.com";

        if (!$spacesKey || !$spacesSecret) {
            $io->error('DO Spaces credentials required for large file uploads.');
            $io->text([
                'Add these to your SDK .env file or environment:',
                '  DO_SPACES_KEY=your_key',
                '  DO_SPACES_SECRET=your_secret',
                '',
                'Or add to: ' . $this->getEnvFilePath(),
            ]);
            return null;
        }

        // Generate safe filename
        $safeFilename = time() . '_' . $this->sanitizeFilename($originalFilename);
        $s3Path = "cloud-files/{$safeFilename}";
        $s3Uri = "s3://{$spacesBucket}/{$s3Path}";

        if (!$jsonOutput) {
            $io->text("Large file detected — uploading directly to DO Spaces...");
            $io->newLine();
        }

        // Upload via AWS CLI with progress
        $env = sprintf(
            'AWS_ACCESS_KEY_ID=%s AWS_SECRET_ACCESS_KEY=%s AWS_DEFAULT_REGION=%s',
            escapeshellarg($spacesKey),
            escapeshellarg($spacesSecret),
            escapeshellarg($spacesRegion)
        );

        $cmd = sprintf(
            '%s aws s3 cp %s %s --endpoint-url %s --acl public-read 2>&1',
            $env,
            escapeshellarg($localPath),
            escapeshellarg($s3Uri),
            escapeshellarg($spacesEndpoint)
        );

        // Run with real-time output so user sees progress
        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, null);

        if (!is_resource($process)) {
            $io->error('Failed to start upload process.');
            return null;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $io->error('S3 upload failed:');
            $io->text($stderr ?: $stdout);
            return null;
        }

        if (!$jsonOutput) {
            $io->text('<info>S3 upload complete.</info> Registering file...');
        }

        // Detect MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($localPath) ?: 'application/octet-stream';

        // Register the file via the API
        $expiration = $this->parseExpiration($input->getOption('expires'));
        $registerData = [
            'filepath' => $s3Path,
            'original_filename' => $originalFilename,
            'file_size' => filesize($localPath),
            'filetype' => $mimeType,
            'user_id' => $iris->getConfig()->requireUserId(),
        ];

        if ($title = $input->getOption('title')) {
            $registerData['title'] = $title;
        }
        if ($description = $input->getOption('description')) {
            $registerData['description'] = $description;
        }
        if ($expiration['hours'] > 0 && $expiration['hours'] < 24) {
            $registerData['expires_hours'] = $expiration['hours'];
        } elseif ($expiration['days'] > 0) {
            $registerData['expires_days'] = $expiration['days'];
        }

        $result = $iris->getHttpClient()->post('/api/v1/cloud-files/register', $registerData);

        return $result['data'] ?? $result;
    }

    private function buildUploadOptions(InputInterface $input): array
    {
        $options = [];

        if ($title = $input->getOption('title')) {
            $options['title'] = $title;
        }
        if ($description = $input->getOption('description')) {
            $options['description'] = $description;
        }
        if ($bloqId = $input->getOption('bloq')) {
            $options['bloq_id'] = (int) $bloqId;
        }

        $expiration = $this->parseExpiration($input->getOption('expires'));
        if ($expiration['days'] > 0) {
            $options['expires_days'] = $expiration['days'];
        }

        return $options;
    }

    /**
     * Parse flexible expiration input.
     * Accepts: 1d, 1day, 1days, "1 day", 12h, 12hours, 2w, 2weeks, 90, 0, never, permanent
     */
    private function parseExpiration(string $input): array
    {
        $input = strtolower(trim($input));

        // Permanent / no expiration
        if (in_array($input, ['0', 'never', 'permanent', 'none', ''])) {
            return ['days' => 0, 'hours' => 0, 'label' => 'Never (permanent)'];
        }

        // Try to parse number + unit
        if (preg_match('/^(\d+)\s*(h|hr|hrs|hour|hours|d|day|days|w|wk|wks|week|weeks|m|mo|mos|month|months|y|yr|yrs|year|years)?$/', $input, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2] ?? 'd'; // Default to days

            switch ($unit) {
                case 'h':
                case 'hr':
                case 'hrs':
                case 'hour':
                case 'hours':
                    return [
                        'days' => (int) ceil($value / 24),
                        'hours' => $value,
                        'label' => "{$value} hour" . ($value !== 1 ? 's' : ''),
                    ];

                case 'w':
                case 'wk':
                case 'wks':
                case 'week':
                case 'weeks':
                    $days = $value * 7;
                    return ['days' => $days, 'hours' => 0, 'label' => "{$value} week" . ($value !== 1 ? 's' : '')];

                case 'm':
                case 'mo':
                case 'mos':
                case 'month':
                case 'months':
                    $days = $value * 30;
                    return ['days' => $days, 'hours' => 0, 'label' => "{$value} month" . ($value !== 1 ? 's' : '')];

                case 'y':
                case 'yr':
                case 'yrs':
                case 'year':
                case 'years':
                    $days = $value * 365;
                    return ['days' => $days, 'hours' => 0, 'label' => "{$value} year" . ($value !== 1 ? 's' : '')];

                default: // d, day, days, or bare number
                    return ['days' => $value, 'hours' => 0, 'label' => "{$value} day" . ($value !== 1 ? 's' : '')];
            }
        }

        // Fallback: try as plain integer (days)
        if (is_numeric($input)) {
            $value = (int) $input;
            return ['days' => $value, 'hours' => 0, 'label' => "{$value} day" . ($value !== 1 ? 's' : '')];
        }

        // Unrecognized — default to 180 days
        return ['days' => 180, 'hours' => 0, 'label' => '180 days (default)'];
    }

    private function getEnvValue(string $key): ?string
    {
        // Check environment first
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        // Check SDK .env file
        $envFile = $this->getEnvFilePath();
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with($line, '#')) continue;
                if (str_contains($line, '=')) {
                    [$envKey, $envVal] = explode('=', $line, 2);
                    if (trim($envKey) === $key) {
                        return trim($envVal);
                    }
                }
            }
        }

        // Check fl-api .env as fallback
        $flApiEnv = dirname(__DIR__, 5) . '/fl-api/.env';
        if (file_exists($flApiEnv)) {
            $lines = file($flApiEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with($line, '#')) continue;
                if (str_contains($line, '=')) {
                    [$envKey, $envVal] = explode('=', $line, 2);
                    if (trim($envKey) === $key) {
                        return trim($envVal);
                    }
                }
            }
        }

        return null;
    }

    private function getEnvFilePath(): string
    {
        return dirname(__DIR__, 3) . '/.env';
    }

    private function sanitizeFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        // Replace problematic characters
        $name = str_replace([' ', ':', '\\', '/', '(', ')', '&', '#', '@', '!'], '-', $name);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '', $name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '-');

        return $ext ? "{$name}.{$ext}" : $name;
    }

    private function downloadFromUrl(string $url): string
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
        $tempFile = sys_get_temp_dir() . '/iris_cloud_upload_' . uniqid() . ($extension ? ".{$extension}" : '');

        $context = stream_context_create([
            'http' => [
                'timeout' => 120,
                'user_agent' => 'IRIS-CLI/1.0',
                'follow_location' => true,
            ],
        ]);

        $content = file_get_contents($url, false, $context);
        if ($content === false) {
            throw new \RuntimeException("Failed to download from URL: {$url}");
        }

        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 1) . ' ' . $units[$i];
    }
}
