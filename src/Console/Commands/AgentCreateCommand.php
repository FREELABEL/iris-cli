<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;
use IRIS\SDK\Resources\Agents\AgentTemplates;

/**
 * Create agents from templates via CLI.
 *
 * Usage:
 *   iris agent:create elderly-care --name="Grandma Helper"
 *   iris agent:create --list
 *   iris agent:create --interactive
 */
class AgentCreateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('agent:create')
            ->setDescription('Create an AI agent from a template')
            ->setHelp(<<<HELP
Create fully configured AI agents in seconds using pre-built templates.

<info>Quick Start:</info>
  iris agent:create elderly-care --name="Grandma Helper"
  iris agent:create customer-support --name="Support Bot"
  iris agent:create --interactive

<info>Available Templates:</info>
  elderly-care         Daily reminders, safety monitoring, medication tracking
  customer-support     Professional support with knowledge base integration
  sales-assistant      Lead qualification, meeting scheduling, CRM updates
  research-agent       Deep research with GPT-4 optimization

<info>Options:</info>
  --list              List all available templates
  --interactive       Interactive template selection and customization
  --name              Agent name (required unless --list or --interactive)
  --timezone          Timezone for scheduled tasks (e.g., America/Chicago)

<info>Examples:</info>
  # Quick create from template
  iris agent:create elderly-care --name="Grandma Helper" --timezone="America/Chicago"
  
  # List available templates
  iris agent:create --list
  
  # Interactive mode with customization
  iris agent:create --interactive

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addArgument('template', InputArgument::OPTIONAL, 'Template name (elderly-care, customer-support, sales-assistant, research-agent)')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List available templates')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactive mode')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Agent name')
            ->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Timezone for scheduled tasks (e.g., America/New_York)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Handle --list option
        if ($input->getOption('list')) {
            return $this->listTemplates($io);
        }

        // Load credentials
        $store = new CredentialStore();
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

        if (!$apiKey || !$userId) {
            $io->error('Missing credentials. Set IRIS_API_KEY and IRIS_USER_ID environment variables or run: iris config');
            return Command::FAILURE;
        }

        // Initialize IRIS
        $iris = new IRIS([
            'api_key' => $apiKey,
            'user_id' => (int)$userId,
        ]);

        // Handle interactive mode
        if ($input->getOption('interactive')) {
            return $this->interactiveCreate($io, $iris);
        }

        // Handle template-based creation
        $template = $input->getArgument('template');
        $name = $input->getOption('name');

        if (!$template) {
            $io->error('Template name is required. Use --list to see available templates or --interactive for guided setup.');
            return Command::FAILURE;
        }

        if (!$name) {
            $io->error('Agent name is required. Use --name="Your Agent Name"');
            return Command::FAILURE;
        }

        return $this->createFromTemplate($io, $iris, $template, $name, $input->getOption('timezone'));
    }

    /**
     * List all available templates.
     */
    private function listTemplates(SymfonyStyle $io): int
    {
        $io->title('📋 Available Agent Templates');

        $templates = AgentTemplates::all();

        foreach ($templates as $key => $config) {
            $icon = $config['icon'] ?? '🤖';
            $name = $config['name'];
            $description = $config['description'] ?? 'No description';
            
            $io->section("{$icon} {$name}");
            $io->text("  Template ID: <info>{$key}</info>");
            $io->text("  Description: {$description}");
            
            if (isset($config['settings']['schedule']['recurring_tasks'])) {
                $taskCount = count($config['settings']['schedule']['recurring_tasks']);
                $io->text("  Scheduled Tasks: {$taskCount}");
            }
            
            if (isset($config['settings']['agentIntegrations'])) {
                $integrations = array_keys(array_filter($config['settings']['agentIntegrations']));
                $io->text("  Integrations: " . implode(', ', $integrations));
            }
            
            $io->newLine();
        }

        $io->note('Create an agent: iris agent:create <template-id> --name="Your Agent Name"');

        return Command::SUCCESS;
    }

    /**
     * Interactive agent creation with template selection.
     */
    private function interactiveCreate(SymfonyStyle $io, IRIS $iris): int
    {
        $io->title('🤖 Create AI Agent (Interactive Mode)');

        // Step 1: Select template
        $templates = AgentTemplates::all();
        $templateChoices = [];
        foreach ($templates as $key => $config) {
            $icon = $config['icon'] ?? '🤖';
            $name = $config['name'];
            $templateChoices[$key] = "{$icon} {$name}";
        }

        $question = new ChoiceQuestion(
            'Select a template:',
            $templateChoices,
            0
        );
        $selectedTemplate = $io->askQuestion($question);
        $templateKey = array_search($selectedTemplate, $templateChoices);

        // Step 2: Get agent name
        $name = $io->ask('Agent name', null, function ($answer) {
            if (!$answer) {
                throw new \RuntimeException('Agent name is required.');
            }
            return $answer;
        });

        // Step 3: Optional timezone customization
        $timezone = $io->ask(
            'Timezone for scheduled tasks (optional, e.g., America/Chicago)',
            null
        );

        // Step 4: Confirm and create
        $io->section('Review Configuration');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Template', $templates[$templateKey]['name']],
                ['Agent Name', $name],
                ['Timezone', $timezone ?: 'Default (from template)'],
            ]
        );

        if (!$io->confirm('Create this agent?', true)) {
            $io->warning('Agent creation cancelled.');
            return Command::SUCCESS;
        }

        return $this->createFromTemplate($io, $iris, $templateKey, $name, $timezone);
    }

    /**
     * Create agent from template.
     */
    private function createFromTemplate(
        SymfonyStyle $io,
        IRIS $iris,
        string $template,
        string $name,
        ?string $timezone
    ): int {
        $io->text("Creating agent from template: <info>{$template}</info>");

        try {
            $customizations = ['name' => $name];

            // Add timezone if provided
            if ($timezone) {
                $customizations['settings'] = [
                    'schedule' => [
                        'timezone' => $timezone
                    ]
                ];
            }

            $agent = $iris->agents->createFromTemplate($template, $customizations);

            $io->success([
                "Agent created successfully!",
                "Agent ID: {$agent->id}",
                "Name: {$agent->name}",
            ]);

            // Show schedule info if available
            $templateConfig = AgentTemplates::get($template);
            if (isset($templateConfig['settings']['schedule']['recurring_tasks'])) {
                $tasks = $templateConfig['settings']['schedule']['recurring_tasks'];
                $io->section('📅 Scheduled Tasks');
                $io->listing(array_map(function ($task) {
                    return "{$task['name']} at {$task['time']}";
                }, $tasks));
            }

            // Show integrations
            if (isset($templateConfig['settings']['agentIntegrations'])) {
                $integrations = array_keys(array_filter($templateConfig['settings']['agentIntegrations']));
                if ($integrations) {
                    $io->section('🔌 Enabled Integrations');
                    $io->listing($integrations);
                }
            }

            $io->note([
                "View agent: https://app.heyiris.io/agents/{$agent->id}",
                "Chat via CLI: iris chat {$agent->id} \"Hello!\"",
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to create agent: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
