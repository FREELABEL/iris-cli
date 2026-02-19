<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * Show detailed information about a knowledge base.
 *
 * Usage:
 *   iris memory:show <id>
 *   iris memory:show <id> --files
 *   iris memory:show <id> --json
 */
class MemoryShowCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('memory:show')
            ->setDescription('Show detailed information about a knowledge base')
            ->setHelp(<<<HELP
Display detailed information about a specific knowledge base including
its contents, files, and metadata.

<info>Examples:</info>
  iris memory:show 123
  iris memory:show 123 --files
  iris memory:show 123 --json

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addArgument('id', InputArgument::REQUIRED, 'Knowledge base ID')
            ->addOption('files', 'f', InputOption::VALUE_NONE, 'Show files only')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Load credentials
        $store = new CredentialStore();
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

        if (!$apiKey || !$userId) {
            $io->error('Missing API credentials. Run "iris config setup" first.');
            return Command::FAILURE;
        }

        try {
            $iris = new IRIS([
                'api_key' => $apiKey,
                'user_id' => (int) $userId,
            ]);

            $bloqId = (int) $input->getArgument('id');
            $showFilesOnly = $input->getOption('files');
            $jsonOutput = $input->getOption('json');

            // Get knowledge base details
            $bloq = $iris->bloqs->get($bloqId);

            if ($jsonOutput) {
                $data = [
                    'bloq' => $bloq,
                    'content' => $iris->bloqs->getContent($bloqId),
                    'files' => $iris->bloqs->getBloqFiles($bloqId),
                ];
                $output->writeln(json_encode($data, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            if ($showFilesOnly) {
                $this->displayFiles($io, $output, $iris, $bloqId, $bloq);
                return Command::SUCCESS;
            }

            // Display full details
            $this->displayFullDetails($io, $output, $iris, $bloqId, $bloq);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function displayFullDetails(SymfonyStyle $io, OutputInterface $output, IRIS $iris, int $bloqId, $bloq): void
    {
        $io->title('📚 Knowledge Base Details');

        // Basic info
        $io->section('Basic Information');
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $bloq->id],
                ['Title', $bloq->title ?? 'Untitled'],
                ['Description', $bloq->description ?? 'No description'],
                ['Color', $bloq->color ?? 'Default'],
                ['Items', $bloq->itemCount ?? 0],
                ['Created', $this->formatDate($bloq->createdAt)],
                ['Updated', $this->formatDate($bloq->updatedAt)],
            ]
        );

        // Content items
        $io->section('Content Items');
        $content = $iris->bloqs->getContent($bloqId);
        
        if (empty($content)) {
            $io->text('<fg=gray>No content items found</>');
        } else {
            $table = new Table($output);
            $table->setHeaders(['ID', 'Title', 'Type', 'Size']);
            
            foreach ($content as $item) {
                $table->addRow([
                    $item['id'] ?? 'N/A',
                    $this->truncate($item['title'] ?? 'Untitled', 50),
                    $item['type'] ?? 'text',
                    isset($item['content']) ? $this->formatSize(strlen($item['content'])) : 'N/A',
                ]);
            }
            
            $table->render();
        }

        // Files
        $io->section('Files');
        $files = $iris->bloqs->getBloqFiles($bloqId);
        
        if (empty($files)) {
            $io->text('<fg=gray>No files uploaded</>');
        } else {
            $table = new Table($output);
            $table->setHeaders(['ID', 'Filename', 'Size', 'Uploaded']);
            
            foreach ($files as $file) {
                $table->addRow([
                    $file['id'] ?? 'N/A',
                    $file['original_filename'] ?? $file['filename'] ?? 'Unknown',
                    $this->formatSize($file['size'] ?? 0),
                    $this->formatDate($file['created_at'] ?? null),
                ]);
            }
            
            $table->render();
        }

        $io->text('');
        $io->text('<fg=gray>Tip: Add content with "iris memory:add ' . $bloqId . '"</>');
    }

    private function displayFiles(SymfonyStyle $io, OutputInterface $output, IRIS $iris, int $bloqId, $bloq): void
    {
        $io->title('📎 Files in: ' . ($bloq->title ?? 'Untitled'));

        $files = $iris->bloqs->getBloqFiles($bloqId);
        
        if (empty($files)) {
            $io->warning('No files found in this knowledge base.');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Filename', 'Size', 'Type', 'Uploaded']);
        
        foreach ($files as $file) {
            $table->addRow([
                $file['id'] ?? 'N/A',
                $file['original_filename'] ?? $file['filename'] ?? 'Unknown',
                $this->formatSize($file['size'] ?? 0),
                $file['mime_type'] ?? 'Unknown',
                $this->formatDate($file['created_at'] ?? null),
            ]);
        }
        
        $table->render();
        
        $io->text('');
        $io->text(sprintf('<fg=gray>Total: %d file(s)</>', count($files)));
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
            return date('M j, Y g:i A', strtotime($date));
        } catch (\Exception) {
            return $date;
        }
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        } else {
            return round($bytes / 1073741824, 2) . ' GB';
        }
    }
}
