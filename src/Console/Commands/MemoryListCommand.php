<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * List all knowledge bases (bloqs).
 *
 * Usage:
 *   iris memory:list
 *   iris memory:list --search="customer"
 */
class MemoryListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('memory:list')
            ->setDescription('List all knowledge bases')
            ->setHelp(<<<HELP
List all your knowledge bases (bloqs) with statistics.

<info>Examples:</info>
  iris memory:list
  iris memory:list --search="customer"
  iris memory:list --json

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Search knowledge bases')
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

            $search = $input->getOption('search');
            $options = [];
            
            if ($search) {
                $options['search'] = $search;
            }

            $bloqs = $iris->bloqs->list($options);
            $jsonOutput = $input->getOption('json');

            if ($jsonOutput) {
                $output->writeln(json_encode($bloqs->toArray(), JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            if ($bloqs->isEmpty()) {
                $io->warning('No knowledge bases found. Create one with: iris memory:compose');
                return Command::SUCCESS;
            }

            // Display header
            $io->title('📚 Knowledge Bases');

            // Create table
            $table = new Table($output);
            $table->setHeaders(['ID', 'Title', 'Description', 'Items', 'Updated']);

            foreach ($bloqs as $bloq) {
                $table->addRow([
                    $bloq->id,
                    $bloq->title ?? 'Untitled',
                    $this->truncate($bloq->description ?? '', 40),
                    $bloq->itemCount ?? 0,
                    $this->formatDate($bloq->updatedAt ?? $bloq->createdAt),
                ]);
            }

            $table->render();

            // Footer
            $io->text('');
            $io->text(sprintf('<fg=gray>Total: %d knowledge base(s)</>', $bloqs->count()));
            $io->text('<fg=gray>Tip: Use "iris memory:show <id>" to view contents</>');

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
