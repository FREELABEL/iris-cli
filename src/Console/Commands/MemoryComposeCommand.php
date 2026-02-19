<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\ProgressBar;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * AI-powered wizard to create optimized knowledge bases.
 *
 * Usage:
 *   iris memory:compose
 *   iris memory:compose --title="Customer Documentation"
 */
class MemoryComposeCommand extends Command
{
    private const COLORS = [
        'blue' => '🔵 Blue',
        'green' => '🟢 Green',
        'red' => '🔴 Red',
        'yellow' => '🟡 Yellow',
        'purple' => '🟣 Purple',
        'orange' => '🟠 Orange',
        'pink' => '🩷 Pink',
        'gray' => '⚫ Gray',
    ];

    protected function configure(): void
    {
        $this
            ->setName('memory:compose')
            ->setDescription('AI-powered wizard to create a knowledge base')
            ->setHelp(<<<HELP
Interactive wizard that helps you create an optimized knowledge base
using AI to suggest the best structure and organization.

<info>Examples:</info>
  iris memory:compose
  iris memory:compose --title="Product Documentation"
  iris memory:compose --auto --files="docs/*.pdf"

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Knowledge base title')
            ->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Description')
            ->addOption('files', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Files to add (glob patterns)')
            ->addOption('auto', null, InputOption::VALUE_NONE, 'Skip AI suggestions (fast mode)')
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

            $io->title('🧠 Knowledge Base Composer');
            $io->text('Create an optimized knowledge base with AI assistance.');
            $io->newLine();

            // Step 1: Get title
            $title = $input->getOption('title');
            if (!$title) {
                $question = new Question('What should we call this knowledge base? ');
                $question->setValidator(function ($answer) {
                    if (empty(trim($answer))) {
                        throw new \RuntimeException('Title cannot be empty.');
                    }
                    return trim($answer);
                });
                $title = $io->askQuestion($question);
            }

            // Step 2: Get purpose/description
            $description = $input->getOption('description');
            if (!$description) {
                $io->newLine();
                $io->section('📝 Purpose');
                $io->text('Help me understand what this knowledge base is for.');
                $question = new Question('Describe the purpose (e.g., "Customer support docs", "Product specs"): ');
                $description = $io->askQuestion($question) ?: '';
            }

            // Step 3: AI suggestions (unless --auto)
            $skipAI = $input->getOption('auto');
            $suggestions = null;
            
            if (!$skipAI && !empty($description)) {
                $io->newLine();
                $io->section('🤖 AI Analysis');
                $io->text('Let me analyze and suggest optimizations...');
                
                try {
                    $suggestions = $this->getAISuggestions($iris, $title, $description);
                    
                    if ($suggestions) {
                        $io->newLine();
                        $io->text('<fg=cyan>AI Suggestions:</>');
                        $io->listing([
                            'Recommended structure: ' . ($suggestions['structure'] ?? 'N/A'),
                            'Best practices: ' . ($suggestions['practices'] ?? 'N/A'),
                            'Content tips: ' . ($suggestions['tips'] ?? 'N/A'),
                        ]);
                        
                        // Update description with AI enhancement if available
                        if (!empty($suggestions['enhanced_description'])) {
                            $description = $suggestions['enhanced_description'];
                        }
                    }
                } catch (\Exception $e) {
                    $io->warning('AI suggestions unavailable: ' . $e->getMessage());
                }
            }

            // Step 4: Choose color
            $io->newLine();
            $io->section('🎨 Visual Identity');
            $colorQuestion = new ChoiceQuestion(
                'Choose a color for this knowledge base:',
                self::COLORS,
                'blue'
            );
            $colorKey = array_search($io->askQuestion($colorQuestion), self::COLORS);
            $color = is_string($colorKey) ? $colorKey : 'blue';

            // Step 5: Create knowledge base
            $io->newLine();
            $io->section('✨ Creating Knowledge Base');
            
            $bloq = $iris->bloqs->create($title, [
                'description' => $description,
                'color' => $color,
            ]);

            $io->success(sprintf('Created knowledge base #%d: %s', $bloq->id, $title));

            // Step 6: Add files if provided
            $filePatterns = $input->getOption('files');
            if (!empty($filePatterns)) {
                $files = $this->expandFilePatterns($filePatterns);
                if (!empty($files)) {
                    $this->uploadFiles($io, $output, $iris, $bloq->id, $files);
                }
            } else {
                // Ask if they want to add files now
                $io->newLine();
                if ($io->confirm('Would you like to add files now?', false)) {
                    $question = new Question('Enter file path or glob pattern (e.g., docs/*.pdf): ');
                    $pattern = $io->askQuestion($question);
                    
                    if ($pattern) {
                        $files = $this->expandFilePatterns([$pattern]);
                        if (!empty($files)) {
                            $this->uploadFiles($io, $output, $iris, $bloq->id, $files);
                        } else {
                            $io->warning('No files matched the pattern.');
                        }
                    }
                }
            }

            // Final tips
            $io->newLine();
            $io->section('🚀 Next Steps');
            $io->text([
                sprintf('View details: <fg=yellow>iris memory:show %d</>', $bloq->id),
                sprintf('Add files: <fg=yellow>iris memory:add %d --file="path/to/file.pdf"</>', $bloq->id),
                sprintf('Add text: <fg=yellow>iris memory:add %d --text="Content" --title="Note"</>', $bloq->id),
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function getAISuggestions(IRIS $iris, string $title, string $description): ?array
    {
        $prompt = <<<PROMPT
I'm creating a knowledge base called "$title" with the following purpose:

$description

Please provide brief, actionable suggestions for:
1. Structure - How should I organize the content?
2. Best Practices - What should I keep in mind?
3. Content Tips - What specific content would be most valuable?
4. Enhanced Description - A refined, clear description (1-2 sentences)

Format as JSON with keys: structure, practices, tips, enhanced_description
PROMPT;

        try {
            $response = $iris->chat->execute([
                'query' => $prompt,
                'options' => [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.7,
                ],
            ]);

            if (isset($response['response'])) {
                $content = $response['response'];
                
                // Try to extract JSON from response
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $suggestions = json_decode($matches[0], true);
                    if ($suggestions) {
                        return $suggestions;
                    }
                }
            }
        } catch (\Exception) {
            // Silently fail - suggestions are optional
        }

        return null;
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

    private function uploadFiles(SymfonyStyle $io, OutputInterface $output, IRIS $iris, int $bloqId, array $files): void
    {
        $io->newLine();
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
    }
}
