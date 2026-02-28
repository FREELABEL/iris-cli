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
 *   ./bin/iris pages set my-page "theme.mode" "light"   # Atomic dot-notation update
 *   ./bin/iris pages get my-page "components.0.props"   # Read value at path
 *   ./bin/iris pages pull my-page                       # Download JSON locally
 *   ./bin/iris pages push my-page                       # Upload local JSON
 *   ./bin/iris pages diff my-page                       # Compare local vs remote
 *   ./bin/iris pages publish my-page --env=production   # Publish on production
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
  pages view <slug>                                  View page JSON content
  pages publish <slug>                               Publish a page
  pages unpublish <slug>                             Unpublish (back to draft)
  pages delete <slug>                                Delete a page
  pages duplicate <slug> --new-slug=copy             Duplicate a page
  pages versions <slug>                              View version history
  pages rollback <slug> --page-version=2             Rollback to version

Atomic Updates (dot notation):
  pages set <slug> <path> <value>                    Set value at JSON path
  pages get <slug> <path>                            Read value at JSON path

Examples:
  pages set genesis "theme.mode" "light"
  pages set genesis "components.0.props.title" "New Hero Title"
  pages set genesis "theme.branding.primaryColor" "#10b981"
  pages get genesis "components.0.props.title"
  pages get genesis "theme"

Pull/Push (local file workflow):
  pages pull <slug>                                  Download page JSON to ./pages/<slug>.json
  pages push <slug>                                  Upload ./pages/<slug>.json to API
  pages diff <slug>                                  Compare local file vs remote

Component Management:
  pages components <slug>                            List components
  pages add-component <slug>                         Add a component
  pages update-component <slug> --component-id=xxx   Update a component
  pages remove-component <slug> --component-id=xxx   Remove a component

Environment:
  pages list --env=production                        Target production API
  pages list --env=local                             Target local API
HELP
            )
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list, create, view, set, get, pull, push, diff, publish, unpublish, delete, duplicate, versions, rollback, components, add-component, update-component, remove-component')
            ->addArgument('slug', InputArgument::OPTIONAL, 'Page slug')
            ->addArgument('path', InputArgument::OPTIONAL, 'Dot-notation path (for set/get)')
            ->addArgument('value', InputArgument::OPTIONAL, 'Value to set (for set)')
            // Common options
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment: local or production (overrides IRIS_ENV)')
            // Page options
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Page slug')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Page title')
            ->addOption('seo-title', null, InputOption::VALUE_REQUIRED, 'SEO title')
            ->addOption('seo-description', null, InputOption::VALUE_REQUIRED, 'SEO description')
            ->addOption('template', null, InputOption::VALUE_REQUIRED, 'Template: landing, product, about, contact')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Status: draft, published, archived', 'draft')
            ->addOption('new-slug', null, InputOption::VALUE_REQUIRED, 'New slug for duplication')
            ->addOption('page-version', null, InputOption::VALUE_REQUIRED, 'Version number for rollback')
            // Dot notation options (alternative to positional args)
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Dot-notation path (for set/get)')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Value to set (for set, supports JSON)')
            // Pull/push options
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Directory for pull/push files', './pages')
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

        // Handle --env flag before loading config
        $env = $input->getOption('env');
        if ($env) {
            putenv("IRIS_ENV={$env}");
            $_ENV['IRIS_ENV'] = $env;
        }

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

        // Show environment info
        $currentEnv = $env ?: (getenv('IRIS_ENV') ?: 'production');
        $baseUrl = $iris->getConfig()->baseUrl;

        try {
            switch ($action) {
                case 'list':
                    return $this->listPages($iris, $io, $input, $currentEnv, $baseUrl);

                case 'create':
                    return $this->createPage($iris, $io, $input);

                case 'edit':
                    return $this->editPage($iris, $io, $input, $slug);

                case 'view':
                    return $this->viewPage($iris, $io, $input, $slug);

                case 'set':
                    return $this->setPath($iris, $io, $input, $slug);

                case 'get':
                    return $this->getPath($iris, $io, $input, $slug);

                case 'pull':
                    return $this->pullPage($iris, $io, $input, $slug);

                case 'push':
                    return $this->pushPage($iris, $io, $input, $slug);

                case 'diff':
                    return $this->diffPage($iris, $io, $input, $slug);

                case 'publish':
                    return $this->publishPage($iris, $io, $slug);

                case 'unpublish':
                    return $this->unpublishPage($iris, $io, $slug);

                case 'delete':
                    return $this->deletePage($iris, $io, $input, $slug);

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

    // ─── Atomic Dot-Notation Commands ─────────────────────────────────

    private function setPath(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages set <slug> <path> <value>');
            return Command::FAILURE;
        }

        $path = $input->getArgument('path') ?? $input->getOption('path');
        $value = $input->getArgument('value') ?? $input->getOption('value');

        if (!$path) {
            $io->error('Path is required. Usage: pages set <slug> <path> <value>');
            $io->text([
                'Examples:',
                '  pages set genesis "theme.mode" "light"',
                '  pages set genesis "components.0.props.title" "New Title"',
                '  pages set genesis "theme.branding.primaryColor" "#10b981"',
            ]);
            return Command::FAILURE;
        }

        if ($value === null) {
            $io->error('Value is required. Usage: pages set <slug> <path> <value>');
            return Command::FAILURE;
        }

        // Auto-detect JSON values
        $parsedValue = $this->parseValue($value);

        // Resolve page ID from slug
        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        // Perform the atomic update
        $result = $iris->pages->updatePath($page['id'], $path, $parsedValue);

        $displayValue = is_array($parsedValue) ? json_encode($parsedValue) : $parsedValue;
        $io->success("Updated {$slug} -> {$path} = {$displayValue}");

        return Command::SUCCESS;
    }

    private function getPath(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages get <slug> <path>');
            return Command::FAILURE;
        }

        $path = $input->getArgument('path') ?? $input->getOption('path');

        // Fetch page with JSON content
        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;
        $jsonContent = $page['json_content'] ?? [];

        if (!$path) {
            // No path = show full JSON content
            $io->writeln(json_encode($jsonContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        // Navigate dot notation path
        $value = $this->getNestedValue($jsonContent, $path);

        if ($value === null) {
            $io->warning("Path '{$path}' not found in page '{$slug}'");
            return Command::FAILURE;
        }

        if (is_array($value)) {
            $io->writeln(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $io->writeln((string) $value);
        }

        return Command::SUCCESS;
    }

    // ─── Pull / Push / Diff Commands ──────────────────────────────────

    private function pullPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages pull <slug>');
            return Command::FAILURE;
        }

        $dir = $input->getOption('dir');

        // Fetch page with full JSON
        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;

        // Ensure directory exists
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = rtrim($dir, '/') . "/{$slug}.json";

        // Build the export object (page metadata + JSON content)
        $export = [
            'id' => $page['id'],
            'slug' => $page['slug'],
            'title' => $page['title'],
            'seo_title' => $page['seo_title'] ?? null,
            'seo_description' => $page['seo_description'] ?? null,
            'og_image' => $page['og_image'] ?? null,
            'status' => $page['status'],
            'owner_type' => $page['owner_type'] ?? 'system',
            'owner_id' => $page['owner_id'] ?? null,
            'json_content' => $page['json_content'] ?? [],
        ];

        file_put_contents($filePath, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $componentCount = count($export['json_content']['components'] ?? []);
        $io->success("Pulled '{$slug}' -> {$filePath} ({$componentCount} components)");

        return Command::SUCCESS;
    }

    private function pushPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages push <slug>');
            return Command::FAILURE;
        }

        $dir = $input->getOption('dir');
        $filePath = rtrim($dir, '/') . "/{$slug}.json";

        if (!file_exists($filePath)) {
            $io->error("Local file not found: {$filePath}");
            $io->note("Pull first: ./bin/iris pages pull {$slug}");
            return Command::FAILURE;
        }

        $localJson = json_decode(file_get_contents($filePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON in {$filePath}: " . json_last_error_msg());
            return Command::FAILURE;
        }

        // Resolve page ID from slug (page must already exist remotely)
        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        // Build update payload
        $updateData = [];
        if (isset($localJson['title'])) {
            $updateData['title'] = $localJson['title'];
        }
        if (isset($localJson['seo_title'])) {
            $updateData['seo_title'] = $localJson['seo_title'];
        }
        if (isset($localJson['seo_description'])) {
            $updateData['seo_description'] = $localJson['seo_description'];
        }
        if (isset($localJson['og_image'])) {
            $updateData['og_image'] = $localJson['og_image'];
        }
        if (isset($localJson['json_content'])) {
            $updateData['json_content'] = $localJson['json_content'];
        }

        $result = $iris->pages->update($page['id'], $updateData);

        $componentCount = count($localJson['json_content']['components'] ?? []);
        $io->success("Pushed '{$slug}' from {$filePath} ({$componentCount} components)");
        $io->note("A new version has been created. Use 'pages versions {$slug}' to see history.");

        return Command::SUCCESS;
    }

    private function diffPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages diff <slug>');
            return Command::FAILURE;
        }

        $dir = $input->getOption('dir');
        $filePath = rtrim($dir, '/') . "/{$slug}.json";

        if (!file_exists($filePath)) {
            $io->error("Local file not found: {$filePath}");
            $io->note("Pull first: ./bin/iris pages pull {$slug}");
            return Command::FAILURE;
        }

        // Load local
        $localJson = json_decode(file_get_contents($filePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON in {$filePath}: " . json_last_error_msg());
            return Command::FAILURE;
        }

        // Fetch remote
        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;

        $localContent = $localJson['json_content'] ?? [];
        $remoteContent = $page['json_content'] ?? [];

        $localEncoded = json_encode($localContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $remoteEncoded = json_encode($remoteContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($localEncoded === $remoteEncoded) {
            $io->success("No differences — local and remote are identical.");
            return Command::SUCCESS;
        }

        $io->title("Diff: {$slug}");

        // Compare metadata
        $metaFields = ['title', 'seo_title', 'seo_description'];
        $metaDiffs = [];
        foreach ($metaFields as $field) {
            $localVal = $localJson[$field] ?? null;
            $remoteVal = $page[$field] ?? null;
            if ($localVal !== $remoteVal) {
                $metaDiffs[] = [$field, $remoteVal ?? '(empty)', $localVal ?? '(empty)'];
            }
        }
        if (!empty($metaDiffs)) {
            $io->section('Metadata Changes');
            $io->table(['Field', 'Remote', 'Local'], $metaDiffs);
        }

        // Compare components
        $localComponents = $localContent['components'] ?? [];
        $remoteComponents = $remoteContent['components'] ?? [];

        $io->section('Components');
        $io->text("Remote: " . count($remoteComponents) . " components");
        $io->text("Local:  " . count($localComponents) . " components");

        // Show component-level diffs
        $maxCount = max(count($localComponents), count($remoteComponents));
        $diffs = [];
        for ($i = 0; $i < $maxCount; $i++) {
            $local = $localComponents[$i] ?? null;
            $remote = $remoteComponents[$i] ?? null;

            if ($local === null) {
                $diffs[] = [$i, '<fg=red>REMOVED</>', $remote['type'] ?? '?', '-'];
            } elseif ($remote === null) {
                $diffs[] = [$i, '<fg=green>ADDED</>', '-', $local['type'] ?? '?'];
            } elseif (json_encode($local) !== json_encode($remote)) {
                $diffs[] = [$i, '<fg=yellow>CHANGED</>', $remote['type'] ?? '?', $local['type'] ?? '?'];
            }
        }

        if (empty($diffs)) {
            $io->text('Component structure identical (possible whitespace/formatting differences).');
        } else {
            $io->table(['Index', 'Status', 'Remote Type', 'Local Type'], $diffs);
        }

        // Theme diff
        $localTheme = json_encode($localContent['theme'] ?? [], JSON_PRETTY_PRINT);
        $remoteTheme = json_encode($remoteContent['theme'] ?? [], JSON_PRETTY_PRINT);
        if ($localTheme !== $remoteTheme) {
            $io->section('Theme Changes');
            $io->text('<fg=red>Remote:</>');
            $io->writeln($remoteTheme);
            $io->text('<fg=green>Local:</>');
            $io->writeln($localTheme);
        }

        $io->note("To apply local changes: ./bin/iris pages push {$slug}");

        return Command::SUCCESS;
    }

    // ─── Existing Commands (with fixes) ───────────────────────────────

    private function listPages(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $env, string $baseUrl): int
    {
        $io->title("Pages [{$env}]");
        $io->text("<fg=gray>API: {$baseUrl}</>");

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
            "View: ./bin/iris pages view {$pageData['slug']}",
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
            ['Published' => $page['published_at'] ?? 'Not published']
        );

        $io->section('JSON Content');
        $io->writeln(json_encode($page['json_content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    private function publishPage(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->publish($page['id']);
        $io->success("Page '{$slug}' published!");

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
        $io->success("Page '{$slug}' unpublished (back to draft)");

        return Command::SUCCESS;
    }

    private function deletePage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
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
        $io->success("Page '{$slug}' deleted");

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
        $io->success("Page duplicated as '{$newSlug}'!");

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

        $response = $iris->pages->versions($page['id']);
        $versions = $response['data'] ?? $response;

        $io->title("Version History: {$page['title']}");

        if (empty($versions)) {
            $io->info('No version history');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($versions as $version) {
            $rows[] = [
                $version['version_number'] ?? '?',
                $version['change_summary'] ?? 'No summary',
                isset($version['created_at']) ? date('Y-m-d H:i', strtotime($version['created_at'])) : '-',
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
        $io->success("Rolled back '{$slug}' to version {$version}");

        return Command::SUCCESS;
    }

    private function editPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $io->title("Edit Page: {$slug}");
        $io->text([
            'Use these commands for editing:',
            '',
            "  <fg=cyan>Atomic updates (dot notation):</>",
            "  ./bin/iris pages set {$slug} \"components.0.props.title\" \"New Title\"",
            "  ./bin/iris pages set {$slug} \"theme.mode\" \"light\"",
            '',
            "  <fg=cyan>Full JSON editing:</>",
            "  ./bin/iris pages pull {$slug}       # Download to ./pages/{$slug}.json",
            "  # Edit the file locally",
            "  ./bin/iris pages push {$slug}       # Upload changes",
            '',
            "  <fg=cyan>View current state:</>",
            "  ./bin/iris pages get {$slug} \"components.0.props\"",
            "  ./bin/iris pages view {$slug} --json",
        ]);

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
            $preview = $component['props']['title']
                ?? $component['props']['text']
                ?? $component['props']['content']
                ?? 'N/A';
            $rows[] = [
                $index,
                $component['id'] ?? 'N/A',
                $component['type'],
                substr((string) $preview, 0, 50),
            ];
        }

        $io->table(['Index', 'ID', 'Type', 'Preview'], $rows);

        $io->text([
            '',
            "Update a component: ./bin/iris pages set {$slug} \"components.<index>.props.<key>\" \"<value>\"",
        ]);

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

        $response = $iris->pages->getBySlug($slug, true);
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

    // ─── Helpers ──────────────────────────────────────────────────────

    /**
     * Parse a value string, auto-detecting JSON objects/arrays.
     */
    private function parseValue(string $value): mixed
    {
        // Try JSON decode first (for objects, arrays, booleans, numbers)
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_bool($decoded))) {
            return $decoded;
        }

        // Check for numeric
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        // Check for boolean strings
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        if (strtolower($value) === 'null') return null;

        // Return as string
        return $value;
    }

    /**
     * Get a nested value from an array using dot notation.
     */
    private function getNestedValue(array $array, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return null;
            }
        }

        return $current;
    }
}
