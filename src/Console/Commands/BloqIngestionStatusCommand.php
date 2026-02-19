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
 * Get the status of a BLOQ ingestion job.
 *
 * Usage:
 *   iris bloq:ingestion-status <job_id>
 *   iris bloq:ingestion-status 123 --watch
 */
class BloqIngestionStatusCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('bloq:ingestion-status')
            ->setDescription('Get ingestion job status')
            ->setHelp(<<<HELP
Get the real-time status of a BLOQ ingestion job.

<info>Examples:</info>
  iris bloq:ingestion-status 123
  iris bloq:ingestion-status 123 --watch
  iris bloq:ingestion-status 123 --json

<info>Status Values:</info>
  pending      Job queued, not started yet
  processing   Currently ingesting files
  completed    All files processed successfully
  partial      Completed with some failures
  failed       Job failed completely
  cancelled    Job was cancelled

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addArgument('job_id', InputArgument::REQUIRED, 'Ingestion job ID')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Watch progress in real-time (polls every 2 seconds)')
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

            $jobId = (int) $input->getArgument('job_id');
            $shouldWatch = $input->getOption('watch');
            $jsonOutput = $input->getOption('json');

            if ($shouldWatch && !$jsonOutput) {
                return $this->watchProgress($iris, $io, $jobId);
            }

            // Get status once
            $status = $iris->bloqs->getIngestionStatus($jobId);

            if ($jsonOutput) {
                $output->writeln(json_encode($status, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            $this->displayStatus($io, $status);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function watchProgress(IRIS $iris, SymfonyStyle $io, int $jobId): int
    {
        $io->title('⏳ Watching Ingestion Progress');
        
        $lastStatus = '';
        $progressBar = null;

        while (true) {
            try {
                $status = $iris->bloqs->getIngestionStatus($jobId);
                
                $currentStatus = $status['status'];
                $progress = $status['progress_percent'] ?? 0;
                $processed = $status['processed_files'] ?? 0;
                $total = $status['total_files'] ?? 0;
                $current = $status['current_file'] ?? '';

                // Initialize progress bar if we have total files
                if ($progressBar === null && $total > 0) {
                    $progressBar = $io->createProgressBar($total);
                    $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
                    $progressBar->setMessage('Starting...');
                    $progressBar->start();
                }

                // Update progress bar
                if ($progressBar !== null) {
                    $progressBar->setProgress($processed);
                    $progressBar->setMessage($this->truncate($current, 50));
                }

                // Check if completed
                if (in_array($currentStatus, ['completed', 'partial', 'failed', 'cancelled'])) {
                    if ($progressBar !== null) {
                        $progressBar->finish();
                        $io->newLine(2);
                    }

                    $this->displayStatus($io, $status);
                    
                    return $currentStatus === 'completed' ? Command::SUCCESS : Command::FAILURE;
                }

                // Sleep before next poll
                sleep(2);

            } catch (\Exception $e) {
                if ($progressBar !== null) {
                    $progressBar->clear();
                }
                $io->error('Error watching progress: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }
    }

    private function displayStatus(SymfonyStyle $io, array $status): void
    {
        $io->title('📊 Ingestion Job Status');

        $jobStatus = $status['status'];
        $statusDisplay = $this->formatStatus($jobStatus);

        $io->definitionList(
            ['Job ID' => $status['job_id']],
            ['Status' => $statusDisplay],
            ['Total Files' => $status['total_files'] ?? 0],
            ['Processed' => $status['processed_files'] ?? 0],
            ['Successful' => "<info>{$status['successful_files']}</info>"],
            ['Failed' => $status['failed_files'] > 0 ? "<error>{$status['failed_files']}</error>" : '0'],
            ['Progress' => ($status['progress_percent'] ?? 0) . '%']
        );

        // Show processing details if active
        if ($jobStatus === 'processing') {
            $io->newLine();
            $io->section('📁 Current Processing');
            
            $io->text([
                "File: <comment>{$status['current_file']}</comment>",
                "Speed: <info>{$status['processing_speed']} files/min</info>",
                "ETA: <info>{$status['estimated_remaining']}</info>",
            ]);
        }
        
        // Show cost tracking if images were processed
        if (isset($status['cost_tracking']) && $status['cost_tracking']['image_files_processed'] > 0) {
            $costInfo = $status['cost_tracking'];
            $io->newLine();
            $io->section('💰 Cost Tracking');
            $io->definitionList(
                ['Images Processed' => "<info>{$costInfo['image_files_processed']}</info>"],
                ['Vision API Calls' => "{$costInfo['vision_api_calls']}"],
                ['Estimated Cost' => "<info>$" . number_format($costInfo['estimated_cost_usd'], 4) . " USD</info>"]
            );
        }

        // Show errors if any
        if (!empty($status['error_log'])) {
            $io->newLine();
            $io->section('⚠ Errors');
            
            $errorCount = count($status['error_log']);
            $displayCount = min($errorCount, 5);
            
            foreach (array_slice($status['error_log'], 0, $displayCount) as $error) {
                $io->text("• <comment>{$error['file']}</comment>: {$error['error']}");
            }
            
            if ($errorCount > $displayCount) {
                $remaining = $errorCount - $displayCount;
                $io->text("<fg=gray>... and {$remaining} more errors</>");
            }
        }
    }

    private function formatStatus(string $status): string
    {
        $statusMap = [
            'pending' => '<comment>⏱ Pending</comment>',
            'processing' => '<info>⚙ Processing</info>',
            'completed' => '<info>✓ Completed</info>',
            'partial' => '<comment>⚠ Partial Success</comment>',
            'failed' => '<error>✗ Failed</error>',
            'cancelled' => '<fg=gray>✗ Cancelled</>',
        ];

        return $statusMap[$status] ?? $status;
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }
}
