<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * Add content or files to a knowledge base.
 *
 * Usage:
 *   iris memory:add <id> --file="path/to/file.pdf"
 *   iris memory:add <id> --file="docs/*.pdf"
 *   iris memory:add <id> --text="Content to add" --title="Note Title"
 */
class MemoryAddCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('memory:add')
            ->setDescription('Add content or files to a knowledge base')
            ->setHelp(<<<HELP
Add files or text content to an existing knowledge base.

<info>Examples:</info>
  # Add a single file
  iris memory:add 123 --file="document.pdf"
  
  # Add multiple files with glob pattern
  iris memory:add 123 --file="docs/*.pdf"
  
  # Add text content
  iris memory:add 123 --text="Important note" --title="Meeting Notes"
  
  # Add content from stdin
  echo "Content" | iris memory:add 123 --title="From Pipe"

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addArgument('id', InputArgument::REQUIRED, 'Knowledge base ID')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'File(s) to upload (supports glob patterns)')
            ->addOption('text', 't', InputOption::VALUE_REQUIRED, 'Text content to add')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Title for text content')
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
            $filePatterns = $input->getOption('file');
            $textContent = $input->getOption('text');
            $title = $input->getOption('title');

            // Validate knowledge base exists
            $bloq = $iris->bloqs->get($bloqId);
            $io->title('📚 Adding to: ' . ($bloq->title ?? 'Untitled'));

            $addedCount = 0;

            // Handle file uploads
            if (!empty($filePatterns)) {
                $files = $this->expandFilePatterns($filePatterns);
                
                if (empty($files)) {
                    $io->warning('No files matched the specified patterns.');
                } else {
                    $addedCount += $this->uploadFiles($io, $output, $iris, $bloqId, $files);
                }
            }

            // Handle text content
            if ($textContent !== null) {
                $addedCount += $this->addTextContent($io, $iris, $bloqId, $textContent, $title);
            }

            // Handle stdin if no other content provided
            if (empty($filePatterns) && $textContent === null && !stream_isatty(STDIN)) {
                $stdinContent = stream_get_contents(STDIN);
                if (!empty($stdinContent)) {
                    $addedCount += $this->addTextContent($io, $iris, $bloqId, $stdinContent, $title);
                }
            }

            if ($addedCount === 0) {
                $io->warning('No content was added. Use --file or --text options.');
                return Command::FAILURE;
            }

            $io->success(sprintf('Added %d item(s) to knowledge base!', $addedCount));
            $io->text('<fg=gray>View with: iris memory:show ' . $bloqId . '</>');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function expandFilePatterns(array $patterns): array
    {
        $files = [];
        
        foreach ($patterns as $pattern) {
            $matches = glob($pattern);
            if ($matches !== false) {
                foreach ($matches as $file) {
                    if (is_file($file)) {
                        $files[] = $file;
                    }
                }
            }
        }
        
        return array_unique($files);
    }

    private function uploadFiles(SymfonyStyle $io, OutputInterface $output, IRIS $iris, int $bloqId, array $files): int
    {
        $io->section('📎 Uploading Files');
        
        $progressBar = new ProgressBar($output, count($files));
        $progressBar->setFormat('very_verbose');
        $progressBar->start();

        $successCount = 0;
        $failures = [];

        foreach ($files as $file) {
            try {
                $iris->bloqs->uploadFile($bloqId, $file);
                $successCount++;
            } catch (\Exception $e) {
                $failures[] = [
                    'file' => basename($file),
                    'error' => $e->getMessage(),
                ];
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        if ($successCount > 0) {
            $io->text(sprintf('<fg=green>✓ Uploaded %d file(s)</>', $successCount));
        }

        if (!empty($failures)) {
            $io->warning('Some files failed to upload:');
            foreach ($failures as $failure) {
                $io->text(sprintf('  • %s: %s', $failure['file'], $failure['error']));
            }
        }

        return $successCount;
    }

    private function addTextContent(SymfonyStyle $io, IRIS $iris, int $bloqId, string $content, ?string $title): int
    {
        $io->section('📝 Adding Text Content');

        if (!$title) {
            $title = 'Note ' . date('Y-m-d H:i:s');
        }

        try {
            $iris->bloqs->addContent($bloqId, [
                'title' => $title,
                'content' => $content,
            ]);

            $io->text(sprintf('<fg=green>✓ Added text content: "%s"</>', $title));
            return 1;

        } catch (\Exception $e) {
            $io->error('Failed to add text content: ' . $e->getMessage());
            return 0;
        }
    }
}
