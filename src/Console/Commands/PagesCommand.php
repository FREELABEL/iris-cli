<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

/**
 * CLI command for managing composable landing pages.
 *
 * Usage:
 *   ./bin/iris pages                                    # List all pages
 *   ./bin/iris pages create                             # Interactive page creation
 *   ./bin/iris pages create --slug=my-page              # Create with options
 *   ./bin/iris pages edit my-page                       # Edit page by slug
 *   ./bin/iris pages publish my-page                    # Publish a page
 *   ./bin/iris pages view my-page                       # View page JSON
 */
class PagesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('pages')
            ->setDescription('Manage composable landing pages')
            ->setHelp(<<<'HELP'
Manage composable landing pages with JSON-based components.

Usage:
  pages                                              List all pages
  pages create                                       Interactive page creation
  pages create --slug=my-page --title="My Page"     Create with options
  pages edit <slug>                                  Edit page by slug
  pages view <slug>                                  View page JSON content
  pages publish <slug>                               Publish a page
  pages unpublish <slug>                             Unpublish (back to draft)
  pages delete <slug>                                Delete a page
  pages duplicate <slug> --new-slug=copy             Duplicate a page
  pages versions <slug>                              View version history
  pages rollback <slug> --version=2                  Rollback to version
  pages components <slug>                            List components
  pages add-component <slug>                         Add a component
  pages update-component <slug> <component-id>       Update a component
  pages remove-component <slug> <component-id>       Remove a component

Examples:
  # List pages
  ./bin/iris pages

  # Create a page interactively
  ./bin/iris pages create

  # Create from template
  ./bin/iris pages create --slug=landing --title="Welcome" --template=landing

  # Publish a page
  ./bin/iris pages publish my-page

  # View page content
  ./bin/iris pages view my-page --json
HELP
            )
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: create, edit, view, publish, unpublish, delete, duplicate, versions, rollback')
            ->addArgument('slug', InputArgument::OPTIONAL, 'Page slug')
            // Common options
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)')
            // Page options
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Page slug')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Page title')
            ->addOption('seo-title', null, InputOption::VALUE_REQUIRED, 'SEO title')
            ->addOption('seo-description', null, InputOption::VALUE_REQUIRED, 'SEO description')
            ->addOption('template', null, InputOption::VALUE_REQUIRED, 'Template: landing, product, about, contact')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Status: draft, published, archived', 'draft')
            ->addOption('new-slug', null, InputOption::VALUE_REQUIRED, 'New slug for duplication')
            ->addOption('page-version', null, InputOption::VALUE_REQUIRED, 'Version number for rollback')
            // Component options
            ->addOption('add-hero', null, InputOption::VALUE_NONE, 'Add a Hero component')
            ->addOption('add-text', null, InputOption::VALUE_NONE, 'Add a TextBlock component')
            ->addOption('add-button', null, InputOption::VALUE_NONE, 'Add a ButtonCTA component')
            ->addOption('component-id', null, InputOption::VALUE_REQUIRED, 'Component ID for update/remove')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Component type: Hero, TextBlock, ButtonCTA')
            ->addOption('position', null, InputOption::VALUE_REQUIRED, 'Position to insert component (0-based index)')
            ->addOption('props', null, InputOption::VALUE_REQUIRED, 'Component props as JSON string');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'list';
        $slug = $input->getArgument('slug') ?? $input->getOption('slug');

        // Get API credentials
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID');

        // Try to load from .env if not provided
        if (!$apiKey || !$userId) {
            try {
                $tempConfig = new \IRIS\SDK\Config([]);
                if (!$apiKey && isset($tempConfig->apiKey)) {
                    $apiKey = $tempConfig->apiKey;
                }
                if (!$userId && isset($tempConfig->userId)) {
                    $userId = $tempConfig->userId;
                }
            } catch (\Exception $e) {
                // Config will throw if api_key not found
            }
        }

        if (!$apiKey || !$userId) {
            $io->error([
                'Missing API credentials.',
                '',
                'Set environment variables or use options:',
                '  IRIS_API_KEY=your-api-key',
                '  IRIS_USER_ID=your-user-id',
            ]);
            return Command::FAILURE;
        }

        // Initialize SDK
        $iris = new IRIS([
            'api_key' => $apiKey,
            'user_id' => (int) $userId,
        ]);

        try {
            switch ($action) {
                case 'list':
                    return $this->listPages($iris, $io, $input);
                
                case 'create':
                    return $this->createPage($iris, $io, $input);
                
                case 'edit':
                    return $this->editPage($iris, $io, $input, $slug);
                
                case 'view':
                    return $this->viewPage($iris, $io, $input, $slug);
                
                case 'publish':
                    return $this->publishPage($iris, $io, $slug);
                
                case 'unpublish':
                    return $this->unpublishPage($iris, $io, $slug);
                
                case 'delete':
                    return $this->deletePage($iris, $io, $slug);
                
                case 'duplicate':
                    return $this->duplicatePage($iris, $io, $input, $slug);
                
                case 'versions':
                    return $this->viewVersions($iris, $io, $slug);
                
                case 'rollback':
                    return $this->rollbackPage($iris, $io, $input, $slug);
                
                case 'components':
                    return $this->listComponents($iris, $io, $slug);
                
                case 'add-component':
                    return $this->addComponent($iris, $io, $input, $slug);
                
                case 'update-component':
                    $componentId = $input->getOption('component-id');
                    return $this->updateComponent($iris, $io, $input, $slug, $componentId);
                
                case 'remove-component':
                    $componentId = $input->getOption('component-id');
                    return $this->removeComponent($iris, $io, $slug, $componentId);
                
                default:
                    $io->error("Unknown action: {$action}");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function listPages(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $io->title('Pages');

        $response = $iris->pages->list();
        $pages = $response['data'] ?? [];

        if (empty($pages)) {
            $io->info('No pages found.');
            return Command::SUCCESS;
        }

        if ($input->getOption('json')) {
            $io->writeln(json_encode($pages, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($pages as $page) {
            $rows[] = [
                $page['id'],
                $page['slug'],
                $page['title'],
                $this->formatStatus($page['status']),
                $page['published_at'] ? date('Y-m-d H:i', strtotime($page['published_at'])) : '-',
                count($page['json_content']['components'] ?? []) . ' components',
            ];
        }

        $io->table(
            ['ID', 'Slug', 'Title', 'Status', 'Published', 'Components'],
            $rows
        );

        $io->text("View URL: <fg=cyan>http://localhost:7200/p/{slug}</>");

        return Command::SUCCESS;
    }

    private function createPage(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $io->title('Create New Page');

        // Get basic info
        $slug = $input->getOption('slug') ?? $io->ask('Page slug (URL-friendly)', 'my-page');
        $title = $input->getOption('title') ?? $io->ask('Page title', 'My Landing Page');
        $seoTitle = $input->getOption('seo-title') ?? $io->ask('SEO title (optional)', $title);
        $seoDescription = $input->getOption('seo-description') ?? $io->ask('SEO description (optional)');
        
        // Ask about template
        $template = $input->getOption('template');
        if (!$template) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Choose a template (or skip to build custom)',
                ['Skip (custom)', 'landing', 'product', 'about', 'contact'],
                0
            );
            $template = $helper->ask($input, $io, $question);
            if ($template === 'Skip (custom)') {
                $template = null;
            }
        }

        $io->section('Building page...');

        // Create from template or custom
        if ($template) {
            $page = $iris->pages->createFromTemplate($template, [
                'slug' => $slug,
                'title' => $title,
                'seo_title' => $seoTitle,
                'seo_description' => $seoDescription,
                'status' => 'draft',
            ]);
        } else {
            // Interactive component builder
            $components = [];
            
            if ($input->getOption('add-hero') || $io->confirm('Add Hero section?', true)) {
                $components[] = $this->buildHeroComponent($io, $input);
            }
            
            if ($input->getOption('add-text') || $io->confirm('Add Text block?', true)) {
                $components[] = $this->buildTextComponent($io, $input);
            }
            
            if ($input->getOption('add-button') || $io->confirm('Add CTA button?', false)) {
                $components[] = $this->buildButtonComponent($io, $input);
            }

            $page = $iris->pages->create([
                'slug' => $slug,
                'title' => $title,
                'seo_title' => $seoTitle,
                'seo_description' => $seoDescription,
                'status' => 'draft',
                'theme' => [
                    'mode' => 'dark',
                    'branding' => [
                        'name' => $title,
                        'primaryColor' => '#6366f1',
                        'secondaryColor' => '#8b5cf6',
                    ],
                ],
                'components' => $components,
            ]);
        }

        $pageData = $page['data'] ?? $page;
        $io->success("Page created successfully!");
        $io->definitionList(
            ['ID' => $pageData['id']],
            ['Slug' => $pageData['slug']],
            ['Title' => $pageData['title']],
            ['Status' => $pageData['status']],
            ['Components' => count($pageData['json_content']['components'] ?? [])]
        );

        $io->note([
            "View: http://localhost:7200/p/{$pageData['slug']}",
            "Publish: ./bin/iris pages publish {$pageData['slug']}",
        ]);

        return Command::SUCCESS;
    }

    private function buildHeroComponent(SymfonyStyle $io, InputInterface $input): array
    {
        $io->section('Hero Component');
        
        $title = $io->ask('Hero title', 'Welcome to Our Platform');
        $subtitle = $io->ask('Hero subtitle (optional)', 'Build amazing experiences');
        
        $helper = $this->getHelper('question');
        $gradientQuestion = new ChoiceQuestion(
            'Choose gradient preset',
            [
                'Purple (default)' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'Green-Blue' => 'linear-gradient(135deg, #10b981 0%, #3b82f6 100%)',
                'Orange-Pink' => 'linear-gradient(135deg, #f97316 0%, #ec4899 100%)',
                'Dark' => 'linear-gradient(to right, #1e293b, #334155)',
                'Custom' => 'custom',
            ],
            0
        );
        $gradient = $helper->ask($input, $io, $gradientQuestion);
        
        if ($gradient === 'custom') {
            $gradient = $io->ask('CSS gradient', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)');
        }

        return [
            'type' => 'Hero',
            'id' => 'hero-' . uniqid(),
            'props' => [
                'title' => $title,
                'subtitle' => $subtitle,
                'backgroundGradient' => $gradient,
                'titleColor' => '#ffffff',
                'subtitleColor' => 'rgba(255, 255, 255, 0.9)',
                'textAlign' => 'center',
                'minHeight' => '500px',
            ],
        ];
    }

    private function buildTextComponent(SymfonyStyle $io, InputInterface $input): array
    {
        $io->section('Text Block Component');
        
        $content = $io->ask('Markdown content', "## About Us\n\nWe provide cutting-edge solutions.");
        
        return [
            'type' => 'TextBlock',
            'id' => 'text-' . uniqid(),
            'props' => [
                'content' => $content,
                'markdown' => true,
                'textAlign' => 'center',
                'maxWidth' => '4xl',
                'themeMode' => 'dark',
            ],
        ];
    }

    private function buildButtonComponent(SymfonyStyle $io, InputInterface $input): array
    {
        $io->section('Button CTA Component');
        
        $text = $io->ask('Button text', 'Get Started');
        $href = $io->ask('Button URL', 'https://example.com/signup');
        
        return [
            'type' => 'ButtonCTA',
            'id' => 'btn-' . uniqid(),
            'props' => [
                'text' => $text,
                'href' => $href,
                'variant' => 'primary',
                'size' => 'lg',
            ],
        ];
    }

    private function viewPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($page, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->title("Page: {$page['title']}");
        $io->definitionList(
            ['ID' => $page['id']],
            ['Slug' => $page['slug']],
            ['Title' => $page['title']],
            ['Status' => $this->formatStatus($page['status'])],
            ['Published' => $page['published_at'] ?? 'Not published'],
            ['URL' => "http://localhost:7200/p/{$page['slug']}"]
        );

        $io->section('JSON Content');
        $io->writeln(json_encode($page['json_content'], JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function publishPage(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        // Get page to find ID
        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->publish($page['id']);
        $io->success("Page published successfully!");
        $io->note("View at: http://localhost:7200/p/{$slug}");

        return Command::SUCCESS;
    }

    private function unpublishPage(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->unpublish($page['id']);
        $io->success("Page unpublished (back to draft)");

        return Command::SUCCESS;
    }

    private function deletePage(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion("Are you sure you want to delete '{$slug}'? [y/N] ", false);
        
        if (!$helper->ask($input, $io, $question)) {
            $io->info('Cancelled');
            return Command::SUCCESS;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $iris->pages->delete($page['id']);
        $io->success("Page deleted");

        return Command::SUCCESS;
    }

    private function duplicatePage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $newSlug = $input->getOption('new-slug') ?? $io->ask('New slug for duplicate', $slug . '-copy');

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->duplicate($page['id'], $newSlug);
        $duplicated = $result['data'] ?? $result;

        $io->success("Page duplicated!");
        $io->note("View at: http://localhost:7200/p/{$newSlug}");

        return Command::SUCCESS;
    }

    private function viewVersions(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $versions = $iris->pages->versions($page['id']);

        $io->title("Version History: {$page['title']}");

        if (empty($versions)) {
            $io->info('No version history');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($versions as $version) {
            $rows[] = [
                $version['version_number'],
                $version['change_summary'] ?? 'No summary',
                date('Y-m-d H:i', strtotime($version['created_at'])),
                $version['changed_by'] ?? '-',
            ];
        }

        $io->table(['Version', 'Summary', 'Date', 'Changed By'], $rows);

        return Command::SUCCESS;
    }

    private function rollbackPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $version = $input->getOption('page-version');
        if (!$version) {
            $io->error('Version number is required (use --page-version=N)');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->rollback($page['id'], (int) $version);
        $io->success("Rolled back to version {$version}");

        return Command::SUCCESS;
    }

    private function formatStatus(string $status): string
    {
        return match($status) {
            'published' => '<fg=green>● Published</>',
            'draft' => '<fg=yellow>○ Draft</>',
            'archived' => '<fg=gray>◌ Archived</>',
            default => $status,
        };
    }

    private function editPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $io->title("Edit Page: {$slug}");
        $io->warning('Interactive editing coming soon. For now, use view + manual updates.');
        $io->note("Tip: ./bin/iris pages view {$slug} --json");

        return Command::SUCCESS;
    }

    private function listComponents(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;
        
        $components = $iris->pages->getComponents($page['id']);

        $io->title("Components: {$page['title']}");

        if (empty($components)) {
            $io->info('No components found');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($components as $index => $component) {
            $rows[] = [
                $index,
                $component['id'] ?? 'N/A',
                $component['type'],
                isset($component['props']['title']) ? substr($component['props']['title'], 0, 40) : 'N/A',
            ];
        }

        $io->table(['Index', 'ID', 'Type', 'Preview'], $rows);

        return Command::SUCCESS;
    }

    private function addComponent(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        // Get component type
        $type = $input->getOption('type');
        if (!$type) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Component type',
                ['Hero', 'TextBlock', 'ButtonCTA'],
                0
            );
            $type = $helper->ask($input, $io, $question);
        }

        // Build component based on type
        $component = match($type) {
            'Hero' => $this->buildHeroComponent($io, $input),
            'TextBlock' => $this->buildTextComponent($io, $input),
            'ButtonCTA' => $this->buildButtonComponent($io, $input),
            default => null,
        };

        if (!$component) {
            $io->error("Unknown component type: {$type}");
            return Command::FAILURE;
        }

        // Get position
        $position = $input->getOption('position');
        if ($position === null) {
            $position = $io->ask('Position (0 for start, leave empty for end)', '');
            if ($position === '') {
                $position = null;
            } else {
                $position = (int) $position;
            }
        }

        $result = $iris->pages->addComponent($page['id'], $component, $position);
        $io->success("Component added successfully!");
        
        return Command::SUCCESS;
    }

    private function updateComponent(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug, ?string $componentId): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        if (!$componentId) {
            $io->error('Component ID is required (use --component-id=xxx)');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, true);  // Include JSON
        $page = $response['data'] ?? $response;

        // Get props update from option or interactive
        $propsJson = $input->getOption('props');
        if ($propsJson) {
            $decoded = json_decode($propsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON: ' . json_last_error_msg());
                return Command::FAILURE;
            }
            $updates = ['props' => $decoded];
        } else {
            // Interactive mode
            $io->section('Update Component Props');
            $io->note('Enter JSON object for props (e.g., {"title": "New Title"})');
            $propsJson = $io->ask('Props JSON', '{}');
            $decoded = json_decode($propsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON: ' . json_last_error_msg());
                return Command::FAILURE;
            }
            $updates = ['props' => $decoded];
        }

        $result = $iris->pages->updateComponentById($page['id'], $componentId, $updates);
        $io->success("Component updated successfully!");

        return Command::SUCCESS;
    }

    private function removeComponent(IRIS $iris, SymfonyStyle $io, ?string $slug, ?string $componentId): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        if (!$componentId) {
            $io->error('Component ID is required (use --component-id=xxx)');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->removeComponentById($page['id'], $componentId);
        $io->success("Component removed successfully!");

        return Command::SUCCESS;
    }
}
