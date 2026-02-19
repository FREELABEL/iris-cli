<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * List all ingestion jobs for a BLOQ.
 *
 * Usage:
 *   iris bloq:ingestion-jobs <bloq_id>
 *   iris bloq:ingestion-jobs 40 --status=completed
 */
class BloqIngestionJobsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('bloq:ingestion-jobs')
            ->setDescription('List ingestion jobs for a BLOQ')
            ->setHelp(<<<HELP
List all ingestion jobs for a specific BLOQ knowledge base.

<info>Examples:</info>
  iris bloq:ingestion-jobs 40
  iris bloq:ingestion-jobs 40 --status=processing
  iris bloq:ingestion-jobs 40 --status=completed --limit=20
  iris bloq:ingestion-jobs 40 --json

<info>Status Filters:</info>
  pending      Jobs not started yet
  processing   Currently running jobs
  completed    Successfully completed jobs
  partial      Completed with some failures
  failed       Failed jobs
  cancelled    Cancelled jobs

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addArgument('bloq_id', InputArgument::REQUIRED, 'BLOQ ID')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Filter by status')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of jobs to show', '20')
            ->addOption('page', 'p', InputOption::VALUE_REQUIRED, 'Page number', '1')
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
            $jsonOutput = $input->getOption('json');

            // Build options
            $options = [
                'limit' => (int) $input->getOption('limit'),
                'page' => (int) $input->getOption('page'),
            ];

            if ($status = $input->getOption('status')) {
                $options['status'] = $status;
            }

            // Fetch jobs
            $response = $iris->bloqs->listIngestionJobs($bloqId, $options);

            if ($jsonOutput) {
                $output->writeln(json_encode($response, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            $jobs = $response['jobs'] ?? [];
            $pagination = $response['pagination'] ?? [];

            if (empty($jobs)) {
                $io->warning('No ingestion jobs found for this BLOQ.');
                $io->newLine();
                $io->text([
                    'Start an ingestion job with:',
                    "  <info>iris bloq:ingest {$bloqId} dropbox \"/path\"</info>",
                ]);
                return Command::SUCCESS;
            }

            // Display header
            $io->title('📥 Ingestion Jobs');
            $io->text("BLOQ ID: <info>{$bloqId}</info>");
            $io->newLine();

            // Create table
            $table = new Table($output);
            $table->setHeaders(['ID', 'Source', 'Path', 'Status', 'Files', 'Success', 'Failed', 'Progress', 'Created']);

            foreach ($jobs as $job) {
                $statusDisplay = $this->formatStatus($job['status']);
                $progressPercent = $job['progress_percent'] ?? 0;
                
                $table->addRow([
                    $job['id'],
                    $job['source_type'],
                    $this->truncate($job['source_path'], 25),
                    $statusDisplay,
                    $job['total_files'] ?? 0,
                    $job['successful_files'] ?? 0,
                    $job['failed_files'] > 0 ? "<error>{$job['failed_files']}</error>" : '0',
                    $progressPercent . '%',
                    $this->formatDate($job['created_at'] ?? null),
                ]);
            }

            $table->render();

            // Footer with pagination
            $io->newLine();
            
            if (!empty($pagination)) {
                $currentPage = $pagination['current_page'] ?? 1;
                $totalPages = $pagination['total_pages'] ?? 1;
                $total = $pagination['total'] ?? count($jobs);
                
                $io->text(sprintf(
                    '<fg=gray>Page %d of %d • Total: %d job(s)</>',
                    $currentPage,
                    $totalPages,
                    $total
                ));
                
                if ($currentPage < $totalPages) {
                    $nextPage = $currentPage + 1;
                    $io->text("<fg=gray>Next page: iris bloq:ingestion-jobs {$bloqId} --page={$nextPage}</>");
                }
            } else {
                $io->text(sprintf('<fg=gray>Total: %d job(s)</>', count($jobs)));
            }

            $io->newLine();
            $io->text('<fg=gray>Tip: Use "iris bloq:ingestion-status <job_id>" to view details</>');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function formatStatus(string $status): string
    {
        $statusMap = [
            'pending' => '<comment>⏱ Pending</comment>',
            'processing' => '<info>⚙ Processing</info>',
            'completed' => '<info>✓ Completed</info>',
            'partial' => '<comment>⚠ Partial</comment>',
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

    private function formatDate(?string $date): string
    {
        if (!$date) {
            return 'N/A';
        }

        try {
            $timestamp = strtotime($date);
            $now = time();
            $diff = $now - $timestamp;

            // Less than 1 minute
            if ($diff < 60) {
                return 'Just now';
            }
            // Less than 1 hour
            if ($diff < 3600) {
                $mins = floor($diff / 60);
                return $mins . 'm ago';
            }
            // Less than 1 day
            if ($diff < 86400) {
                $hours = floor($diff / 3600);
                return $hours . 'h ago';
            }
            // Less than 1 week
            if ($diff < 604800) {
                $days = floor($diff / 86400);
                return $days . 'd ago';
            }
            // Otherwise show date
            return date('M j, Y', $timestamp);
        } catch (\Exception $e) {
            return $date;
        }
    }
}
