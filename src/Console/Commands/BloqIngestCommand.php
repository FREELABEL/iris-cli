<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * Start bulk file ingestion from cloud storage into a BLOQ.
 *
 * Usage:
 *   iris bloq:ingest <bloq_id> <source> <path> [options]
 *   iris bloq:ingest 40 dropbox "/Engineering Projects" --recursive
 *   iris bloq:ingest 40 google_drive "1a2b3c4d5e" --file-types=pdf,docx,txt
 */
class BloqIngestCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('bloq:ingest')
            ->setDescription('Start bulk file ingestion from cloud storage')
            ->setHelp(<<<HELP
Start bulk file ingestion from cloud storage (Dropbox, Google Drive) into a BLOQ knowledge base.

<info>Examples:</info>
  iris bloq:ingest 40 dropbox "/Engineering Projects"
  iris bloq:ingest 40 dropbox "/Research" --recursive
  iris bloq:ingest 40 google_drive "1a2b3c4d5e" --file-types=pdf,docx,txt
  iris bloq:ingest 40 dropbox "/Docs" --list-name="Imported Files" --wait
  iris bloq:ingest 40 dropbox "/Medical" --include-images --image-detail=high

<info>Supported Sources:</info>
  dropbox       Dropbox folders (path starts with /)
  google_drive  Google Drive folders (use folder ID)

<info>Supported File Types (27+):</info>
  Documents: pdf, docx, txt, md, rtf
  Data: csv, json, xml
  Code: py, js, ts, php, java, go, rb, cpp, h
  Spreadsheets: xlsx, xls
  Images: jpg, jpeg, png, gif, bmp, webp, tiff (requires --include-images)

<info>Image Processing:</info>
  Use --include-images to enable OCR and analysis for images via GPT-4 Vision.
  Cost: ~$0.02 per 10 images with high detail level (gpt-4o-mini).
  Detail levels: low (~170 tokens/image), high (~765 tokens/image), auto

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addArgument('bloq_id', InputArgument::REQUIRED, 'BLOQ ID to ingest files into')
            ->addArgument('source', InputArgument::REQUIRED, 'Source type: dropbox, google_drive')
            ->addArgument('path', InputArgument::REQUIRED, 'Folder path (Dropbox) or folder ID (Google Drive)')
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Include subfolders')
            ->addOption('file-types', 't', InputOption::VALUE_REQUIRED, 'Comma-separated file types (e.g., pdf,docx,txt)')
            ->addOption('list-name', 'l', InputOption::VALUE_REQUIRED, 'Name for created BloqList', 'Imported Files')
            ->addOption('include-images', 'i', InputOption::VALUE_NONE, 'Enable image processing with GPT-4 Vision (cost: ~$0.02 per 10 images)')
            ->addOption('image-detail', null, InputOption::VALUE_REQUIRED, 'Image detail level: low, high, auto', 'high')
            ->addOption('wait', 'w', InputOption::VALUE_NONE, 'Wait for ingestion to complete')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Load credentials
        $store = new CredentialStore();
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

        if (!$apiKey || !$userId) {
            $io->error('Missing API credentials. Run "iris setup" first.');
            return Command::FAILURE;
        }

        try {
            $iris = new IRIS([
                'api_key' => $apiKey,
                'user_id' => (int) $userId,
            ]);

            $bloqId = (int) $input->getArgument('bloq_id');
            $source = $input->getArgument('source');
            $path = $input->getArgument('path');

            // Validate source
            if (!in_array($source, ['dropbox', 'google_drive'])) {
                $io->error('Invalid source. Must be: dropbox or google_drive');
                return Command::FAILURE;
            }

            // Build options
            $options = [
                'source' => $source,
                'path' => $path,
                'recursive' => $input->getOption('recursive'),
                'list_name' => $input->getOption('list-name'),
            ];

            // Parse file types
            if ($fileTypes = $input->getOption('file-types')) {
                $options['file_types'] = array_map('trim', explode(',', $fileTypes));
            }
            
            // Add image processing options
            if ($input->getOption('include-images')) {
                $options['include_images'] = true;
                $options['image_detail_level'] = $input->getOption('image-detail') ?? 'high';
            }

            $jsonOutput = $input->getOption('json');
            $shouldWait = $input->getOption('wait');

            if (!$jsonOutput) {
                $io->title('📥 Starting File Ingestion');
                $io->text([
                    "BLOQ ID: <info>{$bloqId}</info>",
                    "Source: <info>{$source}</info>",
                    "Path: <info>{$path}</info>",
                    "Recursive: <info>" . ($options['recursive'] ? 'Yes' : 'No') . "</info>",
                ]);
                
                if (isset($options['file_types'])) {
                    $io->text("File types: <info>" . implode(', ', $options['file_types']) . "</info>");
                }
                
                if (isset($options['include_images']) && $options['include_images']) {
                    $io->text("Image processing: <info>Enabled</info> (detail: {$options['image_detail_level']})");
                    $io->text("<comment>Note: Image processing uses GPT-4 Vision (~$0.02 per 10 images)</comment>");
                }
                
                $io->newLine();
            }

            // Start ingestion
            $job = $iris->bloqs->ingestFolder($bloqId, $options);

            if ($jsonOutput) {
                $output->writeln(json_encode($job, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            $io->success("✓ Ingestion job started");
            $io->text([
                "Job ID: <info>{$job['job_id']}</info>",
                "Status: <info>{$job['status']}</info>",
            ]);
            $io->newLine();

            // Wait for completion if requested
            if ($shouldWait) {
                $io->text('⏳ Waiting for ingestion to complete...');
                $io->newLine();

                $progressBar = null;
                $lastProgress = 0;

                $final = $iris->bloqs->waitForIngestion(
                    $job['job_id'],
                    function($status) use ($io, &$progressBar, &$lastProgress, $output) {
                        $progress = $status['progress_percent'] ?? 0;
                        $current = $status['current_file'] ?? '';
                        $processed = $status['processed_files'] ?? 0;
                        $total = $status['total_files'] ?? 0;
                        
                        // Initialize progress bar on first callback
                        if ($progressBar === null && $total > 0) {
                            $progressBar = $io->createProgressBar($total);
                            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
                            $progressBar->setMessage('Starting...');
                            $progressBar->start();
                        }
                        
                        if ($progressBar !== null) {
                            $progressBar->setProgress($processed);
                            $progressBar->setMessage($this->truncate($current, 50));
                        }
                        
                        $lastProgress = $progress;
                    }
                );

                if ($progressBar !== null) {
                    $progressBar->finish();
                    $io->newLine(2);
                }

                // Show final results
                $status = $final['status'];
                $successful = $final['successful_files'];
                $failed = $final['failed_files'];
                $total = $final['total_files'];

                if ($status === 'completed') {
                    $io->success("✓ Ingestion completed successfully!");
                } elseif ($status === 'partial') {
                    $io->warning("⚠ Ingestion completed with some failures");
                } else {
                    $io->error("✗ Ingestion failed");
                }

                $io->definitionList(
                    ['Total Files' => $total],
                    ['Successful' => "<info>{$successful}</info>"],
                    ['Failed' => $failed > 0 ? "<error>{$failed}</error>" : '0']
                );
                
                // Show cost tracking if images were processed
                if (isset($final['cost_tracking']) && $final['cost_tracking']['image_files_processed'] > 0) {
                    $costInfo = $final['cost_tracking'];
                    $io->newLine();
                    $io->section('💰 Cost Tracking');
                    $io->definitionList(
                        ['Images Processed' => "<info>{$costInfo['image_files_processed']}</info>"],
                        ['Vision API Calls' => "{$costInfo['vision_api_calls']}"],
                        ['Estimated Cost' => "<info>$" . number_format($costInfo['estimated_cost_usd'], 4) . " USD</info>"]
                    );
                }

                // Show error log if failures occurred
                if ($failed > 0 && !empty($final['error_log'])) {
                    $io->newLine();
                    $io->section('⚠ Errors');
                    
                    $errorCount = count($final['error_log']);
                    $displayCount = min($errorCount, 5);
                    
                    foreach (array_slice($final['error_log'], 0, $displayCount) as $error) {
                        $io->text("• <comment>{$error['file']}</comment>: {$error['error']}");
                    }
                    
                    if ($errorCount > $displayCount) {
                        $remaining = $errorCount - $displayCount;
                        $io->text("<fg=gray>... and {$remaining} more errors</>");
                    }
                }

                return $status === 'completed' ? Command::SUCCESS : Command::FAILURE;
            } else {
                $io->text([
                    'Monitor progress with:',
                    "  <info>iris bloq:ingestion-status {$job['job_id']}</info>",
                ]);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }
}
