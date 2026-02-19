<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use IRIS\SDK\IRIS;

class MarketplaceCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('marketplace')
            ->setDescription('Browse, publish, and install skills from the IRIS marketplace')
            ->setHelp('Manage marketplace skills — publish your tools, install others')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: search, install, uninstall, publish, list, info, init, validate', 'search')
            ->addArgument('query', InputArgument::OPTIONAL, 'Search query or skill name/slug')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filter by skill type (cli_tool, api_endpoint, workflow, agent_capability, integration, mcp_server)')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Filter by category')
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'Version for publish')
            ->addOption('auto-publish', null, InputOption::VALUE_NONE, 'Auto-publish without review')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for authentication')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'search';

        try {
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int)$userId;
            }

            switch ($action) {
                case 'search':
                    return $this->searchSkills($io, $input, $configOptions);
                case 'install':
                    return $this->installSkill($io, $input, $configOptions);
                case 'uninstall':
                    return $this->uninstallSkill($io, $input, $configOptions);
                case 'publish':
                    return $this->publishSkill($io, $input, $output, $configOptions);
                case 'list':
                    return $this->listInstalled($io, $configOptions);
                case 'info':
                    return $this->showInfo($io, $input, $configOptions);
                case 'init':
                    return $this->initManifest($io, $input, $output);
                case 'validate':
                    return $this->validateManifest($io);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text("Available actions: search, install, uninstall, publish, list, info, init, validate");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function searchSkills(SymfonyStyle $io, InputInterface $input, array $config): int
    {
        $iris = new IRIS($config);
        $query = $input->getArgument('query') ?? '';

        $filters = [];
        if ($type = $input->getOption('type')) {
            $filters['type'] = $type;
        }
        if ($category = $input->getOption('category')) {
            $filters['category'] = $category;
        }

        $result = $iris->marketplace->search($query, $filters);

        if (empty($result['data'])) {
            $io->warning($query ? "No skills found for '{$query}'" : 'No skills found in marketplace');
            return Command::SUCCESS;
        }

        $io->title('Marketplace Skills' . ($query ? " matching '{$query}'" : ''));

        $table = new Table($io);
        $table->setHeaders(['Name', 'Type', 'Version', 'Rating', 'Installs', 'Price']);

        foreach ($result['data'] as $skill) {
            $price = ($skill['is_free'] ?? true) ? 'Free' : '$' . number_format(($skill['price_cents'] ?? 0) / 100, 2);
            $rating = ($skill['average_rating'] ?? 0) > 0 ? str_repeat('*', (int)round($skill['average_rating'])) : '-';

            $table->addRow([
                $skill['slug'] ?? $skill['name'],
                $skill['skill_type'] ?? '-',
                $skill['current_version'] ?? '1.0.0',
                $rating,
                $skill['install_count'] ?? 0,
                $price,
            ]);
        }

        $table->render();

        $total = $result['meta']['total'] ?? count($result['data']);
        $io->text("Showing " . count($result['data']) . " of {$total} skills");

        return Command::SUCCESS;
    }

    private function installSkill(SymfonyStyle $io, InputInterface $input, array $config): int
    {
        $slug = $input->getArgument('query');
        if (!$slug) {
            $io->error('Skill name is required. Usage: iris marketplace install <skill-name>');
            return Command::FAILURE;
        }

        $iris = new IRIS($config);
        $result = $iris->marketplace->install($slug);

        if ($result['success'] ?? false) {
            $io->success($result['message'] ?? "Skill '{$slug}' installed successfully!");
        } else {
            $io->error($result['error'] ?? "Failed to install '{$slug}'");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function uninstallSkill(SymfonyStyle $io, InputInterface $input, array $config): int
    {
        $slug = $input->getArgument('query');
        if (!$slug) {
            $io->error('Skill name is required. Usage: iris marketplace uninstall <skill-name>');
            return Command::FAILURE;
        }

        $iris = new IRIS($config);
        $result = $iris->marketplace->uninstall($slug);

        if ($result['success'] ?? false) {
            $io->success($result['message'] ?? "Skill '{$slug}' uninstalled");
        } else {
            $io->error($result['error'] ?? "Failed to uninstall '{$slug}'");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function publishSkill(SymfonyStyle $io, InputInterface $input, OutputInterface $output, array $config): int
    {
        $manifestPath = getcwd() . '/iris-skill.json';

        if (!file_exists($manifestPath)) {
            $io->error("No iris-skill.json found in current directory");
            $io->text("Run 'iris marketplace init' to create one");
            return Command::FAILURE;
        }

        $content = file_get_contents($manifestPath);
        $manifest = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON in iris-skill.json: " . json_last_error_msg());
            return Command::FAILURE;
        }

        // Override version if provided
        if ($version = $input->getOption('version')) {
            $manifest['version'] = $version;
        }

        $iris = new IRIS($config);

        $options = [];
        if ($input->getOption('auto-publish')) {
            $options['auto_publish'] = true;
        }

        // Check for README
        $readmePath = getcwd() . '/README.md';
        if (file_exists($readmePath)) {
            $options['readme_content'] = file_get_contents($readmePath);
        }

        // Check if skill already exists (update vs create)
        try {
            $existing = $iris->marketplace->get($manifest['name']);
            if ($existing['success'] ?? false) {
                // Publish as new version
                $result = $iris->marketplace->publishVersion(
                    $manifest['name'],
                    $manifest,
                    [['message' => "Updated to {$manifest['version']}"]]
                );
                $io->success("Version {$manifest['version']} published for '{$manifest['name']}'");
                return Command::SUCCESS;
            }
        } catch (\Exception $e) {
            // Skill doesn't exist yet, create it
        }

        $result = $iris->marketplace->publish($manifest, $options);

        if ($result['success'] ?? false) {
            $io->success("Skill '{$manifest['name']}' published successfully!");
            $io->definitionList(
                ['Name' => $result['data']['display_name'] ?? $manifest['name']],
                ['Slug' => $result['data']['slug'] ?? $manifest['name']],
                ['Version' => $manifest['version'] ?? '1.0.0'],
                ['Status' => $result['data']['status'] ?? 'draft'],
            );
        } else {
            $io->error($result['error'] ?? 'Failed to publish skill');
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $io->text("  - {$error}");
                }
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function listInstalled(SymfonyStyle $io, array $config): int
    {
        $iris = new IRIS($config);
        $result = $iris->marketplace->installed();

        $installations = $result['data'] ?? [];

        if (empty($installations)) {
            $io->warning('No skills installed');
            $io->text("Browse available skills: iris marketplace search");
            return Command::SUCCESS;
        }

        $io->title('Installed Skills');

        $table = new Table($io);
        $table->setHeaders(['Skill', 'Version', 'Status', 'Used', 'Installed']);

        foreach ($installations as $install) {
            $skill = $install['skill'] ?? [];
            $table->addRow([
                $skill['display_name'] ?? $skill['name'] ?? '-',
                $install['installed_version'] ?? '-',
                $install['status'] ?? '-',
                $install['usage_count'] ?? 0,
                isset($install['installed_at']) ? date('Y-m-d', strtotime($install['installed_at'])) : '-',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function showInfo(SymfonyStyle $io, InputInterface $input, array $config): int
    {
        $slug = $input->getArgument('query');
        if (!$slug) {
            $io->error('Skill name is required. Usage: iris marketplace info <skill-name>');
            return Command::FAILURE;
        }

        $iris = new IRIS($config);
        $result = $iris->marketplace->get($slug);

        $skill = $result['data'] ?? $result;

        $io->title($skill['display_name'] ?? $slug);

        $io->definitionList(
            ['Name' => $skill['name'] ?? '-'],
            ['Type' => $skill['skill_type_label'] ?? $skill['skill_type'] ?? '-'],
            ['Version' => $skill['current_version'] ?? '-'],
            ['Category' => $skill['category_label'] ?? $skill['primary_category'] ?? '-'],
            ['Rating' => ($skill['average_rating'] ?? 0) . ' (' . ($skill['rating_count'] ?? 0) . ' reviews)'],
            ['Installs' => $skill['install_count'] ?? 0],
            ['Price' => ($skill['is_free'] ?? true) ? 'Free' : '$' . number_format(($skill['price_cents'] ?? 0) / 100, 2)],
            ['License' => $skill['license'] ?? '-'],
            ['Publisher' => $skill['publisher']['name'] ?? '-'],
            ['Repository' => $skill['repository_url'] ?? '-'],
        );

        if (!empty($skill['description'])) {
            $io->section('Description');
            $io->text($skill['description']);
        }

        if (!empty($skill['capabilities']['functions'])) {
            $io->section('Functions');
            foreach ($skill['capabilities']['functions'] as $fn) {
                $io->text("  - {$fn['name']}: {$fn['description']}");
            }
        }

        if (!empty($skill['tags'])) {
            $io->section('Tags');
            $io->text(implode(', ', $skill['tags']));
        }

        return Command::SUCCESS;
    }

    private function initManifest(SymfonyStyle $io, InputInterface $input, OutputInterface $output): int
    {
        $io->title('Initialize iris-skill.json');

        $manifestPath = getcwd() . '/iris-skill.json';
        if (file_exists($manifestPath)) {
            $io->warning('iris-skill.json already exists in current directory');
            if (!$io->confirm('Overwrite?', false)) {
                return Command::SUCCESS;
            }
        }

        $helper = $this->getHelper('question');

        // Detect project info from existing files
        $defaults = $this->detectProjectDefaults();

        $name = $io->ask('Skill name (lowercase, hyphens)', $defaults['name']);
        $displayName = $io->ask('Display name', ucwords(str_replace('-', ' ', $name)));
        $description = $io->ask('Description', $defaults['description'] ?? '');

        $typeQuestion = new ChoiceQuestion(
            'Skill type',
            ['cli_tool', 'api_endpoint', 'workflow', 'agent_capability', 'integration', 'mcp_server'],
            0
        );
        $type = $helper->ask($input, $output, $typeQuestion);

        $version = $io->ask('Version', $defaults['version'] ?? '1.0.0');
        $license = $io->ask('License', $defaults['license'] ?? 'MIT');
        $repository = $io->ask('Repository URL', $defaults['repository'] ?? '');
        $authorName = $io->ask('Author name', $defaults['author'] ?? '');

        $categories = $io->ask('Categories (comma-separated)', 'utilities');
        $tags = $io->ask('Tags (comma-separated)', '');

        $manifest = [
            'iris_skill' => '1.0',
            'name' => $name,
            'display_name' => $displayName,
            'description' => $description,
            'version' => $version,
            'type' => $type,
            'categories' => array_map('trim', explode(',', $categories)),
            'tags' => $tags ? array_map('trim', explode(',', $tags)) : [],
            'icon' => 'fas fa-puzzle-piece',
            'entry' => $this->buildEntryForType($type, $name),
            'capabilities' => [
                'functions' => [],
                'examples' => [],
            ],
            'dependencies' => [
                'required' => [],
                'optional' => [],
            ],
            'pricing' => [
                'model' => 'free',
            ],
            'author' => [
                'name' => $authorName,
            ],
            'repository' => $repository,
            'license' => $license,
            'min_sdk_version' => '1.0.0',
        ];

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($manifestPath, $json . "\n");

        $io->success("Created iris-skill.json");
        $io->text("Next steps:");
        $io->listing([
            "Edit iris-skill.json to add your skill's functions and capabilities",
            "Run 'iris marketplace validate' to check for errors",
            "Run 'iris marketplace publish' to publish to the IRIS marketplace",
        ]);

        return Command::SUCCESS;
    }

    private function validateManifest(SymfonyStyle $io): int
    {
        $manifestPath = getcwd() . '/iris-skill.json';

        if (!file_exists($manifestPath)) {
            $io->error("No iris-skill.json found in current directory");
            $io->text("Run 'iris marketplace init' to create one");
            return Command::FAILURE;
        }

        $content = file_get_contents($manifestPath);
        $manifest = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON: " . json_last_error_msg());
            return Command::FAILURE;
        }

        $errors = [];

        // Required fields
        if (empty($manifest['iris_skill'])) {
            $errors[] = 'Missing required field: iris_skill';
        }
        if (empty($manifest['name'])) {
            $errors[] = 'Missing required field: name';
        } elseif (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $manifest['name'])) {
            $errors[] = 'name must be lowercase alphanumeric with hyphens';
        }
        if (empty($manifest['version'])) {
            $errors[] = 'Missing required field: version';
        } elseif (!preg_match('/^\d+\.\d+\.\d+/', $manifest['version'])) {
            $errors[] = 'version must follow semver (e.g. 1.0.0)';
        }

        $validTypes = ['cli_tool', 'api_endpoint', 'workflow', 'agent_capability', 'integration', 'mcp_server'];
        if (!empty($manifest['type']) && !in_array($manifest['type'], $validTypes)) {
            $errors[] = 'type must be one of: ' . implode(', ', $validTypes);
        }

        if (empty($manifest['description'])) {
            $io->warning('Recommended: Add a description');
        }
        if (empty($manifest['capabilities']['functions'])) {
            $io->warning('Recommended: Add at least one function to capabilities.functions');
        }

        if (!empty($errors)) {
            $io->error('Validation failed:');
            foreach ($errors as $error) {
                $io->text("  - {$error}");
            }
            return Command::FAILURE;
        }

        $io->success('iris-skill.json is valid!');
        $io->definitionList(
            ['Name' => $manifest['name']],
            ['Type' => $manifest['type'] ?? 'cli_tool'],
            ['Version' => $manifest['version']],
            ['Functions' => count($manifest['capabilities']['functions'] ?? [])],
        );

        return Command::SUCCESS;
    }

    private function detectProjectDefaults(): array
    {
        $defaults = [
            'name' => basename(getcwd()),
            'version' => '1.0.0',
        ];

        // Check composer.json
        $composerPath = getcwd() . '/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true) ?? [];
            $defaults['description'] = $composer['description'] ?? '';
            $defaults['license'] = $composer['license'] ?? 'MIT';
            if (!empty($composer['authors'][0]['name'])) {
                $defaults['author'] = $composer['authors'][0]['name'];
            }
        }

        // Check package.json
        $packagePath = getcwd() . '/package.json';
        if (file_exists($packagePath)) {
            $package = json_decode(file_get_contents($packagePath), true) ?? [];
            $defaults['description'] = $defaults['description'] ?? $package['description'] ?? '';
            $defaults['version'] = $package['version'] ?? '1.0.0';
            $defaults['license'] = $defaults['license'] ?? $package['license'] ?? 'MIT';
            if (!empty($package['author'])) {
                $defaults['author'] = is_string($package['author']) ? $package['author'] : ($package['author']['name'] ?? '');
            }
            if (!empty($package['repository'])) {
                $defaults['repository'] = is_string($package['repository']) ? $package['repository'] : ($package['repository']['url'] ?? '');
            }
        }

        // Sanitize name
        $defaults['name'] = strtolower(preg_replace('/[^a-z0-9-]/', '-', strtolower($defaults['name'])));
        $defaults['name'] = preg_replace('/-+/', '-', trim($defaults['name'], '-'));

        return $defaults;
    }

    private function buildEntryForType(string $type, string $name): array
    {
        switch ($type) {
            case 'cli_tool':
                return [
                    'cli' => [
                        'command' => $name,
                        'install_command' => '',
                    ],
                ];
            case 'api_endpoint':
                return [
                    'api' => [
                        'base_url' => '',
                        'auth_type' => 'bearer',
                    ],
                ];
            case 'workflow':
                return [
                    'workflow' => [
                        'template_slug' => '',
                    ],
                ];
            case 'agent_capability':
                return [
                    'agent' => [
                        'instructions' => '',
                        'tool_mappings' => [],
                    ],
                ];
            case 'integration':
                return [
                    'integration' => [
                        'type' => '',
                        'config' => [],
                    ],
                ];
            case 'mcp_server':
                return [
                    'mcp' => [
                        'command' => '',
                        'args' => [],
                    ],
                ];
            default:
                return [];
        }
    }
}
