<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * Cancel a running ingestion job.
 *
 * Usage:
 *   iris bloq:cancel-ingestion <job_id>
 *   iris bloq:cancel-ingestion 123 --force
 */
class BloqCancelIngestionCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('bloq:cancel-ingestion')
            ->setDescription('Cancel a running ingestion job')
            ->setHelp(<<<HELP
Cancel a running BLOQ ingestion job.

<info>Examples:</info>
  iris bloq:cancel-ingestion 123
  iris bloq:cancel-ingestion 123 --force
  iris bloq:cancel-ingestion 123 --json

<info>Notes:</info>
  • Only jobs with status 'pending' or 'processing' can be cancelled
  • Files already processed will remain in the BLOQ
  • Cancellation may take a few seconds to take effect

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addArgument('job_id', InputArgument::REQUIRED, 'Ingestion job ID to cancel')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt')
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
            $jsonOutput = $input->getOption('json');
            $force = $input->getOption('force');

            // Get current job status
            $status = $iris->bloqs->getIngestionStatus($jobId);
            $currentStatus = $status['status'];

            // Check if job can be cancelled
            if (!in_array($currentStatus, ['pending', 'processing'])) {
                if ($jsonOutput) {
                    $output->writeln(json_encode([
                        'success' => false,
                        'message' => "Cannot cancel job with status '{$currentStatus}'"
                    ], JSON_PRETTY_PRINT));
                } else {
                    $io->error("Cannot cancel job with status '{$currentStatus}'");
                    $io->text('Only pending or processing jobs can be cancelled.');
                }
                return Command::FAILURE;
            }

            // Show job info and confirm
            if (!$force && !$jsonOutput) {
                $io->title('⚠ Cancel Ingestion Job');
                
                $io->definitionList(
                    ['Job ID' => $jobId],
                    ['Status' => $this->formatStatus($currentStatus)],
                    ['Progress' => ($status['progress_percent'] ?? 0) . '%'],
                    ['Processed' => "{$status['processed_files']}/{$status['total_files']} files"],
                    ['Source' => $status['source_type'] ?? 'N/A'],
                    ['Path' => $status['source_path'] ?? 'N/A']
                );

                $io->newLine();
                $io->warning([
                    'This will cancel the ingestion job.',
                    'Files already processed will remain in the BLOQ.',
                    'This action cannot be undone.',
                ]);
                $io->newLine();

                $helper = $this->getHelper('question');
                $question = new Question('Are you sure you want to cancel? (yes/no) ', 'no');
                $answer = $helper->ask($input, $output, $question);
                
                if (strtolower($answer) !== 'yes') {
                    $io->text('Cancelled.');
                    return Command::SUCCESS;
                }
            }

            // Cancel the job
            $result = $iris->bloqs->cancelIngestionJob($jobId);

            if ($jsonOutput) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            if ($result['success']) {
                $io->success('✓ Ingestion job cancelled successfully');
                
                $io->newLine();
                $io->text([
                    "Files processed before cancellation: <info>{$status['successful_files']}</info>",
                    'These files remain in your BLOQ.',
                ]);
                
                $io->newLine();
                $io->text([
                    'View job details:',
                    "  <info>iris bloq:ingestion-status {$jobId}</info>",
                ]);
            } else {
                $io->error('Failed to cancel job: ' . ($result['message'] ?? 'Unknown error'));
                return Command::FAILURE;
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
}
