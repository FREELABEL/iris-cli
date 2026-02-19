<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\Auth\CredentialStore;

/**
 * CLI command for viewing SDK configuration.
 *
 * Usage:
 *   iris config          # Show current configuration
 *   iris config show     # Same as above
 *   iris config test     # Test API connection
 */
class ConfigCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('config')
            ->setDescription('View SDK configuration (loaded from .env)')
            ->setHelp(<<<HELP
View IRIS SDK configuration loaded from the .env file.

<info>Commands:</info>
  iris config          Show current configuration status
  iris config show     Same as above
  iris config test     Test API connection with current credentials

<info>Configuration via .env file:</info>
  IRIS_ENV             Environment: "local" or "production"
  IRIS_USER_ID         Your numeric user ID

  <comment>For local development:</comment>
  IRIS_LOCAL_API_KEY   API token for local environment
  FL_API_LOCAL_URL     Local FL-API URL (default: https://local.raichu.freelabel.net)
  IRIS_LOCAL_URL       Local IRIS URL (default: https://local.iris.freelabel.net)

  <comment>For production:</comment>
  IRIS_API_KEY         API token for production
  FL_API_URL           Production FL-API URL (default: https://apiv2.heyiris.io)
  IRIS_API_URL         Production IRIS URL (default: https://iris-api.freelabel.net)

<info>Example .env file:</info>
  IRIS_ENV=local
  IRIS_USER_ID=193
  IRIS_LOCAL_API_KEY=eyJ0eXAiOiJKV1...
  FL_API_LOCAL_URL=https://local.raichu.freelabel.net
  IRIS_LOCAL_URL=https://local.iris.freelabel.net

<info>Quick Start:</info>
  1. Copy .env.example to .env
  2. Set IRIS_ENV to "local" or "production"
  3. Add your API key and user ID
  4. Run: ./bin/iris config test
HELP
            )
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: show, test', 'show');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $store = new CredentialStore();

        $action = $input->getArgument('action');

        switch ($action) {
            case 'test':
                return $this->handleTest($io, $store);

            case 'show':
            case 'status':
            default:
                return $this->handleShow($io, $store);
        }
    }

    /**
     * Show configuration status.
     */
    private function handleShow(SymfonyStyle $io, CredentialStore $store): int
    {
        $io->title('IRIS SDK Configuration');

        $env = $store->getEnvironment();
        $envColor = $env === 'production' ? 'red' : 'green';

        $io->text([
            "<fg={$envColor};options=bold>Environment: {$env}</>",
            '<fg=gray>Configuration loaded from .env file</>',
            '',
        ]);

        // Show credentials status
        $config = $store->toConfigArray();

        $checks = [
            ['API Key', isset($config['api_key']) && !empty($config['api_key']), 'Required'],
            ['User ID', isset($config['user_id']), 'Required'],
            ['Base URL', isset($config['base_url']), 'FL-API endpoint'],
            ['IRIS URL', isset($config['iris_url']), 'IRIS-API endpoint'],
        ];

        $tableRows = [];
        foreach ($checks as [$name, $configured, $description]) {
            $status = $configured ? '<fg=green>✓ Set</>' : '<fg=red>✗ Missing</>';
            $tableRows[] = [$name, $status, $description];
        }

        $io->table(['Setting', 'Status', 'Description'], $tableRows);

        // Show values (masked)
        $io->section('Current Values');

        $masked = $store->getMaskedCredentials();
        $valueRows = [];
        foreach ($masked as $key => $value) {
            $valueRows[] = [$key, $value];
        }

        if (!empty($valueRows)) {
            $io->table(['Key', 'Value (masked)'], $valueRows);
        } else {
            $io->warning('No credentials configured.');
        }

        // Overall status
        if ($store->hasMinimumCredentials()) {
            $io->success('SDK is ready to use!');
            $io->text([
                '<fg=gray>Test with: ./bin/iris config test</>',
                '<fg=gray>Chat: ./bin/iris chat <agent_id> "Hello!"</>',
            ]);
        } else {
            $io->error('SDK is not configured. Edit your .env file.');
            $io->text([
                '',
                '<fg=yellow>Quick setup:</fg>',
                '  1. Edit .env in the SDK directory',
                '  2. Set IRIS_ENV=local (or production)',
                '  3. Set IRIS_USER_ID=your_user_id',
                '  4. Set IRIS_LOCAL_API_KEY=your_token (or IRIS_API_KEY for production)',
                '',
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Test API connection.
     */
    private function handleTest(SymfonyStyle $io, CredentialStore $store): int
    {
        $io->title('Testing API Connection');

        if (!$store->hasMinimumCredentials()) {
            $io->error('SDK not configured. Edit your .env file first.');
            return Command::FAILURE;
        }

        $config = $store->toConfigArray();
        $io->text([
            'Environment: ' . $store->getEnvironment(),
            'Base URL: ' . ($config['base_url'] ?? 'not set'),
            'User ID: ' . ($config['user_id'] ?? 'not set'),
            '',
        ]);

        // Test the agents endpoint
        $io->text('Testing API connection...');

        $url = ($config['base_url'] ?? '') . '/api/v1/users/' . ($config['user_id'] ?? '') . '/bloqs/agents';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . ($config['api_key'] ?? ''),
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $io->error("Connection failed: {$error}");
            return Command::FAILURE;
        }

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $agentCount = count($data['data'] ?? []);
            $io->success("API connection successful! Found {$agentCount} agents.");
            return Command::SUCCESS;
        }

        $io->error("API returned HTTP {$httpCode}");
        $io->text('<fg=gray>Response: ' . substr($response, 0, 200) . '</>');
        return Command::FAILURE;
    }
}
